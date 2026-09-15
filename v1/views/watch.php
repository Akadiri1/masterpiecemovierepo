<?php
// ==========================================
// 1. GET PARAMETERS & DEFAULTS
// ==========================================
$mediaId = $_GET['id'] ?? null;
$mediaType = $_GET['type'] ?? 'movie';
$seasonNum = isset($_GET['season']) ? (int)$_GET['season'] : 1;
$episodeNum = isset($_GET['episode']) ? (int)$_GET['episode'] : 1;

if (!$mediaId) {
    echo "No Media ID provided.";
    exit;
}

// ==========================================
// 2. CONFIGURATION & HELPERS
// ==========================================
// include 'includes/config.php'; 

$videoTitle = "Media ID: " . htmlspecialchars($mediaId);
$videoSubTitle = ($mediaType === 'tv') ? "S{$seasonNum}:E{$episodeNum}" : "Movie";
$seasonsData = []; 
$backdrop = '';
$isUpcoming = false;
$releaseDateDisplay = '';
$releaseMessage = '';
$trailerKey = '';

// Helper to find the best trailer
function getTrailerKey($videos) {
    if (!empty($videos['results'])) {
        foreach ($videos['results'] as $video) {
            // Prioritize Official Youtube Trailers
            if ($video['site'] === 'YouTube' && $video['type'] === 'Trailer') {
                return $video['key'];
            }
        }
        // Fallback to Teaser or Clip if no full Trailer found
        return $videos['results'][0]['key'] ?? '';
    }
    return '';
}

// ==========================================
// 3. FETCH METADATA FROM TMDB
// ==========================================
if (function_exists('fetchTmdbApi')) {
    
    // --- MOVIE LOGIC ---
    if ($mediaType === 'movie') {
        $details = fetchTmdbApi("movie/{$mediaId}", ['append_to_response' => 'videos,release_dates,recommendations,credits']);
        
        if ($details) {
            $videoTitle = $details['title'] ?? 'Movie';
            $backdrop = $details['backdrop_path'] ?? '';
            
            $trailerKey = getTrailerKey($details['videos'] ?? []);

            // LOGIC: Check Release Date
            $today = date('Y-m-d');
            $releaseDate = $details['release_date'] ?? '';
            
            // If release date is in the future
            if ($releaseDate && $releaseDate > $today) {
                $isUpcoming = true;
                $releaseDateDisplay = date('F j, Y', strtotime($releaseDate));
                $releaseMessage = "This movie is scheduled for theatrical release on {$releaseDateDisplay}. It is not yet available for streaming.";
            }
        }
    } 
    // --- TV SHOW LOGIC ---
    else {
        // 1. Fetch Show Details
        $details = fetchTmdbApi("tv/{$mediaId}", ['append_to_response' => 'recommendations,credits']);
        
        if ($details) {
            $videoTitle = $details['name'] ?? 'TV Show';
            $backdrop = $details['backdrop_path'] ?? '';
            $videoSubTitle = "Season {$seasonNum} - Episode {$episodeNum}";
            
            // 2. Fetch Specific Episode to check Air Date
            $epDetails = fetchTmdbApi("tv/{$mediaId}/season/{$seasonNum}/episode/{$episodeNum}", ['append_to_response' => 'videos,credits']);
            
            if ($epDetails) {
                $airDate = $epDetails['air_date'] ?? '';
                $trailerKey = getTrailerKey($epDetails['videos'] ?? []);
                // Fallback: If episode has no specific trailer, use the main TV show trailer
                if(empty($trailerKey)) {
                     $showVids = fetchTmdbApi("tv/{$mediaId}/videos");
                     $trailerKey = getTrailerKey($showVids ?? []);
                }

                $today = date('Y-m-d');
                
                // If air date is in the future
                if ($airDate && $airDate > $today) {
                    $isUpcoming = true;
                    $releaseDateDisplay = date('F j, Y', strtotime($airDate));
                    
                    $releaseMessage = "This episode airs on {$releaseDateDisplay}. It will be available for streaming shortly after broadcast.";
                }
                
                if (!empty($epDetails['name'])) {
                    $videoSubTitle .= ": " . $epDetails['name'];
                }
            }

            // 3. Build Sidebar Data (Only if show exists)
            if (!empty($details['seasons'])) {
                foreach ($details['seasons'] as $season) {
                    if ($season['season_number'] == 0) continue; 
                    $seasonDetail = fetchTmdbApi("tv/{$mediaId}/season/{$season['season_number']}");
                    if ($seasonDetail && isset($seasonDetail['episodes'])) {
                        $seasonsData[] = [
                            'season_number' => $season['season_number'],
                            'name' => $season['name'] ?? "Season {$season['season_number']}",
                            'episodes' => $seasonDetail['episodes']
                        ];
                    }
                }
            }
        }
    }
}

// ==========================================
// 4. SERVER SELECTION LOGIC
// ==========================================
// Check for custom hosted media source first
$customSource = null;
if (isset($conn)) {
    try {
        $sql = "SELECT video_url, is_embed FROM media_sources 
                WHERE tmdb_id = ? AND media_type = ? 
                AND season = ? AND episode = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mediaId, $mediaType, $seasonNum, $episodeNum]);
        $customSource = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Multiple embed servers for fallback — if one buffers on slow network, switch to another
$servers = [];
if (!$isUpcoming) {
    if ($customSource && !empty($customSource['video_url'])) {
        $servers[] = [
            'name' => $customSource['is_embed'] ? 'Server (Custom Embed)' : 'Server (Direct File)',
            'url' => $customSource['video_url']
        ];
    }

    if ($mediaType === 'movie') {
        $servers[] = ['name' => 'Server 1 (Vidsrc)', 'url' => "https://vidsrc.in/embed/movie/$mediaId"];
        $servers[] = ['name' => 'Server 2 (Vidlink)', 'url' => "https://vidlink.pro/movie/$mediaId"];
        $servers[] = ['name' => 'Server 3 (Auto)', 'url' => "https://autoembed.co/movie/tmdb/$mediaId"];
        $servers[] = ['name' => 'Server 4 (Vidbinge)', 'url' => "https://vidbinge.com/embed/movie/$mediaId"];
        $servers[] = ['name' => 'Server 5 (Smashy)', 'url' => "https://player.smashy.stream/movie/$mediaId"];
    } else {
        $servers[] = ['name' => 'Server 1 (Vidsrc)', 'url' => "https://vidsrc.in/embed/tv/$mediaId/$seasonNum/$episodeNum"];
        $servers[] = ['name' => 'Server 2 (Vidlink)', 'url' => "https://vidlink.pro/tv/$mediaId/$seasonNum/$episodeNum"];
        $servers[] = ['name' => 'Server 3 (Auto)', 'url' => "https://autoembed.co/tv/tmdb/$mediaId-$seasonNum-$episodeNum"];
        $servers[] = ['name' => 'Server 4 (Vidbinge)', 'url' => "https://vidbinge.com/embed/tv/$mediaId/$seasonNum/$episodeNum"];
        $servers[] = ['name' => 'Server 5 (Smashy)', 'url' => "https://player.smashy.stream/tv/$mediaId?s=$seasonNum&e=$episodeNum"];
    }
    
    // 100% unblocked fallbacks
    if (!empty($trailerKey)) {
        $servers[] = ['name' => 'Server 6 (Trailer)', 'url' => "https://www.youtube.com/embed/$trailerKey?autoplay=1"];
    }
    $servers[] = ['name' => 'Server 7 (Demo)', 'url' => "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4"];
}
$videoSrc = !empty($servers) ? $servers[0]['url'] : '';
$serversJson = json_encode($servers);

// Dynamically calculate the base directory for assets and links
$baseDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = rtrim($baseDir, '/\\') . '/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
  <title>Watch: <?php echo htmlspecialchars($videoTitle); ?></title>
  <link rel="shortcut icon" href="/assets/images/favicon.ico" />
  <link rel="apple-touch-icon" href="/assets/images/logo.png">
  <link rel="manifest" href="manifest.json">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <base href="<?php echo htmlspecialchars($baseDir); ?>">
  
  <link rel="stylesheet" href="assets/css/core/watch-theme.css?v=<?php echo time() + 2; ?>">
  <link rel="stylesheet" href="assets/css/core/mobile-player.css?v=<?php echo time() + 2; ?>">
  <!-- Loaded blocking (not deferred) so the inline player script below can use it. -->
  <script src="assets/js/player/mobile-player.js?v=<?php echo time() + 2; ?>"></script>
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">
  <style>
      /* Cinematic Mode */
      body.cinematic-active::before {
          content: "";
          position: fixed;
          top: 0; left: 0; right: 0; bottom: 0;
          backdrop-filter: blur(15px);
          -webkit-backdrop-filter: blur(15px);
          background: rgba(10, 10, 15, 0.4);
          z-index: 9990;
          pointer-events: none;
          transition: all 0.5s ease;
      }
      body.cinematic-active .watch-sidebar,
      body.cinematic-active .recommended-section,
      body.cinematic-active .center-top-bar,
      body.cinematic-active .watch-right {
          opacity: 0.3 !important;
          transition: all 0.5s ease-in-out;
      }
      
      /* Bring AI sidebar into focus during cinematic mode if open */
      body.cinematic-active.ai-active .watch-right {
          opacity: 1 !important;
          z-index: 99999;
          box-shadow: -10px 0 50px rgba(0,0,0,0.8);
      }
      body.cinematic-active .video-wrapper {
          z-index: 99999;
          position: relative;
          box-shadow: 0 0 80px rgba(0,0,0,0.8);
          transition: all 0.5s ease-in-out;
      }
      body.cinematic-active .bottom-controls-bar {
          position: relative;
          z-index: 99999; /* Keep above the blur */
          background: rgba(10, 10, 15, 0.9) !important;
          border-radius: 12px;
          padding: 10px 20px;
          margin-top: 10px;
          transition: all 0.5s ease-in-out;
      }
      
      /* Sidebar Collapsed */
      .watch-app.sidebar-collapsed { grid-template-columns: 80px 1fr 0px !important; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-link span,
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-section-label,
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-user-info,
      .watch-app.sidebar-collapsed .watch-sidebar .logo-text { display: none !important; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-main-actions { padding: 0 !important; background: transparent !important; border: none !important; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-link { justify-content: center; padding: 12px 0; border-radius: 12px; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-brand { justify-content: center !important; padding: 24px 0 !important; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-user { justify-content: center; padding: 10px 0 !important; }
      .watch-app.sidebar-collapsed .watch-sidebar .sidebar-footer { padding: 10px !important; }
      .watch-app.sidebar-collapsed .watch-right { display: none !important; }
      #serverDropdown.open { display: block !important; }
      
      /* Fix iframe unclickable controls */
      .video-wrapper { 
          overflow: visible !important; 
          border-radius: 12px !important; 
          margin: 0 auto; 
          max-width: 100%;
      }
      #networkBanner { pointer-events: none !important; }
      
      /* Fix Bottom Controls Layout */
      .bottom-controls-bar { flex-wrap: wrap !important; gap: 15px; align-items: flex-start !important; }
      .bottom-controls-bar .meta-left { flex: 1 1 200px; }
      .bottom-controls-bar .control-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-start; flex: 0 1 auto; }
      
      /* Episode Grid View */
      .episode-list.grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 10px; }
      .episode-list.grid-view .ep-row { flex-direction: column; justify-content: center; padding: 15px 5px; text-align: center; background: rgba(255,255,255,0.03); border-radius: 10px; height: 100%; border: 1px solid rgba(255,255,255,0.05); }
      .episode-list.grid-view .ep-row:hover { background: rgba(255,255,255,0.1); }
      .episode-list.grid-view .ep-row.active { background: rgba(255,255,255,0.05); border-color: var(--primary); box-shadow: 0 0 15px var(--primary-glow); }
      .episode-list.grid-view .ep-row .num { width: 100%; background: transparent; color: #fff; font-size: 1.3rem; margin-bottom: 5px; }
      .episode-list.grid-view .ep-row.active .num { color: var(--primary); }
      .episode-list.grid-view .ep-row .name { font-size: 0.7rem; white-space: normal; line-height: 1.2; opacity: 0.8; }
      .episode-list.grid-view .ep-row i { display: none; }
      
      /* Mobile Offcanvas Sidebars */
      @media (max-width: 900px) {
          body, html { height: auto !important; overflow: auto !important; }
          .watch-app { display: flex !important; flex-direction: column !important; height: auto !important; overflow: visible !important; }
          .watch-center { flex: 1 0 auto; overflow: visible !important; }
          .watch-sidebar { position: fixed !important; left: 0; top: 0; bottom: 0; width: 280px; z-index: 10000; transform: translateX(-100%); transition: 0.3s; }
          .watch-sidebar.open { transform: translateX(0); display: flex !important; }
          .watch-right { position: static !important; display: block !important; width: 100% !important; transform: none !important; border-left: none; padding: 15px; }
      }
      
      /* Phone landscape: the player fills the screen.
         Turned sideways, a phone left the player taller than the screen and
         80px below the page header, so the embedded server's seek bar and
         fullscreen button sat off-screen and needed a scroll. On a touch
         device in landscape the player now covers the viewport edge to edge,
         like a native video app. Rotating back to portrait restores the page.
         max-height keeps tablets and desktop windows out of this mode, and
         :has(#playerIframe) keeps it off the "coming soon" layout. */
      @media (orientation: landscape) and (max-height: 540px) and (pointer: coarse) {
          html:has(#playerIframe),
          html:has(#playerIframe) body {
              overflow: hidden !important;
              height: 100% !important;
              overscroll-behavior: none;
          }

          /* A transform, filter or backdrop blur on any ancestor turns
             position:fixed into "fixed to that ancestor", which would trap
             the player inside the page instead of covering the screen. */
          html:has(#playerIframe) :has(#videoArea) {
              transform: none !important;
              filter: none !important;
              backdrop-filter: none !important;
              -webkit-backdrop-filter: none !important;
              perspective: none !important;
              contain: none !important;
              container-type: normal !important;
              will-change: auto !important;
          }

          html:has(#playerIframe) #videoArea {
              position: fixed !important;
              inset: 0 !important;
              width: 100vw !important;
              height: 100vh !important;
              height: 100dvh !important;
              max-width: none !important;
              margin: 0 !important;
              border: 0 !important;
              border-radius: 0 !important;
              box-shadow: none !important;
              transform: none !important;
              background: #000 !important;
              z-index: 2147483000 !important;
              /* Keep the video and its edge controls clear of a notch. */
              padding: 0 env(safe-area-inset-right) 0 env(safe-area-inset-left) !important;
              box-sizing: border-box !important;
          }

          html:has(#playerIframe) #videoArea > #playerIframe,
          html:has(#playerIframe) #videoArea > .mp-shell,
          html:has(#playerIframe) #videoArea > #playerVideo {
              width: 100% !important;
              height: 100% !important;
              border-radius: 0 !important;
          }

          /* Floating buttons would sit on top of the video. The theme button
             uses z-index 99999999, so hiding is the only reliable option. */
          html:has(#playerIframe) .theme-switcher-float,
          html:has(#playerIframe) #mobileAiBtn,
          html:has(#playerIframe) #mobileEpToggle,
          html:has(#playerIframe) .zen-ai-float {
              display: none !important;
          }
      }

      /* Fullscreen Search Overlay */
      #searchOverlay {
          position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh;
          background: rgba(8, 8, 12, 0.97); backdrop-filter: blur(20px);
          z-index: 999999; display: none !important; align-items: center; justify-content: center;
          flex-direction: column;
      }
      #searchOverlay.active { display: flex !important; }
      #searchOverlay .search-close-btn {
          position: absolute; top: 25px; right: 30px; background: rgba(255,255,255,0.06);
          border: 1px solid rgba(255,255,255,0.1); width: 48px; height: 48px;
          border-radius: 50%; color: #aaa; font-size: 1.5rem; cursor: pointer;
          transition: 0.25s; display: flex; align-items: center; justify-content: center;
      }
      #searchOverlay .search-close-btn:hover { color: #fff; background: rgba(255,255,255,0.15); transform: rotate(90deg); }
      #searchOverlay .search-overlay-content { width: 100%; max-width: 700px; padding: 20px; text-align: center; }
      #searchOverlay .search-overlay-title { font-size: 1.8rem; font-weight: 700; color: #fff; margin-bottom: 30px; letter-spacing: -0.5px; }
      #searchOverlay .search-overlay-form {
          display: flex; align-items: center; background: rgba(255,255,255,0.05);
          border-radius: 16px; padding: 14px 22px; border: 1px solid rgba(255,255,255,0.08);
          transition: all 0.3s ease; box-shadow: 0 8px 30px rgba(0,0,0,0.4);
      }
      #searchOverlay .search-overlay-form:focus-within { border-color: #e50914; background: rgba(255,255,255,0.08); box-shadow: 0 12px 40px rgba(229, 9, 20, 0.12); }
      #searchOverlay .search-icon { font-size: 1.6rem; color: #666; margin-right: 15px; flex-shrink: 0; }
      #searchOverlay .search-overlay-form:focus-within .search-icon { color: #e50914; }
      #searchOverlay .search-overlay-form input {
          flex: 1; background: transparent !important; border: none !important; color: #fff !important;
          font-size: 1.3rem; font-weight: 400; outline: none !important; width: 100%;
          box-shadow: none !important; padding: 8px 0 !important;
      }
      #searchOverlay .search-overlay-form input::placeholder { color: #555; }
      #searchOverlay .search-submit-btn {
          background: #e50914; color: #fff; border: none;
          padding: 12px 28px; border-radius: 10px; font-size: 1rem; font-weight: 700;
          cursor: pointer; transition: 0.2s; margin-left: 12px; flex-shrink: 0;
      }
      #searchOverlay .search-submit-btn:hover { background: #ff2a35; }
      #searchOverlay .search-overlay-hint { margin-top: 20px; color: #444; font-size: 0.85rem; }
      #searchOverlay .search-overlay-hint kbd { background: rgba(255,255,255,0.08); padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; color: #888; border: 1px solid rgba(255,255,255,0.1); }
      
      /* Force Sidebar Background */
      .watch-sidebar {
          background-image: linear-gradient(rgba(10, 10, 15, 0.6), rgba(10, 10, 15, 0.7)), url('/assets/images/pages/01.webp') !important;
          background-size: cover !important;
          background-position: left center !important;
          background-attachment: fixed !important;
          backdrop-filter: blur(10px);
          -webkit-backdrop-filter: blur(10px);
          border-right: 1px solid rgba(255,255,255,0.05);
      }
      .sidebar-main-actions {
          background: rgba(255,255,255,0.03);
          border: 1px solid rgba(255,255,255,0.05);
      }
      
      /* Full Page Background */
      body, html {
          background-color: #0b0c15 !important;
          background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.95)), url('/assets/images/pages/01.webp') !important;
          background-size: cover !important;
          background-position: center !important;
          background-attachment: fixed !important;
      }
      
      .sidebar-brand .logo-text { color: #fff !important; font-weight: 800; letter-spacing: -1px; }

      /* Dynamic Theme Variables */
      :root {
          --primary: #e50914;
          --primary-hover: #ff2a35;
          --primary-glow: rgba(229, 9, 20, 0.3);
      }
      
      :root[data-theme="cyberpunk"] {
          --primary: #00f0ff;
          --primary-hover: #00d0dd;
          --primary-glow: rgba(0, 240, 255, 0.3);
      }

      :root[data-theme="gold"] {
          --primary: #ffd700;
          --primary-hover: #ffea00;
          --primary-glow: rgba(255, 215, 0, 0.3);
      }
      
      :root[data-theme="emerald"] {
          --primary: #00e676;
          --primary-hover: #00c853;
          --primary-glow: rgba(0, 230, 118, 0.3);
      }

      :root, [data-bs-theme=dark] {
          --bs-primary: var(--primary) !important;
          --bs-primary-rgb: 229, 22, 63;
          --bs-primary-hover: var(--primary-hover) !important;
          --bs-link-color: var(--primary) !important;
          --bs-link-hover-color: var(--primary-hover) !important;
      }

      /* Globally replace static red with variables using high specificity */
      body .text-primary, body i.text-primary, .iq-main-slider .text-primary, .trending-info .text-primary, .cart-content .text-primary { color: var(--primary) !important; }
      body .text-warning, body i.text-warning, .ph-star.text-warning { color: var(--primary) !important; }
      body .bg-primary { background-color: var(--primary) !important; }
      
      /* High specificity for buttons to override template's core.css */
      body .btn-primary, .iq-button .btn-primary, .p-btns .btn-primary, .iq-play-button .btn-primary { 
          background: var(--primary) !important; 
          background-color: var(--primary) !important;
          border-color: var(--primary) !important; 
          color: #fff !important; 
      }
      body .btn-primary:hover, .iq-button .btn-primary:hover, .p-btns .btn-primary:hover { 
          background: var(--primary-hover) !important; 
          background-color: var(--primary-hover) !important;
          border-color: var(--primary-hover) !important; 
          box-shadow: 0 4px 15px var(--primary-glow) !important; 
      }

      /* Fix for outline buttons (like Add Review) defaulting to Bootstrap Blue */
      body .btn-outline-primary {
          --bs-btn-color: var(--primary);
          --bs-btn-border-color: var(--primary);
          --bs-btn-hover-color: #fff;
          --bs-btn-hover-bg: var(--primary);
          --bs-btn-hover-border-color: var(--primary);
          color: var(--primary) !important;
          border-color: var(--primary) !important;
      }
      body .btn-outline-primary:hover {
          background-color: var(--primary-hover) !important;
          border-color: var(--primary-hover) !important;
          color: #fff !important;
          box-shadow: 0 4px 15px var(--primary-glow) !important;
      }
      
      /* Fix for standard links defaulting to Bootstrap Blue */
      a { color: var(--primary); }
      a:hover { color: var(--primary-hover); }
      
      .sidebar-link.active i { color: var(--primary) !important; }
      .movie-title, h1.movie-title { color: var(--primary) !important; text-shadow: 0 0 20px var(--primary-glow); }
      .star-rating label:hover i, .star-rating label:hover ~ label i, .star-rating input:checked ~ label i { color: var(--primary) !important; }
      
      /* Hide the floating AI orb on watch page — we have a dedicated sidebar button */
      .zen-ai-float { display: none !important; }
      
      /* Mobile AI Floating Button - only visible on small screens */
      #mobileAiBtn { display: none; }
      @media (max-width: 800px) {
          #mobileAiBtn {
              display: flex; position: fixed; bottom: 85px; right: 16px;
              width: 52px; height: 52px; border-radius: 50%; z-index: 99999;
              background: linear-gradient(135deg, #00e0ff, #7b2cbf);
              border: none; color: #fff; align-items: center; justify-content: center;
              cursor: pointer; box-shadow: 0 4px 20px rgba(0,224,255,0.35);
              font-size: 1.3rem; transition: transform 0.2s;
          }
          #mobileAiBtn:active { transform: scale(0.92); }
      }
      
      /* Mobile AI Full-Screen Chat Modal */
      #mobileAiModal {
          display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
          background: rgba(10, 10, 15, 0.98); z-index: 999999;
          flex-direction: column;
      }
      #mobileAiModal.active { display: flex; }
  </style>
  <script>
      (function() {
          const savedTheme = localStorage.getItem('zen_theme');
          if (savedTheme) {
              document.documentElement.setAttribute('data-theme', savedTheme);
          }
      })();
  </script>
</head>
<body>

<!-- Network Status Banner -->
<div class="network-banner" id="networkBanner"></div>

<div class="watch-app" id="playerApp">
    <!-- 1. Left Sidebar -->
    <aside class="watch-sidebar">
        <div class="sidebar-brand" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
            <a href="./" class="logo-text text-decoration-none">ZEN</a>
            <button id="sidebarToggleBtn" style="background:transparent; border:none; color:#aaa; font-size:1.5rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#aaa'"><i class="ph ph-list"></i></button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-main-actions">
                <a href="./" class="sidebar-link"><i class="ph ph-house"></i><span>Home</span></a>
                <a href="javascript:void(0)" class="sidebar-link" onclick="openSearchModal();"><i class="ph ph-magnifying-glass"></i><span>Search</span></a>
            </div>
            <div class="sidebar-section-label">Media</div>
            <a href="view-all?type=movie" class="sidebar-link"><i class="ph ph-film-strip"></i><span>Movies</span></a>
            <a href="view-all?type=tv" class="sidebar-link"><i class="ph ph-monitor-play"></i><span>TV Shows</span></a>
            <a href="view-all?type=discover&with_genres=16" class="sidebar-link"><i class="ph ph-sparkle"></i><span>Anime</span></a>
            <a href="view-all?type=discover&with_genres=10759" class="sidebar-link"><i class="ph ph-book-open"></i><span>Manga</span></a>
            <a href="view-all?type=discover&with_genres=10402" class="sidebar-link"><i class="ph ph-music-note"></i><span>Music</span></a>
            <a href="view-all?type=discover&with_genres=99" class="sidebar-link"><i class="ph ph-video-camera"></i><span>Documentaries</span></a>
            <div style="height: 12px;"></div>
            <a href="javascript:void(0)" onclick="openThemeModal(); return false;" class="sidebar-link">
                <i class="ph ph-sparkle text-primary"></i><span>Color House</span>
            </a>
            <a href="profile" class="sidebar-link"><i class="ph ph-heart"></i><span>Watchlist</span></a>
        </nav>
        
        <div class="sidebar-footer" style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); margin-top: auto;">
            <a href="/profile" class="sidebar-user" style="display:flex; align-items:center; gap:12px; text-decoration:none; padding:10px; border-radius:10px; transition:0.2s;">
                <img src="<?php echo htmlspecialchars($_SESSION['avatar_url'] ?? 'assets/images/user/user6.jpg'); ?>" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                <div class="sidebar-user-info" style="overflow:hidden;">
                    <div class="sidebar-user-name" style="color:#ddd; font-size:0.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest User'); ?></div>
                    <div class="sidebar-user-plan" style="color:#666; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($_SESSION['plan_name'] ?? 'Free'); ?> plan</div>
                </div>
            </a>
        </div>
    </aside>

    <!-- 2. Center Content -->
    <main class="watch-center custom-scrollbar">
        <!-- Top Bar -->
        <div class="center-top-bar">
            <a href="javascript:history.back()" class="back-btn"><i class="ph ph-arrow-left"></i></a>
            <h2><?php echo htmlspecialchars($videoTitle); ?></h2>
        </div>

        <!-- Video Wrapper -->
        <div class="video-wrapper" id="videoArea">
             <!-- Network Status Overlay -->
             <div class="network-banner" id="networkBanner" style="position:absolute; top:0; left:0; width:100%; z-index:9999;"></div>
             <!-- Loading overlay for server switches -->
             <div class="player-loading" id="playerLoading" style="position:absolute; inset:0; background:rgba(0,0,0,0.8); z-index:15; display:none; flex-direction:column; align-items:center; justify-content:center; color:#fff;">
                 <div class="load-spinner" style="width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.15); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                 <span>Switching server...</span>
             </div>

             <?php if ($isUpcoming): ?>
                <div class="upcoming-container" style="position:absolute; inset:0; background-image: url('https://image.tmdb.org/t/p/original<?php echo $backdrop; ?>'); background-size:cover; display:flex; align-items:center; justify-content:center; text-align:center;">
                   <div style="position:absolute; inset:0; background: rgba(10, 10, 15, 0.85); backdrop-filter: blur(10px);"></div>
                   <div style="position:relative; z-index:10; padding: 40px; background: rgba(20, 20, 25, 0.6); backdrop-filter: blur(25px); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); max-width: 800px; width: 90%; max-height: 90%; overflow-y: auto;">
                       <div>
                           <span class="badge-upcoming" style="background: linear-gradient(45deg, #e50914, #ff4b2b); color: #fff; padding: 8px 16px; border-radius: 30px; font-weight: bold; font-size: 0.9rem; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3); text-transform: uppercase;">Coming Soon</span>
                       </div>
                       <h1 class="upcoming-title mt-4" style="font-size: 2.5rem; font-weight: 800; color: #fff; letter-spacing: -1px; text-shadow: 0 2px 10px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($videoTitle); ?></h1>
                       <p class="mt-3" style="font-size: 1.1rem; color: #e0e0e0; line-height: 1.6;"><?php echo htmlspecialchars($releaseMessage); ?></p>
                       <?php if ($trailerKey): ?>
                       <div class="trailer-container mt-4" style="border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); aspect-ratio: 16/9; background: #000;">
                           <iframe src="https://www.youtube.com/embed/<?php echo $trailerKey; ?>?autoplay=1&mute=1" style="width:100%; height:100%; border:none;"></iframe>
                       </div>
                       <?php endif; ?>
                   </div>
                </div>              <?php else: 
                 $isDirectVideo = preg_match('/\.(mp4|mkv|webm|m3u8)(\?|$)/i', $videoSrc);
              ?>
                 <iframe id="playerIframe" src="<?php echo !$isDirectVideo ? $videoSrc : ''; ?>" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" style="width:100%; height:100%; border:none; display: <?php echo !$isDirectVideo ? 'block' : 'none'; ?>;"></iframe>
                 <video id="playerVideo" src="<?php echo $isDirectVideo ? $videoSrc : ''; ?>" controls style="width:100%; height:100%; background:#000; display: <?php echo $isDirectVideo ? 'block' : 'none'; ?>; border: none;" playsinline></video>
              <?php endif; ?>
        </div>

        <div class="bottom-controls-bar">
             <div class="meta-left">
                  <?php if ($mediaType === 'tv'): ?>
                      <strong>Season <?php echo $seasonNum; ?> &bull; Episode <?php echo $episodeNum; ?></strong>
                      <span><?php echo htmlspecialchars($epDetails['name'] ?? ''); ?></span>
                  <?php else: ?>
                      <strong><?php echo htmlspecialchars($videoTitle); ?></strong>
                      <?php if(isset($details['runtime'])) echo "<span>".htmlspecialchars($details['runtime'])." m</span>"; ?>
                  <?php endif; ?>
             </div>
             
             <div class="control-actions">
                  <?php if (!$isUpcoming && !empty($servers)): ?>
                  <!-- Server Switcher Inline -->
                  <div style="position:relative;">
                      <button class="btn-action server-switcher-btn" id="serverBtn" title="Servers" style="width:auto; padding:0 15px; font-size:0.9rem;">
                          <i class="ph ph-hard-drives"></i> <span class="srv-label ms-2 d-none d-md-block">Server 1</span>
                      </button>
                      <div class="server-dropdown" id="serverDropdown" style="position:absolute; bottom:100%; right:0; background:#111; border:1px solid #333; border-radius:8px; display:none; min-width:150px; z-index:100;">
                          <?php foreach ($servers as $i => $srv): ?>
                          <button class="server-option <?php echo $i === 0 ? 'active' : ''; ?> w-100 text-start px-3 py-2 border-0 bg-transparent text-white" data-index="<?php echo $i; ?>" style="cursor:pointer; border-bottom:1px solid #222;">
                              <?php echo htmlspecialchars($srv['name']); ?>
                          </button>
                          <?php endforeach; ?>
                      </div>
                  </div>
                  <?php endif; ?>

                  <?php if ($mediaType === 'tv'): ?>
                  <a href="watch?id=<?php echo $mediaId; ?>&type=tv&season=<?php echo $seasonNum; ?>&episode=<?php echo max(1, $episodeNum - 1); ?>" class="btn-action"><i class="ph ph-caret-left"></i></a>
                  <div class="now-playing-box text-center">
                      <small>Playing</small>
                      <strong>Ep <?php echo $episodeNum; ?></strong>
                  </div>
                  <a href="watch?id=<?php echo $mediaId; ?>&type=tv&season=<?php echo $seasonNum; ?>&episode=<?php echo ($episodeNum + 1); ?>" class="btn-action"><i class="ph ph-caret-right"></i></a>
                  <?php endif; ?>
                  
                  <button class="btn-action" id="cinematicToggleBtn" title="Cinematic Mode" onclick="toggleCinematicMode()" style="color: #00e0ff;"><i class="ph-fill ph-moon"></i></button>
                  <button class="btn-action" onclick="window.innerWidth <= 800 ? openMobileAI() : openWatchAI()" title="Ask ZEN AI" style="background: linear-gradient(135deg, #00e0ff, #7b2cbf); border: none; color: #fff; width: auto; padding: 0 15px; font-weight: 600; display: inline-flex; gap: 6px; align-items: center;">
                      <i class="ph-fill ph-sparkle"></i> <span class="d-none d-md-block">ZEN AI</span>
                  </button>
                  <button class="btn-action" id="watchlistBtn" title="Add to Watchlist"><i class="ph ph-plus"></i></button>
                  <button class="btn-action" onclick="navigator.share({title: document.title, url: window.location.href})"><i class="ph ph-share-network"></i></button>
                  <?php if (!$isUpcoming && !empty($servers)): ?>
                  <button class="btn-action" id="downloadBtn" title="Download"><i class="ph ph-download-simple"></i></button>
                  <?php endif; ?>
             </div>
        </div>

        <!-- Recommended Section -->
        <?php if (!empty($details['recommendations']['results'])): ?>
        <div class="recommended-section" style="position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
                <h4 style="margin:0;">Recommended</h4>
                <div style="display:flex; gap:10px;">
                    <button class="btn-action" onclick="document.getElementById('recCards').scrollBy({left:-300, behavior:'smooth'})" style="width:40px;height:40px;padding:0;background:rgba(255,255,255,0.05);color:#fff;border-radius:50%;"><i class="ph ph-caret-left"></i></button>
                    <button class="btn-action" onclick="document.getElementById('recCards').scrollBy({left:300, behavior:'smooth'})" style="width:40px;height:40px;padding:0;background:rgba(255,255,255,0.05);color:#fff;border-radius:50%;"><i class="ph ph-caret-right"></i></button>
                </div>
            </div>
            <div class="rec-cards" id="recCards">
                <?php foreach (array_slice($details['recommendations']['results'], 0, 10) as $rec): ?>
                <a href="/watch?id=<?php echo $rec['id']; ?>&type=<?php echo $mediaType; ?>" class="rec-card">
                    <img src="<?php echo !empty($rec['poster_path']) ? 'https://image.tmdb.org/t/p/w300'.$rec['poster_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" alt="Poster">
                    <div class="rec-rating"><i class="ph-fill ph-star text-warning"></i> <?php echo round($rec['vote_average'], 1); ?></div>
                    <p class="rec-card-title text-truncate"><?php echo htmlspecialchars($rec['title'] ?? $rec['name'] ?? 'Untitled'); ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <aside class="watch-right custom-scrollbar" id="sidebar">
        <!-- Ask ZEN AI Button -->
        <div id="watchAiTrigger" onclick="openWatchAI()" style="background: linear-gradient(135deg, rgba(0,224,255,0.1), rgba(123,44,191,0.1)); border: 1px solid rgba(0,224,255,0.25); color: #fff; font-weight: 600; padding: 12px 16px; border-radius: 10px; display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 18px; user-select: none; transition: all 0.25s ease;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #00e0ff, #7b2cbf); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph-fill ph-sparkle" style="color: #fff; font-size: 1rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.85rem; font-weight: 700;">Ask ZEN AI</div>
                <div style="font-size: 0.7rem; color: #888; font-weight: 400;">Chat about this movie</div>
            </div>
            <i class="ph ph-caret-right" style="color: #555; font-size: 1rem;"></i>
        </div>

       <!-- Normal Sidebar Content -->
       <div id="sidebarContent">
       <?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
       <div class="right-controls-top">
           <div class="dropdown-btn" onclick="document.getElementById('seasonDropdownNav').classList.toggle('d-none');">Season <?php echo $seasonNum; ?> <i class="ph ph-caret-down"></i></div>
           <div class="icon-btn-group">
               <button class="icon-btn" onclick="document.getElementById('epListContainer').classList.remove('grid-view')"><i class="ph ph-list"></i></button>
               <button class="icon-btn" onclick="document.getElementById('epListContainer').classList.add('grid-view')"><i class="ph ph-squares-four"></i></button>
           </div>
       </div>
       
       <!-- Season Dropdown Select -->
       <div id="seasonDropdownNav" class="d-none bg-dark rounded p-2 mb-3" style="border: 1px solid #333;">
           <?php foreach ($seasonsData as $season): ?>
              <a href="watch?id=<?php echo $mediaId; ?>&type=tv&season=<?php echo $season['season_number']; ?>&episode=1" class="d-block text-white py-2 px-2 border-bottom border-secondary text-decoration-none <?php echo $season['season_number'] == $seasonNum ? 'fw-bold text-primary' : ''; ?>">
                  <?php echo htmlspecialchars($season['name']); ?>
              </a>
           <?php endforeach; ?>
       </div>

       <div class="episode-list" id="epListContainer">
           <?php 
           foreach ($seasonsData as $season):
               if($season['season_number'] == $seasonNum):
                   foreach ($season['episodes'] as $ep): 
                       $isActive = ($ep['episode_number'] == $episodeNum);
           ?>
           <a href="watch?id=<?php echo $mediaId; ?>&type=tv&season=<?php echo $seasonNum; ?>&episode=<?php echo $ep['episode_number']; ?>" class="ep-row <?php echo $isActive ? 'active' : ''; ?>">
               <div class="num"><?php echo $ep['episode_number']; ?></div>
               <div class="name text-truncate"><?php echo htmlspecialchars($ep['name']); ?></div>
               <i class="ph-fill ph-play-circle text-muted ms-auto" style="font-size: 1.2rem;"></i>
           </a>
           <?php endforeach; endif; endforeach; ?>
       </div>
       <?php endif; ?>

       <!-- Info Card -->
       <div class="info-card">
           <img src="<?php echo !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w300' . $details['poster_path'] : 'assets/images/user/userblank.jpg'; ?>" class="info-poster" alt="Poster" loading="lazy">
           <div class="info-details">
               <p style="font-size:0.9rem; font-weight:600;"><i class="ph-fill ph-star text-warning"></i> <?php echo round($details['vote_average'] ?? 0, 1); ?> / 10</p>
               <div><span class="label">Status</span><span class="val"><?php echo htmlspecialchars($details['status'] ?? 'Released'); ?></span></div>
               <div><span class="label">Aired</span><span class="val"><?php echo htmlspecialchars($details['release_date'] ?? $details['first_air_date'] ?? 'N/A'); ?></span></div>
           </div>
       </div>

       <!-- Cast -->
       <?php 
       $cast = [];
       if ($mediaType === 'tv' && !empty($epDetails['credits']['cast'])) {
           $cast = $epDetails['credits']['cast'];
       } elseif (!empty($details['credits']['cast'])) {
           $cast = $details['credits']['cast'];
       }
       if (!empty($cast)): 
       ?>
       <div class="section-head">
           <h5>Characters</h5>
       </div>
       <div class="cast-list">
           <?php foreach (array_slice($cast, 0, 6) as $actor): ?>
           <a href="person-detail?id=<?php echo $actor['id']; ?>" class="cast-row-link text-decoration-none">
               <div class="cast-row">
                   <img src="<?php echo !empty($actor['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$actor['profile_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" alt="Actor">
                   <div>
                       <h6 style="color:#fff;"><?php echo htmlspecialchars($actor['name']); ?></h6>
                       <span style="color:#aaa;"><?php echo htmlspecialchars($actor['character'] ?? ''); ?></span>
                   </div>
               </div>
           </a>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>
       </div><!-- end #sidebarContent -->

       <!-- AI Chat Panel (hidden by default, shown when button clicked) -->
       <div id="watchAiPanel" style="display:none; flex-direction:column; height:100%; margin: -20px; padding: 0;">
           <!-- AI Header -->
           <div style="display:flex; align-items:center; justify-content:space-between; padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.2);">
               <div style="display:flex; align-items:center; gap: 10px;">
                   <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #00e0ff, #7b2cbf); display:flex; align-items:center; justify-content:center;">
                       <i class="ph-fill ph-sparkle" style="color:#fff; font-size: 0.85rem;"></i>
                   </div>
                   <div>
                       <div style="font-size: 0.9rem; font-weight: 700; color: #fff;">ZEN AI</div>
                       <div style="font-size: 0.65rem; color: #00e0ff;">Online</div>
                   </div>
               </div>
               <button onclick="closeWatchAI()" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #aaa; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display:flex; align-items:center; justify-content:center; font-size: 0.9rem; transition: 0.2s;" onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.color='#aaa';this.style.background='rgba(255,255,255,0.06)'">
                   <i class="ph ph-arrow-left"></i>
               </button>
           </div>
           <!-- AI Chat Messages -->
           <div id="watchAiChat" style="flex:1; overflow-y:auto; padding: 20px; display:flex; flex-direction:column; gap: 12px;">
               <div style="background: rgba(0,224,255,0.08); border: 1px solid rgba(0,224,255,0.15); border-radius: 12px; border-top-left-radius: 4px; padding: 14px 16px; color: #e0e0e0; font-size: 0.85rem; line-height: 1.6; max-width: 90%;">
                   👋 Hey! I know everything about <strong style="color:#00e0ff;"><?php echo htmlspecialchars($videoTitle); ?></strong>. Ask me about the plot, characters, hidden details, or anything else!
               </div>
           </div>
           <!-- AI Input -->
           <div style="padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.2);">
               <div style="display:flex; gap: 8px;">
                   <input type="text" id="watchAiInput" placeholder="Ask something..." autocomplete="off" style="flex:1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #fff; font-size: 0.85rem; border-radius: 10px; padding: 10px 14px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='rgba(0,224,255,0.4)'" onblur="this.style.borderColor='rgba(255,255,255,0.12)'" onkeypress="if(event.key === 'Enter') handleWatchAiSubmit(event)">
                   <button onclick="handleWatchAiSubmit(event)" style="background: linear-gradient(135deg, #00e0ff, #7b2cbf); border: none; border-radius: 10px; padding: 0 14px; color: #fff; cursor: pointer; display:flex; align-items:center; justify-content:center; transition: transform 0.15s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                       <i class="ph-fill ph-paper-plane-right" style="font-size: 1rem;"></i>
                   </button>
               </div>
               <p style="color: #555; text-align:center; margin: 8px 0 0; font-size: 0.65rem;">AI can make mistakes</p>
           </div>
       </div><!-- end #watchAiPanel -->

    </aside>
</div>

<!-- Mobile Toggle Button (Floating) -->
<button class="mobile-ep-fab" id="mobileEpToggle" style="display:none; position:fixed; bottom:20px; right:20px; width:50px; height:50px; border-radius:50%; background:#e50914; color:#fff; border:none; z-index:99; align-items:center; justify-content:center;"><i class="ph ph-list"></i></button>

<!-- DOWNLOAD MODAL -->
<?php if (!$isUpcoming): ?>
<div class="dl-modal-overlay" id="downloadModal">
    <div class="dl-modal">
        <div class="dl-modal-header">
            <h3><i class="fa-solid fa-download" style="color:#4cd137;margin-right:8px;"></i> Download</h3>
            <button class="dl-modal-close" id="dlModalClose"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="dl-modal-body">
            <p>Select quality for <strong style="color:#fff;"><?php echo htmlspecialchars($videoTitle); ?></strong>
               <?php if ($mediaType === 'tv'): ?>
                 — <?php echo htmlspecialchars($videoSubTitle); ?>
               <?php endif; ?>
            </p>

            <?php
            // Build download links for multiple qualities
            $qualities = [
                ['res' => '480p',  'label' => 'Standard',  'size' => '~400 MB', 'format' => 'MKV'],
                ['res' => '720p',  'label' => 'HD',        'size' => '~800 MB', 'format' => 'MKV'],
                ['res' => '1080p', 'label' => 'Full HD',   'size' => '~1.5 GB', 'format' => 'MKV'],
            ];

            foreach ($qualities as $q):
                $dlParams = "id={$mediaId}&type={$mediaType}&quality={$q['res']}";
                if ($mediaType === 'tv') {
                    $dlParams .= "&season={$seasonNum}&episode={$episodeNum}";
                }
            ?>
            <a href="/download?<?php echo $dlParams; ?>" class="dl-quality-item" target="_blank" rel="noopener">
                <div class="dl-quality-info">
                    <div class="dl-quality-badge">
                        <span class="badge-res"><?php echo $q['res']; ?></span>
                        <span class="badge-format"><?php echo $q['format']; ?></span>
                    </div>
                    <span class="dl-quality-label"><?php echo $q['label']; ?> Quality</span>
                    <span class="dl-quality-meta"><?php echo $q['size']; ?> • Direct Download</span>
                </div>
                <div class="dl-quality-icon">
                    <i class="fa-solid fa-download"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // --- DOWNLOAD MODAL LOGIC ---
    (function() {
        const dlBtn = document.getElementById('downloadBtn');
        const dlModal = document.getElementById('downloadModal');
        const dlClose = document.getElementById('dlModalClose');

        if (dlBtn && dlModal) {
            dlBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dlModal.classList.add('active');
            });

            dlClose.addEventListener('click', function() {
                dlModal.classList.remove('active');
            });

            dlModal.addEventListener('click', function(e) {
                if (e.target === dlModal) {
                    dlModal.classList.remove('active');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dlModal.classList.contains('active')) {
                    dlModal.classList.remove('active');
                }
            });
        }
    })();

    // --- PWA LOGIC ---
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then(reg => console.log('SW Registered!', reg))
                .catch(err => console.log('SW Fail', err));
        });
    }

    let deferredPrompt;
    const installBtn = document.getElementById('installBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) installBtn.style.display = 'flex';
    });

    if (installBtn) {
        installBtn.addEventListener('click', (e) => {
            installBtn.style.display = 'none';
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    deferredPrompt = null;
                });
            }
        });
    }

    // Removed focus manager as it breaks iframe interaction

    // =========================================
    // NETWORK RESILIENCE + SERVER SWITCHING
    // =========================================
    (function() {
        const banner = document.getElementById('networkBanner');
        const playerIframe = document.getElementById('playerIframe');
        const playerLoading = document.getElementById('playerLoading');
        const serverBtn = document.getElementById('serverBtn');
        const serverDropdown = document.getElementById('serverDropdown');
        const servers = <?php echo $serversJson ?? '[]'; ?>;
        let currentServer = 0;
        let wasOffline = false;
        let hideTimer = null;
        let reconnectTimer = null;
        let loadTimeout = null;

        function showBanner(type, html) {
            clearTimeout(hideTimer);
            banner.className = 'network-banner ' + type + ' visible';
            banner.innerHTML = html;
        }

        function hideBanner(delay) {
            hideTimer = setTimeout(() => {
                banner.classList.remove('visible');
            }, delay || 0);
        }

        const playerVideo = document.getElementById('playerVideo');

        // --- Mobile gesture player -------------------------------------
        // Only wraps the real <video>. The embed servers render inside a
        // cross-origin iframe, which the browser seals off completely, so
        // gestures cannot reach them.
        let mobilePlayer = null;
        if (playerVideo && window.MobilePlayer) {
            <?php
            // Previous / next episode for the player's buttons. Walks the season
            // lists the sidebar already loaded, so "next" moves from a season's
            // last episode into the following season and disappears after the
            // final one, instead of guessing episode + 1. Unaired episodes are
            // skipped because they have nothing to play yet.
            $playerEpisodes = [];
            if ($mediaType === 'tv' && !empty($seasonsData)) {
                $today = date('Y-m-d');
                foreach ($seasonsData as $playerSeason) {
                    foreach ($playerSeason['episodes'] as $playerEp) {
                        if (!isset($playerEp['episode_number'])) continue;
                        $sn = (int) $playerSeason['season_number'];
                        $en = (int) $playerEp['episode_number'];
                        $isCurrent = ($sn === (int) $seasonNum && $en === (int) $episodeNum);
                        if (!$isCurrent && !empty($playerEp['air_date']) && $playerEp['air_date'] > $today) continue;
                        $playerEpisodes[] = [$sn, $en];
                    }
                }
            }
            $playerPrevUrl = $playerNextUrl = null;
            foreach ($playerEpisodes as $i => [$sn, $en]) {
                if ($sn === (int) $seasonNum && $en === (int) $episodeNum) {
                    $base = '/watch?id=' . rawurlencode((string) $mediaId) . '&type=tv';
                    if (isset($playerEpisodes[$i - 1])) {
                        $playerPrevUrl = $base . '&season=' . $playerEpisodes[$i - 1][0] . '&episode=' . $playerEpisodes[$i - 1][1];
                    }
                    if (isset($playerEpisodes[$i + 1])) {
                        $playerNextUrl = $base . '&season=' . $playerEpisodes[$i + 1][0] . '&episode=' . $playerEpisodes[$i + 1][1];
                    }
                    break;
                }
            }
            ?>
            mobilePlayer = MobilePlayer.attach(playerVideo, {
                title: <?php echo json_encode($videoTitle . ($mediaType === 'tv' ? ' — ' . $videoSubTitle : '')); ?>,
                // Buttons only appear when these are functions.
                onPrev: <?php echo $playerPrevUrl ? 'function () { location.href = ' . json_encode($playerPrevUrl) . '; }' : 'null'; ?>,
                onNext: <?php echo $playerNextUrl ? 'function () { location.href = ' . json_encode($playerNextUrl) . '; }' : 'null'; ?>
            });
        }

        // The player inserts a wrapper around the <video>, so show/hide has
        // to act on that wrapper rather than the element itself.
        function setDirectVideoVisible(visible) {
            const target = (mobilePlayer && mobilePlayer.shell) ? mobilePlayer.shell : playerVideo;
            if (target) target.style.display = visible ? 'block' : 'none';
        }

        // --- Rotate to landscape when a streaming server goes fullscreen ---
        // The servers run in a cross-origin iframe, so the page can't see
        // their fullscreen button, but it can see the result: the iframe
        // becomes the document's fullscreen element. On a phone held upright
        // that fullscreen shows a widescreen video between large black bars,
        // so ask the browser to rotate, as phone video apps do. Android Chrome
        // honours the request; iPhone and desktop browsers refuse it, which is
        // harmless. The direct-file player above handles its own rotation.
        let rotatedForServer = false;
        function onServerFullscreenChange() {
            const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
            if (fsEl && fsEl === playerIframe && window.matchMedia('(pointer: coarse)').matches) {
                try {
                    if (screen.orientation && screen.orientation.lock) {
                        screen.orientation.lock('landscape').catch(() => {});
                        rotatedForServer = true;
                    }
                } catch (e) { /* not supported */ }
            } else if (!fsEl && rotatedForServer) {
                rotatedForServer = false;
                try {
                    if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
                } catch (e) { /* not supported */ }
            }
        }
        document.addEventListener(
            'onfullscreenchange' in document ? 'fullscreenchange' : 'webkitfullscreenchange',
            onServerFullscreenChange
        );

        // --- Server switching ---
        function switchToServer(index) {
            if (!servers[index]) return;
            currentServer = index;

            // Show loading overlay
            if (playerLoading) playerLoading.classList.add('visible');

            const url = servers[index].url;
            const isDirect = url.match(/\.(mp4|mkv|webm|m3u8)(\?|$)/i);

            if (isDirect) {
                if (playerIframe) {
                    playerIframe.style.display = 'none';
                    playerIframe.src = '';
                }
                if (playerVideo) {
                    setDirectVideoVisible(true);
                    playerVideo.src = url;
                    playerVideo.load();
                    playerVideo.play().catch(e => console.log("Play failed: ", e));
                }
            } else {
                if (playerVideo) {
                    setDirectVideoVisible(false);
                    playerVideo.src = '';
                }
                if (playerIframe) {
                    playerIframe.style.display = 'block';
                    playerIframe.src = url;
                }
            }

            // Update button label
            if (serverBtn) {
                const label = serverBtn.querySelector('.srv-label');
                if (label) label.textContent = servers[index].name;
            }

            // Update active state in dropdown
            if (serverDropdown) {
                serverDropdown.querySelectorAll('.server-option').forEach((opt, i) => {
                    opt.classList.toggle('active', i === index);
                });
            }

            // Auto-switch disabled: let the user manually switch if it buffers
        }

        // Iframe loaded successfully — hide loading overlay
        if (playerIframe) {
            playerIframe.addEventListener('load', () => {
                if (playerLoading) playerLoading.classList.remove('visible');
            });
        }

        // --- Server dropdown UI ---
        if (serverBtn && serverDropdown) {
            serverBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                serverDropdown.classList.toggle('open');
            });

            serverDropdown.querySelectorAll('.server-option').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const idx = parseInt(opt.dataset.index);
                    switchToServer(idx);
                    serverDropdown.classList.remove('open');
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', () => {
                serverDropdown.classList.remove('open');
            });
        }

        // --- Offline / Online handling ---
        function handleOffline() {
            wasOffline = true;
            clearTimeout(reconnectTimer);
            clearTimeout(loadTimeout);
            showBanner('offline', '<i class="fa-solid fa-wifi" style="opacity:0.8"></i> You\'re offline — playback may pause until connection returns');
        }

        function handleOnline() {
            if (wasOffline) {
                showBanner('back-online', '<i class="fa-solid fa-check-circle"></i> Back online — resuming playback...');
                reconnectTimer = setTimeout(() => {
                    // Reload current server to resume playback
                    if (playerIframe && servers[currentServer]) {
                        playerIframe.src = servers[currentServer].url;
                    }
                    hideBanner(2000);
                }, 1500);
                wasOffline = false;
            }
        }

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);
        if (!navigator.onLine) handleOffline();

        // Slow connection detection via Network Information API
        if ('connection' in navigator) {
            const conn = navigator.connection;
            function checkSpeed() {
                if (conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g') {
                    showBanner('slow', '<i class="fa-solid fa-signal" style="opacity:0.8"></i> Slow connection — try switching servers with the <i class="fa-solid fa-server"></i> button');
                    hideBanner(6000);
                }
            }
            conn.addEventListener('change', checkSpeed);
            setTimeout(checkSpeed, 3000);
        }

        // Periodic connectivity check
        setInterval(() => {
            if (!navigator.onLine && !wasOffline) handleOffline();
            if (navigator.onLine && wasOffline) handleOnline();
        }, 5000);
    })();

    // --- UI INTERACTION LOGIC ---
    const app = document.getElementById('playerApp');
    const sidebar = document.getElementById('sidebar');
    let idleTimer;

    // 1. Idle Fade Effect (Throttled)
    let throttleTimer;
    function resetTimer() {
        if (throttleTimer) return;
        throttleTimer = setTimeout(() => throttleTimer = null, 150);

        app.classList.remove('ui-hidden');
        clearTimeout(idleTimer);
        if (!sidebar || !sidebar.classList.contains('open')) {
            idleTimer = setTimeout(() => {
                app.classList.add('ui-hidden');
            }, 4000);
        }
    }

    ['mousemove', 'touchstart', 'click', 'keydown'].forEach(evt => {
        window.addEventListener(evt, resetTimer);
    });
    resetTimer();

    // 2. Sidebar Logic (TV Only)
    if (sidebar) {
        const desktopToggle = document.getElementById('desktopEpToggle');
        const mobileToggle = document.getElementById('mobileEpToggle');
        const closeBtn = document.getElementById('sidebarClose');
        const triggers = document.querySelectorAll('.season-trigger');

        function toggleSidebar() {
            const isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                sidebar.classList.remove('open');
                if (window.innerWidth > 900) {
                     app.classList.add('sidebar-collapsed');
                     app.classList.remove('has-sidebar');
                }
            } else {
                sidebar.classList.add('open');
                if (window.innerWidth > 900) {
                     app.classList.remove('sidebar-collapsed');
                     app.classList.add('has-sidebar');
                }
            }
        }

        if (desktopToggle) desktopToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleSidebar(); });
        if (mobileToggle) mobileToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleSidebar(); });
        if (closeBtn) closeBtn.addEventListener('click', () => toggleSidebar());

        // Accordion Logic for Seasons
        triggers.forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('active');
                const list = btn.nextElementSibling;
                list.classList.toggle('open');
            });
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 900 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && e.target !== mobileToggle && !mobileToggle.contains(e.target)) {
                    toggleSidebar();
                }
            }
        });

        // Auto-open sidebar on Desktop if it's a TV show
        if (window.innerWidth > 900) {
            app.classList.add('has-sidebar');
            sidebar.classList.add('open');
            app.classList.remove('sidebar-collapsed');
        } else {
             app.classList.add('sidebar-collapsed');
        }

        // Handle orientation changes / resizes
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                if (sidebar.classList.contains('open')) {
                    app.classList.add('has-sidebar');
                    app.classList.remove('sidebar-collapsed');
                }
            } else {
                app.classList.remove('has-sidebar');
                app.classList.add('sidebar-collapsed');
            }
        });
    }

    // Bootstrap utility class fallback
    const styleSheet = document.createElement("style");
    styleSheet.innerText = "@media (min-width: 901px) { .d-md-flex { display: flex !important; } .d-md-none { display: none !important; } } @media (max-width: 900px) { .d-none { display: none !important; } }";
    document.head.appendChild(styleSheet);

    // Prevent pull-to-refresh from interrupting playback on mobile
    document.body.addEventListener('touchmove', function(e) {
        if (document.querySelector('.video-main iframe')) {
            if (window.scrollY === 0 && e.touches[0].clientY > 0) {
                e.preventDefault();
            }
        }
    }, { passive: false });

    // Sidebar Toggle Logic
    var sidebarBtn = document.getElementById('sidebarToggleBtn');
    if(sidebarBtn) {
        sidebarBtn.addEventListener('click', function() {
            document.querySelector('.watch-app').classList.toggle('sidebar-collapsed');
        });
    }

    // Premium Search Modal Logic
    function openSearchModal() {
        document.getElementById('searchOverlay').classList.add('active');
        setTimeout(function(){ document.getElementById('overlaySearchInput').focus(); }, 50);
    }
    function closeSearchModal() {
        document.getElementById('searchOverlay').classList.remove('active');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSearchModal();
    });

    // Watchlist Logic
    const watchlistBtn = document.getElementById('watchlistBtn');
    if (watchlistBtn) {
        watchlistBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('ph-plus')) {
                icon.classList.replace('ph-plus', 'ph-check');
                this.style.color = '#4ade80'; // Success green
                showBanner('success', '<i class="fa-solid fa-check"></i> Added to your watchlist');
                hideBanner(3000);
            } else {
                icon.classList.replace('ph-check', 'ph-plus');
                this.style.color = '';
                showBanner('removed', '<i class="fa-solid fa-xmark"></i> Removed from watchlist');
                hideBanner(3000);
            }
        });
    }

    // Download Logic
    const downloadBtn = document.getElementById('downloadBtn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const title = "<?php echo urlencode(preg_replace('/[^a-zA-Z0-9\s]/', '', $videoTitle)); ?>";
            const type = "<?php echo $mediaType; ?>";
            let downloadUrl = "";
            if (type === 'movie') {
                downloadUrl = `https://yts.mx/browse-movies/${title}/all/all/0/latest/0/all`;
            } else {
                downloadUrl = `https://1337x.to/category-search/${title}/TV/1/`;
            }
            window.open(downloadUrl, '_blank');
        });
    }

    // Automatically track history and simulate progress realistically based on time spent on page
    <?php
    $durationSeconds = 120 * 60; // Default 120 minutes (7200 seconds)
    if ($mediaType === 'movie') {
        if (!empty($details['runtime'])) {
            $durationSeconds = $details['runtime'] * 60;
        }
    } else {
        if (!empty($details['episode_run_time'][0])) {
            $durationSeconds = $details['episode_run_time'][0] * 60;
        } else {
            $durationSeconds = 45 * 60; // Default TV show episode 45 minutes
        }
    }
    
    // Fetch saved progress if user is logged in
    $savedProgress = 0;
    if (isset($_SESSION['user_id']) && isset($conn)) {
        try {
            $stmt = $conn->prepare("SELECT current_time FROM watch_history WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
            $savedProgress = (int) $stmt->fetchColumn();
        } catch (Exception $e) {}
    }
    ?>

    const startTime = Date.now();
    const savedProgress = <?php echo $savedProgress; ?>;
    const duration = <?php echo $durationSeconds; ?>;
    
    // If no saved progress, start at a small realistic progress (e.g. 5 minutes or 300 seconds) so it registers on the dashboard immediately.
    const initialProgress = savedProgress > 0 ? savedProgress : 300;
    
    function updateProgress() {
        const playerVideoElement = document.getElementById('playerVideo');
        let currentTime;
        let totalDuration = duration;
        
        if (playerVideoElement && playerVideoElement.style.display !== 'none' && !isNaN(playerVideoElement.duration) && playerVideoElement.duration > 0) {
            currentTime = Math.floor(playerVideoElement.currentTime);
            totalDuration = Math.round(playerVideoElement.duration);
        } else {
            const elapsedSeconds = Math.floor((Date.now() - startTime) / 1000);
            currentTime = initialProgress + elapsedSeconds;
            
            // Cap current time at duration
            if (currentTime > totalDuration) {
                currentTime = totalDuration;
            }
        }
        
        const formData = new FormData();
        formData.append('media_id', "<?php echo $mediaId; ?>");
        formData.append('media_type', "<?php echo $mediaType; ?>");
        formData.append('current_time', currentTime);
        formData.append('duration', totalDuration);
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/update-history', formData);
        } else {
            fetch('/update-history', {
                method: 'POST',
                body: formData
            }).catch(err => console.error('History Error:', err));
        }
    }
    
    // Update after 5 seconds initially so it gets registered
    setTimeout(updateProgress, 5000);
    
    // And then update every 15 seconds
    setInterval(updateProgress, 15000);
    
    // And also update when leaving the page
    window.addEventListener('beforeunload', updateProgress);
</script>

<!-- Fullscreen Search Overlay -->
<div id="searchOverlay" class="search-overlay">
    <button class="search-close-btn" onclick="closeSearchModal()"><i class="ph ph-x"></i></button>
    <div class="search-overlay-content">
        <div class="search-overlay-title">What do you want to watch?</div>
        <form action="view-all" method="GET" class="search-overlay-form">
            <i class="ph ph-magnifying-glass search-icon"></i>
            <input type="text" name="search" id="overlaySearchInput" placeholder="Search movies, TV shows, actors..." autocomplete="off" required>
            <button type="submit" class="search-submit-btn">Search</button>
        </form>
        <div class="search-overlay-hint">Press <kbd>Esc</kbd> to close</div>
    </div>
</div>

<?php include __DIR__ . '/zen-ai.php'; ?>

<!-- Theme Modal (Color House) -->
<div id="themeModal" class="theme-overlay" style="display: none;">
    <div class="theme-modal-content">
        <div class="theme-modal-header">
            <h3><i class="ph ph-sparkle text-primary"></i> Color House</h3>
            <button class="theme-close-btn" onclick="closeThemeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="theme-grid">
            <div class="theme-card" onclick="setTheme('')" data-theme="">
                <div class="theme-color-preview" style="background: #e50914;"></div>
                <span>Ruby Cinematic</span>
            </div>
            <div class="theme-card" onclick="setTheme('cyberpunk')" data-theme="cyberpunk">
                <div class="theme-color-preview" style="background: #00f0ff;"></div>
                <span>Neon Cyberpunk</span>
            </div>
            <div class="theme-card" onclick="setTheme('gold')" data-theme="gold">
                <div class="theme-color-preview" style="background: #ffd700;"></div>
                <span>Midnight Gold</span>
            </div>
            <div class="theme-card" onclick="setTheme('emerald')" data-theme="emerald">
                <div class="theme-color-preview" style="background: #00e676;"></div>
                <span>Emerald Aurora</span>
            </div>
        </div>
    </div>
</div>

<!-- Floating Theme Switcher Button -->
<div class="theme-switcher-float" onclick="openThemeModal()">
    <i class="ph ph-palette text-primary"></i>
</div>

<style>
/* Floating Button */
.theme-switcher-float {
    position: fixed; bottom: 100px; right: 30px; width: 50px; height: 50px;
    z-index: 99999999 !important; cursor: pointer; pointer-events: auto;
    display: flex; align-items: center; justify-content: center;
    background: rgba(11, 12, 21, 0.85); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.theme-switcher-float:hover {
    transform: scale(1.15) rotate(15deg);
    border-color: var(--primary);
    box-shadow: 0 10px 30px var(--primary-glow);
}
.theme-switcher-float i { font-size: 22px; transition: 0.3s; pointer-events: none; }

/* Theme Modal Styles */
.theme-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(8, 8, 12, 0.85); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
    z-index: 99999999 !important; display: flex; align-items: center; justify-content: center;
}
.theme-modal-content {
    background: rgba(20, 20, 25, 0.95); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; width: 90%; max-width: 500px; padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    animation: themeModalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes themeModalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.theme-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
.theme-modal-header h3 { margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.theme-close-btn { background: rgba(255,255,255,0.05); border: none; color: #aaa; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.theme-close-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: rotate(90deg); }

.theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.theme-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
    padding: 20px; border-radius: 16px; cursor: pointer; transition: 0.2s;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.theme-card:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); transform: translateY(-3px); }
.theme-card.active { border-color: var(--primary); background: rgba(255,255,255,0.05); box-shadow: 0 0 20px var(--primary-glow); }
.theme-color-preview { width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
.theme-card span { font-weight: 600; font-size: 0.95rem; color: #eee; }
</style>

<script>
function openThemeModal() {
    document.getElementById('themeModal').style.display = 'flex';
    updateActiveThemeCard();
}

function closeThemeModal() {
    document.getElementById('themeModal').style.display = 'none';
}

function setTheme(themeName) {
    if (themeName) {
        document.documentElement.setAttribute('data-theme', themeName);
        localStorage.setItem('zen_theme', themeName);
    } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('zen_theme');
    }
    updateActiveThemeCard();
}

function updateActiveThemeCard() {
    const currentTheme = localStorage.getItem('zen_theme') || '';
    document.querySelectorAll('.theme-card').forEach(card => {
        if (card.getAttribute('data-theme') === currentTheme) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}

// Watchlist AJAX Logic
const watchlistBtns = document.querySelectorAll('.watchlist-btn');
watchlistBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const mediaId = this.getAttribute('data-id');
        const mediaType = this.getAttribute('data-type') || 'movie';
        const icon = this.querySelector('i');
        
        fetch('/add-watchlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${mediaId}&type=${mediaType}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                if (data.action === 'added') {
                    if (icon) icon.className = 'ph ph-check text-success';
                    this.setAttribute('title', 'Remove from Watchlist');
                } else {
                    if (icon) icon.className = 'ph ph-plus';
                    this.setAttribute('title', 'Add to Watchlist');
                }
            } else {
                if (data.message.includes('login')) {
                    window.location.href = '/login';
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(err => console.error('Watchlist Error:', err));
    });
});

// Cinematic Mode Toggle
function toggleCinematicMode() {
    const isActive = document.body.classList.contains('cinematic-active');
    
    if (!isActive) {
        if (confirm("Enable Cinematic Mode? This will dim the background so you can focus entirely on the movie. (Works best when not in fullscreen)")) {
            document.body.classList.add('cinematic-active');
            const btn = document.getElementById('cinematicToggleBtn');
            if (btn) {
                btn.style.color = '#e50914';
                btn.title = "Turn Off Cinematic Mode";
            }
        }
    } else {
        document.body.classList.remove('cinematic-active');
        const btn = document.getElementById('cinematicToggleBtn');
        if (btn) {
            btn.style.color = '#00e0ff';
            btn.title = "Cinematic Mode";
        }
    }
}
</script>

<script>
// ==========================================
// WATCH PAGE AI COMPANION LOGIC
// ==========================================
(function() {
    const chatId = 'watch-' + Math.random().toString(36).substr(2, 9);
    const movieTitle = <?php echo json_encode($videoTitle); ?>;
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    window.openWatchAI = function() {
        document.body.classList.add('ai-active');
        document.getElementById('sidebarContent').style.display = 'none';
        document.getElementById('watchAiTrigger').style.display = 'none';
        var panel = document.getElementById('watchAiPanel');
        panel.style.display = 'flex';
        // Scroll sidebar to top
        document.getElementById('sidebar').scrollTop = 0;
        setTimeout(function() { document.getElementById('watchAiInput').focus(); }, 100);
    };

    window.closeWatchAI = function() {
        document.body.classList.remove('ai-active');
        document.getElementById('watchAiPanel').style.display = 'none';
        document.getElementById('sidebarContent').style.display = 'block';
        document.getElementById('watchAiTrigger').style.display = 'flex';
    };

    function appendMsg(text, isUser) {
        var chat = document.getElementById('watchAiChat');
        var div = document.createElement('div');
        if (isUser) {
            div.style.cssText = 'background:rgba(0,224,255,0.1);border:1px solid rgba(0,224,255,0.2);border-radius:12px;border-top-right-radius:4px;padding:12px 14px;color:#fff;font-size:0.85rem;line-height:1.5;max-width:90%;align-self:flex-end;';
        } else {
            div.style.cssText = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;border-top-left-radius:4px;padding:12px 14px;color:#e0e0e0;font-size:0.85rem;line-height:1.6;max-width:90%;';
        }
        div.innerHTML = text;
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
        return div;
    }

    window.handleWatchAiSubmit = function(e) {
        e.preventDefault();
        var input = document.getElementById('watchAiInput');
        var query = input.value.trim();
        if (!query) return;

        appendMsg(query, true);
        input.value = '';

        if (!isLoggedIn) {
            appendMsg('🔒 <strong>Please log in</strong> to use ZEN AI.', false);
            return;
        }

        var loader = appendMsg('<i class="ph ph-circle-notch" style="animation:watchAiSpin 1s linear infinite;display:inline-block;"></i> Thinking...', false);

        var contextQuery = "Context: The user is watching '" + movieTitle + "'. Keep answers concise. " + query;
        var fd = new FormData();
        fd.append('query', contextQuery);
        fd.append('conversation_id', chatId);

        fetch('/ask', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loader.remove();
            if (data.status === 'success') {
                var reply = (data.reply || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                appendMsg(reply, false);

                // Render movie cards if AI returned any
                if (data.movies && data.movies.length > 0) {
                    var cardsHtml = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:10px; margin-top:10px;">';
                    data.movies.forEach(function(m) {
                        var link = '/' + (m.type || 'movie') + '/' + m.id;
                        cardsHtml += '<a href="' + link + '" style="text-decoration:none; color:inherit; border-radius:8px; overflow:hidden; position:relative; display:block; aspect-ratio:2/3; background:#1a1a2e;">' +
                            '<img src="' + (m.poster_path || 'assets/images/media/placeholder.webp') + '" alt="' + (m.title || '') + '" style="width:100%; height:100%; object-fit:cover;" loading="lazy">' +
                            '<div style="position:absolute; bottom:0; left:0; right:0; padding:15px 6px 6px; background:linear-gradient(transparent, rgba(0,0,0,0.95)); font-size:0.7rem; color:#fff; font-weight:700; text-align:center; line-height:1.2;">' +
                                (m.title || '') +
                                (m.rating ? '<div style="color:#ffc107; font-size:0.65rem; margin-top:3px;"><i class="ph-fill ph-star"></i> ' + Number(m.rating).toFixed(1) + '</div>' : '') +
                            '</div>' +
                        '</a>';
                    });
                    cardsHtml += '</div>';
                    appendMsg(cardsHtml, false);
                }
            } else {
                appendMsg('❌ ' + (data.message || 'Something went wrong.'), false);
            }
        })
        .catch(function() {
            loader.remove();
            appendMsg('❌ Network error. Please try again.', false);
        });
    };
})();
</script>
<style>@keyframes watchAiSpin{to{transform:rotate(360deg)}}</style>

<!-- Mobile AI Floating Button -->
<button id="mobileAiBtn" onclick="openMobileAI()">
    <i class="ph-fill ph-sparkle"></i>
</button>

<!-- Mobile AI Full-Screen Chat -->
<div id="mobileAiModal">
    <div style="display:flex; align-items:center; justify-content:space-between; padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.3); flex-shrink:0;">
        <div style="display:flex; align-items:center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #00e0ff, #7b2cbf); display:flex; align-items:center; justify-content:center;">
                <i class="ph-fill ph-sparkle" style="color:#fff; font-size: 0.9rem;"></i>
            </div>
            <div>
                <div style="font-size: 0.95rem; font-weight: 700; color: #fff;">ZEN AI</div>
                <div style="font-size: 0.65rem; color: #00e0ff;">Watching: <?php echo htmlspecialchars(mb_strimwidth($videoTitle, 0, 25, '...')); ?></div>
            </div>
        </div>
        <button onclick="closeMobileAI()" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #aaa; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display:flex; align-items:center; justify-content:center; font-size: 1.1rem;">
            <i class="ph ph-x"></i>
        </button>
    </div>
    <div id="mobileAiChat" style="flex:1; overflow-y:auto; padding: 20px; display:flex; flex-direction:column; gap: 12px;">
        <div style="background: rgba(0,224,255,0.08); border: 1px solid rgba(0,224,255,0.15); border-radius: 12px; border-top-left-radius: 4px; padding: 14px 16px; color: #e0e0e0; font-size: 0.85rem; line-height: 1.6; max-width: 90%;">
            👋 Hey! I know everything about <strong style="color:#00e0ff;"><?php echo htmlspecialchars($videoTitle); ?></strong>. Ask me about the plot, characters, hidden details, or anything!
        </div>
    </div>
    <div style="padding: 14px 16px; padding-bottom: max(14px, env(safe-area-inset-bottom)); border-top: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.3); flex-shrink:0;">
        <div style="display:flex; gap: 8px;">
            <input type="text" id="mobileAiInput" placeholder="Ask something..." autocomplete="off" style="flex:1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #fff; font-size: 0.9rem; border-radius: 10px; padding: 12px 14px; outline: none;" onfocus="this.style.borderColor='rgba(0,224,255,0.4)'" onblur="this.style.borderColor='rgba(255,255,255,0.12)'" onkeypress="if(event.key === 'Enter') handleMobileAiSubmit(event)">
            <button onclick="handleMobileAiSubmit(event)" style="background: linear-gradient(135deg, #00e0ff, #7b2cbf); border: none; border-radius: 10px; padding: 0 16px; color: #fff; cursor: pointer; display:flex; align-items:center; justify-content:center;">
                <i class="ph-fill ph-paper-plane-right" style="font-size: 1.1rem;"></i>
            </button>
        </div>
        <p style="color: #555; text-align:center; margin: 8px 0 0; font-size: 0.65rem;">AI can make mistakes</p>
    </div>
</div>

<script>
// Mobile AI Chat Logic
(function() {
    var mobileChatId = 'mobile-' + Math.random().toString(36).substr(2, 9);
    var movieTitle = <?php echo json_encode($videoTitle); ?>;
    var isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    window.openMobileAI = function() {
        document.getElementById('mobileAiModal').classList.add('active');
        setTimeout(function() { document.getElementById('mobileAiInput').focus(); }, 150);
    };
    window.closeMobileAI = function() {
        document.getElementById('mobileAiModal').classList.remove('active');
    };

    function mobileAppendMsg(text, isUser) {
        var chat = document.getElementById('mobileAiChat');
        var div = document.createElement('div');
        if (isUser) {
            div.style.cssText = 'background:rgba(0,224,255,0.1);border:1px solid rgba(0,224,255,0.2);border-radius:12px;border-top-right-radius:4px;padding:12px 14px;color:#fff;font-size:0.85rem;line-height:1.5;max-width:85%;align-self:flex-end;';
        } else {
            div.style.cssText = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;border-top-left-radius:4px;padding:12px 14px;color:#e0e0e0;font-size:0.85rem;line-height:1.6;max-width:85%;';
        }
        div.innerHTML = text;
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
        return div;
    }

    window.handleMobileAiSubmit = function(e) {
        e.preventDefault();
        var input = document.getElementById('mobileAiInput');
        var query = input.value.trim();
        if (!query) return;
        mobileAppendMsg(query, true);
        input.value = '';
        if (!isLoggedIn) { mobileAppendMsg('🔒 <strong>Please log in</strong> to use ZEN AI.', false); return; }
        var loader = mobileAppendMsg('<i class="ph ph-circle-notch" style="animation:watchAiSpin 1s linear infinite;display:inline-block;"></i> Thinking...', false);
        var fd = new FormData();
        fd.append('query', "Context: The user is watching '" + movieTitle + "'. Keep answers concise. " + query);
        fd.append('conversation_id', mobileChatId);
        fetch('/ask', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loader.remove();
            if (data.status === 'success') {
                var reply = (data.reply || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                mobileAppendMsg(reply, false);

                // Render movie cards if AI returned any
                if (data.movies && data.movies.length > 0) {
                    var cardsHtml = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:8px; margin-top:8px;">';
                    data.movies.forEach(function(m) {
                        var link = '/' + (m.type || 'movie') + '/' + m.id;
                        cardsHtml += '<a href="' + link + '" style="text-decoration:none; color:inherit; border-radius:8px; overflow:hidden; position:relative; display:block; aspect-ratio:2/3; background:#1a1a2e;">' +
                            '<img src="' + (m.poster_path || 'assets/images/media/placeholder.webp') + '" alt="' + (m.title || '') + '" style="width:100%; height:100%; object-fit:cover;" loading="lazy">' +
                            '<div style="position:absolute; bottom:0; left:0; right:0; padding:12px 4px 4px; background:linear-gradient(transparent, rgba(0,0,0,0.95)); font-size:0.65rem; color:#fff; font-weight:700; text-align:center; line-height:1.2;">' +
                                (m.title || '') +
                            '</div>' +
                        '</a>';
                    });
                    cardsHtml += '</div>';
                    mobileAppendMsg(cardsHtml, false);
                }
            } else { mobileAppendMsg('❌ ' + (data.message || 'Something went wrong.'), false); }
        })
        .catch(function() { loader.remove(); mobileAppendMsg('❌ Network error.', false); });
    };
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
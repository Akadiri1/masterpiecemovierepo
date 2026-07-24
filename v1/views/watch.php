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
// Multiple embed servers for fallback — if one buffers on slow network, switch to another
$servers = [];
if (!$isUpcoming) {
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
        $servers[] = ['name' => 'Server 4 (Trailer)', 'url' => "https://www.youtube.com/embed/$trailerKey?autoplay=1"];
    }
    $servers[] = ['name' => 'Server 5 (Demo)', 'url' => "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4"];
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
  <link rel="manifest" href="manifest.json">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <base href="<?php echo htmlspecialchars($baseDir); ?>">
  
  <link rel="stylesheet" href="assets/css/core/watch-theme.css?v=<?php echo time() + 2; ?>">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">
  <style>
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
      .watch-app.sidebar-collapsed .watch-right { display: none !important; }
      #serverDropdown.open { display: block !important; }
      
      /* Fix iframe unclickable controls */
      .video-wrapper { overflow: visible !important; border-radius: 0 !important; }
      #networkBanner { pointer-events: none !important; }
      
      /* Mobile Offcanvas Sidebars */
      @media (max-width: 900px) {
          .watch-app { display: block !important; }
          .watch-sidebar { position: fixed !important; left: 0; top: 0; bottom: 0; width: 280px; z-index: 10000; transform: translateX(-100%); transition: 0.3s; }
          .watch-right { position: fixed !important; right: 0; top: 0; bottom: 0; width: 300px; z-index: 10000; transform: translateX(100%); transition: 0.3s; }
          .watch-sidebar.open { transform: translateX(0); display: flex !important; }
          .watch-right.open { transform: translateX(0); display: flex !important; }
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
                </div>
             <?php else: ?>
                <iframe id="playerIframe" src="<?php echo $videoSrc; ?>" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" style="width:100%; height:100%; border:none;"></iframe>
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
                  
                  <button class="btn-action" id="watchlistBtn" title="Add to Watchlist"><i class="ph ph-plus"></i></button>
                  <button class="btn-action" onclick="navigator.share({title: document.title, url: window.location.href})"><i class="ph ph-share-network"></i></button>
                  <?php if (!$isUpcoming && !empty($servers)): ?>
                  <button class="btn-action" id="downloadBtn" title="Download"><i class="ph ph-download-simple"></i></button>
                  <?php endif; ?>
             </div>
        </div>

        <!-- Recommended Section -->
        <?php if (!empty($details['recommendations']['results'])): ?>
        <div class="recommended-section">
            <h4>Recommended</h4>
            <div class="rec-cards">
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

    <!-- 3. Right Panel -->
    <aside class="watch-right custom-scrollbar" id="sidebar">
       <?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
       <div class="right-controls-top">
           <div class="dropdown-btn" onclick="document.getElementById('seasonDropdownNav').classList.toggle('d-none');">Season <?php echo $seasonNum; ?> <i class="ph ph-caret-down"></i></div>
           <div class="icon-btn-group">
               <button class="icon-btn"><i class="ph ph-list"></i></button>
               <button class="icon-btn"><i class="ph ph-squares-four"></i></button>
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

       <div class="episode-list">
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

        // --- Server switching ---
        function switchToServer(index) {
            if (!playerIframe || !servers[index]) return;
            currentServer = index;

            // Show loading overlay
            if (playerLoading) playerLoading.classList.add('visible');

            // Update iframe src
            playerIframe.src = servers[index].url;

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

    // Automatically track history after a few seconds of watching
    setTimeout(function() {
        const formData = new FormData();
        formData.append('media_id', "<?php echo $mediaId; ?>");
        // Simulate progress for iframe-based players
        formData.append('current_time', Math.floor(Math.random() * 30) + 10);
        formData.append('duration', 120); 
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/update-history', formData);
        } else {
            fetch('/update-history', {
                method: 'POST',
                body: formData
            }).catch(err => console.error('History Error:', err));
        }
    }, 5000); // 5 seconds after load
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
</script>
</body>
</html>
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
// Adjust this path to point to your actual configuration file if needed
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
        $details = fetchTmdbApi("movie/{$mediaId}", ['append_to_response' => 'videos,release_dates']);
        
        if ($details) {
            $videoTitle = $details['title'] ?? 'Movie';
            $backdrop = $details['backdrop_path'] ?? '';
            
            // LOGIC: Check Release Date
            $today = date('Y-m-d');
            $releaseDate = $details['release_date'] ?? '';
            
            // If release date is in the future
            if ($releaseDate && $releaseDate > $today) {
                $isUpcoming = true;
                $releaseDateDisplay = date('F j, Y', strtotime($releaseDate));
                $trailerKey = getTrailerKey($details['videos'] ?? []);
                $releaseMessage = "This movie is scheduled for theatrical release on {$releaseDateDisplay}. It is not yet available for streaming.";
            }
        }
    } 
    // --- TV SHOW LOGIC ---
    else {
        // 1. Fetch Show Details
        $details = fetchTmdbApi("tv/{$mediaId}");
        
        if ($details) {
            $videoTitle = $details['name'] ?? 'TV Show';
            $backdrop = $details['backdrop_path'] ?? '';
            $videoSubTitle = "Season {$seasonNum} - Episode {$episodeNum}";
            
            // 2. Fetch Specific Episode to check Air Date
            $epDetails = fetchTmdbApi("tv/{$mediaId}/season/{$seasonNum}/episode/{$episodeNum}", ['append_to_response' => 'videos']);
            
            if ($epDetails) {
                $airDate = $epDetails['air_date'] ?? '';
                $today = date('Y-m-d');
                
                // If air date is in the future
                if ($airDate && $airDate > $today) {
                    $isUpcoming = true;
                    $releaseDateDisplay = date('F j, Y', strtotime($airDate));
                    $trailerKey = getTrailerKey($epDetails['videos'] ?? []);
                    
                    // Fallback: If episode has no specific trailer, use the main TV show trailer
                    if(empty($trailerKey)) {
                         $showVids = fetchTmdbApi("tv/{$mediaId}/videos");
                         $trailerKey = getTrailerKey($showVids ?? []);
                    }
                    
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
// Only set video source if it's NOT upcoming
$videoSrc = "";
if (!$isUpcoming) {
    $baseUrlVidSrc = "https://vidsrc.xyz/embed";
    if ($mediaType === 'movie') {
        $videoSrc = "$baseUrlVidSrc/movie/$mediaId";
    } else {
        $videoSrc = "$baseUrlVidSrc/tv/$mediaId/$seasonNum/$episodeNum";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Watch: <?php echo htmlspecialchars($videoTitle); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <style>
      :root { --primary: #e50914; --dark-bg: #111; --text-light: #eee; --text-dark: #999; }
      * { box-sizing: border-box; }
      body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }

      /* --- Main Layout --- */
      .player-container { display: flex; width: 100vw; height: 100vh; position: relative; }
      .video-main { flex-grow: 1; position: relative; background: #000; display: flex; align-items: center; justify-content: center; }
      iframe { width: 100%; height: 100%; border: none; }

      /* --- Header UI --- */
      .header-ui {
          position: absolute; top: 0; left: 0; width: 100%; padding: 15px 20px;
          background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);
          z-index: 20; display: flex; justify-content: space-between; align-items: center;
          transition: opacity 0.3s ease; opacity: 1;
      }

      /* When UI is hidden, hide Header, Controls, and Play Overlay */
      .ui-hidden .header-ui,
      .ui-hidden .player-controls,
      .ui-hidden .overlay-play,
      .ui-hidden .mobile-ep-toggle { 
          opacity: 0 !important; pointer-events: none; 
      }
      
      .header-left { display: flex; align-items: center; gap: 15px; }
      .back-btn {
          color: white; text-decoration: none; font-size: 20px;
          width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
          border-radius: 50%; background: rgba(30,30,30,0.7); backdrop-filter: blur(5px);
          transition: background 0.2s;
      }
      .back-btn:hover { background: var(--primary); }

      .meta-text { color: white; display: flex; flex-direction: column; }
      .meta-text h2 { font-size: 1.1em; margin: 0; font-weight: 600; text-shadow: 1px 1px 3px rgba(0,0,0,0.7); }
      .meta-text span { font-size: 0.8em; opacity: 0.8; }

      .header-controls { display: flex; gap: 10px; align-items: center; }
      
      .visibility-toggle, .desktop-ep-toggle {
          background: rgba(30,30,30,0.7); border: none; color: white;
          width: 40px; height: 40px; border-radius: 50%; cursor: pointer;
          display: flex; align-items: center; justify-content: center; transition: background 0.2s;
      }
      .visibility-toggle:hover, .desktop-ep-toggle:hover { background: rgba(50,50,50,0.8); }

      /* --- Upcoming Screen Styles --- */
      .upcoming-container {
          position: absolute; inset: 0; width: 100%; height: 100%;
          background-size: cover; background-position: center;
          display: flex; align-items: center; justify-content: center;
          z-index: 5;
      }
      .upcoming-overlay {
          position: absolute; inset: 0;
          background: linear-gradient(0deg, #000 0%, rgba(0,0,0,0.8) 50%, rgba(0,0,0,0.8) 100%);
          backdrop-filter: blur(5px);
      }
      .upcoming-content {
          position: relative; z-index: 10; text-align: center; color: white;
          max-width: 800px; padding: 30px; width: 90%;
      }
      .release-badge {
          display: inline-block; background: var(--primary); color: white;
          padding: 6px 16px; border-radius: 4px; font-weight: bold; font-size: 0.9rem;
          text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;
          box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
      }
      .upcoming-title {
          font-size: 2.5rem; font-weight: 800; margin-bottom: 10px;
          text-shadow: 0 2px 10px rgba(0,0,0,0.5);
      }
      .upcoming-desc {
          font-size: 1.1rem; color: #ccc; margin-bottom: 30px; line-height: 1.5;
      }
      .trailer-box {
          margin-top: 20px; border-radius: 12px; overflow: hidden;
          box-shadow: 0 20px 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);
          aspect-ratio: 16/9; width: 100%; max-width: 800px; margin-left: auto; margin-right: auto;
      }

      /* --- Sidebar --- */
      .episodes-sidebar { 
          width: 320px; background: var(--dark-bg); border-left: 1px solid #222; 
          display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s ease;
          flex-shrink: 0; position: relative; 
      }
      .sidebar-content { flex-grow: 1; overflow-y: auto; padding: 10px; }
      .sidebar-content::-webkit-scrollbar { width: 5px; }
      .sidebar-content::-webkit-scrollbar-thumb { background: #333; }
      .sidebar-header-container { display: flex; justify-content: space-between; align-items: center; padding: 10px 5px 10px 15px; }
      .sidebar-header { color: white; margin: 0; font-size: 1.2em; font-weight: 600; }
      .sidebar-close-btn { color: var(--text-dark); background: none; border: none; font-size: 1.4em; cursor: pointer; padding: 5px 10px; }
      .season-item { margin-bottom: 8px; }
      .season-header { 
          background: #1f1f1f; padding: 12px 15px; cursor: pointer; 
          display: flex; justify-content: space-between; align-items: center; 
          border-radius: 4px; color: var(--text-light); font-weight: 500; transition: background 0.2s;
      }
      .season-header:hover { background: #2a2a2a; }
      .season-header.active i { transform: rotate(180deg); }
      .episode-list { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; background: #0a0a0a; }
      .episode-list.show { max-height: 800px; }
      .episode-item { 
          display: flex; align-items: center; padding: 10px 15px; text-decoration: none; 
          color: var(--text-dark); border-bottom: 1px solid #1a1a1a; transition: all 0.2s; font-size: 0.9em;
      }
      .episode-item:hover { background: #1a1a1a; color: var(--text-light); }
      .episode-item.active { background: #252525; color: var(--primary); border-left: 3px solid var(--primary); padding-left: 12px; }
      
      .mobile-ep-toggle {
          position: absolute; right: 20px; bottom: 80px; z-index: 50;
          background: var(--primary); color: white; border: none; width: 50px; height: 50px; 
          border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.5);
          display: none; align-items: center; justify-content: center; font-size: 1.2em; cursor: pointer; transition: opacity 0.3s ease;
      }
      .sidebar-collapsed .episodes-sidebar { width: 0; border-left: none; }
      
      @media (max-width: 900px) {
          .episodes-sidebar { position: absolute; top: 0; right: 0; height: 100%; width: 85%; max-width: 320px; transform: translateX(100%); box-shadow: -5px 0 15px rgba(0,0,0,0.5); }
          .episodes-sidebar.open { transform: translateX(0); }
          .mobile-ep-toggle { display: <?php echo ($mediaType === 'tv' && !empty($seasonsData)) ? 'flex' : 'none'; ?>; }
          .desktop-ep-toggle { display: none; }
      }

      /* --- Player UI --- */
      .player-wrapper { position: relative; width: 100%; height: 100%; display: flex; align-items: stretch; }
      .player-video { position: relative; width:100%; height:100%; background: #000; display:flex; align-items:center; justify-content:center; }
      
      .overlay-play { 
          position: absolute; z-index: 40; width:78px; height:78px; border-radius:50%; 
          display:flex; align-items:center; justify-content:center; background: rgba(0,0,0,0.5); 
          color:#fff; border: 2px solid rgba(255,255,255,0.06); font-size: 28px; 
          transition: transform .15s ease, opacity .3s ease; 
      }
      .overlay-play:hover { transform: scale(1.06); }

      .player-controls { 
          position: absolute; left: 16px; right: 16px; bottom: 18px; z-index: 45; 
          display:flex; align-items:center; justify-content:space-between; gap:12px; 
          padding: 10px 14px; background: linear-gradient(180deg, rgba(0,0,0,0.25), rgba(0,0,0,0.15)); 
          border-radius: 10px; border: 1px solid rgba(255,255,255,0.04); transition: opacity 0.3s ease;
      }
      .player-info { color: #fff; display:flex; flex-direction:column; gap:2px; }
      .player-title { font-weight:700; font-size:0.98rem; }
      .player-subtitle { font-size:0.82rem; color: rgba(255,255,255,0.7); }
      .player-actions { display:flex; gap:8px; align-items:center; }
      .control-btn { width:40px; height:40px; border-radius:50%; border:none; display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,0.03); color:#fff; cursor:pointer; }
      
      /* Hide elements on small screens if needed */
      @media (max-width: 600px) {
          .upcoming-title { font-size: 1.8rem; }
          .upcoming-desc { font-size: 0.9rem; }
      }
  </style>
</head>
<body>

<div class="player-container" id="playerApp">
    <div class="video-main" id="videoArea">
        <div class="player-wrapper" id="playerWrapper">
            
            <!-- 1. HEADER (Always visible initially) -->
            <div class="player-header header-ui" id="headerUi">
                <div class="header-left">
                    <a href="#" onclick="history.back()" class="back-btn" title="Go Back"><i class="fa-solid fa-arrow-left"></i></a>
                    <div class="meta-text">
                        <h2><?php echo $videoTitle; ?></h2>
                        <span><?php echo htmlspecialchars($videoSubTitle); ?></span>
                    </div>
                </div>
                <div class="header-controls">
                    <?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
                        <button class="desktop-ep-toggle" id="desktopSidebarToggle" title="Toggle Episodes"><i class="fa-solid fa-list-ul"></i></button>
                    <?php endif; ?>
                    <button class="visibility-toggle" onclick="toggleUiLock()" title="Toggle Interface"><i class="fa-solid fa-eye" id="eyeIcon"></i></button>
                </div>
            </div>

            <!-- 2. CONTENT AREA -->
            <div class="player-video" id="playerVideo">
                
                <?php if ($isUpcoming): ?>
                    <!-- A. UPCOMING STATE (Trailer + Info) -->
                    <div class="upcoming-container" style="background-image: url('https://image.tmdb.org/t/p/original<?php echo $backdrop; ?>');">
                        <div class="upcoming-overlay"></div>
                        <div class="upcoming-content">
                            <span class="release-badge">Coming Soon</span>
                            <h1 class="upcoming-title"><?php echo htmlspecialchars($videoTitle); ?></h1>
                            <p class="upcoming-desc">
                                <?php echo htmlspecialchars($releaseMessage); ?>
                            </p>
                            
                            <?php if ($trailerKey): ?>
                                <div class="trailer-box">
                                    <iframe 
                                        src="https://www.youtube.com/embed/<?php echo $trailerKey; ?>?autoplay=1&rel=0&showinfo=0&modestbranding=1" 
                                        frameborder="0" 
                                        allow="autoplay; encrypted-media; picture-in-picture" 
                                        allowfullscreen
                                        style="width: 100%; height: 100%;">
                                    </iframe>
                                </div>
                            <?php else: ?>
                                <div class="p-5 border border-secondary rounded-3 bg-dark bg-opacity-50 mt-4">
                                    <i class="fa-brands fa-youtube fa-3x text-muted mb-3"></i>
                                    <p class="mb-0 text-white-50">Trailer not released yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- B. STANDARD PLAYER STATE -->
                    <iframe 
                        id="videoFrame"
                        src="<?php echo $videoSrc; ?>" 
                        allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
                        allowfullscreen
                        webkitallowfullscreen
                        mozallowfullscreen
                    ></iframe>

                    <button id="overlayPlay" class="overlay-play" aria-label="Play/Pause"><i class="fa-solid fa-play"></i></button>

                    <div class="player-controls" id="playerControls">
                        <div class="player-info">
                            <div class="player-title"><?php echo htmlspecialchars($videoTitle); ?></div>
                            <div class="player-subtitle"><?php echo htmlspecialchars($videoSubTitle); ?></div>
                        </div>
                        <div class="player-actions">
                            <button class="control-btn" id="controlPlay"><i class="fa-solid fa-play"></i></button>
                            <button class="control-btn" id="controlSkipBack" title="Back 10s"><i class="fa-solid fa-rotate-left"></i></button>
                            <button class="control-btn" id="controlFullscreen" title="Fullscreen"><i class="fa-solid fa-expand"></i></button>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        
        <!-- Only show sidebar toggle if standard player -->
        <?php if (!$isUpcoming && ($mediaType === 'tv' && !empty($seasonsData))): ?>
        <button class="mobile-ep-toggle" id="sidebarToggle" title="Show episodes"><i class="fa-solid fa-list-ul"></i></button>
        <?php endif; ?>
    </div>

    <!-- SIDEBAR (Only for TV) -->
    <?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
    <div class="episodes-sidebar" id="episodesSidebar">
        <div class="sidebar-content">
            <div class="sidebar-header-container">
                <h3 class="sidebar-header">Episodes</h3>
                <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <?php foreach ($seasonsData as $season): ?>
            <div class="season-item">
                <div class="season-header <?php echo $season['season_number'] == $seasonNum ? 'active' : ''; ?>">
                    <span><?php echo htmlspecialchars($season['name']); ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="episode-list <?php echo $season['season_number'] == $seasonNum ? 'show' : ''; ?>">
                    <?php foreach ($season['episodes'] as $episode): ?>
                        <a href="?id=<?php echo $mediaId; ?>&type=tv&season=<?php echo $season['season_number']; ?>&episode=<?php echo $episode['episode_number']; ?>" 
                           class="episode-item <?php echo ($season['season_number'] == $seasonNum && $episode['episode_number'] == $episodeNum) ? 'active' : ''; ?>">
                            <span class="ep-num"><?php echo $episode['episode_number']; ?></span>
                            <span class="ep-title"><?php echo htmlspecialchars($episode['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    // --- UI Logic ---
    let idleTimer;
    let isUiLockedHidden = false;
    const playerApp = document.getElementById('playerApp');
    const eyeIcon = document.getElementById('eyeIcon');

    function showUi() {
        if (isUiLockedHidden) return;
        playerApp.classList.remove('ui-hidden');
    }

    function resetIdleTimer() {
        if (isUiLockedHidden) return;
        showUi();
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => { playerApp.classList.add('ui-hidden'); }, 3500);
    }

    function toggleUiLock() {
        isUiLockedHidden = !isUiLockedHidden;
        if (isUiLockedHidden) {
            playerApp.classList.add('ui-hidden');
            eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            showUi();
            eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
            resetIdleTimer();
        }
    }

    document.getElementById('videoArea').addEventListener('mousemove', resetIdleTimer);
    document.getElementById('videoArea').addEventListener('click', resetIdleTimer);
    resetIdleTimer();

    // --- Sidebar Logic ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const desktopSidebarToggle = document.getElementById('desktopSidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('episodesSidebar');
    
    if (sidebar) {
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                sidebarToggle.innerHTML = sidebar.classList.contains('open') ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-list-ul"></i>';
            });
        }
        if(desktopSidebarToggle) desktopSidebarToggle.addEventListener('click', () => playerApp.classList.toggle('sidebar-collapsed'));
        
        if(sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', () => {
                if (window.innerWidth <= 900) sidebar.classList.remove('open');
                else playerApp.classList.add('sidebar-collapsed');
            });
        }
        document.querySelectorAll('.season-header').forEach(header => {
            header.addEventListener('click', function() {
                this.classList.toggle('active');
                this.nextElementSibling.classList.toggle('show');
            });
        });
    }

    // --- Player Logic ---
    // Only activate these listeners if standard player is present (not upcoming)
    const overlayPlay = document.getElementById('overlayPlay');
    if (overlayPlay) {
        const controlPlay = document.getElementById('controlPlay');
        const controlFullscreen = document.getElementById('controlFullscreen');
        let isPlaying = false;

        function togglePlayVisual() {
            isPlaying = !isPlaying;
            const iconClass = isPlaying ? 'fa-pause' : 'fa-play';
            if (overlayPlay.querySelector('i')) overlayPlay.querySelector('i').className = 'fa-solid ' + iconClass;
            if (controlPlay && controlPlay.querySelector('i')) controlPlay.querySelector('i').className = 'fa-solid ' + iconClass;
            
            overlayPlay.style.opacity = isPlaying ? '0.6' : '1';
            setTimeout(() => { overlayPlay.style.opacity = ''; }, 350);
        }

        overlayPlay.addEventListener('click', togglePlayVisual);
        if(controlPlay) controlPlay.addEventListener('click', togglePlayVisual);
        
        if(controlFullscreen) {
            controlFullscreen.addEventListener('click', () => {
                const el = document.getElementById('playerWrapper') || document.getElementById('videoArea');
                if (document.fullscreenElement) document.exitFullscreen();
                else if (el.requestFullscreen) el.requestFullscreen();
                else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
            });
        }
    }
</script>
</body>
</html>
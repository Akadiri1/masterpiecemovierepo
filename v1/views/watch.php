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
        $details = fetchTmdbApi("movie/{$mediaId}", ['append_to_response' => 'videos,release_dates,recommendations,credits,external_ids']);
        
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
        $details = fetchTmdbApi("tv/{$mediaId}", ['append_to_response' => 'recommendations,credits,external_ids']);
        
        if ($details) {
            $videoTitle = $details['name'] ?? 'TV Show';
            $backdrop = $details['backdrop_path'] ?? '';
            $videoSubTitle = "Season {$seasonNum} - Episode {$episodeNum}";
            
            // 2. Fetch Specific Episode to check Air Date
            $epDetails = fetchTmdbApi("tv/{$mediaId}/season/{$seasonNum}/episode/{$episodeNum}", ['append_to_response' => 'videos,credits,external_ids']);
            
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
                // Every season in parallel first; the loop below then reads them from the cache.
                if (function_exists('prefetchTmdbApi')) {
                    prefetchTmdbApi(array_map(fn($s) => ["tv/{$mediaId}/season/{$s['season_number']}", []], $details['seasons']));
                }
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
$imdbId = $details['external_ids']['imdb_id'] ?? ($epDetails['external_ids']['imdb_id'] ?? '');

// ==========================================
// 3b. WATCHLIST STATE AND DOWNLOADS
// ==========================================
// Whether the title is already in the signed-in user's watchlist (the
// button's first state).
$isInWatchlist = false;
if (isset($conn, $_SESSION['user_id'])) {
    try {
        $wlStmt = $conn->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ? LIMIT 1");
        $wlStmt->execute([$_SESSION['user_id'], (int) $mediaId, $mediaType]);
        $isInWatchlist = (bool) $wlStmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Watch page watchlist check: ' . $e->getMessage());
    }
}

// Files the site may share for this movie or episode: titles published by the
// licensed ingestion pipeline (v1/ingest) and links added under Admin >
// Download Links. The Download button only shows when there are some.
$downloadLinks = [];
if (isset($conn)) {
    try {
        if ($mediaType === 'tv') {
            $dlStmt = $conn->prepare("SELECT quality, file_size, language, license_label FROM media_downloads
                WHERE tmdb_id = ? AND media_type = 'tv' AND season = ? AND episode = ? AND is_active = 1 ORDER BY quality DESC");
            $dlStmt->execute([(int) $mediaId, $seasonNum, $episodeNum]);
        } else {
            $dlStmt = $conn->prepare("SELECT quality, file_size, language, license_label FROM media_downloads
                WHERE tmdb_id = ? AND media_type = 'movie' AND is_active = 1 ORDER BY quality DESC");
            $dlStmt->execute([(int) $mediaId]);
        }
        $downloadLinks = $dlStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Watch page downloads: ' . $e->getMessage());
    }
}

// ==========================================
// 4. SERVER SELECTION LOGIC
// ==========================================
// Admin > Playback picks what visitors get (see lib/site_settings.php):
// Discover mode plays only videos the site has the rights to and otherwise
// shows the trailer and where to watch; Servers mode adds the third-party
// streaming servers for every title.
require_once APP_PATH . '/lib/site_settings.php';
require_once APP_PATH . '/lib/where_to_watch.php';
$playbackMode = playbackMode();

$servers = [];
if (!$isUpcoming) {
    // The site's own licensed videos come first in both modes.
    if (isset($conn)) {
        $servers = licensedSources($conn, (int) $mediaId, $mediaType, $seasonNum, $episodeNum);
    }
}

// Servers mode: third-party embed servers, several for fallback — if one
// buffers on a slow network, switch to another.
if (!$isUpcoming && $playbackMode === PLAYBACK_SERVERS) {
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
$servers = markLightestSource($servers);
$videoSrc = !empty($servers) ? $servers[0]['url'] : '';
$serversJson = json_encode($servers);
$canPlay = !empty($servers);

// Discover mode: the services that carry the title, under the player (or in
// place of it when the site has nothing to play).
$whereToWatch = (!$isUpcoming && $playbackMode === PLAYBACK_DISCOVER)
    ? whereToWatch($mediaType, (int) $mediaId, $videoTitle)
    : null;

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
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
  <link rel="manifest" href="manifest.json">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <base href="<?php echo htmlspecialchars($baseDir); ?>">
  
  <link rel="stylesheet" href="assets/css/core/watch-theme.css?v=<?php echo time() + 2; ?>">
  <link rel="stylesheet" href="assets/css/core/mobile-player.css?v=<?php echo time() + 2; ?>">
  <!-- Loaded blocking (not deferred) so the inline player script below can use it. -->
  <script src="assets/js/player/mobile-player.js?v=<?php echo time() + 2; ?>"></script>
  <script>
    window.currentImdbId = <?php echo json_encode($imdbId ?? ''); ?>;
    window.currentTmdbId = <?php echo json_encode($mediaId); ?>;
    window.currentMediaType = <?php echo json_encode($mediaType); ?>;
    window.currentSeason = <?php echo json_encode($seasonNum); ?>;
    window.currentEpisode = <?php echo json_encode($episodeNum); ?>;
  </script>
  <script src="assets/js/player/subtitles.js?v=<?php echo time(); ?>" defer></script>
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">
  <!-- Posters and backdrops come from TMDB's image server: connect early. -->
  <link rel="preconnect" href="https://image.tmdb.org">
  <!-- Loading placeholders for images and page changes -->
  <link rel="stylesheet" href="/assets/css/core/skeleton.css?v=1">
  <?php if ($whereToWatch !== null) echo whereToWatchStyles(); ?>
  <script src="/assets/js/skeleton.js?v=1" defer></script>
  <style>
      /* ========================================================
         UNIVERSAL SUBTITLE SYSTEM (ZenSubtitles)
         ======================================================== */
      .subtitle-overlay {
          position: absolute;
          bottom: 45px;
          left: 5%;
          right: 5%;
          display: flex;
          justify-content: center;
          text-align: center;
          pointer-events: none;
          z-index: 25;
          transition: bottom 0.2s ease;
      }
      .subtitle-text {
          display: inline-block;
          background: rgba(0, 0, 0, 0.78);
          color: #ffffff;
          padding: 5px 14px;
          border-radius: 6px;
          font-size: 20px;
          line-height: 1.45;
          font-weight: 600;
          letter-spacing: 0.3px;
          text-shadow: 0 2px 4px rgba(0, 0, 0, 0.95), 0 0 2px rgba(0, 0, 0, 0.8);
          max-width: 90%;
          word-break: break-word;
          white-space: pre-line;
          box-shadow: 0 4px 16px rgba(0,0,0,0.4);
      }
      :fullscreen .subtitle-overlay,
      :-webkit-full-screen .subtitle-overlay {
          bottom: 65px;
      }
      :fullscreen .subtitle-text,
      :-webkit-full-screen .subtitle-text {
          font-size: 26px;
      }

      /* Subtitle Modal */
      .sub-modal-overlay {
          position: fixed; top: 0; left: 0; width: 100%; height: 100%;
          background: rgba(0,0,0,0.85); backdrop-filter: blur(12px);
          -webkit-backdrop-filter: blur(12px);
          display: flex; align-items: center; justify-content: center;
          z-index: 999999;
          animation: fadeIn 0.2s ease;
      }
      .sub-modal {
          background: #101118;
          border: 1px solid rgba(255,255,255,0.1);
          border-radius: 16px;
          width: 92%; max-width: 520px;
          max-height: 90vh;
          display: flex; flex-direction: column;
          box-shadow: 0 20px 50px rgba(0,0,0,0.7);
          overflow: hidden;
      }
      .sub-modal-header {
          background: rgba(255,255,255,0.03);
          padding: 16px 20px;
          display: flex; align-items: center; justify-content: space-between;
          border-bottom: 1px solid rgba(255,255,255,0.06);
      }
      .sub-modal-header h3 {
          margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff;
          display: flex; align-items: center;
      }
      .sub-modal-close {
          background: transparent; border: none; color: #aaa; font-size: 1.5rem;
          cursor: pointer; padding: 0; line-height: 1;
      }
      .sub-modal-close:hover { color: #fff; }

      .sub-active-banner {
          background: rgba(0, 224, 255, 0.08);
          border-bottom: 1px solid rgba(0, 224, 255, 0.15);
          padding: 10px 20px;
          display: flex; align-items: center; justify-content: space-between;
          font-size: 0.85rem;
      }
      .sub-active-info { display: flex; flex-direction: column; gap: 2px; }
      .sub-active-status { color: #888; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
      .sub-active-name { color: #00e0ff; font-weight: 600; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .btn-sub-turnoff {
          background: rgba(229, 9, 20, 0.15); border: 1px solid rgba(229, 9, 20, 0.3);
          color: #ff4757; font-size: 0.75rem; font-weight: 600; padding: 4px 10px;
          border-radius: 6px; cursor: pointer; transition: 0.2s;
      }
      .btn-sub-turnoff:hover { background: rgba(229, 9, 20, 0.3); color: #fff; }

      .sub-tabs-nav {
          display: flex; background: rgba(0,0,0,0.3);
          border-bottom: 1px solid rgba(255,255,255,0.06);
          padding: 6px 12px; gap: 8px;
      }
      .sub-tab-btn {
          flex: 1; background: transparent; border: none; color: #888;
          padding: 8px 10px; border-radius: 8px; font-size: 0.85rem;
          font-weight: 600; cursor: pointer; transition: 0.2s;
          display: flex; align-items: center; justify-content: center; gap: 6px;
      }
      .sub-tab-btn:hover { color: #ddd; background: rgba(255,255,255,0.03); }
      .sub-tab-btn.active { color: #00e0ff; background: rgba(0, 224, 255, 0.12); }

      .sub-modal-body {
          padding: 18px 20px; overflow-y: auto; flex: 1;
      }
      .sub-filter-row {
          display: flex; gap: 10px; margin-bottom: 15px;
      }
      .sub-select, .sub-search-input {
          background: #181924; border: 1px solid rgba(255,255,255,0.1);
          color: #fff; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem;
          outline: none;
      }
      .sub-select { flex: 0 0 140px; cursor: pointer; }
      .sub-select option { background: #181924; color: #fff; }
      .sub-search-input { flex: 1; }
      .sub-search-input:focus, .sub-select:focus { border-color: #00e0ff; }

      .sub-list-container {
          min-height: 180px; max-height: 300px; overflow-y: auto;
      }
      .sub-loading, .sub-empty {
          text-align: center; color: #888; padding: 40px 10px; font-size: 0.9rem;
      }
      .sub-item {
          display: flex; align-items: center; justify-content: space-between;
          background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
          border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;
          transition: background 0.2s;
      }
      .sub-item:hover { background: rgba(255,255,255,0.05); }
      .sub-item.active { border-color: #00e0ff; background: rgba(0, 224, 255, 0.05); }
      .sub-item-meta { display: flex; flex-direction: column; max-width: 72%; gap: 3px; }
      .sub-item-badge {
          display: inline-block; width: fit-content;
          background: rgba(0, 224, 255, 0.15); color: #00e0ff;
          font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
          padding: 2px 6px; border-radius: 4px;
      }
      .sub-badge-best {
          background: rgba(255, 215, 0, 0.18);
          color: #ffd700;
          border: 1px solid rgba(255, 215, 0, 0.4);
      }
      .sub-item-title {
          font-size: 0.82rem; color: #ddd; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .btn-sub-apply {
          background: #1e202f; border: 1px solid rgba(255,255,255,0.12);
          color: #fff; font-size: 0.8rem; font-weight: 600; padding: 6px 12px;
          border-radius: 6px; cursor: pointer; transition: 0.2s;
          display: inline-flex; align-items: center; gap: 5px;
      }
      .btn-sub-apply:hover { background: #00e0ff; color: #000; border-color: #00e0ff; }
      .sub-item.active .btn-sub-apply { background: #00e0ff; color: #000; }

      .sub-dropzone {
          border: 2px dashed rgba(255,255,255,0.15); border-radius: 12px;
          padding: 35px 20px; text-align: center; cursor: pointer;
          transition: 0.2s; background: rgba(255,255,255,0.01);
      }
      .sub-dropzone.drag-active, .sub-dropzone:hover {
          border-color: #00e0ff; background: rgba(0, 224, 255, 0.04);
      }
      .sub-dropzone-icon { font-size: 2.2rem; color: #00e0ff; margin-bottom: 10px; }
      .sub-dropzone h4 { color: #fff; font-size: 1rem; margin-bottom: 5px; }
      .sub-dropzone p { color: #777; font-size: 0.8rem; margin-bottom: 15px; }

      .sub-setting-group { margin-bottom: 18px; }
      .sub-setting-label {
          display: block; font-size: 0.85rem; font-weight: 600; color: #fff; margin-bottom: 6px;
      }
      .sub-setting-hint { font-size: 0.75rem; color: #888; margin-bottom: 10px; }
      .sub-offset-controls { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
      .btn-offset {
          background: #1b1d2a; border: 1px solid rgba(255,255,255,0.1);
          color: #ddd; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem;
          font-weight: 600; cursor: pointer; transition: 0.2s;
      }
      .btn-offset:hover { background: rgba(255,255,255,0.1); color: #fff; }
      .btn-offset-reset { background: rgba(229, 9, 20, 0.15); border-color: rgba(229, 9, 20, 0.3); color: #ff6b81; }
      .sub-offset-display {
          font-family: monospace; font-size: 0.95rem; font-weight: 700; color: #00e0ff;
          min-width: 55px; text-align: center;
      }
      .sub-pill-group { display: flex; gap: 8px; flex-wrap: wrap; }
      .sub-pill {
          background: #181924; border: 1px solid rgba(255,255,255,0.08);
          color: #aaa; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem;
          font-weight: 600; cursor: pointer; transition: 0.2s;
      }
      .sub-pill:hover { color: #fff; border-color: rgba(255,255,255,0.2); }
      .sub-pill.active { background: #00e0ff; color: #000; border-color: #00e0ff; font-weight: 700; }

      /* Mini Subtitle Sync Bar (HUD for iframes / Server 3 Auto) */
      .sub-mini-bar {
          position: absolute;
          top: 14px;
          right: 14px;
          background: rgba(14, 15, 22, 0.92);
          backdrop-filter: blur(14px);
          -webkit-backdrop-filter: blur(14px);
          border: 1px solid rgba(0, 224, 255, 0.4);
          border-radius: 30px;
          padding: 4px 10px;
          display: flex;
          align-items: center;
          gap: 6px;
          z-index: 9999;
          box-shadow: 0 6px 20px rgba(0,0,0,0.7);
          transition: all 0.25s ease;
      }
      .sub-mini-bar:hover { opacity: 1; border-color: #00e0ff; }
      .sub-mini-time {
          font-family: monospace;
          font-size: 0.82rem;
          font-weight: 700;
          color: #00e0ff;
          padding: 3px 8px;
          background: rgba(0,0,0,0.6);
          border-radius: 6px;
          border: 1px solid rgba(0, 224, 255, 0.25);
          cursor: pointer;
          white-space: nowrap;
          transition: 0.2s;
      }
      .sub-mini-time:hover {
          background: rgba(0, 224, 255, 0.2);
          border-color: #00e0ff;
          color: #fff;
      }
      .btn-sub-rhyme {
          background: linear-gradient(135deg, #00e0ff, #7b2cbf);
          border: none;
          color: #fff;
          font-weight: 700;
          padding: 4px 10px;
          border-radius: 12px;
          font-size: 0.74rem;
          display: inline-flex;
          align-items: center;
          gap: 4px;
          cursor: pointer;
          transition: transform 0.15s ease, box-shadow 0.15s ease;
          box-shadow: 0 0 10px rgba(0, 224, 255, 0.3);
      }
      .btn-sub-rhyme:hover {
          transform: scale(1.05);
          box-shadow: 0 0 16px rgba(0, 224, 255, 0.6);
          color: #fff;
      }
      .sub-mini-actions { display: flex; align-items: center; gap: 4px; }
      .btn-sub-mini {
          background: rgba(255,255,255,0.06);
          border: 1px solid rgba(255,255,255,0.12);
          color: #fff;
          padding: 3px 7px;
          border-radius: 12px;
          font-size: 0.72rem;
          font-weight: 600;
          cursor: pointer;
          transition: 0.2s;
          display: inline-flex; align-items: center; justify-content: center;
      }
      .btn-sub-mini:hover { background: #00e0ff; color: #000; border-color: #00e0ff; }
      .btn-sub-mini-close {
          background: transparent; border: none; color: #888; font-size: 0.85rem;
          cursor: pointer; padding: 2px 4px; line-height: 1;
      }
      .btn-sub-mini-close:hover { color: #fff; }

      /* In-Video Rhyme / Sync Modal */
      .sub-rhyme-modal {
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: rgba(12, 14, 22, 0.96);
          border: 1px solid rgba(0, 224, 255, 0.4);
          box-shadow: 0 12px 45px rgba(0, 0, 0, 0.9), 0 0 25px rgba(0, 224, 255, 0.2);
          backdrop-filter: blur(18px);
          -webkit-backdrop-filter: blur(18px);
          border-radius: 14px;
          padding: 18px 22px;
          z-index: 10000;
          width: 90%;
          max-width: 420px;
          animation: fadeIn 0.2s ease;
      }
      .sub-rhyme-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 10px;
          border-bottom: 1px solid rgba(255,255,255,0.08);
          padding-bottom: 8px;
      }
      .sub-rhyme-title {
          font-size: 0.95rem;
          font-weight: 700;
          color: #fff;
          display: flex;
          align-items: center;
          gap: 6px;
      }
      .sub-rhyme-close {
          background: transparent;
          border: none;
          color: #888;
          font-size: 1.2rem;
          cursor: pointer;
          line-height: 1;
      }
      .sub-rhyme-close:hover { color: #fff; }
      .sub-rhyme-input-row {
          display: flex;
          gap: 8px;
          margin-bottom: 12px;
      }
      .sub-rhyme-input {
          flex: 1;
          background: #08090f;
          border: 1px solid rgba(255,255,255,0.18);
          border-radius: 8px;
          padding: 8px 12px;
          color: #00e0ff;
          font-size: 1rem;
          font-family: monospace;
          font-weight: 700;
          outline: none;
      }
      .sub-rhyme-input:focus {
          border-color: #00e0ff;
          box-shadow: 0 0 10px rgba(0,224,255,0.35);
      }
      .btn-rhyme-action {
          background: #00e0ff;
          color: #000;
          font-weight: 700;
          border: none;
          border-radius: 8px;
          padding: 8px 16px;
          cursor: pointer;
          font-size: 0.85rem;
          white-space: nowrap;
          transition: background 0.15s;
      }
      .btn-rhyme-action:hover { background: #33e7ff; }
      .sub-rhyme-nudges {
          display: flex;
          align-items: center;
          gap: 6px;
          flex-wrap: wrap;
      }
      .btn-rhyme-pill {
          background: #181a26;
          border: 1px solid rgba(255,255,255,0.1);
          color: #ddd;
          padding: 4px 8px;
          border-radius: 6px;
          font-size: 0.75rem;
          font-weight: 600;
          cursor: pointer;
          transition: 0.15s;
      }
      .btn-rhyme-pill:hover {
          background: rgba(0, 224, 255, 0.15);
          color: #00e0ff;
          border-color: rgba(0, 224, 255, 0.3);
      }
      .btn-rhyme-restart {
          background: rgba(229, 9, 20, 0.15);
          color: #ff6b81;
          border-color: rgba(229, 9, 20, 0.3);
      }

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

      /* ---- Episode picker for the player ------------------------------------
         A panel that slides in from the right. Opened by the EP tab over a
         streaming server in landscape, or by the direct-file player's Episodes
         button. It is fixed to the screen, so it works over the immersive
         landscape player; when something is fullscreen the script moves it
         inside the fullscreen element, the only part the browser draws. */
      .ep-tab { display: none; }

      @media (orientation: landscape) and (max-height: 540px) and (pointer: coarse) {
          html:has(#playerIframe) #videoArea:not(.direct-playing) .ep-tab { display: flex; }
      }

      .ep-tab {
          position: absolute;
          top: 50%;
          right: env(safe-area-inset-right, 0px);
          transform: translateY(-50%);
          z-index: 20;
          width: 36px;
          height: 76px;
          padding: 0;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 4px;
          background: rgba(0, 0, 0, 0.62);
          color: #fff;
          border: 1px solid rgba(255, 255, 255, 0.14);
          border-right: 0;
          border-radius: 12px 0 0 12px;
          font-size: 0.62rem;
          font-weight: 700;
          letter-spacing: 0.04em;
          backdrop-filter: blur(6px);
          -webkit-backdrop-filter: blur(6px);
      }
      .ep-tab i { font-size: 1.15rem; }

      .ep-backdrop {
          position: fixed;
          inset: 0;
          z-index: 2147483001;
          background: rgba(0, 0, 0, 0.45);
      }

      .ep-panel {
          position: fixed;
          top: 0;
          right: 0;
          bottom: 0;
          z-index: 2147483002;
          width: min(360px, 88vw);
          display: flex;
          flex-direction: column;
          background: rgba(12, 12, 16, 0.97);
          color: #fff;
          border-left: 1px solid rgba(255, 255, 255, 0.08);
          padding: env(safe-area-inset-top, 0px) env(safe-area-inset-right, 0px) env(safe-area-inset-bottom, 0px) 0;
          transform: translateX(100%);
          transition: transform 0.22s ease;
      }
      .ep-panel.open { transform: translateX(0); }
      .ep-backdrop[hidden], .ep-panel[hidden] { display: none !important; }

      .ep-panel-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 12px 12px 8px 16px;
          font-size: 1rem;
      }
      .ep-panel-close {
          width: 40px;
          height: 40px;
          border: 0;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.08);
          color: #fff;
          font-size: 1.1rem;
          display: flex;
          align-items: center;
          justify-content: center;
      }

      .ep-seasons {
          display: flex;
          flex-shrink: 0;
          gap: 6px;
          padding: 0 14px 10px 16px;
          overflow-x: auto;
          scrollbar-width: none;
          touch-action: pan-x;
      }
      .ep-seasons::-webkit-scrollbar { display: none; }
      .ep-season {
          flex: 0 0 auto;
          min-width: 44px;
          height: 34px;
          padding: 0 12px;
          border-radius: 17px;
          border: 1px solid rgba(255, 255, 255, 0.14);
          background: rgba(255, 255, 255, 0.05);
          color: #ddd;
          font-size: 0.8rem;
          font-weight: 600;
      }
      .ep-season.active { background: var(--primary, #e50914); border-color: var(--primary, #e50914); color: #fff; }

      .ep-lists {
          flex: 1;
          overflow-y: auto;
          padding: 0 10px 14px 12px;
          /* Its own scroller, so it still pans inside the player, which blocks touch panning. */
          touch-action: pan-y;
          overscroll-behavior: contain;
      }
      .ep-item {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 8px 6px;
          border-radius: 10px;
          color: #fff;
          text-decoration: none;
      }
      .ep-item:active { background: rgba(255, 255, 255, 0.08); }
      .ep-item.current { background: rgba(229, 9, 20, 0.16); box-shadow: inset 3px 0 0 var(--primary, #e50914); }
      .ep-item.unaired { opacity: 0.45; }
      .ep-thumb {
          position: relative;
          flex: 0 0 96px;
          height: 54px;
          border-radius: 6px;
          background: #222 center / cover no-repeat;
          overflow: hidden;
      }
      .ep-num {
          position: absolute;
          left: 4px;
          bottom: 3px;
          padding: 1px 6px;
          border-radius: 4px;
          background: rgba(0, 0, 0, 0.7);
          font-size: 0.7rem;
          font-weight: 700;
      }
      .ep-meta { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
      .ep-name { font-size: 0.86rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .ep-sub { font-size: 0.72rem; color: #9aa0a6; }
      .ep-item.current .ep-sub { color: var(--primary, #e50914); font-weight: 600; }

      /* ---- Phone toolbar under the player ----------------------------------
         The buttons were one wrapping row, and two carried inline widths, so on
         a phone they broke into two ragged rows. Lay them out as a grid: for a
         TV episode, previous / now playing / next take the first row, and the
         six actions share the second row equally. */
      @media (max-width: 767.98px) {
          .bottom-controls-bar .control-actions {
              display: grid !important;
              grid-template-columns: repeat(var(--toolbar-cols, 6), minmax(0, 1fr));
              gap: 6px;
              width: 100%;
              flex: 1 1 100% !important;
              padding: 6px;
          }
          .bottom-controls-bar .control-actions > * { order: 4; min-width: 0; }
          .bottom-controls-bar .control-actions > a.btn-action:first-of-type { order: 1; grid-column: 1; }
          .bottom-controls-bar .control-actions > .now-playing-box {
              order: 2;
              grid-column: 2 / -2;
              align-self: stretch;
              height: auto;
              padding: 0;
              border-radius: 8px;
              background: rgba(255, 255, 255, 0.03);
              align-items: center;
          }
          .bottom-controls-bar .control-actions > a.btn-action:last-of-type { order: 3; grid-column: -2 / -1; }
          .bottom-controls-bar .control-actions .btn-action {
              width: 100% !important;
              padding: 0 !important;
              gap: 0 !important;
          }
          /* The server menu used to open to the left of its button, off-screen. */
          .bottom-controls-bar #serverDropdown { left: 0 !important; right: auto !important; }
      }

      /* Floating AI and theme buttons on phones: stack them in the corner with
         their centres lined up, and slide them away while scrolling down so
         they don't sit on the posters. Scrolling up brings them back. */
      @media (max-width: 800px) {
          #mobileAiBtn { right: 16px !important; bottom: 18px !important; transition: transform 0.25s ease, opacity 0.25s ease !important; }
          .theme-switcher-float { right: 17px !important; bottom: 80px !important; transition: transform 0.25s ease, opacity 0.25s ease !important; }
          body.floats-away #mobileAiBtn,
          body.floats-away .theme-switcher-float { transform: translateX(96px) !important; opacity: 0 !important; pointer-events: none !important; }
      }

      /* Fullscreen Search Overlay */
      #searchOverlay {
          position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh;
          background: rgba(8, 8, 12, 0.97); backdrop-filter: blur(20px);
          z-index: 2147483600; display: none !important; align-items: center; justify-content: center;
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
      
      /* The watch page has its own AI buttons, so the floating orb stays hidden. */
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
        <?php include APP_PATH . '/views/includes/sidebar-menu.php'; ?>

        <?php include APP_PATH . '/views/includes/sidebar-user.php'; ?>
    </aside>

    <!-- 2. Center Content -->
    <main class="watch-center custom-scrollbar">
        <!-- Top Bar -->
        <div class="center-top-bar">
            <a href="javascript:history.back()" class="back-btn"><i class="ph ph-arrow-left"></i></a>
            <h2><?php echo htmlspecialchars($videoTitle); ?></h2>
            <?php if (adminPreviewMode() !== null): ?>
            <!-- Only the admin previewing a mode sees this; everyone else gets the site setting. -->
            <a href="/admin-playback" class="playback-preview-pill" title="Change in Admin > Playback">
                <i class="ph ph-eye"></i> Preview: <?php echo $playbackMode === PLAYBACK_SERVERS ? 'Streaming servers' : 'Discover'; ?>
            </a>
            <style>
                .playback-preview-pill { margin-left: auto; flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; border: 1px dashed rgba(255, 196, 0, .6); color: #ffc400; font-size: .75rem; font-weight: 600; text-decoration: none; white-space: nowrap; }
                .playback-preview-pill:hover { background: rgba(255, 196, 0, .1); color: #ffd54f; }
            </style>
            <?php endif; ?>
        </div>

        <!-- Video Wrapper -->
        <div class="video-wrapper" id="videoArea">
             <?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
             <!-- Episodes tab: shown over a streaming server in phone landscape,
                  where the page's own episode list is out of reach. -->
             <button type="button" class="ep-tab" id="epTab" aria-label="Episodes" onclick="window.openEpisodePanel()"><i class="ph ph-list"></i><span>EP</span></button>
             <?php endif; ?>
             <!-- Network Status Overlay -->
             <div class="network-banner" id="networkBanner" style="position:absolute; top:0; left:0; width:100%; z-index:9999;"></div>
             <!-- Loading overlay for server switches -->
             <div class="player-loading" id="playerLoading" style="position:absolute; inset:0; background:rgba(0,0,0,0.8); z-index:15; display:none; flex-direction:column; align-items:center; justify-content:center; color:#fff;">
                 <div class="load-spinner" style="width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.15); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                 <span>Switching server...</span>
             </div>

             <?php if ($isUpcoming): ?>
                <div class="upcoming-container" style="position:absolute; inset:0; background-image: url('https://image.tmdb.org/t/p/w1280<?php echo $backdrop; ?>'); background-size:cover; display:flex; align-items:center; justify-content:center; text-align:center;">
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
             <?php elseif (!$canPlay): ?>
                <?php echo trailerStage($backdrop, $trailerKey, !empty($whereToWatch['providers'])); ?>
             <?php else:
                 $isDirectVideo = preg_match('/\.(mp4|mkv|webm|m3u8)(\?|$)/i', $videoSrc);
              ?>
                 <iframe id="playerIframe" src="<?php echo !$isDirectVideo ? $videoSrc : ''; ?>" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" style="width:100%; height:100%; border:none; display: <?php echo !$isDirectVideo ? 'block' : 'none'; ?>;"></iframe>
                 <video id="playerVideo" src="<?php echo $isDirectVideo ? htmlspecialchars($videoSrc) : ''; ?>" controls preload="metadata" style="width:100%; height:100%; background:#000; display: <?php echo $isDirectVideo ? 'block' : 'none'; ?>; border: none;" playsinline></video>
              <?php endif; ?>

              <!-- Floating Subtitle Overlay (Active for iframe embeds & direct video) -->
              <div id="subtitleOverlay" class="subtitle-overlay" style="display:none;">
                  <span id="subtitleText" class="subtitle-text"></span>
              </div>

              <!-- Mini Subtitle Sync Bar (HUD for iframes / Server 3 Auto) -->
              <div id="subMiniBar" class="sub-mini-bar" style="display:none;">
                  <button type="button" class="btn-sub-mini" id="subMiniPlayPause" onclick="ZenSubtitles.togglePlayback()" title="Pause/Resume Subtitles (Space)">
                      <i class="fa-solid fa-play"></i>
                  </button>
                  <div class="sub-mini-time" id="subMiniTime" title="Current Subtitle Time (Click to Rhyme)" onclick="ZenSubtitles.openRhymeDialog()">00:00</div>
                  <button type="button" class="btn-sub-rhyme" onclick="ZenSubtitles.openRhymeDialog()" title="Rhyme Subtitles with Video (Press R)">
                      <i class="fa-solid fa-wand-magic-sparkles"></i> Rhyme / Sync
                  </button>
                  <div class="sub-mini-actions">
                      <button type="button" class="btn-sub-mini" onclick="ZenSubtitles.adjustOffset(-0.5)" title="Delay subtitles 0.5s (earlier)">-0.5s</button>
                      <button type="button" class="btn-sub-mini" onclick="ZenSubtitles.adjustOffset(0.5)" title="Advance subtitles 0.5s (later)">+0.5s</button>
                      <button type="button" class="btn-sub-mini" onclick="ZenSubtitles.seekRelative(-5)" title="Rewind subtitles 5s">-5s</button>
                      <button type="button" class="btn-sub-mini" onclick="ZenSubtitles.seekRelative(5)" title="Forward subtitles 5s">+5s</button>
                      <button type="button" class="btn-sub-mini" onclick="ZenSubtitles.openModal()" title="Subtitles Menu & Tracks"><i class="fa-solid fa-gear"></i></button>
                  </div>
                  <button type="button" class="btn-sub-mini-close" onclick="ZenSubtitles.toggleMiniBar()" title="Hide HUD">&times;</button>
              </div>

              <!-- In-Video Rhyme / Sync Dialog -->
              <div id="subRhymeModal" class="sub-rhyme-modal" style="display:none;">
                  <div class="sub-rhyme-header">
                      <div class="sub-rhyme-title">
                          <i class="fa-solid fa-wand-magic-sparkles" style="color:#00e0ff;"></i> Rhyme with Video
                      </div>
                      <button type="button" class="sub-rhyme-close" onclick="ZenSubtitles.closeRhymeDialog()">&times;</button>
                  </div>
                  <p style="font-size:0.8rem; color:#aaa; margin:0 0 10px 0;">Look at the timestamp on your video player and enter it here to sync instantly:</p>
                  <div class="sub-rhyme-input-row">
                      <input type="text" id="subRhymeTimeInput" class="sub-rhyme-input" placeholder="e.g. 04:30 or 1:15:00">
                      <button type="button" class="btn-rhyme-action" onclick="ZenSubtitles.applyRhymeInput()">Rhyme Now</button>
                  </div>
                  <div class="sub-rhyme-nudges">
                      <span style="font-size:0.75rem; color:#888; margin-right:4px;">Quick jumps:</span>
                      <button type="button" class="btn-rhyme-pill btn-rhyme-restart" onclick="ZenSubtitles.seekTo(0); ZenSubtitles.closeRhymeDialog();">0:00</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(-10)">-10s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(-5)">-5s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(-1)">-1s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(1)">+1s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(5)">+5s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.seekRelative(10)">+10s</button>
                  </div>
                  <div class="sub-rhyme-nudges" style="margin-top:10px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.06);">
                      <span style="font-size:0.75rem; color:#888; margin-right:4px;">Fine offset:</span>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.adjustOffset(-0.5)">-0.5s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.adjustOffset(-0.25)">-0.25s</button>
                      <span id="subRhymeOffsetDisplay" style="color:#00e0ff; font-family:monospace; font-weight:700; font-size:0.8rem; min-width:40px; text-align:center;">0.0s</span>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.adjustOffset(0.25)">+0.25s</button>
                      <button type="button" class="btn-rhyme-pill" onclick="ZenSubtitles.adjustOffset(0.5)">+0.5s</button>
                      <button type="button" class="btn-rhyme-pill btn-rhyme-restart" onclick="ZenSubtitles.resetOffset()">Reset</button>
                  </div>
              </div>
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
             
             <div class="control-actions" style="--toolbar-cols: <?php echo 6 + ((!$isUpcoming && count($servers) > 1) ? 1 : 0) + ((!$isUpcoming && !empty($downloadLinks)) ? 1 : 0); ?>;">
                  <?php if (!$isUpcoming && count($servers) > 1): ?>
                  <!-- Server Switcher Inline -->
                  <div style="position:relative;">
                      <button class="btn-action server-switcher-btn" id="serverBtn" title="Servers" style="width:auto; padding:0 15px; font-size:0.9rem;">
                          <i class="ph ph-hard-drives"></i> <span class="srv-label ms-2 d-none d-md-block"><?php echo htmlspecialchars($servers[0]['name'] ?? 'Server 1'); ?></span>
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
                  
                  <button class="btn-action" id="subtitlesBtn" title="Subtitles (CC)" onclick="openSubtitlesModal()" style="position:relative;">
                      <i class="fa-solid fa-closed-captioning"></i>
                      <span id="subActiveDot" style="display:none; position:absolute; top:6px; right:6px; width:7px; height:7px; background:#00e0ff; border-radius:50%; box-shadow:0 0 6px #00e0ff;"></span>
                  </button>
                  <button class="btn-action" id="cinematicToggleBtn" title="Cinematic Mode" onclick="toggleCinematicMode()" style="color: #00e0ff;"><i class="ph-fill ph-moon"></i></button>
                  <button class="btn-action" id="fullscreenToggleBtn" title="Fullscreen (with Subtitles)" onclick="ZenSubtitles.toggleFullscreen()"><i class="fa-solid fa-expand"></i></button>
                  <button class="btn-action" onclick="openZenFromWatch()" title="Ask ZEN AI" style="background: linear-gradient(135deg, #00e0ff, #7b2cbf); border: none; color: #fff; width: auto; padding: 0 15px; font-weight: 600; display: inline-flex; gap: 6px; align-items: center;">
                      <i class="ph-fill ph-sparkle"></i> <span class="d-none d-md-block">ZEN AI</span>
                  </button>
                  <button class="btn-action" id="watchlistBtn" title="<?php echo $isInWatchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>" aria-pressed="<?php echo $isInWatchlist ? 'true' : 'false'; ?>"<?php echo $isInWatchlist ? ' style="color: #4ade80;"' : ''; ?>><i class="ph <?php echo $isInWatchlist ? 'ph-check' : 'ph-plus'; ?>"></i></button>
                  <button class="btn-action" onclick="navigator.share({title: document.title, url: window.location.href})"><i class="ph ph-share-network"></i></button>
                  <?php if (!$isUpcoming && !empty($downloadLinks)): ?>
                  <button class="btn-action" id="downloadBtn" title="Download"><i class="ph ph-download-simple"></i></button>
                  <?php endif; ?>
             </div>
        </div>

        <?php if ($whereToWatch !== null) echo whereToWatchSection($whereToWatch, $canPlay); ?>

        <!-- Episodes on phones: every season, under the player. Wider screens
             show the list in the right-hand panel. -->
        <?php if ($mediaType === 'tv' && !empty($details['seasons'])):
            require_once __DIR__ . '/includes/season-episodes.php';
            $playingSeasonEpisodes = [];
            foreach ($seasonsData as $sd) {
                if ((int) $sd['season_number'] === $seasonNum) {
                    $playingSeasonEpisodes = $sd['episodes'];
                    break;
                }
            }
            echo seasonsSection((int) $mediaId, $details['seasons'], $seasonNum, $playingSeasonEpisodes, $seasonNum, $episodeNum, 'se-watch');
        endif; ?>
        <style>
            .se-watch { display: none; }
            @media (max-width: 900px) {
                .se-watch { display: block; margin: 18px 0 8px; }
                /* The right-hand panel's own season picker and list would repeat it. */
                #sidebarContent .right-controls-top, #seasonDropdownNav, #epListContainer { display: none !important; }
            }
        </style>

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
        <div id="watchAiTrigger" onclick="openZenFromWatch()" style="background: linear-gradient(135deg, rgba(0,224,255,0.1), rgba(123,44,191,0.1)); border: 1px solid rgba(0,224,255,0.25); color: #fff; font-weight: 600; padding: 12px 16px; border-radius: 10px; display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 18px; user-select: none; transition: all 0.25s ease;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #00e0ff, #7b2cbf); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph-fill ph-sparkle" style="color: #fff; font-size: 1rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.85rem; font-weight: 700;">Ask ZEN AI</div>
                <div style="font-size: 0.7rem; color: #888; font-weight: 400;">Cast, plot, what to watch next</div>
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


    </aside>
</div>

<!-- Mobile Toggle Button (Floating) -->
<button class="mobile-ep-fab" id="mobileEpToggle" style="display:none; position:fixed; bottom:20px; right:20px; width:50px; height:50px; border-radius:50%; background:#e50914; color:#fff; border:none; z-index:99; align-items:center; justify-content:center;"><i class="ph ph-list"></i></button>

<!-- SUBTITLES MODAL -->
<div class="sub-modal-overlay" id="subtitlesModal" style="display:none;">
    <div class="sub-modal">
        <div class="sub-modal-header">
            <h3><i class="fa-solid fa-closed-captioning" style="color:#00e0ff; margin-right:8px;"></i> Subtitles & Audio</h3>
            <button class="sub-modal-close" id="subModalClose" aria-label="Close">&times;</button>
        </div>

        <!-- Active Subtitle Indicator & Quick Actions -->
        <div class="sub-active-banner">
            <div class="sub-active-info">
                <span class="sub-active-status" id="subActiveStatus">No subtitles active</span>
                <span class="sub-active-name" id="subActiveName"></span>
            </div>
            <button type="button" class="btn-sub-turnoff" id="subTurnOffBtn" style="display:none;" onclick="ZenSubtitles.disable()">
                <i class="fa-solid fa-power-off"></i> Turn Off
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="sub-tabs-nav">
            <button type="button" class="sub-tab-btn active" data-tab="auto">
                <i class="fa-solid fa-globe"></i> OpenSubtitles
            </button>
            <button type="button" class="sub-tab-btn" data-tab="upload">
                <i class="fa-solid fa-file-arrow-up"></i> Upload .SRT
            </button>
            <button type="button" class="sub-tab-btn" data-tab="sync">
                <i class="fa-solid fa-sliders"></i> Sync & Style
            </button>
        </div>

        <div class="sub-modal-body">
            <!-- Tab 1: OpenSubtitles (Auto) -->
            <div class="sub-tab-content active" id="subTabAuto">
                <div class="sub-filter-row">
                    <select id="subLangSelect" class="sub-select">
                        <option value="all">All Languages</option>
                        <option value="eng" selected>English</option>
                        <option value="spa">Spanish</option>
                        <option value="fre">French</option>
                        <option value="ger">German</option>
                        <option value="ita">Italian</option>
                        <option value="por">Portuguese</option>
                        <option value="pob">Portuguese (Brazil)</option>
                        <option value="ara">Arabic</option>
                        <option value="rus">Russian</option>
                        <option value="hin">Hindi</option>
                        <option value="jpn">Japanese</option>
                        <option value="kor">Korean</option>
                        <option value="chi">Chinese</option>
                    </select>
                    <input type="text" id="subSearchInput" class="sub-search-input" placeholder="Filter releases (e.g. 1080p, BluRay)...">
                </div>

                <div class="sub-list-container" id="subListContainer">
                    <div class="sub-loading" id="subLoading">
                        <i class="fa-solid fa-circle-notch fa-spin"></i> Searching OpenSubtitles for this title...
                    </div>
                    <div class="sub-list" id="subList" style="display:none;"></div>
                    <div class="sub-empty" id="subEmpty" style="display:none;">No matching subtitles found for this language.</div>
                </div>
            </div>

            <!-- Tab 2: Upload Custom SRT / VTT -->
            <div class="sub-tab-content" id="subTabUpload" style="display:none;">
                <div class="sub-dropzone" id="subDropzone">
                    <i class="fa-solid fa-cloud-arrow-up sub-dropzone-icon"></i>
                    <h4>Choose a subtitle file</h4>
                    <p>Drag and drop your <strong>.srt</strong> or <strong>.vtt</strong> file here</p>
                    <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('subFileInput').click()">
                        <i class="fa-solid fa-folder-open"></i> Browse from Device
                    </button>
                    <input type="file" id="subFileInput" accept=".srt,.vtt,text/vtt,application/x-subrip" style="display:none;">
                </div>
                <div class="sub-help-text mt-3">
                    <small style="color:#aaa;"><i class="fa-solid fa-circle-info"></i> The file is parsed instantly in your browser. Works with any downloaded movie or series.</small>
                </div>
            </div>

            <!-- Tab 3: Sync & Styling -->
            <div class="sub-tab-content" id="subTabSync" style="display:none;">
                <div class="sub-setting-group">
                    <label class="sub-setting-label">Timing Offset (Sync Delay / Advance)</label>
                    <p class="sub-setting-hint">If subtitles appear too early or too late, adjust timing:</p>
                    <div class="sub-offset-controls">
                        <button type="button" class="btn-offset" onclick="ZenSubtitles.adjustOffset(-1.0)">-1.0s</button>
                        <button type="button" class="btn-offset" onclick="ZenSubtitles.adjustOffset(-0.5)">-0.5s</button>
                        <span class="sub-offset-display" id="subOffsetVal">0.0s</span>
                        <button type="button" class="btn-offset" onclick="ZenSubtitles.adjustOffset(0.5)">+0.5s</button>
                        <button type="button" class="btn-offset" onclick="ZenSubtitles.adjustOffset(1.0)">+1.0s</button>
                        <button type="button" class="btn-offset btn-offset-reset" onclick="ZenSubtitles.resetOffset()">Reset</button>
                    </div>
                </div>

                <div class="sub-setting-group mt-3">
                    <label class="sub-setting-label">Subtitle Font Size</label>
                    <div class="sub-pill-group" id="subSizeGroup">
                        <button type="button" class="sub-pill" data-size="16px">Small</button>
                        <button type="button" class="sub-pill active" data-size="20px">Medium</button>
                        <button type="button" class="sub-pill" data-size="25px">Large</button>
                        <button type="button" class="sub-pill" data-size="30px">Extra</button>
                    </div>
                </div>

                <div class="sub-setting-group mt-3">
                    <label class="sub-setting-label">Subtitle Text Color</label>
                    <div class="sub-pill-group" id="subColorGroup">
                        <button type="button" class="sub-pill active" data-color="#ffffff">White</button>
                        <button type="button" class="sub-pill" data-color="#ffeaa7">Yellow</button>
                        <button type="button" class="sub-pill" data-color="#00e0ff">Cyan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

            <?php foreach ($downloadLinks as $link):
                // Through /download, which records the download and sends the file.
                $dlQuery = ['id' => (int) $mediaId, 'type' => $mediaType, 'quality' => $link['quality']];
                if ($mediaType === 'tv') {
                    $dlQuery['season'] = $seasonNum;
                    $dlQuery['episode'] = $episodeNum;
                }
            ?>
            <a href="/download?<?php echo htmlspecialchars(http_build_query($dlQuery)); ?>" class="dl-quality-item" target="_blank" rel="noopener">
                <div class="dl-quality-info">
                    <div class="dl-quality-badge">
                        <span class="badge-res"><?php echo htmlspecialchars($link['quality']); ?></span>
                        <?php if (!empty($link['language'])): ?><span class="badge-format"><?php echo htmlspecialchars($link['language']); ?></span><?php endif; ?>
                    </div>
                    <span class="dl-quality-label"><?php echo htmlspecialchars($link['file_size'] ?: 'Download'); ?></span>
                    <?php if (!empty($link['license_label'])): ?><span class="dl-quality-meta"><?php echo htmlspecialchars($link['license_label']); ?></span><?php endif; ?>
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
            navigator.serviceWorker.register('/sw.js') // /service-worker.js does not exist; every other page uses /sw.js
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
                onNext: <?php echo $playerNextUrl ? 'function () { location.href = ' . json_encode($playerNextUrl) . '; }' : 'null'; ?>,
                onEpisodes: <?php echo ($mediaType === 'tv' && !empty($seasonsData)) ? 'function () { window.openEpisodePanel(); }' : 'null'; ?>
            });
        }

        // The player inserts a wrapper around the <video>, so show/hide has
        // to act on that wrapper rather than the element itself.
        function setDirectVideoVisible(visible) {
            const target = (mobilePlayer && mobilePlayer.shell) ? mobilePlayer.shell : playerVideo;
            if (target) target.style.display = visible ? 'block' : 'none';
            // Lets CSS hide the EP tab while the direct-file player (with its own button) is showing.
            document.getElementById('videoArea').classList.toggle('direct-playing', !!visible);
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
        function switchToServer(index, resumeAt) {
            if (!servers[index]) return;
            currentServer = index;

            // Show loading overlay
            if (playerLoading) playerLoading.classList.add('visible');

            const url = servers[index].url;
            const isDirect = url.match(/\.(mp4|mkv|webm|m3u8)(\?|$)/i);

            if (isDirect) {
                clearTimeout(loadTimeout);
                if (playerIframe) {
                    playerIframe.style.display = 'none';
                    playerIframe.src = '';
                }
                if (playerVideo) {
                    setDirectVideoVisible(true);
                    playerVideo.src = url;
                    playerVideo.load();
                    if (resumeAt > 0) {
                        // Carry on from the same moment in the new file.
                        playerVideo.addEventListener('loadedmetadata', () => { playerVideo.currentTime = resumeAt; }, { once: true });
                    }
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
                    if (window.zenArmIframeWatchdog) window.zenArmIframeWatchdog();
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

        // Iframe answered — hide the overlay and call off the watchdog below.
        if (playerIframe) {
            playerIframe.addEventListener('load', () => {
                clearTimeout(loadTimeout);
                if (playerLoading) playerLoading.classList.remove('visible');
            });
        }
        // A video file has no iframe load event: hide the overlay once the
        // file starts (or fails) instead.
        if (playerVideo) {
            ['loadeddata', 'playing', 'error'].forEach(evt => playerVideo.addEventListener(evt, () => {
                if (playerLoading) playerLoading.classList.remove('visible');
            }));
        }

        // --- Slow connections ---
        // Films the site plays itself can come in several sizes (Admin > Free
        // films, the ingestion pipeline); the server marks the smallest 'light'.
        const lightIndex = servers.findIndex(s => s.light);
        const isDirectUrl = url => /\.(mp4|mkv|webm|m3u8)(\?|$)/i.test(url || '');
        const netInfo = navigator.connection || null;

        // Auto-switch disabled: user manually selects servers.

        // =================================================================
        // SERVER OPTIONS & MANUAL SWITCHING
        // =================================================================
        // Automatic server switching is disabled — the user controls playback manually.
        const deadSources = new Set();

        function serverHost(url) {
            try { return new URL(url, location.href).hostname; } catch (e) { return ''; }
        }

        function markServerDead(index, note) {
            deadSources.add(index);
            if (!serverDropdown) return;
            const opt = serverDropdown.querySelector('.server-option[data-index="' + index + '"]');
            if (opt && !opt.dataset.dead) {
                opt.dataset.dead = '1';
                opt.style.opacity = '0.45';
                opt.title = note || 'Not responding';
            }
        }

        function switchToNextServer() {
            if (servers.length <= 1) return;
            const next = (currentServer + 1) % servers.length;
            const nextName = (servers[next] && servers[next].name) || 'another source';
            switchToServer(next);
            showBanner('slow', '<i class="fa-solid fa-arrows-rotate" style="opacity:0.8"></i> Switched to ' + nextName);
            hideBanner(4000);
        }

        // Direct video errors — inform user without automatic switching
        if (playerVideo) {
            playerVideo.addEventListener('error', () => {
                const src = playerVideo.getAttribute('src');
                if (!src) return;
                const current = servers[currentServer];
                if (!current || !isDirectUrl(current.url)) return;
                const err = playerVideo.error;
                if (err && err.code === (err.MEDIA_ERR_ABORTED || 1)) return;
                if (src !== current.url) return;
                if (playerLoading) playerLoading.classList.remove('visible');
                showBanner('offline', '<i class="fa-solid fa-circle-exclamation" style="opacity:0.8"></i> Video playback error. You can select another server from the list.');
            });
        }

        // Iframe watchdog: hide loading overlay after 10s so it doesn't block the screen indefinitely, without switching servers
        window.zenArmIframeWatchdog = function () {
            clearTimeout(loadTimeout);
            loadTimeout = setTimeout(function () {
                if (playerLoading) playerLoading.classList.remove('visible');
            }, 10000);
        };
        if (playerIframe && playerIframe.src) window.zenArmIframeWatchdog();

        // Optional server health check to hint in the dropdown list (visual hint only, never switches automatically)
        if (servers.some(s => !isDirectUrl(s.url))) {
            fetch('/server-health.php', { credentials: 'same-origin' })
                .then(r => (r.ok ? r.json() : null))
                .then(d => {
                    if (!d || !d.hosts) return;
                    servers.forEach((s, i) => {
                        if (isDirectUrl(s.url)) return;
                        const info = d.hosts[serverHost(s.url)];
                        if (info && info.up === false) markServerDead(i, 'May be slow or unresponsive');
                    });
                })
                .catch(() => {});
        }

        // Manual option in dropdown: "Not playing? Try the next one"
        if (serverDropdown && servers.length > 1) {
            const tryNext = document.createElement('button');
            tryNext.type = 'button';
            tryNext.className = 'srv-try-next w-100 text-start px-3 py-2 border-0 bg-transparent';
            tryNext.innerHTML = '<i class="ph ph-arrows-clockwise"></i> Not playing? Try the next one';
            tryNext.style.cssText = 'cursor:pointer; border-bottom:1px solid #222; color:#00e0ff; font-weight:600;';
            tryNext.addEventListener('click', function (e) {
                e.stopPropagation();
                serverDropdown.classList.remove('open');
                switchToNextServer();
            });
            serverDropdown.insertBefore(tryNext, serverDropdown.firstChild);
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
                // Already playing the data saver file: nothing to suggest.
                if (lightIndex >= 0 && currentServer === lightIndex) return;
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
        // Close the menu first. It is stacked above everything else, so on
        // phones search used to open underneath it and looked broken.
        var menu = document.getElementById('appSidebar');
        if (menu) menu.classList.remove('mobile-open');
        var menuShade = document.getElementById('sidebarOverlay');
        if (menuShade) menuShade.classList.remove('active');
        var watchMenu = document.querySelector('.watch-sidebar.open');
        if (watchMenu) watchMenu.classList.remove('open');
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
    // Watchlist: saved to the account. Guests are sent to sign in and brought
    // back here. (It used to only flip the icon and never saved anything.)
    const watchlistBtn = document.getElementById('watchlistBtn');
    if (watchlistBtn) {
        // The page's banner helpers are private to another script block, so
        // use them if they're reachable and a toast otherwise.
        const notify = (type, text) => {
            if (typeof showBanner === 'function' && typeof hideBanner === 'function') {
                showBanner(type, '<i class="fa-solid ' + (type === 'success' ? 'fa-check' : 'fa-xmark') + '"></i> ' + text);
                hideBanner(3000);
            } else if (window.Toastify) {
                Toastify({ text: text, style: { background: type === 'success' ? '#00b09b' : '#555' } }).showToast();
            }
        };
        watchlistBtn.addEventListener('click', function() {
            const button = this;
            const icon = button.querySelector('i');
            button.disabled = true;
            fetch('/add-watchlist', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'id=' + encodeURIComponent(<?php echo json_encode((string) $mediaId); ?>) + '&type=' + encodeURIComponent(<?php echo json_encode($mediaType); ?>)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    if (/login/i.test(data.message || '')) {
                        window.location.href = '/login?next=' + encodeURIComponent(location.pathname + location.search);
                        return;
                    }
                    throw new Error(data.message);
                }
                const added = data.action === 'added';
                icon.className = 'ph ' + (added ? 'ph-check' : 'ph-plus');
                button.style.color = added ? '#4ade80' : '';
                button.title = added ? 'Remove from Watchlist' : 'Add to Watchlist';
                button.setAttribute('aria-pressed', added ? 'true' : 'false');
                notify(added ? 'success' : 'removed', added ? 'Added to your watchlist' : 'Removed from watchlist');
            })
            .catch(() => {
                notify('removed', "Couldn't update your watchlist. Please try again.");
            })
            .finally(() => { button.disabled = false; });
        });
    }

    // Download: the button opens the list of the site's own files (the
    // dlBtn handler above). It used to also open torrent sites in a new tab.
<?php if ($canPlay): // Only something that plays counts as watched; a trailer and links don't. ?>
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
<?php endif; ?>
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

<script>
    // The shared ZEN AI chat, opened from the three buttons on this page. It is
    // handed the title playing so answers can be about what is on screen.
    window.zenAiContext = {
        title: <?php echo json_encode($videoTitle); ?>,
        type: <?php echo json_encode($mediaType); ?>,
        id: <?php echo json_encode((string) $mediaId); ?>
    };
    window.openZenFromWatch = function() {
        // Pause a direct file so nothing is missed while they read; embeds we
        // cannot reach, and their own player keeps control.
        var v = document.getElementById('playerVideo');
        if (v && !v.paused && !v.ended) { try { v.pause(); window.__zenResumeVideo = true; } catch (e) {} }
        if (typeof triggerZenAI === 'function') triggerZenAI();
    };
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('zenAIModal');
        if (!modal) return;
        modal.addEventListener('hidden.bs.modal', function() {
            var v = document.getElementById('playerVideo');
            if (v && window.__zenResumeVideo) { window.__zenResumeVideo = false; v.play().catch(function() {}); }
        });
    });
</script>

<?php include __DIR__ . '/includes/pwa-update.php'; ?>

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
                    window.location.href = '/login?next=' + encodeURIComponent(location.pathname + location.search);
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


<!-- Mobile AI Floating Button -->
<button id="mobileAiBtn" onclick="openZenFromWatch()" aria-label="Ask ZEN AI">
    <i class="ph-fill ph-sparkle"></i>
</button>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($mediaType === 'tv' && !empty($seasonsData)): ?>
<!-- Episode picker used by the player: the EP tab in landscape, and the
     direct-file player's Episodes button. -->
<div class="ep-backdrop" id="epBackdrop" hidden></div>
<aside class="ep-panel" id="epPanel" role="dialog" aria-modal="true" aria-label="Episodes" hidden>
    <div class="ep-panel-head">
        <strong>Episodes</strong>
        <button type="button" class="ep-panel-close" id="epPanelClose" aria-label="Close episodes"><i class="ph ph-x"></i></button>
    </div>
    <div class="ep-seasons" role="tablist" aria-label="Seasons">
        <?php foreach ($seasonsData as $epSeason): $epSn = (int) $epSeason['season_number']; ?>
        <button type="button" class="ep-season<?php echo $epSn === (int) $seasonNum ? ' active' : ''; ?>" data-season="<?php echo $epSn; ?>" role="tab" aria-selected="<?php echo $epSn === (int) $seasonNum ? 'true' : 'false'; ?>">S<?php echo $epSn; ?></button>
        <?php endforeach; ?>
    </div>
    <div class="ep-lists">
        <?php $epToday = date('Y-m-d'); foreach ($seasonsData as $epSeason): $epSn = (int) $epSeason['season_number']; ?>
        <div class="ep-list" data-season-list="<?php echo $epSn; ?>"<?php echo $epSn === (int) $seasonNum ? '' : ' hidden'; ?>>
            <?php foreach ($epSeason['episodes'] as $epItem):
                if (!isset($epItem['episode_number'])) continue;
                $epEn = (int) $epItem['episode_number'];
                $epIsCurrent = ($epSn === (int) $seasonNum && $epEn === (int) $episodeNum);
                $epUnaired = !$epIsCurrent && !empty($epItem['air_date']) && $epItem['air_date'] > $epToday;
                $epHref = '/watch?id=' . rawurlencode((string) $mediaId) . '&type=tv&season=' . $epSn . '&episode=' . $epEn;
                $epStill = !empty($epItem['still_path']) ? 'https://image.tmdb.org/t/p/w185' . $epItem['still_path'] : '';
                $epTag = $epUnaired ? 'div' : 'a';
            ?>
            <<?php echo $epTag; ?> class="ep-item<?php echo $epIsCurrent ? ' current' : ''; ?><?php echo $epUnaired ? ' unaired' : ''; ?>"<?php echo $epUnaired ? '' : ' href="' . htmlspecialchars($epHref) . '"'; ?><?php echo $epIsCurrent ? ' aria-current="true"' : ''; ?>>
                <span class="ep-thumb"<?php echo $epStill ? ' style="background-image:url(' . htmlspecialchars($epStill) . ')"' : ''; ?>><span class="ep-num"><?php echo $epEn; ?></span></span>
                <span class="ep-meta">
                    <span class="ep-name"><?php echo htmlspecialchars($epItem['name'] ?? ('Episode ' . $epEn)); ?></span>
                    <span class="ep-sub"><?php
                        if ($epIsCurrent) echo 'Now playing';
                        elseif ($epUnaired) echo 'Airs ' . htmlspecialchars(date('M j, Y', strtotime($epItem['air_date'])));
                        elseif (!empty($epItem['runtime'])) echo (int) $epItem['runtime'] . ' min';
                    ?></span>
                </span>
            </<?php echo $epTag; ?>>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</aside>
<script>
(function () {
    const panel = document.getElementById('epPanel');
    const backdrop = document.getElementById('epBackdrop');
    const lists = panel.querySelector('.ep-lists');
    const area = document.getElementById('videoArea');

    // The EP tab only belongs over a streaming server; the direct-file player
    // has its own Episodes button. Server switches update this class too.
    if (area) area.classList.toggle('direct-playing', <?php echo json_encode(!empty($isDirectVideo)); ?>);

    function showSeason(n) {
        panel.querySelectorAll('.ep-season').forEach(function (b) {
            const on = b.dataset.season === String(n);
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panel.querySelectorAll('.ep-list').forEach(function (l) { l.hidden = l.dataset.seasonList !== String(n); });
        lists.scrollTop = 0;
    }

    window.openEpisodePanel = function () {
        // In fullscreen the browser only draws the fullscreen element, so the
        // panel has to live inside it to be seen.
        const fs = document.fullscreenElement || document.webkitFullscreenElement;
        const host = (fs && fs.tagName !== 'IFRAME') ? fs : document.body;
        if (panel.parentNode !== host) { host.appendChild(backdrop); host.appendChild(panel); }

        showSeason(<?php echo (int) $seasonNum; ?>);
        backdrop.hidden = false;
        panel.hidden = false;
        requestAnimationFrame(function () {
            panel.classList.add('open');
            const current = panel.querySelector('.ep-item.current');
            if (current) lists.scrollTop = current.offsetTop - lists.clientHeight / 2 + current.offsetHeight / 2;
        });
    };

    function closeEpisodePanel() {
        panel.classList.remove('open');
        backdrop.hidden = true;
        panel.hidden = true;
    }
    window.closeEpisodePanel = closeEpisodePanel;

    document.getElementById('epPanelClose').addEventListener('click', closeEpisodePanel);
    backdrop.addEventListener('click', closeEpisodePanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) closeEpisodePanel(); });
    panel.querySelectorAll('.ep-season').forEach(function (b) {
        b.addEventListener('click', function () { showSeason(b.dataset.season); });
    });
})();
</script>
<?php endif; ?>
<script>
// Phones: slide the floating AI and theme buttons out of the way while the
// page scrolls down, and bring them back when it scrolls up. Listens to every
// scroller, because on phones this page may scroll inside a container rather
// than the window.
(function () {
    var phone = window.matchMedia('(max-width: 800px)');
    var last = new WeakMap();
    var ticking = false;
    document.addEventListener('scroll', function (e) {
        var el = (e.target === document) ? document.scrollingElement : e.target;
        if (!el || ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            var y = el.scrollTop;
            var prev = last.has(el) ? last.get(el) : y;
            if (!phone.matches) {
                document.body.classList.remove('floats-away');
            } else if (Math.abs(y - prev) > 8) {
                document.body.classList.toggle('floats-away', y > prev && y > 120);
                last.set(el, y);
            } else if (!last.has(el)) {
                last.set(el, y);
            }
            ticking = false;
        });
    }, { passive: true, capture: true });
})();
</script>
</body>
</html>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Header logic excluded for standalone UI

// ==========================================
// 0. CONFIG: FALLBACK IMAGES
// ==========================================
$posterPlaceholder = 'assets/images/user/userblank.jpg'; 
$backdropPlaceholder = 'assets/images/user/userblank.jpg';
$castPlaceholder = 'assets/images/user/userblank.jpg';

// ==========================================
// 1. INITIALIZE & VALIDATE
// ==========================================
$mediaId = $_GET['id'] ?? null;
$mediaType = $_GET['type'] ?? 'movie'; 
$currentSeason = $_GET['season'] ?? 1;

if (!$mediaId) {
    echo "<script>window.location.href = '/';</script>";
    exit;
}

// ==========================================
// 2. SMART VIEW COUNT LOGIC (Netflix Style)
// ==========================================
$viewCount = 0;

if (isset($conn)) {
    // A. Get Current Views from DB
    $vStmt = $conn->prepare("SELECT views FROM content_views WHERE tmdb_id = ? AND media_type = ?");
    $vStmt->execute([$mediaId, $mediaType]);
    $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
    
    $viewCount = $vRow ? $vRow['views'] : 0; // Start with DB count

    // B. Check Session to prevent "Refresh Spam"
    $sessionKey = "viewed_{$mediaType}_{$mediaId}";
    
    if (!isset($_SESSION[$sessionKey])) {
        // User hasn't viewed this in this session yet. Count it!
        
        // Insert or Update (+1)
        $upsert = "INSERT INTO content_views (tmdb_id, media_type, views) VALUES (?, ?, 1) 
                   ON DUPLICATE KEY UPDATE views = views + 1";
        $conn->prepare($upsert)->execute([$mediaId, $mediaType]);
        
        // Update local variable to show immediate change
        $viewCount++; 
        
        // Set session flag so F5 doesn't count again
        $_SESSION[$sessionKey] = true;
    }
} else {
    // Database connection failed? Fallback to random number based on ID so UI looks good
    $viewCount = ($mediaId * 3) + 140; 
}

// ==========================================
// 3. FETCH DETAILS FROM API
// ==========================================
$endpoint = "{$mediaType}/{$mediaId}";
$params = [
    'append_to_response' => 'credits,videos,recommendations,similar,release_dates,content_ratings,keywords,external_ids'
];

if (function_exists('fetchTmdbApi')) {
    $details = fetchTmdbApi($endpoint, $params);
} else {
    $details = null;
}

if (!$details) {
    echo "<div class='container-fluid p-5'><h2 class='text-center text-white mt-5'>Content not found.</h2></div>";
    include APP_PATH . '/views/includes/footer.php';
    exit;
}

// ==========================================
// 4. PROCESS DATA VARIABLES
// ==========================================
$title = $details['title'] ?? $details['name'];
$overview = $details['overview'];
$backdrop = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $details['backdrop_path'] : $backdropPlaceholder;
$rating = number_format($details['vote_average'] ?? 0, 1);
$releaseDate = $details['release_date'] ?? $details['first_air_date'] ?? '';
$year = $releaseDate ? date('Y', strtotime($releaseDate)) : 'N/A';
$today = date('Y-m-d');
$isUpcoming = ($releaseDate > $today);
$imdbId = $details['external_ids']['imdb_id'] ?? null;

// Runtime
if ($mediaType === 'movie') {
    $runtime = $details['runtime'] ?? 0;
    $duration = floor($runtime / 60) . 'hr : ' . ($runtime % 60) . 'mins';
} else {
    $seasonsCount = $details['number_of_seasons'] ?? 0;
    $duration = "$seasonsCount Seasons";
}

// Trailer - try harder to find a video (Trailer > Teaser > Clip > any YouTube video)
$trailerUrl = '';
$trailerKey = '';
$trailerEmbed = '';
if (!empty($details['videos']['results'])) {
    $fallbackKey = '';
    foreach ($details['videos']['results'] as $video) {
        if ($video['site'] !== 'YouTube') continue;
        // Prioritize: Official Trailer > Trailer > Teaser > anything
        if ($video['type'] === 'Trailer' && ($video['official'] ?? false)) {
            $trailerKey = $video['key'];
            break;
        }
        if ($video['type'] === 'Trailer' && empty($trailerKey)) {
            $trailerKey = $video['key'];
        }
        if (empty($fallbackKey)) {
            $fallbackKey = $video['key']; // First YouTube video as fallback
        }
    }
    if (empty($trailerKey)) $trailerKey = $fallbackKey;
    if ($trailerKey) {
        $trailerUrl = "https://www.youtube.com/watch?v={$trailerKey}";
        $trailerEmbed = "https://www.youtube.com/embed/{$trailerKey}?autoplay=0&controls=1&rel=0&modestbranding=1&playsinline=1";
    }
}
// If no backdrop image AND no trailer, the hero will still look good with the gradient

// Age Rating
$ageRating = 'PG-13';
if ($mediaType === 'movie' && isset($details['release_dates'])) {
    foreach ($details['release_dates']['results'] as $r) {
        if ($r['iso_3166_1'] === 'US') {
            foreach ($r['release_dates'] as $release) {
                if (!empty($release['certification'])) {
                    $ageRating = $release['certification'];
                    break 2;
                }
            }
        }
    }
} elseif ($mediaType === 'tv' && isset($details['content_ratings'])) {
    foreach ($details['content_ratings']['results'] as $r) {
        if ($r['iso_3166_1'] === 'US') {
            $ageRating = $r['rating'];
            break;
        }
    }
}

$originalLang = isset($details['original_language']) ? locale_get_display_language($details['original_language'], 'en') : 'English';

// --- REVIEWS ---
$movieReviews = [];
if (isset($conn)) {
    $sql = "SELECT r.*, u.username, u.avatar_url FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.media_id = ? AND r.media_type = ? ORDER BY r.created_at DESC"; 
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mediaId, $mediaType]);
    $movieReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- WATCHLIST ---
$isInWatchlist = false;
if (isset($conn) && isset($_SESSION['user_id'])) {
    try {
        $wlStmt = $conn->prepare("SELECT id FROM watchlist WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ?");
        $wlStmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
        $isInWatchlist = (bool) $wlStmt->fetch();
    } catch (PDOException $e) {
        error_log("Watchlist Check Error: " . $e->getMessage());
        $isInWatchlist = false;
    }
}

// --- UPCOMING ---
$upcomingList = [];
$upData = fetchTmdbApi('discover/movie', ['region' => 'US', 'sort_by' => 'popularity.desc', 'primary_release_date.gte' => date('Y-m-d', strtotime('+1 day')), 'page' => 1]);
if ($upData && !empty($upData['results'])) { $upcomingList = array_slice($upData['results'], 0, 10); }

// --- RELATED ---
$relatedList = $details['recommendations']['results'] ?? $details['similar']['results'] ?? [];
$relatedList = array_slice($relatedList, 0, 100);

// --- CAST ---
$castList = array_slice($details['credits']['cast'] ?? [], 0, 15);
$crewList = [];
if (!empty($details['credits']['crew'])) {
    $targetJobs = ['Director', 'Producer', 'Writer'];
    foreach ($details['credits']['crew'] as $member) {
        if (in_array($member['job'], $targetJobs)) { $crewList[] = $member; }
    }
    $crewList = array_slice($crewList, 0, 10);
}

// ==========================================
// 1. CONFIGURATION & SITES LIST
// ==========================================
$downloadLinks = [];

// ADD NEW SITES HERE.
// 'query_param': usually '?s=' for WordPress, or specific search paths.
$sites = [
    [
        'name'   => 'FzTvSeries', 
        'url'    => 'https://fztvseries.ng/?s=', 
        'domain' => 'fztvseries.ng'
    ],
    [
        'name'   => 'Nkiri', 
        'url'    => 'https://nkiri.com/?s=', 
        'domain' => 'nkiri.com'
    ],
    [
        'name'   => 'SabiShares', 
        'url'    => 'https://sabishares.com/?s=', 
        'domain' => 'sabishares.com'
    ],
    [
        'name'   => 'TFPDL', 
        'url'    => 'https://tfpdl.se/?s=', 
        'domain' => 'tfpdl.se'
    ],
    [
        'name'   => 'MobileTvShows', 
        'url'    => 'https://mobiletvshows.net/search?q=', 
        'domain' => 'mobiletvshows.net'
    ]
];

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================

if (!function_exists('curlGet')) {
    function curlGet($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            // Randomize User Agent to avoid blocking
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10, // Fast timeout so we don't hang if one site is down
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}

if (!function_exists('isValidLink')) {
    function isValidLink($url, $domain, $existingUrls) {
        if (stripos($url, $domain) === false) return false; 
        if (stripos($url, '/tag/') !== false) return false;
        if (stripos($url, '/category/') !== false) return false;
        if (stripos($url, '/page/') !== false) return false; // Skip pagination links
        if (stripos($url, '#respond') !== false) return false;
        if (in_array($url, $existingUrls)) return false;
        return true;
    }
}

// ==========================================
// 3. MAIN LOGIC (LOOP THROUGH SITES)
// ==========================================

// Check Local DB first (optional)
if (isset($conn) && isset($mediaId) && isset($mediaType)) {
    $dlSql = "SELECT quality, file_size, download_url, language FROM media_downloads 
              WHERE tmdb_id = ? AND media_type = ? ORDER BY quality DESC";
    $stmt = $conn->prepare($dlSql);
    $stmt->execute([$mediaId, $mediaType]); 
    $manualDownloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($manualDownloads)) $downloadLinks = $manualDownloads;
}

// If no local links, start scraping
if (empty($downloadLinks)) {
    
    $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $addedUrls = []; 

    foreach ($sites as $site) {
        // Break loop if we have enough results (e.g., 15 links)
        if (count($downloadLinks) >= 20) break;

        // Skip MobileTvShows if it's a Movie (it only has Series)
        if ($site['name'] === 'MobileTvShows' && $mediaType === 'movie') continue;

        // Build URL
        $searchUrl = $site['url'] . urlencode($cleanTitle);
        $rawHtml = @curlGet($searchUrl);

        if ($rawHtml) {
            // Generic Regex for <a href="...">Title</a>
            // This works for 95% of sites including WordPress and simple HTML sites
            preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>([^<]*' . preg_quote($cleanTitle, '/') . '[^<]*)<\/a>/i', $rawHtml, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $linkUrl = $match[1];
                $linkText = strip_tags($match[2]);

                // Clean relative URLs (specifically for MobileTvShows)
                if (strpos($linkUrl, 'http') === false) {
                    if ($site['name'] === 'MobileTvShows') {
                        $linkUrl = "https://mobiletvshows.net/" . $linkUrl;
                    }
                }

                if (isValidLink($linkUrl, $site['domain'], $addedUrls)) {
                    
                    $isSeries = (preg_match('/S\d+|Season|Episode/i', $linkText));
                    
                    // Add to results
                    $downloadLinks[] = [
                        "quality"      => $site['name'], // Shows source name (e.g. "TFPDL")
                        "file_size"    => $isSeries ? "Select Episode" : "Download Page",
                        "language"     => "English",
                        "seeds"        => 100 - count($downloadLinks), // Fake sorting priority
                        "download_url" => $linkUrl
                    ];
                    
                    $addedUrls[] = $linkUrl;
                }
            }
        }
    }
}
?>


<?php
// Dynamically calculate the base directory for assets and links
$baseDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = rtrim($baseDir, '/\\') . '/';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
  <title><?php echo htmlspecialchars($title); ?> - Details</title>
  <link rel="shortcut icon" href="/assets/images/favicon.ico" />
  <link rel="apple-touch-icon" href="/assets/images/logo.png">
  <link rel="manifest" href="manifest.json">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <base href="<?php echo htmlspecialchars($baseDir); ?>">

  <!-- Add core styles for modal/badges -->
  <link rel="stylesheet" href="assets/css/core/libs.min.css" />
  <link rel="stylesheet" href="assets/css/core/custom.min.cssv=5.4.0.css" />
  <link rel="stylesheet" href="assets/css/core/watch-theme.css?v=<?php echo time() + 2; ?>">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">

  <script>
    // Apply saved theme immediately to prevent flashing
    const savedTheme = localStorage.getItem('zen_theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
  </script>
  <style>
        :root {
            --primary: #e50914;
            --primary-hover: #f40612;
            --primary-glow: rgba(229, 9, 20, 0.4);
        }
        
        @media (max-width: 800px) {
            body, html {
                overflow: auto !important;
                height: auto !important;
            }
            .watch-app {
                overflow: visible !important;
                height: auto !important;
            }
            .meta-actions {
                flex-direction: column;
                align-items: stretch !important;
            }
            .btn-play-now {
                width: 100% !important;
                justify-content: center !important;
                margin-left: 0 !important;
            }
            .meta-actions-circles {
                display: flex;
                gap: 15px;
                justify-content: center;
                width: 100%;
                margin-top: 5px;
            }
            .btn-circle-action {
                margin-left: 0 !important;
            }
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
        /* Fix for primary color overrides to NOT affect stars */
        body .text-warning, body i.text-warning { color: var(--primary) !important; }
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

      body, html { background-color: #0a0a0f !important; color: #b0b0b8 !important; overflow: hidden; margin:0; padding:0; height: 100vh;}
      
      
      .detail-stage {
          position: relative;
          width: 100%;
          height: 0;
          padding-bottom: 56.25%;
          border-radius: 12px;
          overflow: hidden;
          background: #000;
          margin-bottom: 20px;
          border: 1px solid rgba(255,255,255,0.05);
      }
      .detail-stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }
      .detail-meta-card {
          padding: 30px;
          background: #0e0e14;
          border-radius: 12px;
          border: 1px solid rgba(255,255,255,0.05);
          margin-bottom: 25px;
          position: relative;
      }
      
      .meta-title { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 10px; letter-spacing: -0.5px; }
      .meta-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
      .meta-tag { background: rgba(255,255,255,0.1); backdrop-filter: blur(4px); padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; color: #eee; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.05); }
      .meta-desc { font-size: 0.95rem; color: #ccc; max-width: 800px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 20px; line-height: 1.6; }
      .meta-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
      .meta-actions-circles { display: flex; gap: 12px; align-items: center; }
      
      .btn-play-now { background: var(--primary); color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 15px var(--primary-glow); }
      .btn-play-now:hover { background: var(--primary-hover); color: #fff; transform: translateY(-2px); }
      .btn-circle-action { width: 45px; height: 45px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: 0.2s; cursor: pointer; text-decoration: none; }
      .btn-circle-action:hover { background: var(--primary); color: #fff; transform: translateY(-2px); border-color: var(--primary); box-shadow: 0 4px 15px var(--primary-glow); }
      
      /* Sidebar Toggle & Cast Link */
      .cast-row-link { display: block; text-decoration: none; transition: transform 0.2s; }
      .cast-row-link:hover { transform: translateX(5px); }

      
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
      #searchOverlay .search-overlay-form:focus-within { border-color: var(--primary); background: rgba(255,255,255,0.08); box-shadow: 0 12px 40px rgba(229, 9, 20, 0.12); }
      #searchOverlay .search-icon { font-size: 1.6rem; color: #666; margin-right: 15px; flex-shrink: 0; }
      #searchOverlay .search-overlay-form:focus-within .search-icon { color: var(--primary); }
      #searchOverlay .search-overlay-form input {
          flex: 1; background: transparent !important; border: none !important; color: #fff !important;
          font-size: 1.3rem; font-weight: 400; outline: none !important; width: 100%;
          box-shadow: none !important; padding: 8px 0 !important;
      }
      #searchOverlay .search-overlay-form input::placeholder { color: #555; }
      #searchOverlay .search-submit-btn {
          background: var(--primary); color: #fff; border: none;
          padding: 12px 28px; border-radius: 10px; font-size: 1rem; font-weight: 700;
          cursor: pointer; transition: 0.2s; margin-left: 12px; flex-shrink: 0;
      }
      #searchOverlay .search-submit-btn:hover { background: #ff2a35; }
      #searchOverlay .search-overlay-hint { margin-top: 20px; color: #444; font-size: 0.85rem; }
      #searchOverlay .search-overlay-hint kbd { background: rgba(255,255,255,0.08); padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; color: #888; border: 1px solid rgba(255,255,255,0.1); }

      /* Modals inside watch-app fix */
      .dl-modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; z-index: 10000; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
      .dl-modal-overlay.active { display: flex; opacity: 1; }
      .dl-modal { background: #111; border: 1px solid #333; border-radius: 16px; width: 90%; max-width: 500px; padding: 25px; transform: translateY(20px); transition: 0.3s; }
      .dl-modal-overlay.active .dl-modal { transform: translateY(0); }
      .dl-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
      .dl-modal-header h3 { margin: 0; font-size: 1.5rem; color: #fff; font-weight: 700; }
      .dl-modal-close { background: none; border: none; color: #888; font-size: 1.5rem; cursor: pointer; }
      .dl-modal-close:hover { color: #fff; }
      .dl-quality-item { display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; padding: 15px; border-radius: 12px; text-decoration: none; border: 1px solid #222; margin-bottom: 10px; transition: 0.2s; }
      .dl-quality-item:hover { background: #222; border-color: #4cd137; }
      .dl-quality-badge { margin-bottom: 5px; }
      .badge-res { background: var(--primary); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800; font-size: 0.75rem; }
      .badge-format { background: rgba(255,255,255,0.1); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; margin-left: 5px; }
      .dl-quality-label { display: block; color: #fff; font-size: 1rem; font-weight: 600; }
      .dl-quality-meta { font-size: 0.8rem; color: #888; }
      .dl-quality-icon { font-size: 1.2rem; color: #4cd137; }
      
      /* Review Modal Styles */
      .offcanvas { background-color: #111 !important; color: #fff; border-left: 1px solid #333; }
      .offcanvas-header { border-bottom: 1px solid #333; }
      .btn-close { filter: invert(1); }
      .review-card { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05); }
      .form-control { background-color: #222; border-color: #444; color: #fff; }
      .form-control:focus { background-color: #2a2a2a; border-color: #555; color: #fff; box-shadow: none; }
      
      /* Force Sidebar Background */
      .watch-sidebar {
          background: transparent !important;
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
  </style>
</head>
<body>

<div class="watch-app">
    <!-- 1. Left Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- 2. Center Content -->
    <main class="watch-center custom-scrollbar" style="padding-top: 20px; padding-bottom: 60px;">
        <div class="center-top-bar" style="position: relative; background: transparent; padding: 0 0 15px 0;">
            <button class="back-btn" id="sidebarToggleBtn" style="border:none; cursor:pointer;"><i class="ph ph-list"></i></button>
            <a href="javascript:history.back()" class="back-btn text-decoration-none" style="background: rgba(255,255,255,0.05); border-radius: 8px;"><i class="ph ph-arrow-left"></i></a>
        </div>

        <?php if ($trailerEmbed): ?>
        <div class="detail-stage">
            <iframe src="<?php echo htmlspecialchars($trailerEmbed); ?>" allowfullscreen></iframe>
        </div>
        <?php else: ?>
        <div class="detail-stage" style="background-image: url('<?php echo htmlspecialchars($backdrop); ?>'); background-size: cover; background-position: center;"></div>
        <?php endif; ?>

        <div class="detail-meta-card">
            <?php if (!empty($details['genres'])): ?>
            <div class="meta-tags">
                <?php foreach (array_slice($details['genres'], 0, 3) as $genre): ?>
                <span class="meta-tag"><?php echo htmlspecialchars($genre['name']); ?></span>
                <?php endforeach; ?>
                <span class="meta-tag text-warning" style="background: transparent; border-color: rgba(255,193,7,0.3);"><i class="fa-solid fa-star"></i> <?php echo $rating; ?></span>
                <span class="meta-tag" style="background: transparent;"><?php echo $year; ?></span>
                <span class="meta-tag" style="background: transparent;"><?php echo $ageRating; ?></span>
            </div>
            <?php endif; ?>
            
            <h1 class="meta-title"><?php echo htmlspecialchars($title); ?></h1>
            
            <div id="ai-hook-container" style="background: rgba(123, 44, 191, 0.1); border-left: 3px solid #7b2cbf; padding: 12px 16px; margin: 15px 0; border-radius: 4px; font-size: 0.95rem; color: #e0e0e0; display: none;">
                <div style="font-weight: bold; color: #00e0ff; margin-bottom: 5px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;"><i class="ph-fill ph-sparkle"></i> ZEN AI Pitch</div>
                <div id="ai-hook-text">Thinking...</div>
            </div>

            <p class="meta-desc"><?php echo htmlspecialchars($overview); ?></p>
            
            <div class="meta-actions">
                <a href="watch?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn-play-now">
                    <i class="ph-fill ph-play-circle" style="font-size: 1.5rem;"></i> Play Now
                </a>
                
                <button class="btn-play-now" onclick="triggerZenAI('Find 5 movies that are extremely similar to <?php echo addslashes($title); ?>')" style="background: linear-gradient(135deg, #00e0ff, #7b2cbf); border: none; padding: 12px 24px; color: #fff; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="ph-fill ph-sparkle"></i> AI: Find Similar
                </button>
                
                <div class="meta-actions-circles">
                    <a href="#" class="btn-circle-action watchlist-btn" style="margin-left: 10px;" data-id="<?php echo $mediaId; ?>" data-type="<?php echo $mediaType; ?>" title="Add to Watchlist">
                        <i class="<?php echo $isInWatchlist ? 'ph ph-check text-success' : 'ph ph-plus'; ?>"></i>
                    </a>
                    
                    <?php if (!$isUpcoming): ?>
                    <button class="btn-circle-action" data-bs-target="#downloadModal" title="Download">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <?php endif; ?>

                    <button class="btn-circle-action" onclick="navigator.share({title: document.title, url: window.location.href})" title="Share">
                        <i class="ph ph-share-network"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Recommended Section -->
        <?php if (!empty($relatedList)): ?>
        <div class="recommended-section mx-0 px-0 mt-4 mb-4">
            <h4 class="mb-3" style="font-weight: 700; font-size: 1.4rem; color:#fff;">Related Content</h4>
            <div class="rec-cards">
                <?php foreach ($relatedList as $rec): ?>
                <a href="<?php echo ($mediaType === 'tv' ? 'tv/' : 'movie/') . $rec['id']; ?>" class="rec-card text-decoration-none">
                    <img src="<?php echo !empty($rec['poster_path']) ? 'https://image.tmdb.org/t/p/w300'.$rec['poster_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" alt="Poster">
                    <div class="rec-rating"><i class="ph ph-star" style="font-family:'Phosphor-Fill' !important; color:#ffc107;"></i> <?php echo round($rec['vote_average'], 1); ?></div>
                    <p class="rec-card-title text-truncate text-white"><?php echo htmlspecialchars($rec['title'] ?? $rec['name'] ?? 'Untitled'); ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reviews Section -->
        <div class="reviews-section mx-0 px-0 mt-4 mb-3">
             <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary pb-2">
                 <h4 style="font-weight: 700; font-size: 1.4rem; color:#fff; margin:0;">Reviews (<?php echo count($movieReviews); ?>)</h4>
                 <div>
                     <?php if(isset($_SESSION['user_id'])): ?>
                         <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="offcanvas" data-bs-target="#offcanvasReview">Add Review</button>
                     <?php else: ?>
                         <a href="login" class="btn btn-sm btn-outline-light rounded-pill px-3">Login to Review</a>
                     <?php endif; ?>
                 </div>
             </div>
             
             <div class="comments-list">
                 <?php if(!empty($movieReviews)): ?>
                     <?php foreach($movieReviews as $review): 
                         $rAvatar = !empty($review['avatar_url']) ? $review['avatar_url'] : 'assets/images/user/user.jpg';
                     ?>
                     <div class="review-card">
                         <div class="d-flex justify-content-between align-items-center mb-2">
                             <div class="d-flex align-items-center gap-2">
                                 <img src="<?php echo $rAvatar; ?>" alt="user" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                                 <div>
                                     <h6 style="margin:0; font-size:1rem; color:#fff;"><?php echo htmlspecialchars($review['username']); ?></h6>
                                     <small style="color:#888; font-size:0.8rem;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                 </div>
                             </div>
                             <div>
                                 <?php for($i=1; $i<=5; $i++): ?>
                                     <i class="ph ph-star" style="<?php echo ($i <= $review['rating']) ? "font-family:'Phosphor-Fill' !important; color:#ffc107;" : "color:#666;"; ?>"></i>
                                 <?php endfor; ?>
                             </div>
                         </div>
                         <p style="margin:0; color:#ccc; font-size:0.95rem; line-height:1.5;"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                     </div>
                     <?php endforeach; ?>
                 <?php else: ?>
                     <div class="text-center p-4">
                         <i class="ph-light ph-chat-centered-text text-secondary mb-2" style="font-size:3rem;"></i>
                         <p style="color:#888;">No reviews yet. Be the first to share your thoughts!</p>
                     </div>
                 <?php endif; ?>
             </div>
        </div>
    </main>

    <!-- 3. Right Panel -->
    <aside class="watch-right custom-scrollbar" id="sidebar">
       <!-- Info Card -->
       <div class="info-card">
           <img src="<?php echo !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w300'.$details['poster_path'] : $posterPlaceholder; ?>" class="info-poster" alt="Poster" loading="lazy">
           <div class="info-details">
               <p><span class="label">Status</span><span class="val"><?php echo htmlspecialchars($details['status'] ?? 'Released'); ?></span></p>
               <p><span class="label">Aired</span><span class="val"><?php echo htmlspecialchars($year); ?></span></p>
               <p><span class="label">Duration</span><span class="val"><?php echo htmlspecialchars($duration); ?></span></p>
               <p><span class="label">Language</span><span class="val"><?php echo htmlspecialchars($originalLang); ?></span></p>
               <p><span class="label">Views</span><span class="val"><?php echo number_format($viewCount); ?></span></p>
           </div>
       </div>

       <!-- Cast -->
       <?php if (!empty($castList)): ?>
       <div class="section-head mt-4">
           <h5>Characters</h5>
       </div>
       <div class="cast-list">
           <?php foreach (array_slice($castList, 0, 8) as $actor): ?>
           <a href="person-detail?id=<?php echo $actor['id']; ?>" class="cast-row-link">
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

       <!-- Crew -->
       <?php if (!empty($crewList)): ?>
       <div class="section-head mt-4">
           <h5>Crew</h5>
       </div>
       <div class="cast-list">
           <?php foreach ($crewList as $crew): ?>
           <a href="person-detail?id=<?php echo $crew['id']; ?>" class="cast-row-link text-decoration-none">
               <div class="cast-row">
                   <img src="<?php echo !empty($crew['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$crew['profile_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" alt="Crew">
                   <div>
                       <h6 style="color:#fff;"><?php echo htmlspecialchars($crew['name']); ?></h6>
                       <span style="color:#aaa;"><?php echo htmlspecialchars($crew['job'] ?? 'Crew'); ?></span>
                   </div>
               </div>
           </a>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>

    </aside>
</div>

<!-- DOWNLOAD MODAL -->
<?php if (!$isUpcoming): ?>
<div class="dl-modal-overlay" id="downloadModal">
    <div class="dl-modal">
        <div class="dl-modal-header">
            <h3><i class="fa-solid fa-download" style="color:#4cd137;margin-right:8px;"></i> Sources</h3>
            <button class="dl-modal-close" data-bs-dismiss="modal" id="dlModalClose"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="dl-modal-body">
            <p style="color:#ccc;">External sources for <strong style="color:#fff;"><?php echo htmlspecialchars($title); ?></strong></p>

            <?php if(!empty($downloadLinks)): ?>
                <div class="d-flex flex-column gap-2" style="max-height: 400px; overflow-y:auto; overflow-x:hidden;">
                    <?php foreach ($downloadLinks as $link): 
                        $isMagnet = (strpos($link['download_url'], 'magnet:') !== false);
                        $icon = $isMagnet ? 'fa-magnet' : 'fa-external-link-alt';
                    ?>
                        <a href="<?php echo htmlspecialchars($link['download_url']); ?>" class="dl-quality-item" target="_blank" rel="noopener">
                            <div class="dl-quality-info">
                                <div class="dl-quality-badge">
                                    <span class="badge-res"><?php echo htmlspecialchars($link['quality']); ?></span>
                                    <span class="badge-format"><?php echo htmlspecialchars($link['language']); ?></span>
                                </div>
                                <span class="dl-quality-label"><?php echo htmlspecialchars($link['file_size']); ?></span>
                                <span class="dl-quality-meta">External Link</span>
                            </div>
                            <div class="dl-quality-icon">
                                <i class="fa-solid <?php echo $icon; ?>"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-4">
                    <i class="fa-solid fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                    <p style="color:#888;">No external download links are available right now. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- REVIEW OFFCANVAS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasReview" aria-labelledby="offcanvasReviewLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasReviewLabel">Add Review</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
      <form action="" method="POST" id="addReviewForm">
          <input type="hidden" name="media_id" value="<?php echo $mediaId; ?>">
          <input type="hidden" name="media_type" value="<?php echo $mediaType; ?>">
          
          <div class="mb-4 text-center">
              <label class="form-label d-block text-start mb-2 text-secondary">Your Rating</label>
              <div class="star-rating d-flex justify-content-center gap-2 flex-row-reverse" style="background:#1a1a1a; padding:15px; border-radius:12px;">
                    <input type="radio" id="star5" name="rating" value="5"><label for="star5" style="cursor:pointer;"><i class="ph ph-star fs-1 text-secondary"></i></label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" style="cursor:pointer;"><i class="ph ph-star fs-1 text-secondary"></i></label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" style="cursor:pointer;"><i class="ph ph-star fs-1 text-secondary"></i></label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" style="cursor:pointer;"><i class="ph ph-star fs-1 text-secondary"></i></label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" style="cursor:pointer;"><i class="ph ph-star fs-1 text-secondary"></i></label>
              </div>
          </div>
          <div class="mb-4">
              <label class="form-label text-secondary" for="review_text">Your Review</label>
              <textarea id="review_text" name="review_text" class="form-control" rows="6" required placeholder="What did you think of this?"></textarea>
          </div>
          <button type="submit" class="btn w-100" style="background:var(--primary); color:#fff; font-weight:700; padding:12px;">Submit Review</button>
      </form>
  </div>
</div>

<style>
/* CSS Fix for Review Stars */
.star-rating input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    width: 0;
    height: 0;
}
.star-rating label i {
    color: #6c757d !important; /* Force unselected to gray */
}
.star-rating input:checked ~ label i,
.star-rating label:hover i,
.star-rating label:hover ~ label i { 
    color: #ffc107 !important; 
}
.star-rating input:checked ~ label i::before,
.star-rating label:hover i::before,
.star-rating label:hover ~ label i::before { 
    font-family: "Phosphor-Fill" !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- DOWNLOAD MODAL LOGIC ---
    (function() {
        const dlBtnColl = document.querySelectorAll('[data-bs-target="#downloadModal"]');
        const dlModal = document.getElementById('downloadModal');
        const dlClose = document.getElementById('dlModalClose');

        if (dlModal) {
            dlBtnColl.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dlModal.classList.add('active');
                });
            });

            if (dlClose) {
                dlClose.addEventListener('click', function() {
                    dlModal.classList.remove('active');
                });
            }

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
        
        // Sidebar Toggle Logic
        var sidebarBtn = document.getElementById('sidebarToggleBtn');
        if(sidebarBtn) {
            sidebarBtn.addEventListener('click', function() {
                document.querySelector('.watch-app').classList.toggle('sidebar-collapsed');
            });
        }
    })();

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
                        icon.className = 'ph ph-check text-success';
                        this.setAttribute('title', 'Remove from Watchlist');
                    } else {
                        icon.className = 'ph ph-plus';
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

    // --- FETCH AI HOOK ---
    document.addEventListener("DOMContentLoaded", function() {
        const mediaId = <?php echo json_encode($mediaId); ?>;
        const mediaType = <?php echo json_encode($mediaType); ?>;
        const container = document.getElementById('ai-hook-container');
        const textDiv = document.getElementById('ai-hook-text');

        if (mediaId) {
            // Show loading state
            container.style.display = 'block';

            // The title is deliberately not sent -- the endpoint resolves it
            // from TMDB itself, so a caller cannot dictate what text gets
            // cached and shown to everyone else on this page.
            const fd = new FormData();
            fd.append('media_id', mediaId);
            fd.append('media_type', mediaType);

            fetch('/ai-hook', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.hook) {
                        // textContent, not innerHTML: this is model output and
                        // must never be parsed as markup.
                        textDiv.textContent = data.hook;
                    } else {
                        container.style.display = 'none'; // Hide if failed
                    }
                })
                .catch(() => {
                    container.style.display = 'none';
                });
        }
    });

    // --- ADD REVIEW AJAX ---
    const addReviewForm = document.getElementById('addReviewForm');
    if (addReviewForm) {
        addReviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            const formData = new FormData(this);
            fetch('/process-reviews', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (data.status === 'success') {
                    // Close the offcanvas
                    const offcanvasEl = document.getElementById('offcanvasReview');
                    if (offcanvasEl) {
                        const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvasInstance) offcanvasInstance.hide();
                    }
                    
                    // Reset the form
                    addReviewForm.reset();
                    
                    // Dynamically prepend the review
                    const reviewsList = document.querySelector('.comments-list');
                    if (reviewsList) {
                        const rating = parseInt(formData.get('rating')) || 0;
                        const reviewText = formData.get('review_text') || '';
                        const username = '<?php echo addslashes($_SESSION["username"] ?? "Guest"); ?>';
                        const avatarUrl = '<?php echo addslashes($_SESSION["avatar_url"] ?? "assets/images/user/user.jpg"); ?>';
                        
                        const noReviews = reviewsList.querySelector('.text-center.p-4');
                        if (noReviews) noReviews.remove();
                        
                        let starsHtml = '';
                        for(let i=1; i<=5; i++) {
                            starsHtml += `<i class="ph ph-star" style="${i <= rating ? "font-family:'Phosphor-Fill' !important; color:#ffc107;" : "color:#666;"}"></i>`;
                        }
                        
                        const safeText = reviewText.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                        const newReviewHTML = `
                        <div class="review-card" style="animation: fadeIn 0.5s;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="${avatarUrl}" alt="user" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                                    <div>
                                        <h6 style="margin:0; font-size:1rem; color:#fff;">${username}</h6>
                                        <small style="color:#888; font-size:0.8rem;">Just now</small>
                                    </div>
                                </div>
                                <div>${starsHtml}</div>
                            </div>
                            <p style="margin:0; color:#ccc; font-size:0.95rem; line-height:1.5;">${safeText}</p>
                        </div>
                        `;
                        
                        reviewsList.insertAdjacentHTML('afterbegin', newReviewHTML);
                    } else {
                        // Fallback reload if structure missing
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert('An error occurred while submitting your review.');
            });
        });
    }
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
<?php include __DIR__ . '/includes/theme-modal.php'; ?>
<?php include __DIR__ . '/includes/mobile-footer.php'; ?>
</body>
</html>

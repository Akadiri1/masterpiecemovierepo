
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
$backdrop = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] : $backdropPlaceholder;
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

// The signed-in user's own review (the review panel edits it) and the average score.
$myReview = null;
$reviewAverage = null;
if ($movieReviews) {
    $reviewAverage = number_format(array_sum(array_column($movieReviews, 'rating')) / count($movieReviews), 1);
    foreach ($movieReviews as $r) {
        if (isset($_SESSION['user_id']) && (int) $r['user_id'] === (int) $_SESSION['user_id']) {
            $myReview = $r;
            break;
        }
    }
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
// 1. DOWNLOAD LINKS
// ==========================================
// Only links the site itself stores in media_downloads. This page used to
// search five outside download sites on every visit, which held the whole
// page back by several seconds. These are files the site may share: titles
// published by the licensed ingestion pipeline (v1/ingest) and links added
// under Admin > Download Links.
$downloadLinks = [];
if (isset($conn) && isset($mediaId) && isset($mediaType)) {
    $stmt = $conn->prepare("SELECT quality, file_size, download_url, language, season, episode, license_label FROM media_downloads
              WHERE tmdb_id = ? AND media_type = ? AND is_active = 1
              ORDER BY season, episode, quality DESC");
    $stmt->execute([$mediaId, $mediaType]);
    $downloadLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
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
  <!-- Posters and backdrops come from TMDB's image server: connect early. -->
  <link rel="preconnect" href="https://image.tmdb.org">
  <!-- Loading placeholders for images and page changes -->
  <link rel="stylesheet" href="/assets/css/core/skeleton.css?v=1">
  <script src="/assets/js/skeleton.js?v=1" defer></script>

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
      .meta-actions { display: flex; gap: 12px; align-items: stretch; flex-wrap: wrap; }
      
      
      .btn-play-now { background: var(--primary); color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 15px var(--primary-glow); }
      .btn-play-now:hover { background: var(--primary-hover); color: #fff; transform: translateY(-2px); }
      
      
      
      /* Sidebar Toggle & Cast Link */
      .cast-row-link { display: block; text-decoration: none; transition: transform 0.2s; }
      .cast-row-link:hover { transform: translateX(5px); }

      
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

      /* ---- Title, actions and reviews ---------------------------------------
         For every screen size; phones are tuned further in the block below. */
      .center-top-bar { display: flex; align-items: center; gap: 8px; }
      /* Search and ZEN AI sit together on the right of the top bar. */
      .top-search-btn { margin-left: auto; }
      .top-search-btn + .top-ai-btn { margin-left: 0; }
      .top-ai-btn {
          margin-left: auto;
          border: 1px solid transparent !important;
          border-radius: 10px;
          background: linear-gradient(#12121a, #12121a) padding-box,
                      linear-gradient(135deg, #00e0ff, #7b2cbf) border-box !important;
          color: #8ff0ff !important;
          cursor: pointer;
      }
      .section-heading { margin: 0; color: #fff; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.2px; }

      /* Backdrop with a "Watch trailer" button; the player loads when pressed. */
      .detail-hero { background-color: #0d0d14; background-size: cover; background-position: center 25%; }
      .detail-hero-shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(10, 10, 15, 0.05) 0%, rgba(10, 10, 15, 0.2) 50%, rgba(10, 10, 15, 0.7) 100%); pointer-events: none; }
      .detail-trailer-btn { position: absolute; left: 50%; top: 50%; display: inline-flex; align-items: center; gap: 12px; padding: 7px 20px 7px 7px; border: 1px solid rgba(255, 255, 255, 0.22); border-radius: 999px; background: rgba(12, 12, 18, 0.55); color: #fff; font-size: 0.95rem; font-weight: 700; white-space: nowrap; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); transform: translate(-50%, -50%); cursor: pointer; transition: background 0.2s, transform 0.2s; }
      .detail-trailer-btn:hover { background: rgba(12, 12, 18, 0.78); transform: translate(-50%, -50%) scale(1.03); }
      .detail-trailer-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 50%; background: var(--primary); box-shadow: 0 6px 18px -6px var(--primary-glow); font-size: 1.05rem; }
      .detail-trailer-icon i { margin-left: 2px; }

      #ai-hook-container {
          background: linear-gradient(135deg, rgba(0, 224, 255, 0.07), rgba(123, 44, 191, 0.16)) !important;
          border: 1px solid rgba(123, 44, 191, 0.35) !important;
          border-radius: 14px !important;
          padding: 14px 16px !important;
          line-height: 1.55;
      }

      .meta-more { margin: -12px 0 20px; padding: 0; border: 0; background: none; color: #fff; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
      .meta-more:hover { color: var(--primary); }
      .meta-desc.is-open { display: block; -webkit-line-clamp: unset; overflow: visible; }

      .btn-play-now { justify-content: center; min-height: 56px; border-radius: 12px; }
      .meta-tiles { display: flex; gap: 10px; }
      .meta-tile {
          display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
          min-width: 76px; height: 56px; padding: 0 12px;
          border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.06);
          color: #e8e8ef; font-size: 0.72rem; font-weight: 600; text-decoration: none; cursor: pointer;
          transition: background 0.2s, border-color 0.2s, transform 0.2s;
      }
      .meta-tile i { font-size: 1.3rem; line-height: 1; }
      .meta-tile:hover { background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.16); color: #fff; transform: translateY(-1px); }
      .meta-tile-ai i { background: linear-gradient(135deg, #00e0ff, #b46cff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

      .reviews-section { margin: 28px 0 16px; }
      .reviews-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
      .reviews-count { display: inline-block; margin-left: 6px; padding: 2px 9px; border-radius: 999px; background: rgba(255, 255, 255, 0.08); color: #bbb; font-size: 0.8rem; font-weight: 700; vertical-align: middle; }
      .reviews-write { display: inline-flex; align-items: center; gap: 6px; min-height: 0; padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(255, 255, 255, 0.04); color: #fff !important; font-size: 0.85rem; font-weight: 600; text-decoration: none; white-space: nowrap; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
      .reviews-write:hover { background: var(--primary); border-color: var(--primary); }
      .reviews-empty { border: 1px dashed rgba(255, 255, 255, 0.12); border-radius: 14px; background: rgba(255, 255, 255, 0.02); }
      .comments-list .reviews-empty.p-4 { padding: 28px 16px !important; }
      .reviews-empty-icon { width: 48px; height: 48px; margin: 0 auto 10px; display: grid; place-items: center; border-radius: 50%; background: rgba(255, 255, 255, 0.06); color: #9a9aa6; font-size: 1.5rem; }
      .reviews-empty-title { margin: 0 0 4px; color: #fff; font-weight: 600; }
      .reviews-empty-text { margin: 0; color: #8a8a96; font-size: 0.9rem; }
      .review-top { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
      .review-avatar { flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
      .review-who { flex: 1; min-width: 0; }
      .review-who h6 { margin: 0; overflow: hidden; color: #fff; font-size: 0.95rem; white-space: nowrap; text-overflow: ellipsis; }
      .review-who small { color: #888; font-size: 0.78rem; }
      .review-stars { flex-shrink: 0; font-size: 0.85rem; }
      .review-text { margin: 0; color: #ccc; font-size: 0.95rem; line-height: 1.55; }
      .reviews-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: 2px 12px; min-width: 0; }
      .reviews-avg { color: #9a9aa6; font-size: 0.85rem; }
      .reviews-avg strong { color: #fff; }
      .reviews-avg-star { color: #f5c518; }
      .reviews-empty { padding: 26px 16px; text-align: center; }
      .review-you { display: inline-block; margin-left: 8px; padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); color: #ddd; font-size: 0.66rem; font-weight: 700; vertical-align: middle; }
      .review-stars { letter-spacing: 1px; line-height: 1; font-size: 0.95rem; }
      .review-stars .on { color: #f5c518; }
      .review-stars .off { color: #3b3b48; }
      .review-text { white-space: pre-line; }
      .review-card.is-new { animation: reviewIn 0.45s ease-out; border-color: rgba(245, 197, 24, 0.35); }
      @keyframes reviewIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
      /* Keep the floating buttons out of the review panel's way. */
      body.zen-sheet-open .zen-ai-float, body.zen-sheet-open .theme-switcher-float { display: none !important; }

      /* Details on phones (see the markup). Shown only where the right-hand
         panel is squeezed out, below 800px. */
      .mobile-details { display: none; }
      .md-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; overflow: hidden; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.08); }
      .md-fact { min-width: 0; padding: 12px; background: #101017; }
      .md-fact:last-child:nth-child(3n + 2) { grid-column: span 2; }
      .md-fact span { display: block; margin-bottom: 2px; color: #8a8a96; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
      .md-fact strong { display: block; color: #fff; font-size: 0.9rem; font-weight: 600; overflow-wrap: anywhere; }
      .md-heading { margin: 24px 0 12px; }
      .md-people { display: flex; gap: 14px; overflow-x: auto; scrollbar-width: none; padding-bottom: 4px; }
      .md-people::-webkit-scrollbar { display: none; }
      .md-person { flex: 0 0 76px; text-align: center; text-decoration: none; }
      .md-person img { display: block; width: 72px; height: 72px; margin: 0 auto 8px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, 0.08); background: #1a1a24; object-fit: cover; }
      .md-person strong { display: -webkit-box; overflow: hidden; color: #fff; font-size: 0.78rem; font-weight: 600; line-height: 1.25; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
      .md-person span { display: block; margin-top: 2px; overflow: hidden; color: #8a8a96; font-size: 0.7rem; line-height: 1.25; white-space: nowrap; text-overflow: ellipsis; }

      @media (max-width: 800px) {
          /* The desktop side panel. Its content is in .mobile-details on phones, and
             even squeezed to zero width its padding showed as a strip on the right. */
          .watch-right { display: none !important; }
          /* Just enough room above the bottom bar. */
          .watch-center { padding-bottom: 16px !important; }
          .reviews-section { margin-bottom: 0; }
          .mobile-details { display: block; margin: 4px 0 8px; }
          .detail-stage { margin-bottom: 16px; border-radius: 14px; }
          .detail-meta-card { margin-bottom: 8px; padding: 2px 0 4px; border: 0; background: transparent; }
          .meta-tags { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; margin-bottom: 12px; padding-bottom: 2px; }
          .meta-tags::-webkit-scrollbar { display: none; }
          .meta-tag { flex-shrink: 0; padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; }
          .meta-title { margin-bottom: 8px; font-size: 1.9rem; }
          .meta-desc { margin-bottom: 16px; font-size: 0.92rem; }
          .meta-actions { flex-direction: column; gap: 12px; }
          .btn-play-now { width: 100%; min-height: 52px; font-size: 1.05rem; }
          .meta-tiles { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 8px; width: 100%; }
          .meta-tile { min-width: 0; height: 60px; padding: 0 4px; }
          .section-heading { font-size: 1.2rem; }
          .reviews-write { padding: 7px 12px; font-size: 0.8rem; }
      }
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
            <!-- ZEN AI on phones and tablets, instead of the floating button that covered the page. -->
            <button type="button" class="back-btn top-search-btn d-xl-none" onclick="openSearchModal();" title="Search" aria-label="Search" style="background: rgba(255,255,255,0.05); border-radius: 10px;"><i class="ph ph-magnifying-glass"></i></button>
            <button type="button" class="back-btn top-ai-btn d-xl-none" onclick="if (typeof triggerZenAI === 'function') triggerZenAI();" title="Ask ZEN AI" aria-label="Ask ZEN AI"><i class="ph-fill ph-sparkle"></i></button>
        </div>

        <!-- Backdrop with a "Watch trailer" button. The YouTube player (about a
             megabyte of scripts, plus YouTube's own title bar) only loads when
             someone presses it. -->
        <div class="detail-stage detail-hero" style="background-image: url('<?php echo htmlspecialchars($backdrop); ?>');">
            <div class="detail-hero-shade" aria-hidden="true"></div>
            <?php if ($trailerEmbed): ?>
            <button type="button" class="detail-trailer-btn" id="detailTrailerBtn" data-embed="<?php echo htmlspecialchars($trailerEmbed); ?>" aria-label="Watch the trailer for <?php echo htmlspecialchars($title); ?>">
                <span class="detail-trailer-icon"><i class="ph-fill ph-play"></i></span>
                <span class="detail-trailer-label">Watch trailer</span>
            </button>
            <?php endif; ?>
        </div>
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

            <p class="meta-desc" id="metaDesc"><?php echo htmlspecialchars($overview); ?></p>
            <button type="button" class="meta-more" id="metaMore" hidden>More</button>

            <div class="meta-actions">
                <a href="watch?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn-play-now">
                    <i class="ph-fill ph-play" style="font-size: 1.25rem;"></i> Play Now
                </a>

                <div class="meta-tiles">
                    <a href="#" class="meta-tile watchlist-btn" data-id="<?php echo $mediaId; ?>" data-type="<?php echo $mediaType; ?>" title="<?php echo $isInWatchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>">
                        <i class="<?php echo $isInWatchlist ? 'ph ph-check text-success' : 'ph ph-plus'; ?>"></i>
                        <span>My List</span>
                    </a>
                    <?php $similarPrompt = ($mediaType === 'tv' ? 'Find 5 TV shows that are extremely similar to ' : 'Find 5 movies that are extremely similar to ') . $title; ?>
                    <button type="button" class="meta-tile meta-tile-ai" onclick="triggerZenAI(<?php echo htmlspecialchars(json_encode($similarPrompt), ENT_QUOTES); ?>)" title="Ask ZEN AI for similar titles">
                        <i class="ph-fill ph-sparkle"></i>
                        <span>Similar</span>
                    </button>
                    <?php if (!$isUpcoming && !empty($downloadLinks)): ?>
                    <!-- Only for titles the site has files for; others just don't show it. -->
                    <button type="button" class="meta-tile" data-bs-target="#downloadModal" title="Download">
                        <i class="ph ph-download-simple"></i>
                        <span>Download</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="meta-tile" onclick="navigator.share({title: document.title, url: window.location.href})" title="Share">
                        <i class="ph ph-share-network"></i>
                        <span>Share</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Details on phones. Wider screens show these in the right-hand panel,
             which has no room on a phone and was simply hidden there. -->
        <section class="mobile-details">
            <div class="md-facts">
                <div class="md-fact"><span>Status</span><strong><?php echo htmlspecialchars($details['status'] ?? 'Released'); ?></strong></div>
                <div class="md-fact"><span>Aired</span><strong><?php echo htmlspecialchars($year); ?></strong></div>
                <div class="md-fact"><span>Duration</span><strong><?php echo htmlspecialchars($duration); ?></strong></div>
                <div class="md-fact"><span>Language</span><strong><?php echo htmlspecialchars($originalLang); ?></strong></div>
                <div class="md-fact"><span>Views</span><strong><?php echo number_format($viewCount); ?></strong></div>
            </div>

            <?php if (!empty($castList)): ?>
            <h4 class="section-heading md-heading">Cast</h4>
            <div class="md-people">
                <?php foreach (array_slice($castList, 0, 12) as $actor): ?>
                <a href="person-detail?id=<?php echo $actor['id']; ?>" class="md-person">
                    <img src="<?php echo !empty($actor['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$actor['profile_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" decoding="async" alt="">
                    <strong><?php echo htmlspecialchars($actor['name']); ?></strong>
                    <span><?php echo htmlspecialchars($actor['character'] ?? ''); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($crewList)): ?>
            <h4 class="section-heading md-heading">Crew</h4>
            <div class="md-people">
                <?php foreach ($crewList as $crew): ?>
                <a href="person-detail?id=<?php echo $crew['id']; ?>" class="md-person">
                    <img src="<?php echo !empty($crew['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$crew['profile_path'] : 'assets/images/user/userblank.jpg'; ?>" loading="lazy" decoding="async" alt="">
                    <strong><?php echo htmlspecialchars($crew['name']); ?></strong>
                    <span><?php echo htmlspecialchars($crew['job'] ?? 'Crew'); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <!-- Recommended Section -->
        <?php if (!empty($relatedList)): ?>
        <div class="recommended-section mx-0 px-0 mt-4 mb-4">
            <h4 class="section-heading mb-3">More like this</h4>
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
        <section class="reviews-section" id="reviews">
            <div class="reviews-head">
                <div class="reviews-title">
                    <h4 class="section-heading">Reviews <span class="reviews-count" id="reviewsCount"><?php echo count($movieReviews); ?></span></h4>
                    <span class="reviews-avg" id="reviewsAvg"<?php echo $reviewAverage ? '' : ' hidden'; ?>><span class="reviews-avg-star">★</span> <strong><?php echo $reviewAverage ?? ''; ?></strong> average</span>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" class="reviews-write" id="reviewWriteBtn" data-bs-toggle="offcanvas" data-bs-target="#offcanvasReview"><i class="ph ph-pencil-simple-line"></i> <span><?php echo $myReview ? 'Edit your review' : 'Write a review'; ?></span></button>
                <?php else: ?>
                    <a href="/login?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="reviews-write"><i class="ph ph-sign-in"></i> <span>Log in to review</span></a>
                <?php endif; ?>
            </div>

            <div class="comments-list">
                <?php if (!empty($movieReviews)): ?>
                    <?php foreach ($movieReviews as $review):
                        $rAvatar = !empty($review['avatar_url']) ? $review['avatar_url'] : '/assets/images/user/user.jpg';
                        if (!preg_match('~^(https?:)?/~', $rAvatar)) {
                            $rAvatar = '/' . $rAvatar;
                        }
                        $isMine = isset($_SESSION['user_id']) && (int) $review['user_id'] === (int) $_SESSION['user_id'];
                        $stars = max(0, min(5, (int) $review['rating']));
                    ?>
                    <div class="review-card" data-user="<?php echo (int) $review['user_id']; ?>">
                        <div class="review-top">
                            <img src="<?php echo htmlspecialchars($rAvatar); ?>" alt="" class="review-avatar">
                            <div class="review-who">
                                <h6><?php echo htmlspecialchars($review['username']); ?><?php if ($isMine): ?><span class="review-you">You</span><?php endif; ?></h6>
                                <small><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <span class="review-stars" role="img" aria-label="<?php echo $stars; ?> out of 5 stars"><span class="on"><?php echo str_repeat('★', $stars); ?></span><span class="off"><?php echo str_repeat('★', 5 - $stars); ?></span></span>
                        </div>
                        <p class="review-text"><?php echo htmlspecialchars($review['review_text']); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="reviews-empty">
                        <div class="reviews-empty-icon"><i class="ph ph-chat-centered-text"></i></div>
                        <p class="reviews-empty-title">No reviews yet</p>
                        <p class="reviews-empty-text">Watched it? Be the first to share what you thought.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>        </section>    </main>

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
            <h3><i class="ph ph-download-simple" style="color:#4cd137;margin-right:8px;"></i> Download</h3>
            <button class="dl-modal-close" data-bs-dismiss="modal" id="dlModalClose"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="dl-modal-body">
            <p style="color:#ccc;">Downloads for <strong style="color:#fff;"><?php echo htmlspecialchars($title); ?></strong></p>

            <?php if(!empty($downloadLinks)): ?>
                <div class="d-flex flex-column gap-2" style="max-height: 400px; overflow-y:auto; overflow-x:hidden;">
                    <?php foreach ($downloadLinks as $link):
                        // Through /download, which records the download and sends the file.
                        $dlQuery = ['id' => $mediaId, 'type' => $mediaType, 'quality' => $link['quality']];
                        $isEpisode = $mediaType === 'tv' && !empty($link['season']);
                        if ($isEpisode) {
                            $dlQuery['season'] = $link['season'];
                            $dlQuery['episode'] = $link['episode'];
                        }
                        $dlLabel = $isEpisode ? 'Season ' . (int) $link['season'] . ', episode ' . (int) $link['episode'] : 'Full ' . ($mediaType === 'tv' ? 'series' : 'movie');
                    ?>
                        <a href="/download?<?php echo htmlspecialchars(http_build_query($dlQuery)); ?>" class="dl-quality-item" target="_blank" rel="noopener">
                            <div class="dl-quality-info">
                                <div class="dl-quality-badge">
                                    <span class="badge-res"><?php echo htmlspecialchars($link['quality']); ?></span>
                                    <?php if (!empty($link['language'])): ?><span class="badge-format"><?php echo htmlspecialchars($link['language']); ?></span><?php endif; ?>
                                </div>
                                <span class="dl-quality-label"><?php echo htmlspecialchars($dlLabel); ?><?php echo !empty($link['file_size']) ? ' · ' . htmlspecialchars($link['file_size']) : ''; ?></span>
                                <?php if (!empty($link['license_label'])): ?><span class="dl-quality-meta"><?php echo htmlspecialchars($link['license_label']); ?></span><?php endif; ?>
                            </div>
                            <div class="dl-quality-icon"><i class="ph ph-download-simple"></i></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-4">
                    <i class="fa-solid fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                    <p style="color:#888;">No download links are available for this title yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- REVIEW PANEL -->
<div class="offcanvas offcanvas-end review-sheet" tabindex="-1" id="offcanvasReview" aria-labelledby="offcanvasReviewLabel">
  <div class="offcanvas-header review-sheet-head">
    <div class="review-sheet-title">
      <img src="<?php echo htmlspecialchars(!empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w185' . $details['poster_path'] : $posterPlaceholder); ?>" alt="" class="review-sheet-poster">
      <div class="review-sheet-heading">
        <h5 class="offcanvas-title" id="offcanvasReviewLabel"><?php echo $myReview ? 'Edit your review' : 'Write a review'; ?></h5>
        <p class="review-sheet-sub"><?php echo htmlspecialchars($title); ?><?php echo $year !== 'N/A' ? ' · ' . htmlspecialchars($year) : ''; ?></p>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form id="addReviewForm" novalidate>
      <input type="hidden" name="media_id" value="<?php echo (int) $mediaId; ?>">
      <input type="hidden" name="media_type" value="<?php echo htmlspecialchars($mediaType); ?>">

      <fieldset class="review-field">
        <legend class="review-label">Your rating</legend>
        <div class="review-stars-input" id="reviewStars">
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <input type="radio" id="rate-<?php echo $s; ?>" name="rating" value="<?php echo $s; ?>"<?php echo ($myReview && (int) $myReview['rating'] === $s) ? ' checked' : ''; ?>>
          <label for="rate-<?php echo $s; ?>" aria-label="<?php echo $s; ?> star<?php echo $s > 1 ? 's' : ''; ?>">★</label>
          <?php endfor; ?>
        </div>
        <p class="review-rating-word" id="reviewRatingWord" aria-live="polite">Tap a star to rate</p>
        <p class="review-error" id="reviewRatingError" hidden>Choose a rating from 1 to 5 stars.</p>
      </fieldset>

      <div class="review-field">
        <label class="review-label" for="review_text">Your review</label>
        <textarea id="review_text" name="review_text" class="review-textarea" rows="6" maxlength="2000" placeholder="What did you like or dislike? Please keep it spoiler-free."><?php echo $myReview ? htmlspecialchars($myReview['review_text']) : ''; ?></textarea>
        <div class="review-textarea-foot">
          <p class="review-error" id="reviewTextError" hidden>Write a few words about it.</p>
          <span class="review-count" id="reviewCount">0 / 2000</span>
        </div>
      </div>

      <p class="review-error review-error-box" id="reviewFormError" role="alert" hidden></p>

      <button type="submit" class="review-submit" id="reviewSubmit">
        <span class="review-submit-text"><?php echo $myReview ? 'Update review' : 'Post review'; ?></span>
      </button>
    </form>
  </div>
</div>

<style>
/* ---- Review panel ------------------------------------------------------------ */
.review-sheet.offcanvas { width: min(440px, 100vw) !important; background: #111118 !important; border-left: 1px solid rgba(255, 255, 255, 0.08) !important; color: #fff; }
.review-sheet .review-sheet-head { gap: 12px; padding: 16px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
.review-sheet-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.review-sheet-poster { flex-shrink: 0; width: 44px; height: 66px; border-radius: 8px; object-fit: cover; background: #1c1c26; }
.review-sheet-heading { min-width: 0; }
.review-sheet-head .offcanvas-title { margin: 0; font-size: 1.1rem; font-weight: 700; }
.review-sheet-sub { margin: 2px 0 0; overflow: hidden; color: #8f8f9b; font-size: 0.85rem; white-space: nowrap; text-overflow: ellipsis; }
.review-sheet .offcanvas-body { padding: 20px; }

.review-field { min-width: 0; margin: 0 0 22px; padding: 0; border: 0; }
.review-label { display: block; float: none; width: auto; margin-bottom: 10px; color: #c9c9d3; font-size: 0.85rem; font-weight: 600; }

.review-stars-input { position: relative; display: flex; justify-content: center; gap: 4px; padding: 12px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.06); background: rgba(255, 255, 255, 0.04); }
.review-stars-input.has-error { border-color: rgba(255, 90, 90, 0.5); }
.review-stars-input input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.review-stars-input label { padding: 2px 6px; color: #3b3b48; font-size: 2.3rem; line-height: 1; cursor: pointer; transition: color 0.15s, transform 0.15s; user-select: none; }
.review-stars-input label.is-on { color: #f5c518; }
.review-stars-input label:active { transform: scale(0.88); }
.review-stars-input input:focus-visible + label { outline: 2px solid #f5c518; outline-offset: 2px; border-radius: 6px; }
.review-rating-word { min-height: 1.3em; margin: 8px 0 0; color: #8f8f9b; font-size: 0.85rem; text-align: center; }
.review-rating-word.is-set { color: #f5c518; font-weight: 600; }

.review-textarea { display: block; width: 100%; min-height: 150px; padding: 14px; resize: vertical; border-radius: 14px; border: 1px solid #2a2a36; background: #0c0c12; color: #fff; font-size: 0.95rem; line-height: 1.5; }
.review-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.review-textarea::placeholder { color: #666674; }
.review-textarea.has-error { border-color: rgba(255, 90, 90, 0.6); }
.review-textarea-foot { display: flex; justify-content: space-between; gap: 12px; margin-top: 6px; }
.review-count { margin-left: auto; color: #6f6f7b; font-size: 0.75rem; font-variant-numeric: tabular-nums; white-space: nowrap; }

.review-error { margin: 6px 0 0; color: #ff7a7a; font-size: 0.8rem; }
.review-error-box { margin: 0 0 14px; padding: 10px 12px; border-radius: 10px; background: rgba(255, 90, 90, 0.1); }

.review-submit { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 50px; border: 0; border-radius: 12px; background: var(--primary); color: #fff; font-size: 1rem; font-weight: 700; transition: filter 0.2s; }
.review-submit:hover { filter: brightness(1.08); }
.review-submit:disabled { opacity: 0.7; cursor: default; }
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

    // --- Trailer: swap the button for the YouTube player when pressed ---
    (function () {
        var button = document.getElementById('detailTrailerBtn');
        if (!button) return;
        button.addEventListener('click', function () {
            var frame = document.createElement('iframe');
            frame.src = button.dataset.embed.replace('autoplay=0', 'autoplay=1');
            frame.title = 'Trailer';
            frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            frame.allowFullscreen = true;
            var shade = button.parentElement.querySelector('.detail-hero-shade');
            if (shade) shade.remove();
            button.replaceWith(frame);
        });
    })();

    // --- "More" for long descriptions ---
    (function () {
        var desc = document.getElementById('metaDesc');
        var more = document.getElementById('metaMore');
        if (!desc || !more) return;
        if (desc.scrollHeight > desc.clientHeight + 2) more.hidden = false;
        more.addEventListener('click', function () {
            var open = desc.classList.toggle('is-open');
            more.textContent = open ? 'Less' : 'More';
        });
    })();

    // --- FETCH AI HOOK ---
    document.addEventListener("DOMContentLoaded", function() {
        const mediaId = <?php echo json_encode($mediaId); ?>;
        const mediaType = <?php echo json_encode($mediaType); ?>;
        const container = document.getElementById('ai-hook-container');
        const textDiv = document.getElementById('ai-hook-text');

        if (mediaId && <?php echo json_encode(!empty($_SESSION['user_id'])); ?>) {
            // The pitch needs a signed-in user; for guests the request only returned 401.
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

    // --- REVIEWS: star rating, checks, and saving without reloading the page ---
    (function () {
        const form = document.getElementById('addReviewForm');
        if (!form) return;

        const panel = document.getElementById('offcanvasReview');
        const starBox = document.getElementById('reviewStars');
        const labels = Array.from(starBox.querySelectorAll('label'));
        const radios = Array.from(starBox.querySelectorAll('input'));
        const word = document.getElementById('reviewRatingWord');
        const text = document.getElementById('review_text');
        const counter = document.getElementById('reviewCount');
        const ratingError = document.getElementById('reviewRatingError');
        const textError = document.getElementById('reviewTextError');
        const formError = document.getElementById('reviewFormError');
        const submit = document.getElementById('reviewSubmit');
        const submitText = submit.querySelector('.review-submit-text');
        const WORDS = ['Tap a star to rate', 'Awful', 'Poor', 'Okay', 'Good', 'Loved it'];

        const selected = () => {
            const checked = radios.find(r => r.checked);
            return checked ? Number(checked.value) : 0;
        };
        const paint = value => {
            labels.forEach((label, i) => label.classList.toggle('is-on', i < value));
            word.textContent = WORDS[value] || WORDS[0];
            word.classList.toggle('is-set', value > 0);
        };
        const count = () => { counter.textContent = text.value.length + ' / ' + text.maxLength; };

        labels.forEach((label, i) => {
            label.addEventListener('mouseenter', () => paint(i + 1));
            label.addEventListener('mouseleave', () => paint(selected()));
        });
        radios.forEach(radio => radio.addEventListener('change', () => {
            paint(selected());
            ratingError.hidden = true;
            starBox.classList.remove('has-error');
        }));
        text.addEventListener('input', () => {
            count();
            if (text.value.trim()) {
                textError.hidden = true;
                text.classList.remove('has-error');
            }
        });
        paint(selected());
        count();

        panel.addEventListener('show.bs.offcanvas', () => document.body.classList.add('zen-sheet-open'));
        panel.addEventListener('hidden.bs.offcanvas', () => document.body.classList.remove('zen-sheet-open'));

        // A review card built with DOM methods, so names and review text are
        // never interpreted as HTML.
        function reviewCard(review) {
            const make = (tag, className, content) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (content !== undefined) node.textContent = content;
                return node;
            };
            const card = make('div', 'review-card is-new');
            card.dataset.user = review.user_id;

            const top = make('div', 'review-top');
            const avatar = make('img', 'review-avatar');
            avatar.src = review.avatar_url;
            avatar.alt = '';
            const who = make('div', 'review-who');
            const name = make('h6', null, review.username);
            name.appendChild(make('span', 'review-you', 'You'));
            who.append(name, make('small', null, review.date_label));
            const stars = make('span', 'review-stars');
            stars.setAttribute('role', 'img');
            stars.setAttribute('aria-label', review.rating + ' out of 5 stars');
            stars.append(make('span', 'on', '★'.repeat(review.rating)), make('span', 'off', '★'.repeat(5 - review.rating)));
            top.append(avatar, who, stars);

            card.append(top, make('p', 'review-text', review.review_text));
            return card;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            formError.hidden = true;

            const rating = selected();
            let valid = true;
            if (!rating) {
                ratingError.hidden = false;
                starBox.classList.add('has-error');
                valid = false;
            }
            if (!text.value.trim()) {
                textError.hidden = false;
                text.classList.add('has-error');
                valid = false;
            }
            if (!valid) return;

            const idleLabel = submitText.textContent;
            submit.disabled = true;
            submitText.textContent = 'Saving…';

            try {
                const response = await fetch('/process-reviews', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json().catch(() => ({}));
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Your review could not be saved. Please try again.');
                }

                // Show it straight away: replace this user's earlier review, or add it on top.
                const list = document.querySelector('.comments-list');
                const empty = list.querySelector('.reviews-empty');
                if (empty) empty.remove();
                const card = reviewCard(data.review);
                const earlier = list.querySelector('.review-card[data-user="' + data.review.user_id + '"]');
                if (earlier) earlier.replaceWith(card); else list.prepend(card);

                document.getElementById('reviewsCount').textContent = data.count;
                const average = document.getElementById('reviewsAvg');
                if (average && data.average) {
                    average.hidden = false;
                    average.querySelector('strong').textContent = data.average;
                }

                // From now on the panel edits this review.
                document.getElementById('offcanvasReviewLabel').textContent = 'Edit your review';
                const writeButton = document.querySelector('#reviewWriteBtn span');
                if (writeButton) writeButton.textContent = 'Edit your review';
                submitText.textContent = 'Update review';

                bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (window.Toastify) {
                    Toastify({ text: data.action === 'updated' ? 'Review updated' : 'Review posted', style: { background: '#00b09b' } }).showToast();
                }
            } catch (error) {
                submitText.textContent = idleLabel;
                formError.textContent = error.message;
                formError.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });
    })();
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
<?php include __DIR__ . '/includes/kids-mode.php'; ?>
<?php include __DIR__ . '/includes/mobile-footer.php'; ?>
</body>
</html>

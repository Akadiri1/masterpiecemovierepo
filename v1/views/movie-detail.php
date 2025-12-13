
<?php
session_start();
include APP_PATH . '/views/includes/header.php';

// ==========================================
// 0. CONFIG: FALLBACK IMAGES
// ==========================================
$posterPlaceholder = 'assets/images/media/robert.jpg'; 
$backdropPlaceholder = 'assets/images/media/robert.jpg';
$castPlaceholder = 'assets/images/media/robert.jpg';

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

// Trailer
$trailerUrl = '';
$trailerKey = '';
if (!empty($details['videos']['results'])) {
    foreach ($details['videos']['results'] as $video) {
        if ($video['site'] === 'YouTube' && $video['type'] === 'Trailer') {
            $trailerKey = $video['key'];
            $trailerUrl = "https://www.youtube.com/watch?v={$video['key']}";
            break;
        }
    }
}

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

<!-- Banner Start -->
<div class="position-relative" style="height: 100vh; overflow: hidden; background-color: #000;">
    
  <!-- 1. Full Screen Video/Image Background -->
  <div class="iq-main-slider site-video" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
    <?php if ($trailerKey): ?>
    <!-- Dynamic Video Player -->
    <video id="my-video" poster="<?php echo $backdrop; ?>"
      class="my-video video-js vjs-default-skin" 
      loop autoplay muted playsinline preload="auto" 
      style="width: 100%; height: 100%; object-fit: cover;"
      data-setup='{
          "techOrder": ["youtube"],
          "sources": [{
              "type": "video/youtube",
              "src": "<?php echo $trailerUrl; ?>"
          }],
          "youtube": {         
              "modestbranding": 1,
              "rel": 0,
              "showinfo": 0,
              "autoplay": 1,
              "controls": 0,
              "mute": 1
          },
          "fullscreen": false,
          "controls": false
      }'>
    </video>
    <?php else: ?>
    <!-- Fallback Image -->
    <div style="width: 100%; height: 100%; background-image: url('<?php echo $backdrop; ?>'); background-size: cover; background-position: center;"></div>
    <?php endif; ?>
    
    <!-- Gradient Overlay (To make text readable) -->
    <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, #141414 10%, rgba(20,20,20,0.6) 50%, rgba(0,0,0,0) 100%); pointer-events: none;"></div>
  </div>


  <!-- 2. Movie Details Overlay (Positioned at Bottom) -->
  <div class="movie-detail-part" style="position: absolute; bottom: 0; left: 0; width: 100%; z-index: 10; padding-bottom: 3rem;">
    <div class="container-fluid">
        <div class="trending-info pt-0 pb-0">
          <div class="details-parts">
            
            <!-- Genres -->
            <ul class="p-0 mb-3 list-inline d-flex flex-wrap movie-tag">
              <?php if (!empty($data['genres'])): ?>
                <?php foreach (array_slice($data['genres'], 0, 3) as $genre): ?>
                  <li class="trending-list">
                    <a class="text-uppercase fw-bold px-3 py-1 small text-white" 
                       href="/view-all?genre_id=<?php echo $genre['id']; ?>"
                       style="background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); border-radius: 20px;">
                      <?php echo htmlspecialchars($genre['name']); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>

            <!-- Title -->
            <div class="d-block d-lg-flex align-items-center">
              <h3 class="trending-text fw-bold texture-text text-uppercase my-0 fadeInLeft animated d-inline-block display-2"
                  style="text-shadow: 0 4px 15px rgba(0,0,0,0.9);">
                <?php echo htmlspecialchars($title); ?>
              </h3>
            </div>

            <!-- Description -->
            <div class="movie-description mt-3 mb-4 col-lg-6" id="readmore-wrapper">
              <p class="line-count-3 RightAnimate-two mb-0 text-white opacity-75" style="font-size: 1.1rem;">
                <?php echo htmlspecialchars($overview); ?>
              </p>
              <div class="iq-blog-meta-cat-tag iq-blogtag readmore-tags mt-2">
                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#viewMoreDataModal" class="position-relative text-primary fw-bold">Read More</a>
              </div>
            </div>
            
            <!-- Metadata -->
            <ul class="list-inline mb-4 p-0 d-flex align-items-center flex-wrap gap-4 movie-metalist text-white">
              <!-- Year -->
              <li>
                <span class="d-flex align-items-center gap-1">
                  <span class="fw-bold fs-5"><?php echo $year; ?></span>
                </span>
              </li>
              
              <!-- Duration -->
              <li>
                <span class="d-flex align-items-center gap-1">
                  <span class="d-flex align-items-center justify-content-center"><i class="ph ph-clock text-primary"></i></span>
                  <span class="fw-medium"><?php echo $duration; ?></span> 
                </span>
              </li>
              
              <!-- Views -->
              <li>
                <div class="d-flex align-items-center gap-1">
                  <i class="ph ph-eye text-primary"></i>
                  <span class="fw-medium"><?php echo number_format($viewCount); ?> views</span>
                </div>
              </li>
              
              <!-- Rating -->
              <li>
                <span class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-warning fs-5"><?php echo $rating; ?></span>
                    <span class="imdb-logo">
                      <img src="assets/images/pages/imdb-logo.svg" loading="lazy" alt="imdb" class="img-fluid" style="width: 40px;">
                    </span>
                </span>
              </li>
              
              <!-- Age Rating -->
              <li>
                <span class="badge bg-transparent border border-white px-2 py-1"><?php echo $ageRating; ?></span>
              </li>
            </ul>
            
            <!-- Language -->
            <div class="video-language d-flex align-items-center gap-1 mt-2 text-white-50">
              <i class="ph ph-translate"></i>
              <ul class="list-inline m-0 p-0 d-inline-flex align-items-center gap-3 flex-wrap">
                <li>
                  <small class="text-capitalize text-white"><?php echo $originalLang; ?></small>
                </li>
              </ul>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex align-items-center flex-wrap gap-3 gap-md-4 my-5">
              <div class="iq-play-button iq-button">
                <a href="/watch?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" 
                   class="btn-primary rounded-pill px-5 py-3 d-flex align-items-center gap-2 lh-1 fw-bold shadow-lg"
                    >
                    <span><i class="ph-fill ph-play fs-5"></i></span>
                    <span>Start Watching</span>
                </a>
              </div>
            
             <div class="watchlist-button-wrapper">
                <!-- AJAX Watchlist Button -->
                <a href="#" class="btn btn-dark border-secondary rounded-pill px-4 py-3 watchlist-btn" 
                   data-id="<?php echo $mediaId; ?>" 
                   data-type="<?php echo $mediaType; ?>"
                   data-bs-toggle="tooltip" 
                   title="<?php echo $isInWatchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <!-- Icon switches based on state -->
                        <i class="ph <?php echo $isInWatchlist ? 'ph-check text-success' : 'ph-plus'; ?> fs-5 icon-status"></i>
                        <span class="fw-semibold text-status">
                            <?php echo $isInWatchlist ? 'Saved' : 'Watch List'; ?>
                        </span>
                    </span>
                </a>
              </div>
              
              <div class="d-flex align-items-center gap-3 flex-wrap ms-md-2">
                <button type="button" class="action-btn btn btn-sm rounded-circle btn-dark border-secondary" data-bs-toggle="modal" data-bs-target="#likeModal" id="like-toggle">
                  <span id="like-movies">
                    <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" title="Like">
                      <i class="ph ph-heart heart-icon text-danger"></i>
                    </span>
                  </span>
                </button>
            
                <button type="button" class="action-btn btn btn-sm rounded-circle btn-dark border-secondary" data-bs-toggle="modal" data-bs-target="#shareModal">
                  <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" title="Share">
                    <i class="ph ph-share-network"></i>
                  </span>
                </button>
            
                <button type="button" class="btn btn-sm rounded-circle btn-dark border-secondary action-btn" data-bs-toggle="modal" data-bs-target="#playlistModal">
                  <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" title="Playlist">
                    <i class="ph ph-playlist"></i>
                  </span>
                </button>
            
                <!-- Add data-id and data-title to pass info to the modal -->
<button type="button" 
        class="btn btn-sm rounded-circle btn-dark border-secondary action-btn" 
        data-bs-toggle="modal" 
        data-bs-target="#downloadModal">
   <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" title="Download">
      <i class="fa-solid fa-download text-primary"></i>
   </span>
</button>
              </div>
            </div>
          </div>
        </div>
    </div>
  </div>
</div>

<!-- DOWNLOAD MODAL STYLES -->
<style>
    /* Download Modal Styles */
.modal-content {
    background-color: #1a1a1a; /* Dark background */
    border: 1px solid #333;
    color: #fff;
}
.modal-header { border-bottom: 1px solid #333; }
.btn-close-white { filter: invert(1); }

/* Download Row Item */
.dl-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #252525;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    text-decoration: none;
}
.dl-item:hover {
    background: #333;
    border-color: #555;
    transform: translateY(-2px);
}

/* Left side: Quality & Info */
.dl-info h4 { margin: 0; font-size: 16px; font-weight: 700; color: #fff; }
.dl-info span { font-size: 12px; color: #aaa; margin-right: 10px; }
.badge-quality { 
    background: #e50914; color: white; padding: 2px 6px; 
    border-radius: 4px; font-size: 11px; font-weight: bold; 
}

/* Right side: Icon */
.dl-action {
    background: rgba(255,255,255,0.1);
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #4cd137; /* Green for download */
}
.dl-item:hover .dl-action { background: #4cd137; color: #000; }
</style>

<!-- MODAL HTML -->
<div class="modal fade" id="downloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Download <span class="text-primary"><?php echo htmlspecialchars($movieTitle); ?></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                
                <?php if (empty($downloadLinks)): ?>
                    <div class="text-center py-4 text-white-50">
                        <i class="fa-solid fa-triangle-exclamation fa-2x mb-3"></i>
                        <p>No download links available for this title yet.</p>
                    </div>

                <?php else: ?>
                    <p class="text-white-50 mb-3 small">Select a file quality (Magnet/Torrent):</p>
                    
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($downloadLinks as $link): 
                            $isMagnet = (strpos($link['download_url'], 'magnet:') !== false);
                            $icon = $isMagnet ? 'fa-magnet' : 'fa-download';
                            $typeLabel = $isMagnet ? 'Magnet' : 'Direct';
                        ?>
                            <a href="<?php echo $link['download_url']; ?>" class="dl-item">
                                <div class="dl-info">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge-quality"><?php echo htmlspecialchars($link['quality']); ?></span>
                                        <h4><?php echo htmlspecialchars($link['language']); ?></h4>
                                    </div>
                                    <span><?php echo htmlspecialchars($link['file_size']); ?></span>
                                    <span>• <?php echo $typeLabel; ?></span>
                                </div>
                                
                                <div class="dl-action">
                                    <i class="fa-solid <?php echo $icon; ?>"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<div class="container-fluid">
  <div class="overflow-hidden">
    
    <!-- Recommended Block -->
    <?php if(!empty($relatedList)): ?>
    <div class="recommended-block section-padding-top">
      <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Related</h4>
      </div>
      <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3" data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
          <ul class="p-0 swiper-wrapper m-0  list-inline">
            
            <?php foreach($relatedList as $item): 
                 $iPoster = !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w342'.$item['poster_path'] : 'assets/images/media/robert.jpg';
                 $iTitle = $item['title'] ?? $item['name'];
                 $iId = $item['id'];
            ?>
            <li class="swiper-slide">
              <div class="iq-card card-hover">
                <div class="block-images position-relative w-100">
                  <div class="img-box w-100">
                    <a href="movie-detail?id=<?php echo $iId; ?>&type=movie" class="position-relative top-0 bottom-0 start-0 end-0">
                      <img src="<?php echo $iPoster; ?>" alt="movie-card" class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                    </a>
                  </div>
                  <div class="card-description with-transition">
                    <div class="cart-content">
                      <div class="content-left">
                        <h5 class="iq-title text-capitalize">
                          <a href="movie-detail?id=<?php echo $iId; ?>"><?php echo htmlspecialchars($iTitle); ?></a>
                        </h5>
                      </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                       <div class="iq-play-button iq-button">
                        <a href="movie-detail?id=<?php echo $iId; ?>&type=movie" class="btn btn-primary w-100">Play Now</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </li>
            <?php endforeach; ?>

          </ul>
          <div class="swiper-button swiper-button-next d-none d-lg-block"></div>
          <div class="swiper-button swiper-button-prev d-none d-lg-block"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Starring (Cast) -->
    <?php if(!empty($castList)): ?>
    <div class="favourite-person-block">
      <div class="overflow-hidden">
        <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
          <h4 class="main-title text-capitalize mb-0">Starring</h4>
        </div>
        <div class="position-relative swiper swiper-card" data-slide="11" data-laptop="11" data-tab="4" data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
          <ul class="p-0 swiper-wrapper m-0  list-inline personality-card">
              
              <?php foreach($castList as $actor): 
                  $aImg = !empty($actor['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$actor['profile_path'] : 'assets/images/user/user.jpg';
              ?>
                <li class="swiper-slide">
                  <a href="person-detail?id=<?php echo $actor['id']; ?>">
                  <img src="<?php echo $aImg; ?>" alt="personality" class="img-fluid object-cover mb-3 rounded-3 personality-img" style="height:150px; object-fit:cover;">
                  </a>
                  <div class="text-center">
                      <h6 class="mb-0">
                          <a href="person-detail?id=<?php echo $actor['id']; ?>" class="font-size-14 text-decoration-none cast-title text-capitalize"><?php echo htmlspecialchars($actor['name']); ?></a>
                      </h6>
                      <a href="#" class="font-size-12 fw-semibold text-decoration-none text-capitalize text-body"><?php echo htmlspecialchars($actor['character']); ?></a>
                  </div>               
               </li>
               <?php endforeach; ?>
               
          </ul>
          <div class="swiper-button swiper-button-next d-none d-lg-block"></div>
          <div class="swiper-button swiper-button-prev d-none d-lg-block"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Crew -->
    <?php if(!empty($crewList)): ?>
    <div class="favourite-person-block">
      <div class="overflow-hidden">
        <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
          <h4 class="main-title text-capitalize mb-0">Crew</h4>
        </div>
        <div class="position-relative swiper swiper-card" data-slide="11" data-laptop="11" data-tab="4" data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
          <ul class="p-0 swiper-wrapper m-0  list-inline personality-card">
             <?php foreach($crewList as $crew): 
                  $cImg = !empty($crew['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$crew['profile_path'] : 'assets/images/user/user.jpg';
              ?>
            <li class="swiper-slide">
              <a href="person-detail?id=<?php echo $crew['id']; ?>">
              <img src="<?php echo $cImg; ?>" alt="personality" class="img-fluid object-cover mb-3 rounded-3 personality-img" style="height:150px; object-fit:cover;">
              </a>
              <div class="text-center">
                  <h6 class="mb-0">
                      <a href="person-detail?id=<?php echo $crew['id']; ?>" class="font-size-14 text-decoration-none cast-title text-capitalize"><?php echo htmlspecialchars($crew['name']); ?></a>
                  </h6>
                  <span class="font-size-12 fw-semibold text-decoration-none text-capitalize text-body"><?php echo htmlspecialchars($crew['job']); ?></span>
              </div>            
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="swiper-button swiper-button-next d-none d-lg-block"></div>
          <div class="swiper-button swiper-button-prev d-none d-lg-block"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Start -->
    <?php if(!empty($upcomingList)): ?>
    <div class="upcomimg-block">
      <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Upcoming</h4>
      </div>
      <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3" data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
          <ul class="p-0 swiper-wrapper m-0  list-inline">
            <?php foreach($upcomingList as $up): 
                 $uPoster = !empty($up['poster_path']) ? 'https://image.tmdb.org/t/p/w342'.$up['poster_path'] : 'assets/images/media/robert.jpg';
            ?>
            <li class="swiper-slide">
              <div class="iq-card card-hover">
                <div class="block-images position-relative w-100">
                  <div class="img-box w-100">
                    <a href="movie-detail?id=<?php echo $up['id']; ?>&type=movie" class="position-relative top-0 bottom-0 start-0 end-0">
                      <img src="<?php echo $uPoster; ?>" alt="movie-card" class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                    </a>
                  </div>
                  <div class="card-description with-transition">
                    <div class="cart-content">
                      <div class="content-left">
                        <h5 class="iq-title text-capitalize">
                          <a href="movie-detail?id=<?php echo $up['id']; ?>&type=movie"><?php echo htmlspecialchars($up['title']); ?></a>
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <small class="font-size-12 text-warning"><?php echo $up['release_date']; ?></small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="swiper-button swiper-button-next"></div>
          <div class="swiper-button swiper-button-prev"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <!-- Upcoming End -->

    <!-- Reviews Section -->
     <div class="section-padding-bottom">
      <div class="rate-review-details">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
              <h5 class="main-title text-capitalize m-0 fw-medium">Review (<?php echo count($movieReviews); ?>)</h5>
              <div class="d-flex align-items-center gap-3">
                  <?php if(isset($_SESSION['user_id'])): ?>
                      <a id="openReviewButton" class="btn btn-link p-0 custom-fs-14 openReviewButton" data-bs-toggle="offcanvas" href="#offcanvasReview" role="button" aria-controls="offcanvasReview">Add Review</a>
                  <?php else: ?>
                      <a href="/login" class="btn btn-link p-0 custom-fs-14">Login to Review</a>
                  <?php endif; ?>
              </div>
          </div>
          <div class="comments-section">
              <?php if(!empty($movieReviews)): ?>
                  <?php foreach($movieReviews as $review): 
                      $rAvatar = !empty($review['avatar_url']) ? $review['avatar_url'] : 'assets/images/user/user.jpg';
                  ?>
                  <div class="review-card mb-3">
                      <div class="review-detail rounded-3 p-3 border-bottom">
                          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                              <div class="d-flex align-items-center justify-content-center gap-3">
                                  <img src="<?php echo $rAvatar; ?>" class="img-fluid user-img rounded-circle" alt="user" style="width:50px; height:50px; object-fit:cover;">
                                  <div>
                                      <h6 class="line-count-1 m-0"><?php echo htmlspecialchars($review['username']); ?></h6>
                                      <p class="mb-0 mt-1 small-date-font"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></p>
                                  </div>
                              </div>
                              <div class="star-rating">
                                  <?php for($i=1; $i<=5; $i++): ?>
                                      <i class="ph-fill ph-star <?php echo ($i <= $review['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                  <?php endfor; ?>
                              </div>
                          </div>
                          <p class="mb-0 mt-3 pt-3 border-top fw-medium"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                      </div>
                  </div>
                  <?php endforeach; ?>
              <?php else: ?>
                  <p class="text-white-50">No reviews yet. Be the first to share your thoughts!</p>
              <?php endif; ?>
          </div>
      </div>
      
      <!-- Review Offcanvas (Backend Pointing to accurate location: SELF) -->
      <div class="offcanvas overflow-y-auto widget-shopping-cart-content offcanvas-end offcanvas-sidebar sidebar-container on-rtl end border-left-0" tabindex="-1" id="offcanvasReview" aria-modal="true" role="dialog">
          <div class="offcanvas-header position-relative border-bottom">
              <h5 class="offcanvas-title fw-500" id="offcanvasReviewLabel">Add Review</h5>
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body">
              <!-- Form submits to the same page -->
              <form action="" method="POST">
                  <!-- Hidden inputs to pass data -->
                  <input type="hidden" name="media_id" value="<?php echo $mediaId; ?>">
                  <input type="hidden" name="media_type" value="<?php echo $mediaType; ?>">
                  
                  <?php if(isset($_SESSION['user_id'])): ?>
                  <div class="form-group">
                      <p class="mt-0 text-heading">Logged in as: <?php echo $_SESSION['username'] ?? 'User'; ?></p>
                  </div>
                  <?php endif; ?>
      
                  <div class="form-group mb-4">
                      <label class="form-label mb-3">Your Rating</label>
                      <div class="star-rating d-flex flex-row-reverse justify-content-end gap-2">
                            <input type="radio" id="star5" name="rating" value="5" class="d-none"><label for="star5" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                            <input type="radio" id="star4" name="rating" value="4" class="d-none"><label for="star4" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                            <input type="radio" id="star3" name="rating" value="3" class="d-none"><label for="star3" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                            <input type="radio" id="star2" name="rating" value="2" class="d-none"><label for="star2" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                            <input type="radio" id="star1" name="rating" value="1" class="d-none"><label for="star1" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="form-label flex-grow-1" for="review_text">Your Review</label>
                      <textarea id="review_text" name="review_text" class="form-control review-text-area" rows="8" cols="45" required></textarea>
                  </div>
      
                  <div class="iq-button">
                      <button type="submit" name="submit_review" class="btn btn-primary text-capitalize w-100">
                          <span class="button-text">Submit Review</span>
                      </button>
                  </div>
              </form>
          </div>
      </div>    
    </div>
  </div>
</div>

<!-- View More Modal (Full details) -->
<div class="modal fade view-more-data-modal trending-info" id="viewMoreDataModal" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <h3 class="text-uppercase m-0 texture-text texture-text-modal fw-bold"><?php echo htmlspecialchars($title); ?></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <ul class="list-inline d-flex align-items-center flex-wrap gap-3 mt-4">
                    <li><span class="fw-medium"><?php echo $year; ?></span></li>
                    <li><span class="d-flex align-items-center gap-1"><i class="icon-eye-2"></i> <?php echo number_format($viewCount); ?> views</span></li>
                    <li>
                        <span class="d-flex align-items-center gap-1">
                            <span class="fw-medium"><?php echo $rating; ?></span>
                            <span class="imdb-logo ms-1"><img src="./assets/images/pages/imdb-logo.svg" loading="lazy" class="img-fluid imdb-logo1"></span>
                        </span>
                    </li>
                </ul>

                <div class="d-flex align-items-baseline flex-wrap gap-2 mt-md-1 mt-2">
                    <h6 class="m-0">Genres:</h6>
                    <ul class="p-0 mb-0 list-inline d-flex flex-wrap movie-tag">
                        <?php foreach($genres as $g): ?>
                            <li class="trending-list"><a href="#"><?php echo $g['name']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="mt-4">
                    <div class="d-flex align-items-center gap-1">
                        <i class="icon-translate"></i>
                        <small>Original Language: <?php echo $details['original_language']; ?></small>
                    </div>
                </div>

                <p class="mt-4 mb-0"><?php echo htmlspecialchars($overview); ?></p>

                <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                    <h6 class="m-0">Cast:</h6>
                    <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                         <?php foreach($castList as $c): ?>
                           <li><a href="person-detail?id=<?php echo $c['id']; ?>" class="color-inherit"><?php echo htmlspecialchars($c['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
 
</main>
<style>
/* CSS Fix for Review Stars */
.star-rating input:checked ~ label i { color: #ffc107 !important; }
.star-rating label:hover i, .star-rating label:hover ~ label i { color: #ffc107 !important; }
</style>
<?php include ('includes/footer.php');?>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Select the form inside the Offcanvas
    const reviewForm = document.querySelector('#offcanvasReview form');
    
    if (reviewForm) {
        reviewForm.addEventListener('submit', async function(e) {
            e.preventDefault(); // Stop page reload

            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.button-text');
            const originalText = btnText.innerText;

            // 2. Validate Inputs
            const formData = new FormData(this);
            const rating = formData.get('rating');
            const reviewText = formData.get('review_text').trim();

            if (!rating) {
                Toastify({ 
                    text: "Please select a star rating.", 
                    duration: 3000,
                    style: { background: "#ff5f6d" } 
                }).showToast();
                return;
            }

            if (reviewText.length < 5) {
                Toastify({ 
                    text: "Review must be at least 5 characters.", 
                    duration: 3000,
                    style: { background: "#ff5f6d" } 
                }).showToast();
                return;
            }

            // 3. UI Loading State
            submitBtn.disabled = true;
            btnText.innerText = "Submitting...";

            try {
                // 4. Send Request
                // Point this to your backend handler file (e.g., ajax/add-review.php or current page)
                const response = await fetch('/process-reviews', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    // Success: Show Toast & Close Modal
                    Toastify({ 
                        text: "Review submitted successfully!", 
                        duration: 3000,
                        style: { background: "#00b09b" } 
                    }).showToast();

                    this.reset(); // Clear form
                    
                    // Close the offcanvas sidebar
                    const offcanvasEl = document.getElementById('offcanvasReview');
                    const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (offcanvasInstance) {
                        offcanvasInstance.hide();
                    }

                    // Optional: Reload to show new review
                    setTimeout(() => location.reload(), 1000);

                } else {
                    // Error from server
                    Toastify({ 
                        text: data.message || "Error submitting review.", 
                        duration: 3000,
                        style: { background: "#ff5f6d" } 
                    }).showToast();
                }

            } catch (error) {
                console.error("Review Error:", error);
                Toastify({ 
                    text: "Network error. Please try again.", 
                    duration: 3000,
                    style: { background: "#ff5f6d" } 
                }).showToast();
            } finally {
                // Reset Button
                submitBtn.disabled = false;
                btnText.innerText = originalText;
            }
        });
    }
});
</script>
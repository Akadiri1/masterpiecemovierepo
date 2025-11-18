<?php 
// 1. Get ID & Type from URL
$mediaId = $_GET['id'] ?? null;
$mediaType = $_GET['type'] ?? 'movie'; 

if (!$mediaId) {
    echo "<script>window.location.href = '/';</script>";
    exit;
}

// 2. Fetch Data
$details = fetchTmdbApi("{$mediaType}/{$mediaId}", [
    'append_to_response' => 'credits,videos,release_dates,content_ratings'
]);

if (!$details) {
    echo "<h2>Content not found</h2>";
    return;
}

// 3. Process Variables
$title = $details['title'] ?? $details['name'];
$overview = $details['overview'];
$backdrop = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/original'.$details['backdrop_path'] : 'assets/images/media/placeholder.webp';
$rating = number_format($details['vote_average'] ?? 0, 1);
$year = isset($details['release_date']) ? date('Y', strtotime($details['release_date'])) : (isset($details['first_air_date']) ? date('Y', strtotime($details['first_air_date'])) : '');
$runtimeText = ($mediaType === 'movie') ? floor(($details['runtime']??0)/60).'h : '.($details['runtime']??0)%60 .'mins' : ($details['number_of_seasons']??0).' Seasons';

// 4. Get Trailer
$trailerId = '';
foreach ($details['videos']['results'] as $vid) {
    if ($vid['site'] === 'YouTube' && $vid['type'] === 'Trailer') {
        $trailerId = $vid['key'];
        break;
    }
}
// If no trailer found, use a default placeholder or hide the player
$videoSrc = $trailerId ? "https://www.youtube.com/watch?v={$trailerId}" : "";

// 5. Get Age Rating
$ageRating = 'PG-13';
if (isset($details['release_dates'])) {
    foreach($details['release_dates']['results'] as $r) {
        if ($r['iso_3166_1'] === 'US') {
            $ageRating = $r['release_dates'][0]['certification']; 
            break;
        }
    }
}

// --- FETCH REVIEWS FROM DATABASE ---
$movieReviews = [];
$averageRating = 0;

if (isset($conn)) {
    // Fetch reviews joined with user details (to get name and avatar)
    // We assume your users table is named 'users' and has 'username' and 'avatar_url'
    $sql = "SELECT r.*, u.username, u.avatar_url 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.media_id = ? AND r.media_type = ? 
            ORDER BY r.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mediaId, $mediaType]);
    $movieReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate specific site rating (if needed)
    if (count($movieReviews) > 0) {
        $sum = 0;
        foreach ($movieReviews as $rev) { $sum += $rev['rating']; }
        $averageRating = number_format($sum / count($movieReviews), 1);
    }
}

// ==========================================
// 1. FETCH UPCOMING MOVIES (Strict Future)
// ==========================================
$upcomingList = [];
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Fetch movies releasing after today
$upData = fetchTmdbApi('discover/movie', [
    'region' => 'US',
    'sort_by' => 'popularity.desc',
    'primary_release_date.gte' => $tomorrow,
    'with_release_type' => '2|3',
    'page' => 1
]);

if ($upData && !empty($upData['results'])) {
    $upcomingList = array_slice($upData['results'], 0, 10);
}

// ==========================================
// 2. PREPARE RELATED/SIMILAR LIST
// ==========================================
// We use 'similar' results fetched in the main $data object
$relatedList = $data['similar']['results'] ?? [];
$relatedList = array_slice($relatedList, 0, 10);

include ("includes/header.php");
?>
    <!--bread-crumb-->
    <!--bread-crumb-->

<!-- Banner Start -->
<div class="poition-relative">
  <div class="iq-main-slider site-video position-relative">
    <?php if ($trailerKey): ?>
    <!-- Dynamic Video Player -->
    <video id="my-video" poster="<?php echo $backdrop; ?>"
      class="my-video video-js vjs-big-play-centered w-100" loop autoplay muted preload="auto" data-setup='{
                  "techOrder": ["youtube"],
                  "sources": [{
                      "type": "video/youtube",
                      "src": "<?php echo $trailerUrl; ?>"
                  }],
                  "youtube": {         
                      "modestbranding": 1,
                      "rel": 0,
                      "showinfo": 0,
                      "autoplay": 1
                  },
                  "fullscreen": true
              }'>
    </video>
    <?php else: ?>
    <!-- Fallback Image if no trailer -->
    <div style="width: 100%; height: 100vh; background-image: url('<?php echo $backdrop; ?>'); background-size: cover; background-position: center;"></div>
    <?php endif; ?>
  </div>


  <div class="movie-detail-part position-relative">
    <div class="trending-info pt-0 pb-0">
      <div class="details-parts">
        <!-- Movie Description Start-->
        <ul class="p-0 mb-2 list-inline d-flex flex-wrap movie-tag">
          <?php if (!empty($data['genres'])): ?>
            <?php foreach (array_slice($data['genres'], 0, 3) as $genre): ?>
              <li class="trending-list">
                <a class="" href="/view-all?genre_id=<?php echo $genre['id']; ?>">
                  <?php echo htmlspecialchars($genre['name']); ?>
                </a>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
        <div class="d-block d-lg-flex align-items-center">
          <h3 class="trending-text fw-bold texture-text text-uppercase my-0 fadeInLeft animated d-inline-block"
            data-animation-in="fadeInLeft" data-delay-in="0.6" style="opacity: 1; animation-delay: 0.6s">
            <?php echo htmlspecialchars($title); ?>
          </h3>
        </div>
        <div class="movie-description mt-3 mb-4" id="readmore-wrapper">
          <p class="line-count-3 RightAnimate-two mb-0">
            <?php echo htmlspecialchars($overview); ?>
          </p>
          <div class="iq-blog-meta-cat-tag iq-blogtag readmore-tags">
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#viewMoreDataModal" class="position-relative">Read More</a>
          </div>
        </div>
        
        <ul class="list-inline mb-0 mx-0 p-0 d-flex align-items-center flex-wrap gap-3 movie-metalist">
        
          <!-- Movie Releas data  -->
          <li>
            <span class="d-flex align-items-center gap-1">
              <span class="fw-medium">
                <?php echo $year; ?> </span>
            </span>
          </li>
        
          <!-- Movie Runtime  -->
          <li>
            <span class="d-flex align-items-center gap-1">
              <span class="d-flex align-items-center justify-content-center"><i class="ph ph-clock"></i></span>
              <?php echo $duration; ?> </span>
          </li>
        
          <!-- Movie Views (Static for now) -->
          <li>
            <div class="d-flex align-items-center gap-1">
              <i class="ph ph-eye"></i>
              <span class="">284 views</span>
            </div>
          </li>
        
          <!-- Movie IMDP Rating  -->
          <li>
            <span class="d-flex align-items-center gap-1">
              <span class="fw-medium">
                <span>
                  <?php echo $rating; ?> </span>
                <span class="imdb-logo ms-1">
                  <img src="assets/images/pages/imdb-logo.svg" loading="lazy" decoding="async" alt="imdb logo"
                    class="img-fluid imdb-logo1">
                </span>
              </span>
            </span>
          </li>
        
          <!-- Movie Censor Rating -->
          <li>
            <span class="badge bg-secondary d-flex align-items-center gap-2 fw-bold font-size-12 movie-type-tag">
              <span>
                <?php echo $ageRating; ?> </span>
            </span>
          </li>
        
        </ul>
        
        <div class="video-language d-flex align-items-center gap-1 mt-2">
          <i class="ph ph-translate"></i>
          <ul class="list-inline m-0 p-0 d-inline-flex align-items-center gap-3 flex-wrap">
            <li>
              <small class="text-capitalize"><?php echo $originalLang; ?></small>
            </li>
          </ul>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-3 gap-md-4 my-5">
          <div class="iq-play-button iq-button">
            <!-- Link to the actual player page -->
            <a href="/watch?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn btn-primary w-100 rounded d-flex align-items-center justify-content-center gap-2 lh-1">
                <span><i class="ph-fill ph-play"></i></span>
                <span>Start Watching</span>
            </a>
          </div>
        
          <div class="watchlist-button-wrapper">
        
            <a href="/add-watchlist?id=<?php echo $mediaId; ?>" class="btn btn-secondary border rounded" data-bs-toggle="tooltip"
              data-bs-placement="top" data-bs-original-title="Add to watchlist" data-bs-trigger="focus">
              <span class="d-flex align-items-center justify-content-center gap-2">
                <span class="fw-semibold"><i class="ph ph-plus"></i></span>
                <span class="fw-semibold">Watch List</span>
              </span>
            </a>
          </div>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#likeModal"
              id="like-toggle">
              <span id="like-movies">
                <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Like">
                  <i class="ph ph-heart heart-icon"></i>
                </span>
              </span>
            </button>
        
            <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal"
              data-bs-target="#shareModal">
              <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Share">
                <i class="ph ph-share-network"></i>
              </span>
            </button>
        
            <button type="button" class="btn btn-secondary action-btn border" data-bs-toggle="modal"
              data-bs-target="#playlistModal">
              <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Playlist">
                <i class="ph ph-playlist"></i>
              </span>
            </button>
        
            <button type="button" class="btn btn-secondary action-btn border" data-bs-toggle="modal"
              data-bs-target="#downloadModal">
              <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top"
                data-bs-original-title="Download">
                <i class="ph ph-download-simple"></i>
              </span>
            </button>
          </div>
        </div>
        <!-- Movie Description End -->
        
        <!-- Modals (Kept exactly as provided) -->
        <div class="modal fade view-more-data-modal" id="playlistModal" tabindex="-1" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered share-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Select Playlist</h5>
                        <button type="button" class="btn-close me-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-0">
                        <div class="playlist-modal-content">
                            <!-- Dynamic Playlist Content could go here in future -->
                            <div class="form-check"><input id="26" class="st_manage_playlist form-check-input" type="checkbox"
                                    data-playlist_id="26" data-post_id="32" data-post_type="movie"><label for="26">My Favorites</label>
                            </div>
                             <div class="form-check"><input id="11" class="st_manage_playlist form-check-input" type="checkbox"
                                    data-playlist_id="11" data-post_id="32" data-post_type="movie" checked=""><label
                                    for="11">Watch Later</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4">
                        <button type="button" class="playlist-action-btn btn btn-secondary border" data-bs-toggle="modal"
                            data-bs-target="#creatplaylistModal">
                            Create Playlist </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal fade view-more-data-modal" id="creatplaylistModal" tabindex="-1" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered share-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1">Create Playlist</h5>
                        <button type="button" class="btn-close me-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <form id="st_creat_playlist" action="#" method="post">
                            <input type="hidden" id="st_playlist_post_type" value="movie">
                            <div class="form-group mb-4">
                                <label class="form-label">Playlist Title</label>
                                <span class="text-danger">*</span>
                                <input class="form-control" type="text" id="st_playlist_title" value="">
                            </div>
                            <div class="iq-button d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary position-relative" data-bs-toggle="modal"
                                    data-bs-target="#addNewPlaylist">
                                    <span class="button-text">Create Playlist</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade view-more-data-modal" id="shareModal" tabindex="-1" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered share-modal">
                <div class="modal-content">
                    <div class="modal-header pb-0">
                        <h5 class="modal-title" id="exampleModalLabelshare">Share</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="share-media-box">
                            <div class="media-box">
                                <a href="https://www.facebook.com/" target="_blank">
                                    <span class="image-icon">
                                        <i class="icon-facebook-icon"></i>
                                    </span>
                                    <span class="titles">Facebook</span>
                                </a>
                            </div>
                            <div class="media-box">
                                <a href="https://twitter.com/" target="_blank">
                                    <span class="image-icon">
                                        <i class="icon-twitter-icon"></i>
                                    </span>
                                    <span class="titles text-center">Twitter</span>
                                </a>
                            </div>
                            <!-- Add more social icons here as needed -->
                        </div>
                        <div class="copy-link">
                            <h6 id="basic-addon1">Copy Link </h6>
                            <div class="input-group mb-0">
                                <input type="text" id="copyInput" class="form-control copy-post-url" placeholder="Username"
                                    value="<?php echo "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>"
                                    aria-label="Username" readonly="">
                                <button class="input-group-text copy-url-btn" id="copyButton"><i class="ph ph-copy-simple"
                                        id="copyIcon"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade view-more-data-modal downloadModal" id="downloadModal" tabindex="-1" aria-modal="true"
            role="dialog">
            <div class="modal-dialog modal-dialog-centered share-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabeldownload">Download Quality</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <!-- Normal download functionality -->
                        <ul class="list-inline m-0 p-0 downloadModal-list">
                            <li>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="flex-grow-1">
                                        <h6 class="mt-0 mb-1">720p</h6>
                                        <p class="m-0 small">English</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="#" class="link-primary">
                                            <i class="ph ph-download-simple"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="flex-grow-1">
                                        <h6 class="mt-0 mb-1">1080p</h6>
                                        <p class="m-0 small">English</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="#" class="link-primary">
                                            <i class="ph ph-download-simple"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modals End -->      </div>
    </div>
  </div>
</div>
<!-- Banner End -->

<div class="container-fluid">
  <div class="overflow-hidden">
  <?php 
// --- SMART RECOMMENDATION LOGIC ---
$recommendations = [];

// 1. Try Official Recommendations
if (!empty($data['recommendations']['results'])) {
    $recommendations = $data['recommendations']['results'];
} 
// 2. If empty, try Similar Movies
elseif (!empty($data['similar']['results'])) {
    $recommendations = $data['similar']['results'];
} 
// 3. If still empty, fallback to Popular Movies (Safety Net)
else {
    $fallbackData = fetchTmdbApi('movie/popular', ['page' => 1]);
    if ($fallbackData) {
        $recommendations = $fallbackData['results'];
    }
}

// Limit to 10 items
$recommendations = array_slice($recommendations, 0, 10);
?>

<!-- Only show if we successfully found movies (which should be always now) -->
<?php if (!empty($recommendations)): ?>
<div class="recommended-block section-padding-top">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Recommended</h4>
    </div>
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3" data-mobile="2"
            data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
            
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php foreach ($recommendations as $rec): 
                    // Determine Title, Image, and ID safely
                    $recId = $rec['id'];
                    $recTitle = $rec['title'] ?? $rec['name'] ?? 'Unknown';
                    $recPoster = !empty($rec['poster_path']) 
                                 ? 'https://image.tmdb.org/t/p/w500' . $rec['poster_path'] 
                                 : 'assets/images/media/placeholder-portrait.webp';
                    $recLang = isset($rec['original_language']) ? locale_get_display_language($rec['original_language'], 'en') : 'English';
                    
                    // Use 'movie' or 'tv' based on the item, or fallback to current page type
                    $recType = $rec['media_type'] ?? $mediaType; 
                ?>
                
                <li class="swiper-slide">
                    <div class="iq-card card-hover">
                        <div class="block-images position-relative w-100">
                            <div class="img-box w-100">
                                <a href="/movie-detail?id=<?php echo $recId; ?>&type=<?php echo $recType; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                    <img src="<?php echo $recPoster; ?>" alt="movie-card"
                                        class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                </a>
                            </div>
                            <div class="card-description with-transition">
                                <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                    <li class="fw-semi-bold">
                                        <a href="/movie-detail?id=<?php echo $recId; ?>&type=<?php echo $recType; ?>" tabindex="0" class="font-size-14">
                                            View
                                        </a>
                                    </li>
                                </ul>
                                <div class="cart-content">
                                    <div class="content-left">
                                        <h5 class="iq-title text-capitalize">
                                            <a href="/movie-detail?id=<?php echo $recId; ?>&type=<?php echo $recType; ?>">
                                                <?php echo htmlspecialchars($recTitle); ?>
                                            </a>
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ph ph-translate"></i>
                                                <small class="font-size-12 text-capitalize"><?php echo $recLang; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                    <a href="/add-watchlist?id=<?php echo $recId; ?>"
                                        class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                        data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                        data-bs-title="Add to Watchlist">
                                        <i class="ph ph-plus font-size-18"></i>
                                    </a>
                                    <div class="iq-play-button iq-button">
                                        <a href="/movie-detail?id=<?php echo $recId; ?>&type=<?php echo $recType; ?>" class="btn btn-primary w-100">Play Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                <i class="ph-fill ph-crown "></i>
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

    <div class="favourite-person-block">
     <?php 
// 1. Detect which variable holds the movie info ($data or $details)
$movieInfo = isset($data) ? $data : (isset($details) ? $details : []);

// 2. Extract Cast
$castMembers = [];

// Check inside 'credits' (Standard API response)
if (!empty($movieInfo['credits']['cast'])) {
    $castMembers = $movieInfo['credits']['cast'];
} 
// Fallback: Check if cast is directly in the root (rare but possible)
elseif (!empty($movieInfo['cast'])) {
    $castMembers = $movieInfo['cast'];
}

// 3. Only display if we found actors
if (!empty($castMembers)): 
    // Limit to 12 actors
    $castList = array_slice($castMembers, 0, 12);
?>

<div class="overflow-hidden">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Starring</h4>
    </div>
    
    <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="4" data-mobile="2"
        data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
        
        <ul class="p-0 swiper-wrapper m-0 list-inline personality-card">
            
            <?php foreach ($castList as $actor): 
                // Safe Image Handling
                $profilePath = $actor['profile_path'] ?? null;
                $actorImage = $profilePath 
                              ? 'https://image.tmdb.org/t/p/w300' . $profilePath 
                              : 'assets/images/media/cast-placeholder.webp';
                
                $name = $actor['name'] ?? 'Unknown';
                $char = $actor['character'] ?? 'Actor';
                $id   = $actor['id'];
            ?>
            
            <li class="swiper-slide">
                <!-- Actor Image -->
                <a href="/person-detail?id=<?php echo $id; ?>">
                    <img src="<?php echo $actorImage; ?>" 
                         alt="<?php echo htmlspecialchars($name); ?>" 
                         class="img-fluid object-cover mb-3 rounded-3 personality-img" 
                         loading="lazy"
                         style="width: 100%; height: 150px; object-fit: cover; min-height: 150px;">
                </a>
                
                <!-- Actor Name & Role -->
                <div class="text-center">
                    <h6 class="mb-0">
                        <a href="/person-detail?id=<?php echo $id; ?>" class="font-size-14 text-decoration-none cast-title text-capitalize text-white">
                            <?php echo htmlspecialchars($name); ?>
                        </a>
                    </h6>
                    <a href="/person-detail?id=<?php echo $id; ?>" class="font-size-12 fw-semibold text-decoration-none text-capitalize text-body">
                        <?php echo htmlspecialchars($char); ?>
                    </a>
                </div>
            </li>
            
            <?php endforeach; ?>
            
        </ul>
        
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</div>

<?php else: ?>
    <!-- Debugging: Remove this else block after testing -->
    <!-- <p class="text-white">Debug: No cast data found. Check API connection.</p> -->
<?php endif; ?>
    </div>

    <div class="favourite-person-block">
    <?php 
// 1. Detect variable ($data or $details)
$movieInfo = isset($data) ? $data : (isset($details) ? $details : []);

$crewList = [];
// 2. Filter for Key Roles only (Director, Producer, Writer)
if (!empty($movieInfo['credits']['crew'])) {
    $targetJobs = ['Director', 'Producer', 'Executive Producer', 'Writer', 'Screenplay', 'Story'];
    
    foreach ($movieInfo['credits']['crew'] as $member) {
        if (in_array($member['job'], $targetJobs)) {
            $crewList[] = $member;
        }
    }
    // Limit to 15 people so the slider isn't endless
    $crewList = array_slice($crewList, 0, 15);
}

// 3. Display section only if we found relevant crew
if (!empty($crewList)): 
?>

<div class="overflow-hidden">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Crew</h4>
    </div>
    
    <div class="position-relative swiper swiper-card" data-slide="11" data-laptop="11" data-tab="4" data-mobile="2"
        data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
        
        <ul class="p-0 swiper-wrapper m-0 list-inline personality-card">
            
            <?php foreach ($crewList as $crewMember): 
                // Image Handling
                $crewImage = !empty($crewMember['profile_path']) 
                             ? 'https://image.tmdb.org/t/p/w300' . $crewMember['profile_path'] 
                             : 'assets/images/media/cast-placeholder.webp'; // Ensure you have a placeholder here
                
                $name = $crewMember['name'];
                $job = $crewMember['job'];
                $id = $crewMember['id'];
            ?>
            
            <li class="swiper-slide">
                <a href="/person-detail?id=<?php echo $id; ?>">
                    <img src="<?php echo $crewImage; ?>" 
                         alt="<?php echo htmlspecialchars($name); ?>" 
                         class="img-fluid object-cover mb-3 rounded-3 personality-img" 
                         loading="lazy"
                         style="width: 100%; height: 150px; object-fit: cover; min-height: 150px;">
                </a>
                <div class="text-center">
                    <h6 class="mb-0">
                        <a href="/person-detail?id=<?php echo $id; ?>" class="font-size-14 text-decoration-none cast-title text-capitalize text-white">
                            <?php echo htmlspecialchars($name); ?>
                        </a>
                    </h6>
                    <a href="/person-detail?id=<?php echo $id; ?>" class="font-size-12 fw-semibold text-decoration-none text-capitalize text-body">
                        <?php echo htmlspecialchars($job); ?>
                    </a>
                </div>
            </li>
            
            <?php endforeach; ?>
            
        </ul>
        
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>
<?php endif; ?>
    </div>

    <!-- Upcoming Start -->
 <!-- ==========================================
     UPCOMING SECTION
     ========================================== -->
<?php if (!empty($upcomingList)): ?>
<div class="upcomimg-block">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Upcoming</h4>
    </div>
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3" data-mobile="2"
             data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php foreach ($upcomingList as $movie): 
                    $poster = !empty($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w780'.$movie['poster_path'] : 'assets/images/media/placeholder-portrait.webp';
                    $title = $movie['title'] ?? $movie['name'];
                    $id = $movie['id'];
                    $release = date('M d, Y', strtotime($movie['release_date']));
                ?>
                <li class="swiper-slide">
                    <div class="iq-card card-hover">
                        <div class="block-images position-relative w-100">
                            <div class="img-box w-100">
                                <a href="/movie-detail?id=<?php echo $id; ?>&type=movie" class="position-relative top-0 bottom-0 start-0 end-0">
                                    <img src="<?php echo $poster; ?>" alt="<?php echo htmlspecialchars($title); ?>"
                                         class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                </a>
                            </div>
                            <div class="card-description with-transition">
                                <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                    <li class="fw-semi-bold">
                                        <a href="/movie-detail?id=<?php echo $id; ?>&type=movie" tabindex="0" class="font-size-14">View</a>
                                    </li>
                                </ul>
                                <div class="cart-content">
                                    <div class="content-left">
                                        <h5 class="iq-title text-capitalize">
                                            <a href="/movie-detail?id=<?php echo $id; ?>&type=movie">
                                                <?php echo htmlspecialchars($title); ?>
                                            </a>
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ph ph-calendar text-warning"></i>
                                                <small class="font-size-12 text-warning"><?php echo $release; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                    <a href="/add-watchlist?id=<?php echo $id; ?>"
                                       class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                       data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                       data-bs-title="Add to Watchlist">
                                        <i class="ph ph-plus font-size-18"></i>
                                    </a>
                                    <div class="iq-play-button iq-button">
                                        <a href="/movie-detail?id=<?php echo $id; ?>&type=movie" class="btn btn-primary w-100">Pre-Order</a>
                                    </div>
                                </div>
                            </div>
                            <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center">
                                <i class="ph-fill ph-crown "></i>
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


<!-- ==========================================
     RELATED VIDEOS (SIMILAR CONTENT) SECTION
     ========================================== -->
<?php if (!empty($relatedList)): ?>
<div class="video-block">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0">Related Movies</h4>
    </div>
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3" data-mobile="2"
             data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php foreach ($relatedList as $sim): 
                    $simPoster = !empty($sim['poster_path']) ? 'https://image.tmdb.org/t/p/w780'.$sim['poster_path'] : 'assets/images/media/placeholder-portrait.webp';
                    $simTitle = $sim['title'] ?? $sim['name'];
                    $simId = $sim['id'];
                    $simType = $sim['media_type'] ?? $mediaType; // Fallback to parent type
                    $simLang = isset($sim['original_language']) ? locale_get_display_language($sim['original_language'], 'en') : 'English';
                ?>
                <li class="swiper-slide">
                    <div class="iq-card card-hover">
                        <div class="block-images position-relative w-100">
                            <div class="img-box w-100">
                                <a href="/movie-detail?id=<?php echo $simId; ?>&type=<?php echo $simType; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                    <img src="<?php echo $simPoster; ?>" alt="<?php echo htmlspecialchars($simTitle); ?>"
                                         class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                </a>
                            </div>
                            <div class="card-description with-transition">
                                <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                    <li class="fw-semi-bold">
                                        <a href="/movie-detail?id=<?php echo $simId; ?>&type=<?php echo $simType; ?>" tabindex="0" class="font-size-14">
                                            View
                                        </a>
                                    </li>
                                </ul>
                                <div class="cart-content">
                                    <div class="content-left">
                                        <h5 class="iq-title text-capitalize">
                                            <a href="/movie-detail?id=<?php echo $simId; ?>&type=<?php echo $simType; ?>">
                                                <?php echo htmlspecialchars($simTitle); ?>
                                            </a>
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ph ph-translate"></i>
                                                <small class="font-size-12 text-capitalize"><?php echo $simLang; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                    <a href="/add-watchlist?id=<?php echo $simId; ?>"
                                       class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                       data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                       data-bs-title="Add to Watchlist">
                                        <i class="ph ph-plus font-size-18"></i>
                                    </a>
                                    <div class="iq-play-button iq-button">
                                        <a href="/movie-detail?id=<?php echo $simId; ?>&type=<?php echo $simType; ?>" class="btn btn-primary w-100">Play Now</a>
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
    <!-- Upcoming End -->

<div class="section-padding-bottom">
    <div class="rate-review-details">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h5 class="main-title text-capitalize m-0 fw-medium">Reviews (<?php echo count($movieReviews); ?>)</h5>
            <div class="d-flex align-items-center gap-3">
                
                <!-- Only show Add Review if logged in -->
                <?php if(isset($_SESSION['user_id'])): ?>
                <a id="openReviewButton" class="btn btn-link p-0 custom-fs-14 openReviewButton" data-bs-toggle="offcanvas"
                    href="#offcanvasReview" role="button" aria-controls="offcanvasReview">
                    Add Review 
                </a>
                <?php else: ?>
                <a href="/login" class="btn btn-link p-0 custom-fs-14">Login to Review</a>
                <?php endif; ?>
                
            </div>
        </div>

        <div class="comments-section">
            
            <?php if (!empty($movieReviews)): ?>
                <?php foreach ($movieReviews as $review): 
                    $reviewerAvatar = !empty($review['avatar_url']) ? $review['avatar_url'] : 'assets/images/user/user.jpg';
                ?>
                <div class="review-card mb-3">
                    <div class="review-detail rounded-3 p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center justify-content-center gap-3">
                                <!-- User Avatar -->
                                <img src="<?php echo htmlspecialchars($reviewerAvatar); ?>" class="img-fluid user-img rounded-circle"
                                    alt="user" style="width: 50px; height: 50px; object-fit: cover;">
                                <div>
                                    <!-- Author Name & Date -->
                                    <h6 class="line-count-1 m-0"><?php echo htmlspecialchars($review['username']); ?></h6>
                                    <p class="mb-0 mt-1 small-date-font text-muted">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Star Rating Display -->
                            <div class="star-rating">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <?php if($i <= $review['rating']): ?>
                                        <i class="ph-fill ph-star text-warning"></i>
                                    <?php else: ?>
                                        <i class="ph ph-star text-muted"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Review Content -->
                        <p class="mb-0 mt-3 pt-3 border-top fw-medium text-white">
                            <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center p-4">
                    <p class="text-muted">No reviews yet. Be the first to review!</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- REVIEW FORM OFF-CANVAS -->
    <div class="offcanvas overflow-y-auto widget-shopping-cart-content offcanvas-end offcanvas-sidebar sidebar-container on-rtl end border-left-0"
        tabindex="-1" id="offcanvasReview" aria-modal="true" role="dialog">
        
        <div class="offcanvas-header position-relative border-bottom">
            <h5 class="offcanvas-title fw-500" id="offcanvasReviewLabel">Add Review</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body">
            <!-- Form points to a separate processing file -->
            <form action="includes/process_review.php" method="POST">
                
                <!-- Hidden Inputs to identify the movie -->
                <input type="hidden" name="media_id" value="<?php echo $mediaId; ?>">
                <input type="hidden" name="media_type" value="<?php echo $mediaType; ?>">
                <input type="hidden" name="return_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">

                <div class="form-group mb-4">
                    <label class="form-label mb-3">Your Rating</label>
                    <!-- Custom Star Radio Buttons -->
                    <div class="star-rating d-flex flex-row-reverse justify-content-end gap-2">
                        <!-- Note: CSS usually handles the reverse logic for stars. 
                             Ensure your CSS supports selecting stars right-to-left or adjust logic. -->
                        <input type="radio" id="star5" name="rating" value="5" class="d-none"><label for="star5" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                        <input type="radio" id="star4" name="rating" value="4" class="d-none"><label for="star4" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                        <input type="radio" id="star3" name="rating" value="3" class="d-none"><label for="star3" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                        <input type="radio" id="star2" name="rating" value="2" class="d-none"><label for="star2" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                        <input type="radio" id="star1" name="rating" value="1" class="d-none"><label for="star1" class="cursor-pointer"><i class="ph-fill ph-star fs-3 text-muted hover-warning"></i></label>
                    </div>
                    <!-- Simple JS to handle star clicking visuals recommended -->
                </div>

                <div class="form-group mb-4">
                    <label class="form-label flex-grow-1" for="review_text">Your Review</label>
                    <textarea id="review_text" name="review_text" class="form-control review-text-area bg-dark text-white" rows="5" required placeholder="Write your thoughts here..."></textarea>
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

<style>
    /* Quick CSS for the Star Rating Input interaction */
    .star-rating input:checked ~ label i { color: #ffc107 !important; } /* Gold color */
    .star-rating label:hover i,
    .star-rating label:hover ~ label i { color: #ffc107 !important; }
</style>
</div>

<!-- READ MORE MODAL (Dynamic) -->
<div class="modal fade view-more-data-modal trending-info" id="viewMoreDataModal" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            
            <!-- Modal Header -->
            <div class="modal-header pb-0">
                <h3 class="text-uppercase m-0 texture-text texture-text-modal fw-bold">
                    <?php echo htmlspecialchars($title); ?>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-1">
                
                <!-- Metadata Row (Year, Views, Rating) -->
                <ul class="list-inline d-flex align-items-center flex-wrap gap-3 mt-4">
                    <li>
                        <span class="fw-medium"><?php echo $year; ?></span>
                    </li>
                    <li>
                        <span class="d-flex align-items-center gap-1">
                            <i class="ph ph-eye"></i> 2,841 views <!-- Static for now -->
                        </span>
                    </li>
                    <li>
                        <span class="d-flex align-items-center gap-1">
                            <span class="fw-medium"><?php echo $rating; ?></span>
                            <span class="imdb-logo ms-1">
                                <img src="assets/images/pages/imdb-logo.svg" loading="lazy" decoding="async" alt="imdb logo" class="img-fluid imdb-logo1">
                            </span>
                        </span>
                    </li>
                </ul>

               <!-- Genres Section -->
<div class="d-flex align-items-baseline flex-wrap gap-2 mt-md-1 mt-2">
    <h6 class="m-0">Genres:</h6>
    <ul class="p-0 mb-0 list-inline d-flex flex-wrap movie-tag">
        <?php 
        // Safety check: Support both $data and $details variables
        $genresSource = $data['genres'] ?? $details['genres'] ?? [];
        
        if (!empty($genresSource)): 
            foreach ($genresSource as $genre): 
        ?>
            <li class="trending-list">
                <a href="/view-all?genre_id=<?php echo $genre['id']; ?>" class="text-primary text-decoration-none fw-normal">
                    <?php echo htmlspecialchars($genre['name']); ?>
                </a>
            </li>
        <?php 
            endforeach; 
        else:
        ?>
            <li class="trending-list text-white">N/A</li>
        <?php endif; ?>
    </ul>
</div>

                <!-- Tags (Keywords) Section -->
                <?php 
                // Try to fetch keywords (TMDB structure varies for Movies vs TV)
                $keywords = $data['keywords']['keywords'] ?? $data['keywords']['results'] ?? [];
                if (!empty($keywords)): 
                ?>
                <div class="d-flex align-items-baseline flex-wrap gap-2 mt-3">
                    <h6 class="m-0">Tags:</h6>
                    <ul class="iq-blog-meta-cat-tag iq-blogtag mb-0 list-inline d-flex flex-wrap align-items-center gap-1 gap-md-3 mt-2 mt-md-3 tvshow-tags">
                        <?php foreach (array_slice($keywords, 0, 10) as $keyword): ?>
                            <li>
                                <a href="/view-all?keyword_id=<?php echo $keyword['id']; ?>" class="position-relative">
                                    <?php echo htmlspecialchars($keyword['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Language -->
                <div class="mt-4">
                    <div class="d-flex align-items-center gap-1">
                        <i class="ph ph-translate"></i>
                        <ul class="list-inline m-0 d-inline-flex align-items-center gap-2">
                            <li><small class="text-capitalize"><?php echo isset($data['original_language']) ? locale_get_display_language($data['original_language'], 'en') : 'English'; ?></small></li>
                        </ul>
                    </div>
                </div>

                <!-- Description -->
                <p class="mt-4 mb-0">
                    <?php echo htmlspecialchars($overview); ?>
                </p>

                <!-- Cast List -->
                <?php if (!empty($castList)): ?>
                <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                    <h6 class="m-0">Cast:</h6>
                    <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                        <?php foreach ($castList as $i => $actor): ?>
                            <li>
                                <a href="/person-detail?id=<?php echo $actor['id']; ?>" class="color-inherit">
                                    <?php echo htmlspecialchars($actor['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Crew List -->
                <?php if (!empty($crewList)): ?>
                <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-4">
                    <h6 class="m-0">Crew:</h6>
                    <ul class="list-inline m-0 p-0 d-flex align-items-center flex-wrap row-gap-1 column-gap-2 cast-crew-list">
                        <?php foreach ($crewList as $i => $crew): ?>
                            <li>
                                <a href="/person-detail?id=<?php echo $crew['id']; ?>" class="color-inherit">
                                    <?php echo htmlspecialchars($crew['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>
</main>

 <?php include('includes/footer.php'); ?>
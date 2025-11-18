<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: /login'); // Redirect to login
    exit;
}

$current_plan = $_SESSION['plan_name'] ?? 'free'; 
$current_profile_is_kid = $_SESSION['is_kid'] ?? 0;



$pageThemeClass = 'theme-premium'; // Default
if ($current_plan === 'pro') {
    $pageThemeClass = 'theme-pro';
}
if ($current_profile_is_kid) {
    $pageThemeClass .= '-kid'; // e.g., 'theme-pro-kid'
}

// --- 5. FETCH DATA FOR *THIS* PAGE ---
// (Example: Get "New Release" movies)
$newReleases = [];
/**
 * Converts runtime in minutes to a formatted string "Xh Ym".
 * @param int $minutes Total runtime in minutes.
 * @return string Formatted string.
 */
function formatRuntime(int $minutes): string
{
    if ($minutes <= 0) {
        return 'N/A';
    }
    $hours = floor($minutes / 60);
    $rem_minutes = $minutes % 60;
    return "{$hours}h : {$rem_minutes}m";
}

/**
 * Finds the official YouTube trailer URL from a list of videos.
 * @param array $videos The 'results' array from the TMDB videos endpoint.
 * @return string The YouTube embed URL or an empty string if not found.
 */
function getTrailerUrl(array $videos): string
{
    foreach ($videos as $video) {
        // We want the official 'Trailer' from 'YouTube'
        if ($video['site'] === 'YouTube' && $video['type'] === 'Trailer') {
            return 'https://www.youtube.com/embed/' . $video['key'] . '?autoplay=1&mute=1&loop=1&playlist=' . $video['key'];
        }
    }
    return ''; // Return empty if no official trailer is found
}


// --- Main Data Fetching Logic for the Banner ---

$bannerMovies = [];
// Fetch the movies currently playing in theaters in the US region
$nowPlayingData = fetchTmdbApi('movie/now_playing', ['region' => 'US']);

if ($nowPlayingData && !empty($nowPlayingData['results'])) {
    // Get the top 5 movies for the banner
    $topMovies = array_slice($nowPlayingData['results'], 0, 100);

    foreach ($topMovies as $baseMovie) {
        // For each movie, get its full details, including credits (cast) and videos (trailers)
        // Using 'append_to_response' is highly efficient as it bundles multiple requests into one.
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", [
            'append_to_response' => 'credits,videos'
        ]);

        if (!$details) {
            continue; // Skip this movie if details can't be fetched
        }

        // Prepare a clean array with only the data we need for the template
        $bannerMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'] ?? 'Title Unavailable',
            'overview'     => $details['overview'] ?? '',
            'backdrop_url' => isset($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $details['backdrop_path'] : '/assets/images/media/placeholder.webp',
            'rating'       => $details['vote_average'] ?? 0,
            'runtime'      => formatRuntime($details['runtime'] ?? 0),
            'genres'       => array_slice($details['genres'] ?? [], 0, 3), // Get first 3 genres
            'cast'         => array_slice($details['credits']['cast'] ?? [], 0, 3), // Get first 3 cast members
            'trailer_url'  => getTrailerUrl($details['videos']['results'] ?? []),
        ];
    }
}

// ... [Your existing Banner Logic ends here] ...
// --- 6. FETCH DATA FOR "UPCOMING MOVIES" SLIDER ---
$upcomingMovies = [];
$today = date('Y-m-d'); // Get today's date (e.g., 2025-11-18)

// Fetch upcoming movies (Region US ensures we get US release dates)
$upcomingData = fetchTmdbApi('movie/upcoming', ['region' => 'US']);

if ($upcomingData && !empty($upcomingData['results'])) {
    // We fetch more items (50) because we might filter many out
    $list = array_slice($upcomingData['results'], 0, 200);

    foreach ($list as $baseMovie) {
        
        // --- FILTER 1: STRICT FUTURE DATE CHECK ---
        // If the movie has no date, or the date is today or in the past, SKIP IT.
        if (empty($baseMovie['release_date']) || $baseMovie['release_date'] <= $today) {
            continue; 
        }

        // --- OPTIMIZATION ---
        // Only fetch details IF the date is valid. This saves API calls and speeds up the page.
        $details = fetchTmdbApi("movie/{$baseMovie['id']}");

        if (!$details) continue;

        $upcomingMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.webp', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie', 
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
            // Format date to look nice (e.g., "Nov 25, 2025")
            'release_date' => date('M d, Y', strtotime($details['release_date'])),
            'raw_date'     => $details['release_date'] // Keep raw date for sorting
        ];
    }

    // --- SORTING ---
    // Sort the final list so the movie coming out soonest appears first
    usort($upcomingMovies, function ($a, $b) {
        return strtotime($a['raw_date']) - strtotime($b['raw_date']);
    });
}

// --- 7. FETCH DATA FOR "CONTINUE WATCHING" (Simulated) ---
// In a real app, you would fetch this from your SQL database where you save:
// user_id, movie_id, current_time_marker, and total_duration.
// --- 7. FETCH REAL DATA FOR "CONTINUE WATCHING" (PDO Fix) ---
$continueWatching = [];

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // 1. Fetch the last 10 movies from YOUR database using PDO
    // We assume your connection variable is named $conn
    try {
        $sql = "SELECT * FROM watch_history WHERE user_id = :userId ORDER BY last_watched DESC LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        if ($historyRows) {
            foreach ($historyRows as $row) {
                
                // 2. Get details from TMDB API
                $details = fetchTmdbApi("movie/" . $row['tmdb_movie_id']);
    
                if (!$details) continue;
    
                // 3. Calculate Progress
                $minutes_watched = $row['current_time'];
                $runtime = $row['total_duration'];
                
                // Prevent division by zero
                if ($runtime > 0) {
                    $percentage = floor(($minutes_watched / $runtime) * 100);
                    $minutes_left = $runtime - $minutes_watched;
                } else {
                    $percentage = 0;
                    $minutes_left = 0;
                }
                
                // Cap limits
                if ($percentage > 100) $percentage = 100;
                if ($minutes_left < 0) $minutes_left = 0;
    
                // 4. Add to array
                $continueWatching[] = [
                    'id'           => $details['id'],
                    'title'        => $details['title'],
                    'image_url'    => isset($details['backdrop_path']) 
                                      ? 'https://image.tmdb.org/t/p/w780' . $details['backdrop_path'] 
                                      : '/assets/images/media/placeholder.webp',
                    'time_left'    => $minutes_left . ' m Left',
                    'percent'      => $percentage,
                    'last_viewed'  => date('M-Y', strtotime($row['last_watched'])), 
                ];
            }
        }
    } catch (Exception $e) {
        // If the table doesn't exist yet, just ignore the error so the page loads
        // echo "Error: " . $e->getMessage(); 
    }
}

// --- 8. FETCH DATA FOR "BEST IN TV" SLIDER ---
$bestTvShows = [];
// Fetch Top Rated TV Shows
$tvData = fetchTmdbApi('tv/top_rated', ['language' => 'en-US', 'page' => 1]);

if ($tvData && !empty($tvData['results'])) {
    // Limit to 10 items
    $list = array_slice($tvData['results'], 0, 100);

    foreach ($list as $show) {
        // Fetch details to get specific Genre
        $details = fetchTmdbApi("tv/{$show['id']}");

        if (!$details) continue;

        $bestTvShows[] = [
            'id'           => $details['id'],
            'title'        => $details['name'], // Note: TV shows use 'name', movies use 'title'
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.webp', 
            'genre'        => $details['genres'][0]['name'] ?? 'TV Show',
            'vote_average' => $details['vote_average'] ?? 0,
        ];
    }
}

// --- 9. FETCH DATA FOR "LATEST MOVIES" (Now Playing) ---
$latestMovies = [];
// Fetch movies currently playing in US theaters
$latestData = fetchTmdbApi('movie/now_playing', ['region' => 'US']);

if ($latestData && !empty($latestData['results'])) {
    // Limit to 10 items
    $list = array_slice($latestData['results'], 0, 100);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}");

        if (!$details) continue;

        $latestMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.webp', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

// --- 10. FETCH DATA FOR "VERTICAL SLIDER" (Top Rated / Top 10) ---
$topTenMovies = [];
$topRatedData = fetchTmdbApi('movie/top_rated', ['region' => 'US', 'page' => 1]);

if ($topRatedData && !empty($topRatedData['results'])) {
    // Limit to 6-10 items for the vertical slider
    $list = array_slice($topRatedData['results'], 0, 100);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}");

        if (!$details) continue;

        $topTenMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'overview'     => $details['overview'],
            // Use high-res backdrop for this large slider
            'image_url'    => isset($details['backdrop_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['backdrop_path'] 
                              : '/assets/images/media/placeholder.webp',
            'runtime'      => formatRuntime($details['runtime'] ?? 0), // Uses your existing helper
            'rating'       => $details['vote_average'] ?? 0,
            'genres'       => array_slice($details['genres'] ?? [], 0, 4), // Get up to 4 genres
        ];
    }
}

// --- 11. FETCH DATA FOR "SUGGESTED FOR YOU" (Smart Recommendations) ---
$suggestedMovies = [];
$recommendationBasis = null; // To store the ID of the movie we are basing suggestions on

// 1. Check if we have history from Section 7 ($continueWatching)
if (!empty($continueWatching)) {
    // Get the ID of the most recently watched movie
    $lastWatchedId = $continueWatching[0]['id'];
    // Fetch recommendations based on that ID
    $suggestedData = fetchTmdbApi("movie/{$lastWatchedId}/recommendations");
} else {
    // 2. Fallback: If no history, fetch generic popular movies (Page 3 to vary content)
    $suggestedData = fetchTmdbApi('movie/popular', ['page' => 3, 'region' => 'US']);
}

if ($suggestedData && !empty($suggestedData['results'])) {
    // Limit to 10 items
    $list = array_slice($suggestedData['results'], 0, 100);

    foreach ($list as $baseMovie) {
        // Optimization: Skip if no poster (looks bad in suggestions)
        if (empty($baseMovie['poster_path'])) continue;

        $details = fetchTmdbApi("movie/{$baseMovie['id']}");
        if (!$details) continue;

        $suggestedMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'poster_url'   => 'https://image.tmdb.org/t/p/w780' . $details['poster_path'], 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

// --- 12. FETCH DATA FOR "PARALLAX SECTION" (Movie of the Year) ---
$parallaxMovie = [];
$currentYear = date('Y');

// We use 'discover/movie' instead of 'popular' to get more control
$parallaxData = fetchTmdbApi('discover/movie', [
    'region' => 'US',
    'sort_by' => 'vote_average.desc', // Get the highest rated...
    'primary_release_year' => $currentYear, // ...of this year
    'vote_count.gte' => 1000, // ...that has at least 1000 votes (filters out unknown movies)
    'with_original_language' => 'en'
]);

// Fallback: If no 2025 movie has 1000 votes yet, just get the most popular one
if (empty($parallaxData['results'])) {
    $parallaxData = fetchTmdbApi('movie/popular', ['region' => 'US', 'page' => 1]);
}

if ($parallaxData && !empty($parallaxData['results'])) {
    // Get the #1 result
    $heroId = $parallaxData['results'][0]['id'];
    $details = fetchTmdbApi("movie/{$heroId}");

    if ($details) {
        $parallaxMovie = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'overview'     => $details['overview'],
            'backdrop_url' => isset($details['backdrop_path']) 
                              ? 'https://image.tmdb.org/t/p/original' . $details['backdrop_path'] 
                              : '/assets/images/media/placeholder.webp',
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.webp',
            'runtime'      => formatRuntime($details['runtime'] ?? 0),
            'rating'       => $details['vote_average'] ?? 0,
            'stars'        => round(($details['vote_average'] ?? 0) / 2),
        ];
    }
}

// --- 13. FETCH DATA FOR "TRENDING TV" (Complex Tabbed Section) ---
$trendingShows = [];
// Fetch Trending TV Shows for the week
$trendingData = fetchTmdbApi('trending/tv/week');

if ($trendingData && !empty($trendingData['results'])) {
    // Limit to 5 items to prevent slow loading (since we fetch deep details for each)
    $list = array_slice($trendingData['results'], 0, 100);

    foreach ($list as $baseShow) {
        $tvId = $baseShow['id'];
        
        // 1. Fetch Main Details + Videos + Recommendations in one go
        $details = fetchTmdbApi("tv/{$tvId}", [
            'append_to_response' => 'videos,recommendations'
        ]);

        if (!$details) continue;

        // 2. Fetch Season 1 details separately to fill the "Episodes" tab
        $season1 = fetchTmdbApi("tv/{$tvId}/season/1");

        $trendingShows[] = [
            'id'           => $details['id'],
            'title'        => $details['name'],
            'overview'     => $details['overview'],
            'backdrop_url' => isset($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $details['backdrop_path'] : '',
            'poster_url'   => isset($details['poster_path']) ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] : '',
            'year'         => isset($details['first_air_date']) ? date('Y', strtotime($details['first_air_date'])) : '',
            'seasons_count'=> $details['number_of_seasons'] ?? 1,
            // Get first 5 episodes of Season 1
            'episodes'     => array_slice($season1['episodes'] ?? [], 0, 50),
            // Get Trailer
            'trailer_url'  => getTrailerUrl($details['videos']['results'] ?? []),
            // Get Similar/Recommended
            'similar'      => array_slice($details['recommendations']['results'] ?? [], 0, 4),
        ];
    }
}

// --- 14. FETCH DATA FOR "RECOMMENDED TV SHOWS" ---
$recommendedTv = [];
// Fetch Popular TV Shows (Page 2 ensures we get different shows than the "Trending" section)
$recTvData = fetchTmdbApi('tv/popular', ['page' => 2, 'language' => 'en-US']);

if ($recTvData && !empty($recTvData['results'])) {
    // Limit to 10 items
    $list = array_slice($recTvData['results'], 0, 100);

    foreach ($list as $show) {
        // Fetch details to get specific Genre
        $details = fetchTmdbApi("tv/{$show['id']}");

        if (!$details) continue;

        $recommendedTv[] = [
            'id'           => $details['id'],
            'title'        => $details['name'], // TV shows use 'name'
            // I'm using w780 here for higher quality than standard w500, closer to 4K look on small screens
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w780' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.webp', 
            'genre'        => $details['genres'][0]['name'] ?? 'TV Show',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

include 'includes/header.php';
?>

<!-- =================================== -->
<!-- DYNAMIC HOME BANNER SLIDER          -->
<!-- =================================== -->
<section>
   <div class="overflow-hidden">
      <div id="home-banner-slider" class="iq-main-slider p-0 swiper banner-home-swiper" data-swiper="home-banner-slider">

         <div class="slider m-0 p-0 swiper-wrapper home-slider">

            <!-- PHP LOOP STARTS HERE -->
            <?php if (!empty($bannerMovies)): ?>
               <?php foreach ($bannerMovies as $movie): ?>
               <div class="swiper-slide slide s-bg-1 p-0">
                  <div class="banner-home-swiper-image"
                     style="background-image: url('<?php echo htmlspecialchars($movie['backdrop_url']); ?>')">
                     <div class="container-fluid position-relative">
                        <div class="row align-items-center iq-ltr-direction h-100 slider-content-full-height">
                           <div class="col-lg-6 col-md-12 col-xl-5">
                              <h2 class="texture-text big-font letter-spacing-1 line-count-1 text-capitalize RightAnimate">
                                 <?php echo htmlspecialchars($movie['title']); ?>
                              </h2>
                              <div class="d-flex flex-wrap align-items-center gap-3 r-mb-23 RightAnimate-two">
                                 <div class="slider-ratting d-flex align-items-center">
                                    <ul class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                       <!-- Dynamic Star Rating -->
                                       <?php
                                          $ratingOutOf5 = round($movie['rating'] / 2);
                                          for ($i = 1; $i <= 5; $i++) {
                                             echo '<li><i class="ph-fill ph-star' . ($i > $ratingOutOf5 ? '-half' : '') . '" aria-hidden="true"></i></li>';
                                          }
                                       ?>
                                    </ul>
                                 </div>
                                 <span class="d-flex align-items-center gap-1">
                                    <span class=""><?php echo htmlspecialchars(number_format($movie['rating'], 1)); ?></span>
                                    <img src="/assets/images/pages/imdb-logo.svg" alt="imdb logo" class="img-fluid imdb-img">
                                 </span>
                                 <div class="d-flex align-items-center gap-1">
                                    <i class="ph ph-clock"></i>
                                    <span class="time"><?php echo htmlspecialchars($movie['runtime']); ?></span>
                                 </div>
                              </div>
                              <p class="line-count-3 my-3 RightAnimate-two">
                                 <?php echo htmlspecialchars($movie['overview']); ?>
                              </p>
                              <div class="trending-list RightAnimate-three">
                                 <?php if (!empty($movie['cast'])): ?>
                                 <div class="text-primary genres font-size-14 mb-1"> Starring:
                                    <?php foreach ($movie['cast'] as $i => $actor): ?>
                                       <a href="/cast-detail?id=<?php echo $actor['id']; ?>" class="fw-normal text-decoration-none text-body"><?php echo htmlspecialchars($actor['name']); ?></a><?php if ($i < count($movie['cast']) - 1) echo ', '; ?>
                                    <?php endforeach; ?>
                                 </div>
                                 <?php endif; ?>
                                 
                                 <?php if (!empty($movie['genres'])): ?>
                                 <div class="text-primary genres font-size-14 mb-1"> Genres:
                                    <?php foreach ($movie['genres'] as $i => $genre): ?>
                                       <a href="/genre-detail?id=<?php echo $genre['id']; ?>" class="fw-normal text-decoration-none text-body"><?php echo htmlspecialchars($genre['name']); ?></a><?php if ($i < count($movie['genres']) - 1) echo ', '; ?>
                                    <?php endforeach; ?>
                                 </div>
                                 <?php endif; ?>
                              </div>
                              <div class="RightAnimate-four">
                                 <a href="/movie-detail?id=<?php echo $movie['id']; ?>" class="btn btn-primary text-capitalize position-relative rounded-3">
                                     <span class="d-flex align-items-center gap-2">
                                         <span class="button-text">Play Now</span>
                                         <i class="ph-fill ph-play fs-6"></i>
                                     </span>
                                 </a>
                              </div>
                           </div>

                           <?php if (!empty($movie['trailer_url'])): ?>
                           <div class="col-xl-7 col-lg-6 col-md-12 trailor-video iq-slider d-none d-lg-block">
                              <a data-fslightbox="html5-video"
                                 href="<?php echo htmlspecialchars($movie['trailer_url']); ?>"
                                 class="video-open playbtn text-decoration-none" tabindex="0">
                                 <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                    xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="80px" height="80px"
                                    viewBox="0 0 213.7 213.7" enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                    <polygon class="triangle" fill="none" stroke-width="7" stroke-linecap="round"
                                       stroke-linejoin="round" stroke-miterlimit="10"
                                       points="73.5,62.5 148.5,105.8 73.5,149.1 ">
                                    </polygon>
                                    <circle class="circle" fill="none" stroke-width="7" stroke-linecap="round"
                                       stroke-linejoin="round" stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                    </circle>
                                 </svg>
                                 <span class="w-trailor text-uppercase"> Watch Trailer </span>
                              </a>
                           </div>
                           <?php endif; ?>

                        </div>
                     </div>
                  </div>
               </div>
               <?php endforeach; ?>
            <?php else: ?>
               <!-- Fallback slide if the API fails -->
               <div class="swiper-slide slide s-bg-1 p-0">
                  <div class="container-fluid position-relative"><div class="row"><div class="col-12"><p class="text-white text-center">Could not load movies.</p></div></div></div>
               </div>
            <?php endif; ?>
            <!-- PHP LOOP ENDS HERE -->

         </div>
         <!-- Slider Controls -->
         <button class="PreArrow-two swiper-arrows d-flex align-items-center justify-content-center d-xl-block d-none" id="home-banner-slider-prev"><i class="ph ph-caret-left"></i></button>
         <button class="NextArrow-two swiper-arrows d-flex align-items-center justify-content-center d-xl-block d-none" id="home-banner-slider-next"><i class="ph ph-caret-right"></i></button>
      </div>
   </div>
</section>

<div class="container-fluid">
   <div class="overflow-hidden">
      <div class="continue-watching-block home-continue-watch section-padding-top">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Continue Watch Movies</h4>
    </div>
    
    <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="3" data-tab="3" data-mobile="2"
         data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true" data-pagination="false">
         
        <ul class="p-0 swiper-wrapper m-0 list-inline">
            <?php if (!empty($continueWatching)): ?>
                <?php foreach ($continueWatching as $item): ?>
                <li class="swiper-slide">
                    <div class="iq-watching-block">
                        <div class="block-images position-relative">
                            
                            <div class="iq-image-box overly-images">
                                <a href="movie-detail?id=<?php echo $item['id']; ?>" class="d-block">
                                    <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                         class="w-100 d-block border-0 rounded-3 continue-image" loading="lazy">
                                </a>
                            </div>
                            
                            <div class="iq-preogress">
                                <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">
                                    <?php echo $item['time_left']; ?>
                                </span>
                                
                                <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                    <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                        <li class="iq-preogress-movie-title position-relative font-size-14">
                                            <span class="text-capitalize fw-semibold">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </span>
                                        </li>
                                        <li class="flex-shrink-0 fw-semibold font-size-14">
                                            <span><?php echo $item['last_viewed']; ?></span>
                                        </li>
                                    </ul>
                                    <a href="movie-detail?id=<?php echo $item['id']; ?>">
                                        <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                    </a>
                                </div>
                                
                                <div class="progress" role="progressbar" aria-label="Progress" 
                                     aria-valuenow="<?php echo $item['percent']; ?>" aria-valuemin="0"
                                     aria-valuemax="100" style="height: 2px">
                                    <div class="progress-bar" style="width: <?php echo $item['percent']; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="close-icon-section">
                                <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon"
                                     data-bs-toggle="tooltip" data-bs-placement="left" 
                                     aria-label="Remove from list" data-bs-original-title="Remove from list">
                                    <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <div class="d-none d-lg-block">
            <div class="swiper-button swiper-button-next"></div>
            <div class="swiper-button swiper-button-prev"></div>
        </div>
    </div>
</div>

<div class="upcomimg-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Upcoming Movies</h4>
    </div>
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
            
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($upcomingMovies)): ?>
                    <?php foreach ($upcomingMovies as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover">
                                <div class="block-images position-relative w-100">
                                    <div class="img-box w-100">
                                        <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="movie-detail?id=<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="movie-detail?id=<?php echo $movie['id']; ?>">
                                                        <?php echo htmlspecialchars($movie['title']); ?>
                                                    </a>
                                                </h5>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="ph ph-calendar"></i>
                                                        <small class="font-size-12 text-capitalize text-warning">
                                                            <?php echo $movie['release_date']; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="watchlist-add.php?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Pre-Order
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No upcoming movies found.</p>
                <?php endif; ?>

            </ul>
            
            <div class="d-none d-lg-block">
                <div class="swiper-button swiper-button-next"></div>
                <div class="swiper-button swiper-button-prev"></div>
            </div>
        </div>
    </div>
</div>

      <div class="streamit-card-height-block">
    <div class="d-flex align-items-center justify-content-between px-1 mb-4">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Best in TV</h4>
        <a href="view-all.php?type=tv" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="5" data-laptop="3" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($bestTvShows)): ?>
                    <?php foreach ($bestTvShows as $tv): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover landscape-card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <div class="img-box w-100">
                                        <a href="movie-detail?id=<?php echo $tv['id']; ?>&type=tv" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $tv['poster_url']; ?>" alt="<?php echo htmlspecialchars($tv['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="movie-detail?id=<?php echo $tv['id']; ?>&type=tv" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($tv['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize mb-0">
                                                    <a href="movie-detail?id=<?php echo $tv['id']; ?>&type=tv">
                                                        <?php echo htmlspecialchars($tv['title']); ?>
                                                    </a>
                                                </h5>
                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                     <i class="ph-fill ph-star text-warning"></i>
                                                     <span class="text-white font-size-12"><?php echo number_format($tv['vote_average'], 1); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="watchlist-add.php?id=<?php echo $tv['id']; ?>&type=tv"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="movie-detail?id=<?php echo $tv['id']; ?>&type=tv" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <div class="position-absolute z-1 premium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
                                    </div>
                                    
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No TV shows found.</p>
                <?php endif; ?>

            </ul>
            
            <div class="swiper-button swiper-button-next"></div>
            <div class="swiper-button swiper-button-prev"></div>
        </div>
    </div>
</div>

      <div class="latest-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Latest Movies</h4>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($latestMovies)): ?>
                    <?php foreach ($latestMovies as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <div class="img-box w-100">
                                        <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="movie-detail?id=<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="movie-detail?id=<?php echo $movie['id']; ?>">
                                                        <?php echo htmlspecialchars($movie['title']); ?>
                                                    </a>
                                                </h5>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="ph ph-translate"></i>
                                                        <small class="font-size-12 text-capitalize"><?php echo $movie['language']; ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="watchlist-add.php?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No latest movies found.</p>
                <?php endif; ?>

            </ul>
            
            <div class="d-none d-lg-block">
                <div class="swiper-button swiper-button-next"></div>
                <div class="swiper-button swiper-button-prev"></div>
            </div>
        </div>
    </div>
</div>


   </div>
</div>

<div class="verticle-slider section-padding-bottom">
   <div class="slider">
      <div class="slider-flex position-relative">
         
         <div class="slider--col position-relative">
            <div class="vertical-slider-prev swiper-button"><i class="iconly-Arrow-Up-2 icli"></i></div>
            <div class="slider-thumbs" data-swiper="slider-thumbs">
               <div class="swiper-container " data-swiper="slider-thumbs-inner">
                  <ul class="swiper-wrapper top-ten-slider-nav p-0 m-0 list-inline">
                     
                     <?php foreach ($topTenMovies as $movie): ?>
                     <li class="swiper-slide swiper-bg">
                        <div class="block-images position-relative ">
                           <div class="img-box slider--image">
                              <img src="<?php echo $movie['image_url']; ?>" class="w-100 rounded-3" 
                                   alt="<?php echo htmlspecialchars($movie['title']); ?>" loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">
                                  <?php echo htmlspecialchars($movie['title']); ?>
                              </h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock"></i>
                                    <span class="text-body"><?php echo $movie['runtime']; ?></span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <?php endforeach; ?>

                  </ul>
               </div>
            </div>
            <div class="vertical-slider-next swiper-button"><i class="iconly-Arrow-Down-2 icli"></i></div>
         </div>

         <div class="slider-images" data-swiper="slider-images">
            <div class="swiper-container " data-swiper="slider-images-inner">
               <ul class="swiper-wrapper p-0 m-0 list-inline">
                  
                  <?php foreach ($topTenMovies as $movie): ?>
                  <li class="swiper-slide">
                     <div class="slider--image block-images">
                         <img src="<?php echo $movie['image_url']; ?>" loading="lazy" 
                              alt="<?php echo htmlspecialchars($movie['title']); ?>" />
                     </div>
                     
                     <div class="description">
                        <div class="block-description">
                           
                           <ul class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <?php foreach ($movie['genres'] as $genre): ?>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="#" class="text-decoration-none"><?php echo $genre['name']; ?></a>
                              </li>
                              <?php endforeach; ?>
                           </ul>

                           <h2 class="iq-title m-0 line-count-2">
                               <a href="movie-detail?id=<?php echo $movie['id']; ?>">
                                   <?php echo htmlspecialchars($movie['title']); ?>
                               </a>
                           </h2>

                           <div class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li><i class="ph-fill ph-star"></i></li>
                                    <li><i class="ph-fill ph-star"></i></li>
                                    <li><i class="ph-fill ph-star"></i></li>
                                    <li><i class="ph-fill ph-star"></i></li>
                                    <li><i class="ph-fill ph-star-half"></i></li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0"><?php echo number_format($movie['rating'], 1); ?></p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body"><?php echo $movie['runtime']; ?></span>
                              </div>
                           </div>

                           <p class="mt-2 mb-3 line-count-3">
                               <?php echo htmlspecialchars($movie['overview']); ?>
                           </p>

                           <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="btn btn-primary text-capitalize position-relative rounded-3">
                              <span class="d-flex align-items-center gap-2">
                                 <span class="button-text">Play Now</span>
                                 <i class="ph-fill ph-play fs-6"></i>
                              </span>
                           </a>

                        </div>
                     </div>
                  </li>
                  <?php endforeach; ?>

               </ul>
               
               <div class="d-block d-lg-none">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>

      </div>
   </div>
</div>

<div class="container-fluid">
  <div class="overflow-hidden">
    <div class="suggested-block section-wraper">
        <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Suggested For You</h4>
        </div>
        
        <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
                 data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
                 data-pagination="true">
                 
                <ul class="p-0 swiper-wrapper m-0 list-inline">
                    
                    <?php if (!empty($suggestedMovies)): ?>
                        <?php foreach ($suggestedMovies as $movie): ?>
                            <li class="swiper-slide">
                                <div class="iq-card card-hover">
                                    <div class="block-images position-relative w-100">
                                        
                                        <div class="img-box w-100">
                                            <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                                <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                     class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                            </a>
                                        </div>
                                        
                                        <div class="card-description with-transition">
                                            <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                                <li class="fw-semi-bold">
                                                    <a href="movie-detail?id=<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                        <?php echo htmlspecialchars($movie['genre']); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                            
                                            <div class="cart-content">
                                                <div class="content-left">
                                                    <h5 class="iq-title text-capitalize">
                                                        <a href="movie-detail?id=<?php echo $movie['id']; ?>">
                                                            <?php echo htmlspecialchars($movie['title']); ?>
                                                        </a>
                                                    </h5>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <i class="ph ph-translate"></i>
                                                            <small class="font-size-12 text-capitalize"><?php echo $movie['language']; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                                <a href="watchlist-add.php?id=<?php echo $movie['id']; ?>"
                                                   class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                                   data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                   data-bs-title="Add to Watchlist">
                                                    <i class="ph ph-plus font-size-18"></i>
                                                </a>
                                                <div class="iq-play-button iq-button">
                                                    <a href="movie-detail?id=<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                        Play Now
                                                    </a>
                                                </div>
                                            </div>
                                            
                                        </div>
                                        
                                        <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                             data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                            <i class="ph-fill ph-crown"></i>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-white px-3">Start watching movies to get recommendations!</p>
                    <?php endif; ?>

                </ul>
                
                <div class="d-none d-lg-block">
                    <div class="swiper-button swiper-button-next"></div>
                    <div class="swiper-button swiper-button-prev"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php if (!empty($parallaxMovie)): ?>
<section id="parallex" class="parallax-window bg-attachment-fixed"
    style="background:url(<?php echo $parallaxMovie['backdrop_url']; ?>) fixed; background-size: cover; background-position: center;">
    
    <div class="container-fluid h-100">
        <div class="row align-items-center justify-content-center h-100 parallaxt-details flex-column-reverse flex-lg-row gap-4 gap-lg-0">
            
            <div class="col-xl-6 col-lg-6 col-md-12 r-mb-23">
                <div class="parallax-window-detail">
                    
                    <h2 class="mb-0 parallaxt-details-heading">
                        <?php echo htmlspecialchars($parallaxMovie['title']); ?>
                    </h2>
                    
                    <div class="d-flex flex-column flex-md-row gap-2 flex-wrap align-items-center r-mb-23 mt-2 mb-3 gap-md-3 justify-content-center justify-content-lg-start">
                        
                        <div class="slider-ratting d-flex align-items-center">
                            <ul class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                <?php 
                                // Loop to display stars based on rating
                                for($i=1; $i<=5; $i++) {
                                    if($i <= $parallaxMovie['stars']) {
                                        echo '<li><i class="ph-fill ph-star"></i></li>';
                                    } else {
                                        echo '<li><i class="ph ph-star"></i></li>';
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        
                        <div class="d-flex gap-2 align-items-center">
                            <span><?php echo number_format($parallaxMovie['rating'], 1); ?></span>
                            <img src="/assets/images/pages/imdb-logo.svg" alt="imdb logo" class="img-fluid">
                        </div>
                        
                        <div class="d-flex align-items-center gap-1">
                            <i class="ph ph-clock"></i>
                            <span class=""><?php echo $parallaxMovie['runtime']; ?></span>
                        </div>
                    </div>
                    
                    <h4 class="iq-title mb-2 pb-1 pb-lg-0 mb-lg-3 fw-medium">
                        Movie of the Year
                    </h4>
                    
                    <p class="mb-4 pb-2 parallaxt-details-descripttion line-count-3">
                        <?php echo htmlspecialchars($parallaxMovie['overview']); ?>
                    </p>
                    
                    <div class="parallax-buttons">
                        <a href="movie-detail?id=<?php echo $parallaxMovie['id']; ?>" class="btn btn-primary text-capitalize position-relative rounded-3">
                            <span class="d-flex align-items-center gap-2">
                                <span class="button-text">Play Now</span>
                                <i class="ph-fill ph-play fs-6"></i>
                            </span>
                        </a>
                    </div>
                    
                </div>
            </div>
            
            <div class="col-xl-6 col-lg-6 col-md-12 mt-0 mt-lg-5 mt-xl-0">
                <div class="parallax-img">
                    <a href="movie-detail?id=<?php echo $parallaxMovie['id']; ?>">
                        <img src="<?php echo $parallaxMovie['poster_url']; ?>" 
                             class="img-fluid w-100 rounded-3 shadow-lg" 
                             loading="lazy" 
                             alt="<?php echo htmlspecialchars($parallaxMovie['title']); ?>">
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</section>
<?php endif; ?>

<section class="tranding-tab-slider section-padding">
   <div class="container-fluid">
      <div class="row m-0 p-0">
         <div id="iq-trending" class="s-margin iq-tvshow-tabs iq-trending-tabs overflow-hidden">
            <div class="d-flex align-items-center justify-content-between px-1">
               <h4 class="main-title text-capitalize mb-0 fw-medium">Trending TV</h4>
            </div>
            <div class="trending-contens position-relative ">
               
               <div id="gallery-top" class="swiper gallery-thumbs pt-0" data-swiper="gallery-top">
                  <ul class="swiper-wrapper list-inline m-0 trending-swiper-padding trending-slider-nav align-items-center ">
                     <?php foreach ($trendingShows as $show): ?>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);">
                           <div class="movie-swiper position-relative">
                              <img src="<?php echo $show['poster_url']; ?>" alt="<?php echo htmlspecialchars($show['title']); ?>" />
                           </div>
                        </a>
                     </li>
                     <?php endforeach; ?>
                  </ul>
               </div>

               <div id="gallery-bottom" class="swiper trending-tab-slider swiper-no-swiping" data-swiper="gallery-bottom">
                  <ul class="swiper-wrapper list-inline p-0 m-0 d-flex trending-slider">
                     
                     <?php foreach ($trendingShows as $show): 
                        $uniq = $show['id']; // Unique ID for Tab targeting
                     ?>
                     <li class="swiper-slide">
                        <div class="trending-tab-slider-image">
                           <img src="<?php echo $show['backdrop_url']; ?>" alt="bg-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-<?php echo $uniq; ?>" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" data-bs-toggle="pill" data-bs-target="#overview-<?php echo $uniq; ?>" role="tab" aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" data-bs-toggle="pill" data-bs-target="#episodes-<?php echo $uniq; ?>" role="tab" aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" data-bs-toggle="pill" data-bs-target="#trailers-<?php echo $uniq; ?>" role="tab" aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" data-bs-toggle="pill" data-bs-target="#similar-<?php echo $uniq; ?>" role="tab" aria-selected="false">Similar</a>
                                    </li>
                                 </ul>
                              </div>

                              <div class="tab-content trending-content">
                                 
                                 <div id="overview-<?php echo $uniq; ?>" class="tab-pane fade show active" role="tabpanel">
                                    <div class="trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">
                                           <?php echo htmlspecialchars($show['title']); ?>
                                       </h1>
                                       <div class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span><?php echo $show['seasons_count']; ?> Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"><?php echo $show['year']; ?></span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <span class="fw-bold">#Trending TV</span>
                                       </div>
                                       <p class="trending-dec line-count-4">
                                           <?php echo htmlspecialchars($show['overview']); ?>
                                       </p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="movie-detail?id=<?php echo $show['id']; ?>&type=tv" class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>

                                 <div id="episodes-<?php echo $uniq; ?>" class="tab-pane fade" role="tabpanel">
                                    <div class="trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select class="form-control season-select select2-basic-single js-states">
                                             <option value="season1">Season 1</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3" data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false" data-loop="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0 list-inline">
                                             <?php foreach($show['episodes'] as $ep): ?>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                   <div class="block-image position-relative z-1">
                                                      <img src="<?php echo isset($ep['still_path']) ? 'https://image.tmdb.org/t/p/w300'.$ep['still_path'] : '/assets/images/media/placeholder.webp'; ?>" class="img-fluid img-zoom" loading="lazy">
                                                   </div>
                                                   <div class="episode-detail fw-medium position-absolute">
                                                      <h6 class="mt-2 mb-0">E<?php echo $ep['episode_number']; ?>: <?php echo $ep['name']; ?></h6>
                                                      <span class="mb-0 line-count-2 mt-2 small lh-base"><?php echo $ep['overview']; ?></span>
                                                   </div>
                                                </div>
                                             </li>
                                             <?php endforeach; ?>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>

                                 <div id="trailers-<?php echo $uniq; ?>" class="tab-pane fade" role="tabpanel">
                                    <div class="trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <?php if(!empty($show['trailer_url'])): ?>
                                                <iframe width="100%" height="350" src="<?php echo $show['trailer_url']; ?>" frameborder="0" allowfullscreen></iframe>
                                                <?php else: ?>
                                                   <p class="mt-5">No Trailer Available</p>
                                                <?php endif; ?>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>

                                 <div id="similar-<?php echo $uniq; ?>" class="tab-pane fade" role="tabpanel">
                                    <div class="trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3" data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false" data-loop="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0 list-inline">
                                             <?php foreach($show['similar'] as $sim): ?>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                   <div class="block-image position-relative z-1">
                                                      <a href="movie-detail?id=<?php echo $sim['id']; ?>&type=tv">
                                                         <img src="<?php echo isset($sim['backdrop_path']) ? 'https://image.tmdb.org/t/p/w300'.$sim['backdrop_path'] : '/assets/images/media/placeholder.webp'; ?>" class="img-fluid img-zoom" loading="lazy">
                                                      </a>
                                                   </div>
                                                   <div class="episode-detail fw-medium position-absolute">
                                                      <h6 class="mt-2 mb-0"><?php echo htmlspecialchars($sim['name']); ?></h6>
                                                   </div>
                                                </div>
                                             </li>
                                             <?php endforeach; ?>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>

                              </div> </div>
                        </div>
                     </li>
                     <?php endforeach; ?>

                  </ul>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>

<div class="container-fluid">
   <div class="overflow-hidden">
      <div class="recommended-blocks section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Recommended TV Shows</h4>
         </div>
         
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               
               <ul class="p-0 swiper-wrapper m-0 list-inline">
                  
                  <?php if (!empty($recommendedTv)): ?>
                      <?php foreach ($recommendedTv as $show): ?>
                      <li class="swiper-slide">
                         <div class="iq-card card-hover">
                            <div class="block-images position-relative w-100">
                               
                               <div class="img-box w-100">
                                  <a href="movie-detail?id=<?php echo $show['id']; ?>&type=tv" class="position-relative top-0 bottom-0 start-0 end-0">
                                     <img src="<?php echo $show['poster_url']; ?>" alt="<?php echo htmlspecialchars($show['title']); ?>"
                                        class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                                  </a>
                               </div>
                               
                               <div class="card-description with-transition">
                                  <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                     <li class="fw-semi-bold">
                                        <a href="movie-detail?id=<?php echo $show['id']; ?>&type=tv" tabindex="0" class="font-size-14">
                                           <?php echo htmlspecialchars($show['genre']); ?>
                                        </a>
                                     </li>
                                  </ul>
                                  
                                  <div class="cart-content">
                                     <div class="content-left">
                                        <h5 class="iq-title text-capitalize">
                                           <a href="movie-detail?id=<?php echo $show['id']; ?>&type=tv">
                                               <?php echo htmlspecialchars($show['title']); ?>
                                           </a>
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                           <div class="d-flex align-items-center gap-2">
                                              <i class="ph ph-translate"></i>
                                              <small class="font-size-12 text-capitalize"><?php echo $show['language']; ?></small>
                                           </div>
                                        </div>
                                     </div>
                                  </div>
                                  
                                  <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                     <a href="watchlist-add.php?id=<?php echo $show['id']; ?>&type=tv"
                                        class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                        data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                        data-bs-title="Add to Watchlist">
                                        <i class="ph ph-plus font-size-18"></i>
                                     </a>
                                     <div class="iq-play-button iq-button">
                                        <a href="movie-detail?id=<?php echo $show['id']; ?>&type=tv" class="btn btn-primary w-100">
                                            Play Now
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
                  <?php else: ?>
                      <p class="text-white px-3">No recommendations available.</p>
                  <?php endif; ?>

               </ul>
               
               <div class="d-none d-lg-block">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<div class="streamit-mobile-footer-menu" aria-label="Mobile Footer Navigation">
    <ul class="footer-menu list-inline d-flex align-items-center justify-content-between m-0">
        <li class="footer-menu-item">
            <a href="/movie-detail" class="menu-link font-size-12">
                <i class="ph ph-film-reel d-block  text-center "></i>
                Movies </a>
        </li>
        <li class="footer-menu-item">
            <a href="/movie-detail" class="menu-link font-size-12">
                <i class="ph ph-monitor-play d-block  text-center "></i>
                Videos </a>
        </li>
        <li class="footer-menu-item">
            <a href="/movie-detail" class="menu- font-size-12">
                <i class="ph ph-magnifying-glass d-block  text-center "></i>
                Search </a>
        </li>
        <li class="footer-menu-item">
            <a href="/movie-detail" class="menu-link font-size-12">
                <i class="ph ph-television d-block  text-center "></i>
                TV Shows </a>
        </li>
        <li class="footer-menu-item">
            <a href="profile-marvin.html" class="menu-link font-size-12">
                <i class="ph ph-user d-block  text-center "></i>
                Profile </a>
        </li>
    </ul>
</div>
  </main>
<?php include 'includes/footer.php'; ?>
<?php
// ==========================================
// 1. HELPER FUNCTIONS (Safety Check)
// ==========================================
if (!function_exists('formatRuntime')) {
    function formatRuntime(int $minutes): string {
        if ($minutes <= 0) return 'N/A';
        $hours = floor($minutes / 60);
        $rem_minutes = $minutes % 60;
        return "{$hours}h {$rem_minutes}m";
    }
}

// Helper: Decide whether a TMDB details block is kid-safe
if (!function_exists('isSafeForKids')) {
    function isSafeForKids(array $details): bool {
        // 1. Immediately reject explicitly adult content
        if (!empty($details['adult'])) return false;

        // 2. Reject mature genres (Horror: 27, Crime: 80, Thriller: 53, War: 10768)
        $adultGenres = [27, 80, 53, 10768];
        if (!empty($details['genre_ids'])) {
            foreach ($details['genre_ids'] as $gId) {
                if (in_array($gId, $adultGenres)) return false;
            }
        }
        if (!empty($details['genres'])) {
            foreach ($details['genres'] as $g) {
                if (in_array($g['id'], $adultGenres)) return false;
            }
        }

        $hasSafeCert = false;
        $hasMatureCert = false;
        $safeCerts = ['G', 'PG', 'PG-13', 'TV-Y', 'TV-Y7', 'TV-G', 'TV-PG', 'TV-14', 'U', 'U/A', 'A', 'ALL'];

        // 3. Check Movie Certifications globally
        if (!empty($details['release_dates']['results'])) {
            foreach ($details['release_dates']['results'] as $result) {
                foreach ($result['release_dates'] as $r) {
                    $cert = strtoupper(trim((string)($r['certification'] ?? '')));
                    if ($cert === '') continue;
                    
                    if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $cert)) {
                        $hasMatureCert = true;
                    } elseif (in_array($cert, $safeCerts)) {
                        $hasSafeCert = true;
                    }
                }
            }
        }

        // 4. Check TV Certifications globally
        if (!empty($details['content_ratings']['results'])) {
            foreach ($details['content_ratings']['results'] as $result) {
                $rating = strtoupper(trim((string)($result['rating'] ?? '')));
                if ($rating === '') continue;
                
                if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $rating)) {
                    $hasMatureCert = true;
                } elseif (in_array($rating, $safeCerts)) {
                    $hasSafeCert = true;
                }
            }
        }

        // 5. Final Decision
        if ($hasMatureCert) return false;        // If we found a safe certification, allow it.
        if ($hasSafeCert) return true;

        // If there are no certifications at all, we've already verified it doesn't have 
        // explicitly mature genres (Horror, Crime, etc) or an 'adult' flag.
        // It's safer to allow it so the catalog isn't completely empty for older/unrated titles.
        return true;
    }
}

// Check current session for Kids Mode.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isKidsModeActive = !empty($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'];

// ==========================================
// 1b. DOWNLOAD TMDB DATA IN PARALLEL
// ==========================================
// The sections below make about seventy TMDB requests. With an empty cache
// (after every deploy, or once the free server has been asleep) they ran one
// after another and the page could take minutes. Fetch everything they are
// about to ask for in three parallel rounds first, so their own
// fetchTmdbApi() calls come straight from the cache. The endpoints and
// parameters here must match those calls exactly, or the cache is missed.
if (function_exists('prefetchTmdbApi')) {
    $movieExtras = ['append_to_response' => 'release_dates,content_ratings'];
    $prefetchLists = [
        'hero'        => ['trending/all/day', []],
        'topTen'      => ['trending/movie/day', []],
        'exclusive'   => ['movie/top_rated', ['page' => 4, 'region' => 'US']],
        'fresh'       => ['trending/movie/week', ['page' => 2]],
        'upcoming'    => ['discover/movie', [
            'region' => 'US',
            'sort_by' => 'popularity.desc',
            'primary_release_date.gte' => date('Y-m-d', strtotime('+1 day')),
            'primary_release_date.lte' => date('Y-m-d', strtotime('+3 months')),
            'with_release_type' => '2|3',
            'page' => 1
        ]],
        'vertical'    => ['movie/top_rated', ['region' => 'US', 'page' => 1]],
        'people'      => ['person/popular', ['page' => 1]],
        'popular'     => ['movie/popular', ['page' => 2]],
        'ott'         => ['trending/tv/week', []],
        'recommended' => ['movie/now_playing', ['page' => 2, 'region' => 'US']],
        'picks'       => ['movie/popular', ['page' => 5, 'region' => 'US']],
    ];

    // Round 1: the lists each section starts from, plus Continue Watching.
    $prefetchRound = array_values($prefetchLists);
    if (isset($_SESSION['user_id']) && isset($conn)) {
        try {
            $prefetchStmt = $conn->prepare("SELECT tmdb_movie_id, media_type FROM watch_history WHERE user_id = ? ORDER BY last_watched DESC LIMIT 20");
            $prefetchStmt->execute([$_SESSION['user_id']]);
            foreach ($prefetchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prefetchRound[] = [($row['media_type'] ?? 'movie') . '/' . $row['tmdb_movie_id'], $movieExtras];
            }
        } catch (Exception $e) {
            // Continue Watching below handles its own errors.
        }
    }
    prefetchTmdbApi($prefetchRound);

    // Round 2: details for the titles in those lists.
    $listResults = function (string $key, int $limit) use ($prefetchLists): array {
        $data = fetchTmdbApi($prefetchLists[$key][0], $prefetchLists[$key][1]);
        return array_slice($data['results'] ?? [], 0, $limit);
    };
    $prefetchRound = [];
    foreach ($listResults('hero', 50) as $item) {
        $prefetchRound[] = ["{$item['media_type']}/{$item['id']}", ['append_to_response' => 'credits,release_dates,content_ratings']];
    }
    foreach (['exclusive' => 10, 'fresh' => 10, 'upcoming' => 12, 'vertical' => 6, 'recommended' => 10, 'picks' => 10] as $key => $limit) {
        foreach ($listResults($key, $limit) as $item) {
            $prefetchRound[] = ["movie/{$item['id']}", $movieExtras];
        }
    }
    $trendingShows = $listResults('ott', 3);
    foreach ($trendingShows as $item) {
        $prefetchRound[] = ["tv/{$item['id']}", ['append_to_response' => 'content_ratings,credits']];
    }
    prefetchTmdbApi($prefetchRound);

    // Round 3: every season of the trending shows, for their episode lists.
    $prefetchRound = [];
    foreach ($trendingShows as $item) {
        $show = fetchTmdbApi("tv/{$item['id']}", ['append_to_response' => 'content_ratings,credits']);
        for ($s = 1; $s <= (int) ($show['number_of_seasons'] ?? 0); $s++) {
            $prefetchRound[] = ["tv/{$item['id']}/season/{$s}", []];
        }
    }
    prefetchTmdbApi($prefetchRound);

    unset($movieExtras, $prefetchLists, $prefetchRound, $prefetchStmt, $listResults, $trendingShows, $item, $show, $s, $row, $key, $limit, $data);
}

// ==========================================
// 2. DATA FETCHING LOGIC
// ==========================================

$heroSlides = [];
// Fetch Trending Movies & TV for the day
$heroData = fetchTmdbApi('trending/all/day');

if ($heroData && !empty($heroData['results'])) {
    // LIMIT TO 5 ITEMS to prevent page timeout/crashing
    $list = array_slice($heroData['results'], 0, 50);

        foreach ($list as $item) {
        $mediaType = $item['media_type']; // 'movie' or 'tv'
        $itemId = $item['id'];

        // Fetch full details (Runtime, Genres, Cast, Content Ratings)
        // We use 'append_to_response' to get everything in 1 request per movie
        $details = fetchTmdbApi("{$mediaType}/{$itemId}", [
            'append_to_response' => 'credits,release_dates,content_ratings'
        ]);

        if (!$details) continue;

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        // Hide TV shows that are not kid-safe when Kids Mode is enabled
        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        // If Kids Mode is active, skip anything not considered kid-safe
        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        // A. Calculate Duration
        $duration = '';
        if ($mediaType === 'movie') {
            $duration = formatRuntime($details['runtime'] ?? 0);
        } else {
            $seasons = $details['number_of_seasons'] ?? 1;
            $duration = $seasons . ($seasons > 1 ? ' Seasons' : ' Season');
        }

        // B. Get Date
        $dateRaw = $details['release_date'] ?? $details['first_air_date'] ?? '';
        $formattedDate = $dateRaw ? date('M Y', strtotime($dateRaw)) : '';

        // C. Get Cast (Top 3)
        $castList = [];
        if (!empty($details['credits']['cast'])) {
            foreach (array_slice($details['credits']['cast'], 0, 3) as $actor) {
                $castList[] = ['id' => $actor['id'], 'name' => $actor['name']];
            }
        }

        // D. Get Age Rating (PG-13, TV-MA, etc.)
        $ageRating = 'PG-13'; // Default
        if ($mediaType === 'movie' && isset($details['release_dates']['results'])) {
            foreach ($details['release_dates']['results'] as $result) {
                if ($result['iso_3166_1'] === 'US') {
                    foreach ($result['release_dates'] as $release) {
                        if (!empty($release['certification'])) {
                            $ageRating = $release['certification'];
                            break 2;
                        }
                    }
                }
            }
        } elseif ($mediaType === 'tv' && isset($details['content_ratings']['results'])) {
            foreach ($details['content_ratings']['results'] as $result) {
                if ($result['iso_3166_1'] === 'US') {
                    $ageRating = $result['rating'];
                    break;
                }
            }
        }

        // E. Build the Array
        $heroSlides[] = [
            'id'           => $details['id'],
            'type'         => $mediaType,
            'title'        => $details['title'] ?? $details['name'],
            'overview'     => $details['overview'],
            // Backdrop. w1280 is sharp on any screen; "original" files were several MB each.
            'bg_url'       => isset($details['backdrop_path']) 
                              ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] 
                              : '/assets/images/media/placeholder.svg',
            // Standard HD Thumb
            'thumb_url'    => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg',
            'rating'       => $details['vote_average'] ?? 0,
            'stars'        => round(($details['vote_average'] ?? 0) / 2),
            'duration'     => $duration,
            'date'         => $formattedDate,
            'genres'       => array_slice($details['genres'] ?? [], 0, 5),
            'cast'         => $castList,
            'age_rating'   => $ageRating
        ];
    }
}
$continueWatching = [];

if (isset($_SESSION['user_id']) && isset($conn)) {
    try {
        $userId = $_SESSION['user_id'];
        
        // 1. Fetch from DB using YOUR column names
        // We fetch 20 items to allow buffer for filtering duplicates
        $sql = "SELECT * FROM watch_history 
                WHERE user_id = :uid 
                ORDER BY last_watched DESC 
                LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $historyList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $addedIds = []; // To track duplicates
        $limit = 6;     // Max items to show
        $count = 0;

        foreach ($historyList as $row) {
            if ($count >= $limit) break;

            // 2. Use YOUR database column name: tmdb_movie_id
            $tmdbId = $row['tmdb_movie_id'];
            $mediaType = $row['media_type'] ?? 'movie';

            // 3. Skip if we already added this movie to the list
            if (in_array($tmdbId, $addedIds)) continue;

            $details = fetchTmdbApi($mediaType . "/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
            
            // Auto-heal old database records that were missing media_type
            if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
                // If it failed as a movie, it might be an old TV show record
                if ($mediaType === 'movie') {
                    $mediaType = 'tv';
                    $details = fetchTmdbApi("tv/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
                }
            }

            if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
                continue;
            }

            if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
                // if the user's continue-watching item is not kid-safe, skip it in Kids Mode
                continue;
            }

            // Mark as added
            $addedIds[] = $tmdbId;
            $count++;

            // 4. Calculate Stats (using YOUR column names: current_time, total_duration)
            $watched = floatval($row['current_time']);
            $total   = floatval($row['total_duration']);
            
            // Avoid division by zero
            $percentage = ($total > 0) ? floor(($watched / $total) * 100) : 0;
            
            // Assuming time is in Seconds. If your DB stores Minutes, remove the (/ 60).
            $minutesLeft = floor(max(0, $total - $watched) / 60); 

            $continueWatching[] = [
                'id'          => $details['id'],
                'type'        => $mediaType,
                'title'       => $details['title'] ?? $details['name'],
                'image_url'   => isset($details['backdrop_path']) 
                                 ? 'https://image.tmdb.org/t/p/w780' . $details['backdrop_path'] 
                                 : '/assets/images/media/placeholder.svg',
                'time_left'   => $minutesLeft . 'm Left',
                'percent'     => $percentage,
                'last_viewed' => date('M d', strtotime($row['last_watched'])),
            ];
        }
    } catch (Exception $e) {
        // Table doesn't exist or connection failed
    }
}
// --- 16. FETCH DATA FOR "TOP 10 MOVIES" BLOCK ---
$topTenList = [];
// Fetch Trending Movies for the day
$topTenData = fetchTmdbApi('trending/movie/day');

if ($topTenData && !empty($topTenData['results'])) {
    // Strictly fill up to 10 items, skipping adult-rated content when Kids Mode is on
    $rank = 1; // Initialize counter
    foreach ($topTenData['results'] as $item) {
        if ($rank > 10) break;

        // If Kids Mode active, skip if base item marks adult
        if (!empty($isKidsModeActive) && !empty($item['adult'])) {
            continue;
        }

        $topTenList[] = [
            'id'           => $item['id'],
            'title'        => $item['title'] ?? ($item['name'] ?? ''),
            'poster_url'   => isset($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] : '/assets/images/media/placeholder-portrait.svg',
            'rank'         => $rank++
        ];
    }
}

// --- 17. FETCH DATA FOR "ONLY ON ZEN" (Exclusives) ---
$exclusiveMovies = [];
// Fetch Top Rated movies from Page 4 to get unique, high-quality content
$exclusiveData = fetchTmdbApi('movie/top_rated', ['page' => 4, 'region' => 'US']);

if ($exclusiveData && !empty($exclusiveData['results'])) {
    // Limit to 10 items
    $list = array_slice($exclusiveData['results'], 0, 10);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);

        if (!$details) continue;

        $exclusiveMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            // w500: sharp on phones and in desktop rows, about half the size of w780
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

// --- 18. FETCH DATA FOR "FRESH PICKS" (Trending Week - Page 2) ---
$freshPicks = [];
// Fetch Page 2 of weekly trends to get "Fresh" content that isn't in the main hero banner
$freshData = fetchTmdbApi('trending/movie/week', ['page' => 2]);

if ($freshData && !empty($freshData['results'])) {
    // Limit to 10 items
    $list = array_slice($freshData['results'], 0, 10);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);

        if (!$details) continue;

        $freshPicks[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            // w500: sharp on phones and in desktop rows, about half the size of w780
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

// --- 6. FETCH DATA FOR "UPCOMING MOVIES" (Strict Future) ---
$upcomingMovies = [];

// 1. Define the date range: Tomorrow onwards
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$threeMonthsLater = date('Y-m-d', strtotime('+3 months'));

// 2. Use 'discover/movie' for precise date filtering
$upcomingData = fetchTmdbApi('discover/movie', [
    'region' => 'US', // Focus on US release dates
    'sort_by' => 'popularity.desc', // Show the most popular ones first
    'primary_release_date.gte' => $tomorrow, // Greater Than or Equal to Tomorrow
    'primary_release_date.lte' => $threeMonthsLater, // Don't show movies 2 years away
    'with_release_type' => '2|3', // 2=Theatrical (Limited), 3=Theatrical
    'page' => 1
]);

if ($upcomingData && !empty($upcomingData['results'])) {
    // Limit to 12 items
    $list = array_slice($upcomingData['results'], 0, 12);

    foreach ($list as $baseMovie) {
        // Double-check: Skip if date is missing or in the past (Safety net)
        if (empty($baseMovie['release_date']) || $baseMovie['release_date'] < $tomorrow) {
            continue;
        }

        // Fetch details for runtime and genres
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);
        if (!$details) continue;

        if (!empty($isKidsModeActive) && !isSafeForKids($details)) {
            continue;
        }

        $upcomingMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            // High Quality Poster
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie', 
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
            // Format date nicely (e.g. "Nov 25, 2025")
            'release_date' => date('M d, Y', strtotime($details['release_date'])),
            'raw_date'     => $details['release_date']
        ];
    }

    // Sort by Release Date (Soonest first)
    usort($upcomingMovies, function ($a, $b) {
        return strtotime($a['raw_date']) - strtotime($b['raw_date']);
    });
}

// --- 10. FETCH DATA FOR "VERTICAL SLIDER" (Top Rated) ---
$verticalSliderMovies = [];
$verticalData = fetchTmdbApi('movie/top_rated', ['region' => 'US', 'page' => 1]);

if ($verticalData && !empty($verticalData['results'])) {
    // Limit to 6 items for the vertical layout
    $list = array_slice($verticalData['results'], 0, 6);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);

        if (!$details) continue;

        $verticalSliderMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            'overview'     => $details['overview'],
            // Big image for the right side (High Quality)
            'backdrop_url' => isset($details['backdrop_path']) 
                              ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] 
                              : '/assets/images/media/placeholder.svg',
            // Smaller image for the left thumb (Optimized)
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg',
            'runtime'      => formatRuntime($details['runtime'] ?? 0),
            'rating'       => $details['vote_average'] ?? 0,
            'stars'        => round(($details['vote_average'] ?? 0) / 2),
            'genres'       => array_slice($details['genres'] ?? [], 0, 3), // Top 3 genres
            'age_rating'   => $details['adult'] ? '18+' : 'PG-13', 
        ];
    }
}

// --- 19. FETCH DATA FOR "FAVOURITE PERSONALITY" (Popular Actors) ---
// --- 19. FETCH DATA FOR "FAVOURITE PERSONALITY" (Popular Actors) ---
$popularPeople = [];
$peopleData = fetchTmdbApi('person/popular', ['page' => 1]);

// Define a default placeholder image (Make sure this file exists in your folder!)
$defaultCastImage = '/assets/images/media/placeholder-portrait.svg'; 

if ($peopleData && !empty($peopleData['results'])) {
    $list = array_slice($peopleData['results'], 0, 11);

    foreach ($list as $person) {
        $role = $person['known_for_department'] ?? 'Actor';
        if ($role === 'Acting' && isset($person['gender'])) {
            $role = ($person['gender'] === 1) ? 'Actress' : 'Actor';
        }

        $popularPeople[] = [
            'id'           => $person['id'],
            'name'         => $person['name'],
            // PHP Check: If API has no path, use default immediately
            'profile_url'  => !empty($person['profile_path']) 
                              ? 'https://image.tmdb.org/t/p/w300' . $person['profile_path'] 
                              : $defaultCastImage, 
            'role'         => $role,
        ];
    }
}

        $popMoviesList = [];
        if (empty($popMoviesList)) {
             $popData = fetchTmdbApi('movie/popular', ['page' => 2]); // Page 2 to differentiate from Top 10
             if ($popData) {
                 foreach (array_slice($popData['results'], 0, 10) as $pm) {
                     // Skip adult-marked items in Kids Mode
                     if (!empty($isKidsModeActive) && !empty($pm['adult'])) continue;
                     $popMoviesList[] = [
                         'id' => $pm['id'],
                         'title' => $pm['title'],
                         'poster_url' => 'https://image.tmdb.org/t/p/w500' . $pm['poster_path'],
                         'genre' => 'Movie', // Simplified for speed
                         'lang' => 'English' // Simplified
                     ];
                 }
             }
        }

// --- 20. FETCH DATA FOR "OTT TAB SLIDER" (Seasons & Episodes) ---
$tabSliderShows = [];
// Fetch Trending TV Shows
$ottData = fetchTmdbApi('trending/tv/week');

if ($ottData && !empty($ottData['results'])) {
    // Limit to Top 3 shows to save API calls (3 shows * 3 seasons = 9 calls)
    $list = array_slice($ottData['results'], 0, 3);
    $rank = 1;

    foreach ($list as $item) {
        $tvId = $item['id'];
        
        // Get Show Details
        $details = fetchTmdbApi("tv/{$tvId}", ['append_to_response' => 'content_ratings,credits']);
        if (!$details) continue;

        $seasonsData = [];
        $seasonCount = $details['number_of_seasons'];
        // Fetch all seasons instead of limiting to 3
        $limitSeasons = $seasonCount; 

        for ($s = 1; $s <= $limitSeasons; $s++) {
            // Fetch specific season details to get episodes
            $seasonDetails = fetchTmdbApi("tv/{$tvId}/season/{$s}");
            
            if ($seasonDetails && !empty($seasonDetails['episodes'])) {
                $seasonsData[] = [
                    'season_number' => $s,
                    // Get all episodes
                    'episodes' => $seasonDetails['episodes']
                ];
            }
        }

        $tabSliderShows[] = [
            'id'           => $details['id'],
            'title'        => $details['name'],
            'overview'     => $details['overview'],
            'backdrop_url' => isset($details['backdrop_path']) 
                              ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] 
                              : '/assets/images/media/placeholder.svg',
            'rank'         => $rank++,
            'date'         => date('F Y', strtotime($details['first_air_date'])),
            'total_seasons'=> $details['number_of_seasons'],
            'seasons_data' => $seasonsData
        ];
    }
}

// --- 22. FETCH DATA FOR "RECOMMENDED BLOCK" (More Now Playing) ---
$recommendedBlockMovies = [];
// Fetch Page 2 of Now Playing to get different movies than the "Latest" section
$recData = fetchTmdbApi('movie/now_playing', ['page' => 2, 'region' => 'US']);

if ($recData && !empty($recData['results'])) {
    // Limit to 10 items
    $list = array_slice($recData['results'], 0, 10);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);

        if (!$details) continue;

        $recommendedBlockMovies[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            // w500: sharp on phones and in desktop rows, about half the size of w780
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

// --- 23. FETCH DATA FOR "TOP PICKS FOR YOU" ---
$topPicks = [];
// Fetch Page 5 of Popular movies to get a unique set of "Must Watch" titles
$picksData = fetchTmdbApi('movie/popular', ['page' => 5, 'region' => 'US']);

if ($picksData && !empty($picksData['results'])) {
    // Limit to 10 items
    $list = array_slice($picksData['results'], 0, 10);

    foreach ($list as $baseMovie) {
        $details = fetchTmdbApi("movie/{$baseMovie['id']}", ['append_to_response' => 'release_dates,content_ratings']);

        if (!$details) continue;

        $topPicks[] = [
            'id'           => $details['id'],
            'title'        => $details['title'],
            // w500: sharp on phones and in desktop rows, about half the size of w780
            'poster_url'   => isset($details['poster_path']) 
                              ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] 
                              : '/assets/images/media/placeholder-portrait.svg', 
            'genre'        => $details['genres'][0]['name'] ?? 'Movie',
            'language'     => isset($details['original_language']) 
                              ? locale_get_display_language($details['original_language'], 'en') 
                              : 'English',
        ];
    }
}

include ("includes/header.php");
?>


<div class="iq-banner-thumb-slider overflow-hidden">
   <div class="slider">
      <div class="position-relative slider-bg my-auto">
         
         <!-- LEFT SIDE: THUMBNAIL NAV -->
         <div class="horizontal_thumb_slider" data-swiper="slider-thumbs-ott">
            <div class="banner-thumb-slider-nav">
               <div class="swiper-container " data-swiper="slider-thumbs-inner-ott">
                  <ul class="swiper-wrapper list-inline p-0 m-0">
                     
                     <?php foreach ($heroSlides as $slide): ?>
                     <li class="swiper-slide swiper-bg">
                        <div class="block-images position-relative ">
                           <div class="img-box">
                              <img src="<?php echo $slide['thumb_url']; ?>" class="img-fluid" alt="img" loading="lazy">
                              <div class="block-description">
                                 <h6 class="iq-title fw-500 line-count-1">
                                     <?php echo htmlspecialchars($slide['title']); ?>
                                 </h6>
                                 <div class="d-flex align-items-center gap-1">
                                    <i class="ph ph-clock"></i>
                                    <span class="fs-12"><?php echo $slide['duration']; ?></span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <?php endforeach; ?>

                  </ul>
                  <div class="slider-prev swiper-button d-flex align-items-center justify-content-center">
                     <i class="ph ph-caret-left"></i>
                  </div>
                  <div class="slider-next swiper-button d-flex align-items-center justify-content-center">
                     <i class="ph ph-caret-right"></i>
                  </div>
               </div>
            </div>
         </div>

         <!-- RIGHT SIDE: MAIN BACKGROUND SLIDER -->
         <div class="slider-images" data-swiper="slider-images-ott">
            <div class="swiper-container" data-swiper="slider-images-inner-ott">
               <ul class="swiper-wrapper m-0 list-inline">
                  
                  <?php foreach ($heroSlides as $heroIndex => $slide): ?>
                  <li class="swiper-slide banner-bg p-0">
                     <div class="slider--image block-images" style="background-image: url(<?php echo $slide['bg_url']; ?>);">
                        <div class="container-fluid position-relative">
                           <div class="row align-items-center h-100 slider-content-full-height">
                              <div class="col-lg-5 col-md-12">
                                 <div class="slider-content">
                                    
                                    <!-- Rank and type -->
                                    <div class="hero-eyebrow RightAnimate-two">
                                       <span class="hero-rank">#<?php echo $heroIndex + 1; ?> Trending</span>
                                       <span class="hero-type"><?php echo $slide['type'] === 'tv' ? 'Series' : 'Movie'; ?></span>
                                    </div>

                                    <!-- Title -->
                                    <h2 class="texture-text big-font letter-spacing-1 line-count-2 RightAnimate-two mb-1 mb-md-3 hero-title">
                                        <?php echo htmlspecialchars($slide['title']); ?>
                                    </h2>

                                    <!-- Facts: age rating, score, length, release date -->
                                    <div class="hero-meta d-flex flex-wrap align-items-center gap-3 py-2 RightAnimate-three">
                                       <?php if (!empty($slide['age_rating'])): ?>
                                       <span class="badge rounded-0 text-white text-uppercase bg-secondary fw-bold hero-age"><?php echo htmlspecialchars($slide['age_rating']); ?></span>
                                       <?php endif; ?>
                                       <span class="hero-rating">
                                          <!-- Stars shaded to the exact score (TMDB scores out of 10). -->
                                          <span class="star-meter" style="--score: <?php echo round(min(10, max(0, (float) $slide['rating'])) * 10); ?>%;" role="img" aria-label="Rated <?php echo number_format($slide['rating'], 1); ?> out of 10">
                                             <span class="star-meter-base" aria-hidden="true">★★★★★</span>
                                             <span class="star-meter-fill" aria-hidden="true">★★★★★</span>
                                          </span>
                                          <img loading="lazy" decoding="async" src="/assets/images/pages/imdb-logo.svg" alt="IMDb" class="img-fluid imdb-img d-none d-md-inline-block">
                                          <span class="text-white fw-bold"><?php echo number_format($slide['rating'], 1); ?></span>
                                       </span>
                                       <?php if (!empty($slide['duration'])): ?>
                                       <span class="hero-fact"><i class="ph ph-clock"></i><?php echo htmlspecialchars($slide['duration']); ?></span>
                                       <?php endif; ?>
                                       <?php if (!empty($slide['date'])): ?>
                                       <span class="hero-fact"><i class="ph ph-calendar-dots"></i><?php echo htmlspecialchars($slide['date']); ?></span>
                                       <?php endif; ?>
                                    </div>

                                    <!-- Genres, linking to their lists -->
                                    <?php if (!empty($slide['genres'])): ?>
                                    <ul class="hero-genres list-unstyled m-0 mt-2 RightAnimate-three">
                                       <?php foreach (array_slice(array_values($slide['genres']), 0, 3) as $genre): ?>
                                       <li><a href="/view-all?type=discover&amp;with_genres=<?php echo (int) $genre['id']; ?>"><?php echo htmlspecialchars($genre['name']); ?></a></li>
                                       <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>

                                    <!-- Overview -->
                                    <p class="line-count-3 my-3 RightAnimate-two hero-overview">
                                        <?php echo htmlspecialchars($slide['overview']); ?>
                                    </p>

                                    <!-- Cast, linking to each person's page -->
                                    <?php if (!empty($slide['cast'])): ?>
                                    <p class="hero-cast RightAnimate-three">
                                       <span class="hero-cast-label">Starring</span>
                                       <?php foreach ($slide['cast'] as $ci => $actor): ?><a href="/person-detail?id=<?php echo (int) $actor['id']; ?>"><?php echo htmlspecialchars($actor['name']); ?></a><?php echo $ci < count($slide['cast']) - 1 ? '<span class="hero-sep">, </span>' : ''; ?><?php endforeach; ?>
                                    </p>
                                    <?php endif; ?>

                                    <!-- Actions -->
                                    <div class="hero-actions RightAnimate-four mt-4 pt-2">
                                       <a href="/watch?id=<?php echo (int) $slide['id']; ?>&amp;type=<?php echo htmlspecialchars($slide['type']); ?><?php echo $slide['type'] === 'tv' ? '&amp;season=1&amp;episode=1' : ''; ?>" class="btn btn-primary text-capitalize position-relative rounded-3 hero-play">
                                          <span class="d-flex align-items-center justify-content-center gap-2">
                                             <i class="ph-fill ph-play fs-6"></i>
                                             <span class="button-text">Play</span>
                                          </span>
                                       </a>
                                       <a href="/<?php echo htmlspecialchars($slide['type']); ?>/<?php echo (int) $slide['id']; ?>" class="hero-more">
                                          <i class="ph ph-info"></i><span>More info</span>
                                       </a>
                                    </div>

                                 </div>
                              </div>
                              <div class="col-lg-7 col-md-12"></div>
                           </div>
                        </div>
                     </div>
                  </li>
                  <?php endforeach; ?>

               </ul>
               <div class="swiper-pagination d-block d-lg-none"></div>
            </div>
         </div>
         
      </div>
   </div>
</div>

<!-- Phones: the featured titles as a strip under the banner, with arrows. -->
<div class="hero-strip d-md-none" aria-label="Featured titles">
   <button type="button" class="hero-nav hero-nav-prev" aria-label="Previous title"><i class="ph ph-caret-left"></i></button>
   <div class="hero-strip-track">
      <?php foreach ($heroSlides as $heroIndex => $slide): ?>
      <button type="button" class="hero-thumb<?php echo $heroIndex === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $heroIndex; ?>" aria-label="<?php echo htmlspecialchars($slide['title']); ?>">
         <img src="<?php echo htmlspecialchars(str_replace('/t/p/w500/', '/t/p/w185/', $slide['thumb_url'])); ?>" alt="" loading="lazy" decoding="async">
      </button>
      <?php endforeach; ?>
   </div>
   <button type="button" class="hero-nav hero-nav-next" aria-label="Next title"><i class="ph ph-caret-right"></i></button>
</div>
<script>
(function () {
   var strip = document.querySelector('.hero-strip');
   var sliderEl = document.querySelector('[data-swiper="slider-images-inner-ott"]');
   if (!strip || !sliderEl) return;
   var thumbs = Array.prototype.slice.call(strip.querySelectorAll('.hero-thumb'));
   var track = strip.querySelector('.hero-strip-track');

   function mark(index) {
      thumbs.forEach(function (thumb, i) { thumb.classList.toggle('is-active', i === index); });
      var active = thumbs[index];
      if (active) track.scrollTo({ left: active.offsetLeft - (track.clientWidth - active.offsetWidth) / 2, behavior: 'smooth' });
   }

   function connect(swiper) {
      swiper.on('slideChange', function () { mark(swiper.realIndex); });
      thumbs.forEach(function (thumb, i) {
         thumb.addEventListener('click', function () { swiper.slideToLoop(i); });
      });
      strip.querySelector('.hero-nav-prev').addEventListener('click', function () { swiper.slidePrev(); });
      strip.querySelector('.hero-nav-next').addEventListener('click', function () { swiper.slideNext(); });
      mark(swiper.realIndex || 0);
   }

   // The banner's Swiper is created by a script further down the page.
   var tries = 0;
   (function waitForSwiper() {
      if (sliderEl.swiper) return connect(sliderEl.swiper);
      if (++tries < 100) setTimeout(waitForSwiper, 100);
   })();
})();
</script>
<div class="container-fluid">
   <div class="overflow-hidden">
     <div class="continue-watching-block home-continue-watch section-padding-top">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Continue Watching</h4>
    </div>
    
    <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="3" data-tab="3" data-mobile="2"
         data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true" data-pagination="false">
         
        <ul class="p-0 swiper-wrapper m-0 list-inline">
            
            <?php if (!empty($continueWatching)): ?>
                <?php foreach ($continueWatching as $item): ?>
                <li class="swiper-slide">
                    <div class="iq-watching-block">
                        <div class="block-images position-relative">
                            
                            <!-- Image & Link -->
                            <div class="iq-image-box overly-images">
                                <a href="/<?php echo $item['type']; ?>/<?php echo $item['id']; ?>" class="d-block">
                                    <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                         class="w-100 d-block border-0 rounded-3 continue-image" loading="lazy">
                                </a>
                            </div>
                            
                            <!-- Progress Info Overlay -->
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
                                    <a href="/<?php echo $item['type']; ?>/<?php echo $item['id']; ?>">
                                        <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                    </a>
                                </div>
                                
                                <!-- Dynamic Progress Bar -->
                                <div class="progress" role="progressbar" aria-label="Progress" 
                                     aria-valuenow="<?php echo $item['percent']; ?>" aria-valuemin="0"
                                     aria-valuemax="100" style="height: 2px">
                                    <!-- The width style here controls the white bar length -->
                                    <div class="progress-bar" style="width: <?php echo $item['percent']; ?>%"></div>
                                </div>
                            </div>
                            
<!-- Close/Remove Icon with Click Event -->
<div class="close-icon-section">
    <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon"
         onclick="removeFromHistory(this, <?php echo $item['id']; ?>)"
         style="cursor: pointer;"
         data-bs-toggle="tooltip" data-bs-placement="left" 
         aria-label="Remove from list" data-bs-original-title="Remove from list">
         <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
    </div>
</div>

<script>
function removeFromHistory(element, mediaId) {
    if(!confirm("Remove this from Continue Watching?")) return;

    const formData = new FormData();
    formData.append('media_id', mediaId);

    fetch('/remove-history', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Remove the slide from the DOM smoothly
            const slide = element.closest('.swiper-slide');
            slide.style.transition = "0.3s ease";
            slide.style.opacity = "0";
            slide.style.transform = "scale(0.8)";
            
            setTimeout(() => {
                slide.remove();
                // Optional: Update swiper if needed, though removing the DOM element usually suffices for visual purposes
            }, 300);

            Toastify({ text: "Removed from History", style: { background: "var(--primary)" } }).showToast();
        } else {
            Toastify({ text: data.message, style: { background: "var(--primary)" } }).showToast();
        }
    })
    .catch(error => console.error('Error:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('main-video');

    if (!video) {
        console.warn('Video Tracker: No video element with id "main-video" found.');
        return;
    }

    const mediaId = video.getAttribute('data-media-id');
    let lastUpdateCall = 0;
    const UPDATE_INTERVAL = 10000; // Update database every 10 seconds

    // 1. Listen for time updates
    video.addEventListener('timeupdate', function() {
        const now = Date.now();
        
        // Throttle: Only send data if 10 seconds have passed since last send
        if (now - lastUpdateCall > UPDATE_INTERVAL) {
            updateHistory(mediaId, video.currentTime, video.duration);
            lastUpdateCall = now;
        }
    });

    // 2. Update immediately when paused or window closed
    video.addEventListener('pause', () => updateHistory(mediaId, video.currentTime, video.duration));
    window.addEventListener('beforeunload', () => updateHistory(mediaId, video.currentTime, video.duration));

    function updateHistory(id, currentTime, duration) {
        if (!id || duration <= 0) return;

        // Calculate percentage (0 to 100)
        const percent = Math.round((currentTime / duration) * 100);
        
        // Prepare data
        const formData = new FormData();
        formData.append('media_id', id);
        formData.append('current_time', currentTime);
        formData.append('duration', duration);
        formData.append('percent', percent);

        // Send to Backend
        // Use 'navigator.sendBeacon' for reliability on page close, or fallback to fetch
        const url = '/update-history'; // Make sure this path matches Step 2

        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, formData);
        } else {
            fetch(url, {
                method: 'POST',
                body: formData
            }).catch(err => console.error('History Error:', err));
        }
    }
});
</script>
                            
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-white px-3">You haven't watched any movies yet.</p>
            <?php endif; ?>

        </ul>

        <div class="d-none d-lg-block">
            <div class="swiper-button swiper-button-next"></div>
            <div class="swiper-button swiper-button-prev"></div>
        </div>
    </div>
</div>

<div class="top-ten-block">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Top 10 Movies To Watch</h4>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card iq-top-ten-block-slider" data-slide="6" data-laptop="6"
             data-tab="3" data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false"
             data-navigation="true" data-pagination="true">
             
            <ul class="p-0 swiper-wrapper mb-5 list-inline">
                
                <?php if (!empty($topTenList)): ?>
                    <?php foreach ($topTenList as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-top-ten-block position-relative">
                                <div class="block-image position-relative">
                                    <div class="img-box">
                                        <a class="overly-images" href="/movie/<?php echo $movie['id']; ?>">
                                            <!-- Added loading="lazy" and decoding="async" here -->
                                            <img src="<?php echo $movie['poster_url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="object-cover rounded-3" 
                                                 loading="lazy" 
                                                 decoding="async">
                                        </a>
                                        <!-- The Rank Number (1-10) -->
                                        <span class="top-ten-numbers texture-text">
                                            <?php echo $movie['rank']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No trending movies found today.</p>
                <?php endif; ?>

            </ul>
            
            <div class="swiper-button swiper-button-next"></div>
            <div class="swiper-button swiper-button-prev"></div>
        </div>
    </div>
</div>

      <div class="streamit-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-4">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Only on ZEN</h4>
        <a href="view-all?type=exclusive" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($exclusiveMovies)): ?>
                    <?php foreach ($exclusiveMovies as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <!-- Poster Image -->
                                    <div class="img-box w-100">
                                        <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" 
                                                 loading="lazy" decoding="async">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="/movie/<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="/movie/<?php echo $movie['id']; ?>">
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
                                        
                                        <!-- Action Buttons -->
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="/add-watchlist?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- Brand/Premium Icon -->
                                    <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
                                    </div>
                                    
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No exclusive movies found.</p>
                <?php endif; ?>

            </ul>
            
            <!-- Navigation Arrows -->
            <div class="d-none d-lg-block">
                <div class="swiper-button swiper-button-next"></div>
                <div class="swiper-button swiper-button-prev"></div>
            </div>
        </div>
    </div>
</div>

<div class="streamit-card-height-block">
    <div class="d-flex align-items-center justify-content-between px-1 mb-4">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Fresh Picks Just For You</h4>
        <a href="view-all?type=fresh" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="5" data-laptop="3" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($freshPicks)): ?>
                    <?php foreach ($freshPicks as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover landscape-card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <!-- Poster Image -->
                                    <div class="img-box w-100">
                                        <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" 
                                                 loading="lazy" decoding="async">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="/movie/<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize mb-0">
                                                    <a href="/movie/<?php echo $movie['id']; ?>">
                                                        <?php echo htmlspecialchars($movie['title']); ?>
                                                    </a>
                                                </h5>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="/add-watchlist?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- Premium/Pro Icon -->
                                    <div class="position-absolute z-1 premium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
                                    </div>
                                    
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No fresh picks available.</p>
                <?php endif; ?>

            </ul>
            
            <!-- Navigation -->
            <div class="d-none d-lg-block">
                <div class="swiper-button swiper-button-next"></div>
                <div class="swiper-button swiper-button-prev"></div>
            </div>
        </div>
    </div>
</div>

     <div class="upcomimg-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Upcoming Movies</h4>
        <a href="view-all?type=upcoming" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
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
                                    
                                    <!-- Poster Image -->
                                    <div class="img-box w-100">
                                        <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" 
                                                 loading="lazy" decoding="async">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="/movie/<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="/movie/<?php echo $movie['id']; ?>">
                                                        <?php echo htmlspecialchars($movie['title']); ?>
                                                    </a>
                                                </h5>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="ph ph-calendar text-warning"></i>
                                                        <!-- Display Release Date in Yellow -->
                                                        <small class="font-size-12 text-capitalize text-warning">
                                                            <?php echo $movie['release_date']; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Buttons -->
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="/add-watchlist?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <!-- Changed text to Pre-Order for logic consistency -->
                                                <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Pre-Order
                                                </a>
                                            </div>
                                        </div>
                                        
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
   </div>
</div>

<!-- Scoped styles: make the vertical slider previous/next buttons smaller and horizontally centered -->
<style>
    /* target only the vertical slider on the homepage to avoid global overrides */
    .verticle-slider .slider--col { position: relative; }
    .verticle-slider .slider--col .vertical-slider-prev,
    .verticle-slider .slider--col .vertical-slider-next {
        position: absolute;
        left: 50%; /* center horizontally */
        transform: translateX(-50%);
        width: 36px;
        height: 36px;
        display: flex !important;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255,255,255,0.06);
        color: #ffffff;
        cursor: pointer;
        box-shadow: none;
        transition: transform 120ms ease, background 120ms ease, opacity 120ms ease;
        z-index: 20;
    }

    /* keep one near the top and one near the bottom, but tightened up */
    .verticle-slider .slider--col .vertical-slider-prev { top: 8px; }
    .verticle-slider .slider--col .vertical-slider-next { bottom: 8px; }

    /* icon sizing inside button */
    .verticle-slider .slider--col .vertical-slider-prev i,
    .verticle-slider .slider--col .vertical-slider-next i {
        font-size: 16px;
        line-height: 1;
    }

    /* stronger hover affordance */
    .verticle-slider .slider--col .vertical-slider-prev:hover,
    .verticle-slider .slider--col .vertical-slider-next:hover {
        transform: translateX(-50%) scale(1.06);
        background: rgba(255,255,255,0.12);
        opacity: 1;
    }

    /* smaller footprint on very small devices */
    @media (max-width: 576px) {
        .verticle-slider .slider--col .vertical-slider-prev,
        .verticle-slider .slider--col .vertical-slider-next {
            width: 32px; height: 32px; font-size: 14px;
        }
    }

    /* ---------------------------------------------------------------
       Mobile home page
       --------------------------------------------------------------- */
    @media (max-width: 991.98px) {
        /* The IMDb logo rendered 63x32, twice the height of the rating
           beside it, which pushed the number onto its own line. */
        .imdb-img { height: 18px !important; width: auto !important; vertical-align: middle; }
        .slider-content span:has(> .imdb-img) { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

        /* Touch screens swipe; the arrow buttons only covered the posters. */
        .swiper-button { display: none !important; }

        /* Swiper's default dots are iOS blue when active and black at 20%
           otherwise, which disappears against this dark background. */
        .swiper-pagination-bullet { background: #fff !important; opacity: 0.35 !important; transition: width 0.2s ease; }
        .swiper-pagination-bullet-active { background: var(--bs-primary, #e50914) !important; opacity: 1 !important; width: 18px !important; border-radius: 4px !important; }

        /* Floating AI and theme buttons: tuck them against the edge with
           their centres lined up, and slide them away while scrolling down
           so they don't cover posters and text. Scrolling up brings them back. */
        .zen-ai-float { right: 14px !important; }
        .theme-switcher-float { right: 19px !important; }
        .zen-ai-float, .theme-switcher-float { transition: transform 0.25s ease, opacity 0.25s ease !important; }
        body.floats-away .zen-ai-float,
        body.floats-away .theme-switcher-float { transform: translateX(96px) !important; opacity: 0 !important; pointer-events: none !important; }
    }
    /* Continue Watching: the title and progress strip covers the lower part
       of each card but wasn't a link, so taps there did nothing. Let taps
       pass through to the card link underneath; the play and remove
       buttons stay tappable. */
    .continue-watching-block .iq-preogress { pointer-events: none; }
    .continue-watching-block .iq-preogress a { pointer-events: auto; }

    /* ---- Home hero -------------------------------------------------------- */
    .hero-eyebrow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
    .hero-rank { padding: 4px 10px; border-radius: 999px; background: var(--bs-primary, #e50914); color: #fff; }
    .hero-type { padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, 0.14); color: #e0e0ea; backdrop-filter: blur(6px); }
    .hero-meta { color: #e6e6ee; }
    .hero-rating { display: inline-flex; align-items: center; gap: 6px; }
    .hero-fact { display: inline-flex; align-items: center; gap: 5px; font-weight: 500; }
    .hero-genres { display: flex; flex-wrap: wrap; gap: 8px; padding: 0; }
    .hero-genres a { display: inline-flex; align-items: center; min-height: 0; padding: 4px 12px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.18); background: rgba(255, 255, 255, 0.06); color: #e6e6ee; font-size: 0.8rem; text-decoration: none; backdrop-filter: blur(6px); }
    .hero-genres a:hover { border-color: var(--bs-primary, #e50914); color: #fff; }
    .hero-cast { margin: 0; color: #cfcfd8; font-size: 0.88rem; line-height: 1.6; }
    .hero-cast-label { margin-right: 6px; color: var(--bs-primary, #e50914); font-weight: 600; }
    .hero-cast a { display: inline; min-height: 0; color: #fff; text-decoration: none; border-bottom: 1px solid rgba(255, 255, 255, 0.25); }
    .hero-cast a:hover { border-bottom-color: var(--bs-primary, #e50914); }
    .hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
    /* Centre the icon and label: on phones the site-wide button rule makes this
       a flex box, which otherwise lines its content up on the left. */
    .hero-actions .hero-play { display: inline-flex; align-items: center; justify-content: center; min-width: 140px; }
    .hero-more { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 20px; border-radius: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.25); background: rgba(255, 255, 255, 0.1); color: #fff; font-weight: 600; text-decoration: none; backdrop-filter: blur(8px); }
    .hero-more:hover { background: rgba(255, 255, 255, 0.2); color: #fff; }
    .hero-more i { font-size: 1.15rem; }

    /* Five stars shaded to the exact score. The icon-font stars always drew
       as outlines, so every film looked unrated. */
    .star-meter { position: relative; display: inline-block; font-size: 15px; line-height: 1; letter-spacing: 1px; white-space: nowrap; vertical-align: middle; }
    .star-meter-base { color: rgba(255, 255, 255, 0.28); }
    .star-meter-fill { position: absolute; top: 0; left: 0; bottom: 0; width: var(--score, 0%); overflow: hidden; color: #f5c518; }

    /* Title strip under the banner (phones) */
    .hero-strip { display: flex; align-items: center; gap: 8px; padding: 10px 12px 2px; background: #0b0c15; }
    .hero-strip-track { position: relative; flex: 1; display: flex; gap: 8px; padding: 4px 2px 6px; overflow-x: auto; scrollbar-width: none; }
    .hero-strip-track::-webkit-scrollbar { display: none; }
    .hero-thumb { flex: 0 0 54px; aspect-ratio: 2 / 3; padding: 0; overflow: hidden; border: 2px solid transparent; border-radius: 10px; background: #1a1a24; opacity: 0.5; transition: opacity 0.2s, border-color 0.2s, transform 0.2s; }
    .hero-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .hero-thumb.is-active { opacity: 1; border-color: var(--bs-primary, #e50914); transform: translateY(-2px); }
    .hero-nav { flex-shrink: 0; display: grid; place-items: center; width: 36px; height: 36px; min-height: 36px; padding: 0; border-radius: 50%; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(255, 255, 255, 0.06); color: #fff; font-size: 1.1rem; }
    .hero-nav:active { transform: scale(0.92); }

    /* The hero on phones: full-bleed picture, everything grouped at the
       bottom and centred like a poster, then the title strip. */
    @media (max-width: 767.98px) {
        .iq-banner-thumb-slider .slider--image { height: min(76vh, 600px) !important; min-height: 540px; background-position: center 20% !important; }
        .iq-banner-thumb-slider .slider--image::before { background: linear-gradient(180deg, rgba(11, 12, 21, 0.55) 0%, rgba(11, 12, 21, 0) 18%, rgba(11, 12, 21, 0) 30%, rgba(11, 12, 21, 0.9) 64%, #0b0c15 100%) !important; }
        .iq-banner-thumb-slider .slider--image > .container-fluid,
        .iq-banner-thumb-slider .slider-content-full-height { height: 100% !important; }
        /* The row also holds an empty desktop column, so on phones it wraps into
           two lines; align-content keeps both at the bottom. */
        .iq-banner-thumb-slider .slider-content-full-height { align-items: flex-end !important; align-content: flex-end !important; padding-top: 0 !important; padding-bottom: 18px !important; }
        .iq-banner-thumb-slider .slider-content { text-align: center; }
        .iq-banner-thumb-slider .swiper-pagination { display: none !important; }
        .hero-eyebrow, .hero-meta, .hero-genres, .hero-actions { justify-content: center; }
        .iq-banner-thumb-slider .hero-title { margin-bottom: 6px !important; background: none !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font-size: 2.05rem !important; line-height: 1.08 !important; letter-spacing: -0.5px !important; text-shadow: 0 2px 18px rgba(0, 0, 0, 0.55); }
        .hero-meta { gap: 6px 12px !important; padding: 2px 0 !important; font-size: 0.85rem; }
        .hero-meta .hero-age { padding: 3px 6px; font-size: 0.68rem; }
        .hero-genres { margin-top: 8px !important; }
        .hero-genres a { padding: 3px 10px; font-size: 0.74rem; }
        .iq-banner-thumb-slider .hero-overview { margin: 10px 0 8px !important; color: #c9c9d3; font-size: 0.88rem; -webkit-line-clamp: 2 !important; }
        .hero-cast { font-size: 0.8rem; }
        .hero-actions { gap: 10px; margin-top: 14px !important; padding-top: 0 !important; }
        .hero-actions .hero-play,
        .hero-actions .hero-more { flex: 1 1 0; min-width: 0; max-width: 175px; min-height: 48px; }
    }
</style>

<div class="verticle-slider section-padding-bottom">
   <div class="slider">
      <div class="slider-flex position-relative">
         
         <!-- LEFT COLUMN: THUMBNAIL NAVIGATION -->
         <div class="slider--col position-relative">
            <div class="vertical-slider-prev swiper-button"><i class="iconly-Arrow-Up-2 icli"></i></div>
            <div class="slider-thumbs" data-swiper="slider-thumbs">
               <div class="swiper-container " data-swiper="slider-thumbs-inner">
                  <div class="swiper-wrapper top-ten-slider-nav">
                     
                     <?php foreach ($verticalSliderMovies as $movie): ?>
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative ">
                           <div class="img-box slider--image">
                              <img src="<?php echo $movie['poster_url']; ?>" class="w-100 rounded-3" 
                                   alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                   loading="lazy" decoding="async">
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
                     </div>
                     <?php endforeach; ?>

                  </div>
               </div>
            </div>
            <div class="vertical-slider-next swiper-button"><i class="iconly-Arrow-Down-2 icli"></i></div>
         </div>

         <!-- RIGHT COLUMN: MAIN CONTENT -->
         <div class="slider-images" data-swiper="slider-images">
            <div class="swiper-container " data-swiper="slider-images-inner">
               <div class="swiper-wrapper ">
                  
                  <?php foreach ($verticalSliderMovies as $movie): ?>
                  <div class="swiper-slide">
                     <div class="slider--image block-images">
                         <img src="<?php echo $movie['backdrop_url']; ?>" loading="lazy" decoding="async" 
                              alt="<?php echo htmlspecialchars($movie['title']); ?>" />
                     </div>
                     
                     <div class="description">
                        <div class="block-description">
                           
                           <!-- Genres Tags -->
                           <ul class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <?php foreach ($movie['genres'] as $genre): ?>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="#" class="text-decoration-none"><?php echo $genre['name']; ?></a>
                              </li>
                              <?php endforeach; ?>
                           </ul>

                           <!-- Title -->
                           <h2 class="iq-title m-0 line-count-2">
                               <a href="/movie/<?php echo $movie['id']; ?>">
                                   <?php echo htmlspecialchars($movie['title']); ?>
                               </a>
                           </h2>

                           <!-- Ratings & Metadata -->
                           <div class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                       <li><i class="ph<?php echo ($i <= $movie['stars']) ? '-fill' : ''; ?> ph-star"></i></li>
                                    <?php endfor; ?>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0"><?php echo number_format($movie['rating'], 1); ?></p>
                                 <img loading="lazy" decoding="async" class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body"><?php echo $movie['runtime']; ?></span>
                              </div>
                           </div>

                           <!-- Description -->
                           <p class="mt-2 mb-3 line-count-3">
                               <?php echo htmlspecialchars($movie['overview']); ?>
                           </p>

                           <!-- Play Button -->
                           <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary text-capitalize position-relative rounded-3">
                              <span class="d-flex align-items-center gap-2">
                                 <span class="button-text">play now</span>
                                 <i class="ph-fill ph-play fs-6"></i>
                              </span>
                           </a>

                        </div>
                     </div>
                  </div>
                  <?php endforeach; ?>

               </div>
               
               <!-- Mobile Navigation Arrows -->
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
      
      <!-- SECTION: FAVOURITE PERSONALITY -->
     <div class="favourite-person-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Your Favourite Personality</h4>
        <a href="view-all?type=person" class="text-primary iq-view-all text-decoration-none">View All</a>
    </div>
    
    <div class="position-relative swiper swiper-card" data-slide="11" data-laptop="11" data-tab="4" data-mobile="2"
       data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true" data-pagination="true">
       
       <ul class="p-0 swiper-wrapper m-0 list-inline personality-card">
          
          <?php if (!empty($popularPeople)): ?>
              <?php foreach ($popularPeople as $person): ?>
              <li class="swiper-slide">
                 <a href="person-detail?id=<?php echo $person['id']; ?>">
                     <!-- ADDED: onerror event to handle broken images -->
                     <img src="<?php echo $person['profile_url']; ?>" 
                          alt="<?php echo htmlspecialchars($person['name']); ?>" 
                          class="img-fluid object-cover mb-3 rounded-3 personality-img" 
                          loading="lazy" 
                          decoding="async"
                          onerror="this.onerror=null; this.src='<?php echo $defaultCastImage; ?>';">
                 </a>
                 <div class="text-center">
                    <h6 class="mb-0">
                       <a href="person-detail.php?id=<?php echo $person['id']; ?>" class="font-size-14 text-decoration-none cast-title text-capitalize">
                           <?php echo htmlspecialchars($person['name']); ?>
                       </a>
                    </h6>
                    <a href="person-detail.php?id=<?php echo $person['id']; ?>" class="font-size-12 fw-semibold text-decoration-none text-capitalize text-body">
                        <?php echo $person['role']; ?>
                    </a>
                 </div>
              </li>
              <?php endforeach; ?>
          <?php else: ?>
              <p class="text-white px-3">No popular personalities found.</p>
          <?php endif; ?>

       </ul>
       
       <div class="d-none d-lg-block">
          <div class="swiper-button swiper-button-next"></div>
          <div class="swiper-button swiper-button-prev"></div>
       </div>
    </div>
</div>
      <div class="popular-movies-block section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Popular Movies</h4>
            <a href="view-all?type=popular" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0 list-inline">
                  
                  <?php foreach ($popMoviesList as $movie): ?>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                        <div class="block-images position-relative w-100">
                           <div class="img-box w-100">
                              <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                 <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                    class="img-fluid object-cover w-100 d-block border-0 rounded-3" loading="lazy">
                              </a>
                           </div>
                           <div class="card-description with-transition">
                              <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                 <li class="fw-semi-bold">
                                    <a href="#" tabindex="0" class="font-size-14">Movie</a>
                                 </li>
                              </ul>
                              <div class="cart-content">
                                 <div class="content-left">
                                    <h5 class="iq-title text-capitalize">
                                       <a href="/movie/<?php echo $movie['id']; ?>"><?php echo htmlspecialchars($movie['title']); ?></a>
                                    </h5>
                                    <div class="d-flex align-items-center gap-3">
                                       <div class="d-flex align-items-center gap-2">
                                          <i class="ph ph-translate"></i>
                                          <small class="font-size-12">English</small>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                              <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                 <a href="/add-watchlist?id=<?php echo $movie['id']; ?>" class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary">
                                    <i class="ph ph-plus font-size-18"></i>
                                 </a>
                                 <div class="iq-play-button iq-button">
                                    <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">Play Now</a>
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
               <div class="d-none d-lg-block">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>

   </div>
</div>

<div class="tab-slider otthome-tab-slider">
   <div class="slider">
      <div class="position-relative swiper swiper-card" data-slide="1" data-laptop="1" data-tab="1" data-mobile="1"
         data-mobile-sm="1" data-autoplay="false" data-loop="true" data-navigation="true" data-pagination="true"
         data-effect="fade">
         
         <ul class="p-0 swiper-wrapper m-0 list-inline">
            
            <?php if (!empty($tabSliderShows)): ?>
                <?php foreach ($tabSliderShows as $show): ?>
                <li class="swiper-slide tab-slider-banner p-0">
                   <!-- Background Image -->
                   <div class="tab-slider-banner-images" style="background-image: url(<?php echo $show['backdrop_url']; ?>);">
                      <div class="block-images position-relative w-100">
                         <div class="container-fluid">
                            <div class="row align-items-center h-100 my-4">
                               
                               <!-- LEFT SIDE: Show Info -->
                               <div class="col-lg-5 col-xxl-5">
                                  <div class="tab-left-details">
                                     <div class="d-flex align-items-center gap-3 mb-4">
                                        <a href="javascript:void(0);">
                                            <img loading="lazy" decoding="async" src="assets/images/pages/trending-label.webp" class="img-fluid trending-label-img rounded-3" alt="img">
                                        </a>
                                        <span class="text-gold fw-bold font-size-18">#<?php echo $show['rank']; ?> in Series today</span>
                                     </div>
                                     <h1 class="mb-2 fw-500 text-capitalize texture-text">
                                         <?php echo htmlspecialchars($show['title']); ?>
                                     </h1>
                                     <p class="my-3 line-count-3 RightAnimate-three">
                                         <?php echo htmlspecialchars($show['overview']); ?>
                                     </p>
                                     <ul class="d-flex align-items-center list-inline gap-2 movie-tag p-0 mt-3 mb-40">
                                        <li class="font-size-18 trending-list"><?php echo $show['date']; ?></li>
                                        <li class="font-size-18"><?php echo $show['total_seasons']; ?> Seasons</li>
                                     </ul>
                                     <a href="/tv/<?php echo $show['id']; ?>" class="btn btn-primary text-capitalize position-relative rounded-3">
                                        <span class="d-flex align-items-center gap-2">
                                           <span class="button-text">Stream Now</span>
                                           <i class="ph-fill ph-play fs-6"></i>
                                        </span>
                                     </a>
                                  </div>
                               </div>
                               
                               <!-- Spacer -->
                               <div class="col-md-1 col-lg-2 col-xxl-3"></div>
                               
                               <!-- RIGHT SIDE: Episode Tabs -->
                               <div class="col-md-6 col-lg-5 col-xxl-3 mt-5 mt-md-0 d-none d-lg-block">
                                  <div class="tab-block">
                                     <h4 class="tab-title text-capitalize mb-0">Episodes</h4>
                                     
                                     <!-- Tab Headers (Season 1, Season 2...) -->
                                     <div class="tab-bottom-bordered border-0">
                                        <ul class="nav nav-tabs nav-pills mb-3 flex-nowrap custom-scrollbar" role="tablist" style="overflow-x: auto; overflow-y: hidden;">
                                           <?php foreach ($show['seasons_data'] as $index => $season): ?>
                                           <li class="nav-item" role="presentation">
                                              <button class="nav-link <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                                      data-bs-toggle="pill"
                                                      data-bs-target="#season-tab-<?php echo $show['id']; ?>-<?php echo $season['season_number']; ?>" 
                                                      type="button" role="tab"
                                                      style="white-space: nowrap;"
                                                      aria-selected="<?php echo ($index === 0) ? 'true' : 'false'; ?>">
                                                  Season <?php echo $season['season_number']; ?>
                                              </button>
                                           </li>
                                           <?php endforeach; ?>
                                        </ul>
                                     </div>

                                     <!-- Tab Content (Episode Lists) -->
                                     <div class="tab-content iq-tab-fade-up">
                                        
                                        <?php foreach ($show['seasons_data'] as $index => $season): ?>
                                        <div class="tab-pane fade <?php echo ($index === 0) ? 'show active' : ''; ?>" 
                                             id="season-tab-<?php echo $show['id']; ?>-<?php echo $season['season_number']; ?>"
                                             role="tabpanel" tabindex="0">
                                           
                                           <ul class="list-inline m-0 p-0 pe-2 custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                                              <?php foreach ($season['episodes'] as $ep): ?>
                                              <li class="mb-3">
                                                 <a href="watch?id=<?php echo $show['id']; ?>&type=tv&season=<?php echo $season['season_number']; ?>&episode=<?php echo $ep['episode_number']; ?>" class="d-flex align-items-center gap-3 text-white text-decoration-none episode-link-hover">
                                                     <div class="image-box flex-shrink-0 position-relative">
                                                        <!-- Episode Thumbnail -->
                                                        <img src="<?php echo isset($ep['still_path']) ? 'https://image.tmdb.org/t/p/w185'.$ep['still_path'] : 'assets/images/media/placeholder.webp'; ?>" 
                                                             alt="" loading="lazy" decoding="async" class="img-fluid rounded" style="width: 80px; height: 45px; object-fit: cover;">
                                                        <div class="play-overlay position-absolute top-50 start-50 translate-middle" style="background: rgba(0,0,0,0.5); border-radius: 50%; padding: 5px; display: none;">
                                                            <i class="ph-fill ph-play text-white font-size-12"></i>
                                                        </div>
                                                     </div>
                                                     <div class="image-details">
                                                        <h6 class="mb-1 text-capitalize font-size-14 line-count-1">
                                                            E<?php echo $ep['episode_number']; ?>: <?php echo htmlspecialchars($ep['name']); ?>
                                                        </h6>
                                                        <div class="episode-time d-flex align-items-center gap-1 text-muted">
                                                           <i class="ph ph-clock font-size-12"></i>
                                                           <small class="font-size-12"><?php echo $ep['runtime'] ?? '45'; ?>m</small>
                                                        </div>
                                                     </div>
                                                 </a>
                                              </li>
                                              <?php endforeach; ?>
                                           </ul>
                                           
                                        </div>
                                        <?php endforeach; ?>

                                     </div>
                                  </div>
                               </div>

                            </div>
                         </div>
                      </div>
                   </div>
                </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="swiper-slide"><p class="text-white p-5">No trending shows available.</p></li>
            <?php endif; ?>

         </ul>
         
         <div class="joint-arrows d-none d-lg-block">
            <div class="swiper-button swiper-button-next"></div>
            <div class="swiper-button swiper-button-prev"></div>
         </div>
         <div class="swiper-pagination d-block d-lg-none"></div>

      </div>
   </div>
</div>

<div class="container-fluid">
   <div class="overflow-hidden">
     <div class="recommended-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Recommended For You</h4>
        <a href="view-all?type=recommended" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($recommendedBlockMovies)): ?>
                    <?php foreach ($recommendedBlockMovies as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <!-- Poster -->
                                    <div class="img-box w-100">
                                        <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" 
                                                 loading="lazy" decoding="async">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="/movie/<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="/movie/<?php echo $movie['id']; ?>">
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
                                        
                                        <!-- Buttons -->
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="/add-watchlist?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- Premium Icon -->
                                    <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
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

<div class="top-pics-block section-wraper">
    <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
        <h4 class="main-title text-capitalize mb-0 fw-medium">Top Picks For You</h4>
        <a href="view-all?type=toppicks" class="text-primary iq-view-all text-decoration-none flex-none">View All</a>
    </div>
    
    <div class="card-style-slider">
        <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="6" data-tab="3"
             data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
             data-pagination="true">
             
            <ul class="p-0 swiper-wrapper m-0 list-inline">
                
                <?php if (!empty($topPicks)): ?>
                    <?php foreach ($topPicks as $movie): ?>
                        <li class="swiper-slide">
                            <div class="iq-card card-hover">
                                <div class="block-images position-relative w-100">
                                    
                                    <!-- Poster -->
                                    <div class="img-box w-100">
                                        <a href="/movie/<?php echo $movie['id']; ?>" class="position-relative top-0 bottom-0 start-0 end-0">
                                            <img src="<?php echo $movie['poster_url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                 class="img-fluid object-cover w-100 d-block border-0 rounded-3" 
                                                 loading="lazy" decoding="async">
                                        </a>
                                    </div>
                                    
                                    <div class="card-description with-transition">
                                        <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                                            <li class="fw-semi-bold">
                                                <a href="/movie/<?php echo $movie['id']; ?>" tabindex="0" class="font-size-14">
                                                    <?php echo htmlspecialchars($movie['genre']); ?>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <div class="cart-content">
                                            <div class="content-left">
                                                <h5 class="iq-title text-capitalize">
                                                    <a href="/movie/<?php echo $movie['id']; ?>">
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
                                        
                                        <!-- Buttons -->
                                        <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                                            <a href="/add-watchlist?id=<?php echo $movie['id']; ?>"
                                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                               data-bs-title="Add to Watchlist">
                                                <i class="ph ph-plus font-size-18"></i>
                                            </a>
                                            <div class="iq-play-button iq-button">
                                                <a href="/movie/<?php echo $movie['id']; ?>" class="btn btn-primary w-100">
                                                    Play Now
                                                </a>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- Premium Icon -->
                                    <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                                         data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                                        <i class="ph-fill ph-crown"></i>
                                    </div>
                                    
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white px-3">No top picks available.</p>
                <?php endif; ?>

            </ul>
            
            <div class="d-none d-lg-block">
                <div class="swiper-button swiper-button-next"></div>
                <div class="swiper-button swiper-button-prev"></div>
            </div>
        </div>
    </div>
</div>
</main>
<script>
// Mobile home: slide the floating AI and theme buttons out of the way while
// the page scrolls down, and bring them back when it scrolls up.
(function () {
    const mobile = window.matchMedia('(max-width: 991.98px)');
    let lastY = window.scrollY;
    let ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            const y = window.scrollY;
            if (!mobile.matches) {
                document.body.classList.remove('floats-away');
            } else if (Math.abs(y - lastY) > 8) {
                document.body.classList.toggle('floats-away', y > lastY && y > 120);
                lastY = y;
            }
            ticking = false;
        });
    }, { passive: true });
})();
</script>
<?php include 'includes/footer.php'; ?>

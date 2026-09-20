<?php
/**
 * Picks built from what a member has actually watched.
 *
 * Each of their recent titles is a "seed": TMDB is asked what people who
 * liked that title also liked, and the answers are scored across all the
 * seeds, so a title suggested by several of them rises to the top. Recent
 * viewing counts for more than older viewing, and anything already watched
 * or already on the watchlist is left out.
 *
 * The result is cached per member and rebuilt as soon as they watch
 * something new, because the cache key includes their latest history.
 */

const RECS_CACHE_SECONDS = 21600; // 6 hours
const RECS_SEEDS         = 8;     // recent titles the picks are based on
const RECS_PER_SEED      = 12;    // suggestions taken from each seed

function recsCacheDir(): string
{
    $dir = __DIR__ . '/../cache/recs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Titles the member has watched or saved: the seeds, and what to leave out. */
function recsSeeds(PDO $conn, int $userId): array
{
    $seeds = [];
    $seen = [];
    try {
        $stmt = $conn->prepare("SELECT tmdb_movie_id, media_type, last_watched FROM watch_history
                                 WHERE user_id = ? ORDER BY last_watched DESC LIMIT 30");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = ($row['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
            $key = $type . ':' . (int) $row['tmdb_movie_id'];
            $seen[$key] = true;
            if (count($seeds) < RECS_SEEDS && !isset($seeds[$key])) {
                $seeds[$key] = ['id' => (int) $row['tmdb_movie_id'], 'type' => $type, 'weight' => 1.0, 'at' => $row['last_watched']];
            }
        }
    } catch (PDOException $e) {
        error_log('recsSeeds history: ' . $e->getMessage());
    }
    try {
        // Saved for later says as much as watched, but a little less.
        $stmt = $conn->prepare("SELECT tmdb_movie_id, media_type FROM watchlist
                                 WHERE user_id = ? ORDER BY date_added DESC LIMIT 12");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = ($row['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
            $key = $type . ':' . (int) $row['tmdb_movie_id'];
            $seen[$key] = true;
            if (count($seeds) < RECS_SEEDS + 3 && !isset($seeds[$key])) {
                $seeds[$key] = ['id' => (int) $row['tmdb_movie_id'], 'type' => $type, 'weight' => 0.6, 'at' => null];
            }
        }
    } catch (PDOException $e) {
        error_log('recsSeeds watchlist: ' . $e->getMessage());
    }
    return [array_values($seeds), $seen];
}

/** TMDB genre id => name, for both movies and shows. */
function recsGenreNames(): array
{
    static $names = null;
    if ($names !== null) {
        return $names;
    }
    $names = [];
    foreach (['genre/movie/list', 'genre/tv/list'] as $endpoint) {
        foreach (fetchTmdbApi($endpoint, [], 2592000)['genres'] ?? [] as $genre) {
            $names[(int) $genre['id']] = $genre['name'];
        }
    }
    return $names;
}

function recsIsSafeForKids(array $item): bool
{
    if (function_exists('isSafeForKids')) {
        return isSafeForKids($item);
    }
    if (!empty($item['adult'])) {
        return false;
    }
    foreach (array_merge($item['genre_ids'] ?? [], array_column($item['genres'] ?? [], 'id')) as $genreId) {
        if (in_array((int) $genreId, [27, 80, 53, 10768], true)) { // horror, crime, thriller, war
            return false;
        }
    }
    return true;
}

/** One TMDB result turned into what the home page rows expect. */
function recsCard(array $item, string $type, string $because = ''): array
{
    $names = recsGenreNames();
    $genreId = (int) ($item['genre_ids'][0] ?? ($item['genres'][0]['id'] ?? 0));
    $title = $item['title'] ?? $item['name'] ?? '';
    $date = $item['release_date'] ?? $item['first_air_date'] ?? '';
    return [
        'id'         => (int) $item['id'],
        'type'       => $type,
        'link'       => '/' . $type . '/' . (int) $item['id'],
        'title'      => $title,
        'poster_url' => !empty($item['poster_path'])
            ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path']
            : '/assets/images/media/placeholder-portrait.svg',
        'genre'      => $names[$genreId] ?? ($type === 'tv' ? 'TV Show' : 'Movie'),
        'year'       => $date ? substr($date, 0, 4) : '',
        'because'    => $because,
    ];
}

/**
 * Kids Mode picks: TMDB's "people who liked this also liked" lists carry no
 * age rating, so they can suggest a horror film off the back of an innocent
 * one. Here the picks come from titles rated for children instead, chosen in
 * the child's own safe genres (cartoons, family, adventure and so on).
 */
function recsKidsPicks(array $genreCounts, array $seen, int $limit): array
{
    $safeGenres = [16 => 'Animation', 10751 => 'Family', 12 => 'Adventure', 35 => 'Comedy', 14 => 'Fantasy', 10762 => 'Kids', 10402 => 'Music'];
    $preferred = array_intersect_key($genreCounts, $safeGenres);
    arsort($preferred);
    $genreId = (int) (array_key_first($preferred) ?? 10751);

    $items = [];
    foreach ([
        ['movie', ['with_genres' => $genreId, 'certification_country' => 'US', 'certification.lte' => 'PG',
                   'sort_by' => 'popularity.desc', 'vote_count.gte' => 50, 'include_adult' => 'false']],
        ['tv', ['with_genres' => '10762,' . $genreId, 'sort_by' => 'popularity.desc',
                'vote_count.gte' => 20, 'include_adult' => 'false']],
    ] as [$type, $params]) {
        // Two pages, so there is enough for both rows.
        foreach ([1, 2] as $page) {
            foreach (fetchTmdbApi("discover/$type", $params + ['page' => $page], 86400)['results'] ?? [] as $item) {
                $key = $type . ':' . (int) $item['id'];
                if (isset($seen[$key]) || empty($item['poster_path']) || !recsIsSafeForKids($item)) {
                    continue;
                }
                $items[$key] = recsCard($item, $type);
            }
        }
    }
    $items = array_values($items);
    shuffle($items);
    return [
        'seed_title' => '',
        'items' => array_slice($items, 0, $limit),
        'genre' => $safeGenres[$genreId] ?? 'Family',
        'genre_items' => array_slice($items, $limit, $limit),
    ];
}

/**
 * Picks for one member.
 *
 * @return array{seed_title: string, items: array, genre: string, genre_items: array}
 *         Empty items when they haven't watched anything yet.
 */
function personalPicks(PDO $conn, int $userId, bool $kidsMode = false, int $limit = 12): array
{
    $empty = ['seed_title' => '', 'items' => [], 'genre' => '', 'genre_items' => []];
    if ($userId <= 0 || !function_exists('fetchTmdbApi')) {
        return $empty;
    }

    [$seeds, $seen] = recsSeeds($conn, $userId);
    if (!$seeds) {
        return $empty;
    }

    // Rebuilt whenever they watch something new, otherwise reused for hours.
    $signature = md5(json_encode($seeds) . '|' . ($kidsMode ? 'kids' : 'all') . '|' . $limit);
    $cacheFile = recsCacheDir() . '/' . $userId . '-' . $signature . '.json';
    if (is_file($cacheFile) && time() - filemtime($cacheFile) < RECS_CACHE_SECONDS) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    // Everything this needs from TMDB, fetched side by side.
    if (function_exists('prefetchTmdbApi')) {
        $requests = [];
        foreach ($seeds as $seed) {
            $requests[] = ["{$seed['type']}/{$seed['id']}/recommendations", []];
            $requests[] = ["{$seed['type']}/{$seed['id']}", []];
        }
        prefetchTmdbApi($requests);
    }

    $scores = [];
    $cards = [];
    $genreCounts = [];
    $seedTitle = '';

    foreach ($seeds as $index => $seed) {
        $details = fetchTmdbApi("{$seed['type']}/{$seed['id']}");
        $title = $details['title'] ?? $details['name'] ?? '';
        if ($seedTitle === '' && $seed['weight'] >= 1.0 && $title !== '') {
            $seedTitle = $title; // the most recent thing they watched
        }
        foreach ($details['genres'] ?? [] as $genre) {
            $genreCounts[(int) $genre['id']] = ($genreCounts[(int) $genre['id']] ?? 0) + $seed['weight'];
        }

        // Older viewing counts for less.
        $seedWeight = $seed['weight'] / (1 + $index * 0.35);
        $results = $kidsMode ? [] : (fetchTmdbApi("{$seed['type']}/{$seed['id']}/recommendations")['results'] ?? []);

        foreach (array_slice($results, 0, RECS_PER_SEED) as $position => $item) {
            $type = ($item['media_type'] ?? $seed['type']) === 'tv' ? 'tv' : 'movie';
            $key = $type . ':' . (int) $item['id'];
            if (isset($seen[$key]) || empty($item['poster_path']) || !empty($item['adult'])) {
                continue;
            }
            if ($kidsMode && !recsIsSafeForKids($item)) {
                continue;
            }
            // Position in the list, and how well the title is rated.
            $score = $seedWeight * (1 - $position * 0.04) * (0.6 + min((float) ($item['vote_average'] ?? 0), 10) / 25);
            if (!isset($scores[$key]) || $scores[$key] < $score) {
                $cards[$key] = recsCard($item, $type, $title);
            }
            $scores[$key] = ($scores[$key] ?? 0) + $score;
        }
    }

    if ($kidsMode) {
        $picks = recsKidsPicks($genreCounts, $seen, $limit);
        @file_put_contents($cacheFile, json_encode($picks));
        return $picks;
    }

    arsort($scores);
    $items = [];
    foreach (array_slice(array_keys($scores), 0, $limit) as $key) {
        $items[] = $cards[$key];
    }

    // A row of the genre they watch most, for when the picks run thin.
    arsort($genreCounts);
    $genreId = (int) (array_key_first($genreCounts) ?? 0);
    $genreItems = [];
    $genreName = recsGenreNames()[$genreId] ?? '';
    if ($genreId) {
        foreach (['movie', 'tv'] as $type) {
            $discover = fetchTmdbApi("discover/$type", [
                'with_genres' => $genreId,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 100,
                'include_adult' => 'false',
            ], 86400)['results'] ?? [];
            foreach (array_slice($discover, 0, 12) as $item) {
                $key = $type . ':' . (int) $item['id'];
                if (isset($seen[$key]) || isset($scores[$key]) || empty($item['poster_path'])) {
                    continue;
                }
                if ($kidsMode && !recsIsSafeForKids($item)) {
                    continue;
                }
                $genreItems[] = recsCard($item, $type);
            }
        }
        shuffle($genreItems);
        $genreItems = array_slice($genreItems, 0, $limit);
    }

    $picks = ['seed_title' => $seedTitle, 'items' => $items, 'genre' => $genreName, 'genre_items' => $genreItems];
    @file_put_contents($cacheFile, json_encode($picks));
    return $picks;
}

<?php
session_start();
require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php';
require 'c:/wamp64/www/masterpiecemovie/v1/controllers/controller.php';

$userId = 1;
$_SESSION['is_kids_mode'] = true;
$isKidsModeActive = true;
define('TMDB_API_KEY', '7cbacdc0889524a2280570a11b40c117');

$sql = "SELECT * FROM watch_history WHERE user_id = :uid ORDER BY last_watched DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->execute([':uid' => $userId]);
$historyList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$addedIds = [];
$continueWatching = [];
$count = 0;
$limit = 6;
$out = "";

function isSafeForKidsLocal(array $details): bool {
    if (!empty($details['adult'])) return false;
    $adultGenres = [27, 80, 53, 10768];
    if (!empty($details['genre_ids'])) { foreach ($details['genre_ids'] as $gId) { if (in_array($gId, $adultGenres)) return false; } }
    if (!empty($details['genres'])) { foreach ($details['genres'] as $g) { if (in_array($g['id'], $adultGenres)) return false; } }
    $hasSafeCert = false;
    $hasMatureCert = false;
    $safeCerts = ['G', 'PG', 'PG-13', 'TV-Y', 'TV-Y7', 'TV-G', 'TV-PG', 'TV-14', 'U', 'U/A', 'A', 'ALL'];
    if (!empty($details['release_dates']['results'])) {
        foreach ($details['release_dates']['results'] as $result) {
            foreach ($result['release_dates'] as $r) {
                $cert = strtoupper(trim((string)($r['certification'] ?? '')));
                if ($cert === '') continue;
                if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $cert)) { $hasMatureCert = true; } elseif (in_array($cert, $safeCerts)) { $hasSafeCert = true; }
            }
        }
    }
    if (!empty($details['content_ratings']['results'])) {
        foreach ($details['content_ratings']['results'] as $result) {
            $rating = strtoupper(trim((string)($result['rating'] ?? '')));
            if ($rating === '') continue;
            if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $rating)) { $hasMatureCert = true; } elseif (in_array($rating, $safeCerts)) { $hasSafeCert = true; }
        }
    }
    if ($hasMatureCert) return false;
    if ($hasSafeCert) return true;
    return true;
}

foreach ($historyList as $row) {
    if ($count >= $limit) break;
    $tmdbId = $row['tmdb_movie_id'];
    $mediaType = $row['media_type'] ?? 'movie';
    if (in_array($tmdbId, $addedIds)) continue;
    $details = fetchTmdbApi($mediaType . "/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
    $out .= "Fetched $mediaType $tmdbId: " . (empty($details) ? 'EMPTY' : 'OK') . "\n";
    if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
        if ($mediaType === 'movie') {
            $mediaType = 'tv';
            $details = fetchTmdbApi("tv/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
            $out .= "Fallback tv $tmdbId: " . (empty($details) ? 'EMPTY' : 'OK') . "\n";
        }
    }
    if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
        $out .= "-> Skipping because still empty\n";
        continue;
    }
    $safe = isSafeForKidsLocal($details);
    $out .= "-> Kids mode safe? " . ($safe ? 'YES' : 'NO') . "\n";
    if (!empty($isKidsModeActive) && !$safe) {
        $out .= "-> Skipping because NOT SAFE\n";
        continue;
    }
    $addedIds[] = $tmdbId;
    $count++;
    $continueWatching[] = [ 'id' => $details['id'], 'type' => $mediaType, 'title' => $details['title'] ?? $details['name'] ];
}
$out .= "\nFinal Array: " . count($continueWatching) . " items\n";
file_put_contents('c:/wamp64/www/masterpiecemovie/v1/cache/test_index_trace.txt', $out);
?>

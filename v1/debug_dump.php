<?php
$userId = 1; // Hardcoded for debugging
$out = "User ID: $userId\n\n";

// Fetch watch_history
$sql = "SELECT * FROM watch_history WHERE user_id = :uid ORDER BY last_watched DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->execute([':uid' => $userId]);
$historyList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out .= "Watch History Rows: " . count($historyList) . "\n";
$out .= print_r($historyList, true);

// Test TMDB API for the first item
if (count($historyList) > 0) {
    $row = $historyList[0];
    $tmdbId = $row['tmdb_movie_id'];
    $mediaType = $row['media_type'] ?? 'movie';
    $out .= "\nTesting TMDB fetch for ID: $tmdbId, Type: $mediaType\n";
    $details = fetchTmdbApi($mediaType . "/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
    $out .= "Result for $mediaType:\n";
    $out .= print_r($details, true);

    if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
        if ($mediaType === 'movie') {
            $out .= "Falling back to tv...\n";
            $details = fetchTmdbApi("tv/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
            $out .= "Result for tv:\n";
            $out .= print_r($details, true);
        }
    }
}
file_put_contents('c:/wamp64/www/masterpiecemovie/v1/cache/debug_out.txt', $out);
?>

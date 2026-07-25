<?php
session_start();
require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php';
require 'c:/wamp64/www/masterpiecemovie/v1/controllers/controller.php';

$userId = 1; // Hardcoded for debugging
echo "User ID: $userId\n\n";

// Fetch watch_history
$sql = "SELECT * FROM watch_history WHERE user_id = :uid ORDER BY last_watched DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->execute([':uid' => $userId]);
$historyList = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Watch History Rows: " . count($historyList) . "\n";
print_r($historyList);

// Test TMDB API for the first item
if (count($historyList) > 0) {
    $row = $historyList[0];
    $tmdbId = $row['tmdb_movie_id'];
    $mediaType = $row['media_type'] ?? 'movie';
    echo "\nTesting TMDB fetch for ID: $tmdbId, Type: $mediaType\n";
    $details = fetchTmdbApi($mediaType . "/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
    echo "Result for $mediaType:\n";
    print_r($details);

    if (empty($details) || (isset($details['success']) && $details['success'] == false) || isset($details['status_code'])) {
        if ($mediaType === 'movie') {
            echo "Falling back to tv...\n";
            $details = fetchTmdbApi("tv/" . $tmdbId, ['append_to_response' => 'release_dates,content_ratings']);
            echo "Result for tv:\n";
            print_r($details);
        }
    }
}

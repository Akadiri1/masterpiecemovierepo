<?php
require 'c:/wamp64/www/masterpiecemovie/.env/config.php';
require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php';

$userId = 1;
$tmdbId = 217288;
$mediaType = 'tv';
$currentTime = 10;
$totalDuration = 120;

try {
    $sql = "INSERT INTO watch_history 
            (`user_id`, `tmdb_movie_id`, `media_type`, `current_time`, `total_duration`, `last_watched`) 
            VALUES (:uid, :mid, :mtype, :cur, :total, NOW())
            ON DUPLICATE KEY UPDATE 
            `current_time` = VALUES(`current_time`),
            `total_duration` = VALUES(`total_duration`),
            `last_watched` = NOW()";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':uid'   => $userId,
        ':mid'   => $tmdbId,
        ':mtype' => $mediaType,
        ':cur'   => $currentTime,
        ':total' => $totalDuration
    ]);
    echo "Success!\n";
} catch (PDOException $e) {
    echo "Insert failed: " . $e->getMessage() . "\n";
}
?>

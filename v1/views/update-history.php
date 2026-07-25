<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

require_once __DIR__ . '/../models/model.php'; // Include DB connection

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Data from JavaScript
    // (We accept 'media_id' from JS but map it to 'tmdb_movie_id' for DB)
    $tmdbId      = intval($_POST['media_id'] ?? 0);
    $mediaType   = $_POST['media_type'] ?? 'movie';
    
    // JS sends seconds, your DB seems to want minutes based on sample data?
    // If your DB wants minutes: $currentTime = floatval($_POST['current_time']) / 60;
    // If your DB wants seconds (recommended): keep as is.
    $currentTime = floatval($_POST['current_time'] ?? 0); 
    
    $totalDuration = floatval($_POST['duration'] ?? 0);

    if ($tmdbId <= 0) exit;

    try {
        // 2. Insert or Update (Matches your specific column names)
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

        echo json_encode(['status' => 'success']);

    } catch (PDOException $e) {
        // Log error to a text file for debugging
        file_put_contents('db_error.log', date('[Y-m-d H:i:s] ') . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
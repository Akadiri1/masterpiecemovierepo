<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Data from JavaScript
    // (We accept 'media_id' from JS but map it to 'tmdb_movie_id' for DB)
    $tmdbId      = intval($_POST['media_id'] ?? 0);
    
    // JS sends seconds, your DB seems to want minutes based on sample data?
    // If your DB wants minutes: $currentTime = floatval($_POST['current_time']) / 60;
    // If your DB wants seconds (recommended): keep as is.
    $currentTime = floatval($_POST['current_time'] ?? 0); 
    
    $totalDuration = floatval($_POST['duration'] ?? 0);

    if ($tmdbId <= 0) exit;

    try {
        // 2. Insert or Update (Matches your specific column names)
        $sql = "INSERT INTO watch_history 
                (user_id, tmdb_movie_id, current_time, total_duration, last_watched) 
                VALUES (:uid, :mid, :cur, :total, NOW())
                ON DUPLICATE KEY UPDATE 
                current_time = VALUES(current_time),
                total_duration = VALUES(total_duration),
                last_watched = NOW()";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'   => $userId,
            ':mid'   => $tmdbId,
            ':cur'   => $currentTime,
            ':total' => $totalDuration
        ]);

        echo json_encode(['status' => 'success']);

    } catch (PDOException $e) {
        // Silent error
    }
}
?>
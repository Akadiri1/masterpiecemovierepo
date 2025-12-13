<?php
// Prevent unwanted output
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');


// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to add to watchlist.']);
    exit;
}

// 3. Process Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId    = $_SESSION['user_id'];
    $mediaId   = $_POST['media_id'] ?? 0;
    $mediaType = $_POST['media_type'] ?? 'movie';

    if (empty($mediaId)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Media ID.']);
        exit;
    }

    try {
        // Check if exists
        $check = $conn->prepare("SELECT id FROM watchlist WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ?");
        $check->execute([$userId, $mediaId, $mediaType]);
        
        if ($check->rowCount() > 0) {
            // REMOVE
            $del = $conn->prepare("DELETE FROM watchlist WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ?");
            $del->execute([$userId, $mediaId, $mediaType]);
            echo json_encode(['status' => 'success', 'action' => 'removed', 'message' => 'Removed from Watchlist']);
        } else {
            // ADD
            $add = $conn->prepare("INSERT INTO watchlist (user_id, tmdb_movie_id, media_type, date_added) VALUES (?, ?, ?, NOW())");
            $add->execute([$userId, $mediaId, $mediaType]);
            echo json_encode(['status' => 'success', 'action' => 'added', 'message' => 'Added to Watchlist']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
}
?>
<?php
// Prevent unwanted output
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');


// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Please login to add to watchlist.']);
    } else {
        header('Location: /login');
    }
    exit;
}

// 3. Process Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $userId    = $_SESSION['user_id'];
    
    // Support both AJAX POST (id/type) and direct GET requests (?id=...)
    $mediaId   = $_POST['id'] ?? $_POST['media_id'] ?? $_GET['id'] ?? 0;
    $mediaType = $_POST['type'] ?? $_POST['media_type'] ?? $_GET['type'] ?? 'movie';
    $isAjax    = $_SERVER['REQUEST_METHOD'] === 'POST';

    if (empty($mediaId)) {
        if ($isAjax) echo json_encode(['status' => 'error', 'message' => 'Invalid Media ID.']);
        else header('Location: /');
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
            if ($isAjax) echo json_encode(['status' => 'success', 'action' => 'removed', 'message' => 'Removed from Watchlist']);
            else header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        } else {
            // ADD
            $add = $conn->prepare("INSERT INTO watchlist (user_id, tmdb_movie_id, media_type, date_added) VALUES (?, ?, ?, NOW())");
            $add->execute([$userId, $mediaId, $mediaType]);
            if ($isAjax) echo json_encode(['status' => 'success', 'action' => 'added', 'message' => 'Added to Watchlist']);
            else header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        }

    } catch (Exception $e) {
        if ($isAjax) echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        else header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    }
}
?>
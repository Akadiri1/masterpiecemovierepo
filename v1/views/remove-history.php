<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $mediaId = $_POST['media_id'] ?? 0;

    if (!$mediaId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    try {
        // Delete from History
        $stmt = $conn->prepare("DELETE FROM watch_history WHERE user_id = ? AND tmdb_movie_id = ?");
        $stmt->execute([$userId, $mediaId]);

        echo json_encode(['status' => 'success', 'message' => 'Removed from History']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
}
?>
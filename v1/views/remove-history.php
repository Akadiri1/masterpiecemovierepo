<?php
// Removes titles from the signed-in user's watch history: one title, or all of
// it (action=clear). Used by Continue Watching on the home page and the History
// tab on the profile. Always answers with JSON.

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

function historyResponse(array $body): void
{
    echo json_encode($body);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    historyResponse(['status' => 'error', 'message' => 'Please sign in first.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    historyResponse(['status' => 'error', 'message' => 'Invalid request.']);
}
if (!isset($conn)) {
    historyResponse(['status' => 'error', 'message' => "Your history can't be changed right now. Please try again shortly."]);
}

$userId = (int) $_SESSION['user_id'];

try {
    if (($_POST['action'] ?? '') === 'clear') {
        $conn->prepare("DELETE FROM watch_history WHERE user_id = ?")->execute([$userId]);
        historyResponse(['status' => 'success', 'message' => 'Watch history cleared.']);
    }

    $mediaId = (int) ($_POST['media_id'] ?? 0);
    if ($mediaId <= 0) {
        historyResponse(['status' => 'error', 'message' => 'Invalid ID.']);
    }

    // The type is optional: Continue Watching only sends the id.
    $mediaType = $_POST['media_type'] ?? '';
    if ($mediaType === 'movie' || $mediaType === 'tv') {
        $conn->prepare("DELETE FROM watch_history WHERE user_id = ? AND tmdb_movie_id = ? AND media_type = ?")
             ->execute([$userId, $mediaId, $mediaType]);
    } else {
        $conn->prepare("DELETE FROM watch_history WHERE user_id = ? AND tmdb_movie_id = ?")
             ->execute([$userId, $mediaId]);
    }
    historyResponse(['status' => 'success', 'message' => 'Removed from history.']);
} catch (Exception $e) {
    error_log('remove-history: ' . $e->getMessage());
    historyResponse(['status' => 'error', 'message' => "Your history can't be changed right now. Please try again shortly."]);
}

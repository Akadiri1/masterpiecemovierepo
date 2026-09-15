<?php
// Saves a review for a movie or show. Called by the review panel on the
// details page; always answers with JSON.
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function reviewResponse(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reviewResponse(405, ['status' => 'error', 'message' => 'Invalid request method.']);
}
if (!isset($_SESSION['user_id'])) {
    reviewResponse(401, ['status' => 'error', 'message' => 'Please log in to write a review.']);
}
if (!isset($conn)) {
    reviewResponse(503, ['status' => 'error', 'message' => "Reviews can't be saved right now. Please try again shortly."]);
}

$maxLength = 2000;
$userId    = (int) $_SESSION['user_id'];
$mediaId   = (int) ($_POST['media_id'] ?? 0);
$mediaType = ($_POST['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
$rating    = (int) ($_POST['rating'] ?? 0);
$review    = trim((string) ($_POST['review_text'] ?? ''));

if ($mediaId <= 0) {
    reviewResponse(422, ['status' => 'error', 'message' => 'This title could not be found.']);
}
if ($rating < 1 || $rating > 5) {
    reviewResponse(422, ['status' => 'error', 'field' => 'rating', 'message' => 'Choose a rating from 1 to 5 stars.']);
}
if ($review === '') {
    reviewResponse(422, ['status' => 'error', 'field' => 'review_text', 'message' => 'Write a few words about it.']);
}
if (mb_strlen($review) > $maxLength) {
    reviewResponse(422, ['status' => 'error', 'field' => 'review_text', 'message' => "Reviews can be up to {$maxLength} characters."]);
}

try {
    // One review per person per title: posting again updates it.
    $check = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND media_id = ? AND media_type = ? LIMIT 1");
    $check->execute([$userId, $mediaId, $mediaType]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        $conn->prepare("UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE id = ?")
             ->execute([$rating, $review, $existingId]);
        $action = 'updated';
    } else {
        $conn->prepare("INSERT INTO reviews (user_id, media_id, media_type, rating, review_text, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
             ->execute([$userId, $mediaId, $mediaType, $rating, $review]);
        $action = 'created';
    }

    // New totals, so the page can update its count and average in place.
    $stats = $conn->prepare("SELECT COUNT(*) AS total, AVG(rating) AS average FROM reviews WHERE media_id = ? AND media_type = ?");
    $stats->execute([$mediaId, $mediaType]);
    $totals = $stats->fetch(PDO::FETCH_ASSOC);

    $author = $conn->prepare("SELECT username, avatar_url FROM users WHERE id = ?");
    $author->execute([$userId]);
    $author = $author->fetch(PDO::FETCH_ASSOC) ?: [];

    $avatar = !empty($author['avatar_url']) ? $author['avatar_url'] : '/assets/images/user/user.jpg';
    if (!preg_match('~^(https?:)?/~', $avatar)) {
        $avatar = '/' . $avatar;
    }

    reviewResponse(200, [
        'status'  => 'success',
        'action'  => $action,
        'message' => $action === 'updated' ? 'Review updated.' : 'Review posted.',
        'review'  => [
            'user_id'     => $userId,
            'username'    => $author['username'] ?? ($_SESSION['username'] ?? 'You'),
            'avatar_url'  => $avatar,
            'rating'      => $rating,
            'review_text' => $review,
            'date_label'  => date('M d, Y'),
        ],
        'count'   => (int) $totals['total'],
        'average' => $totals['total'] ? number_format((float) $totals['average'], 1) : null,
    ]);
} catch (Exception $e) {
    error_log('Review save failed: ' . $e->getMessage());
    reviewResponse(500, ['status' => 'error', 'message' => "Your review couldn't be saved. Please try again."]);
}

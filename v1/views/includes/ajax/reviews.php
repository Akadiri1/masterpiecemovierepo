<?php
// Ensure no output before JSON
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to submit a review.']);
    exit;
}
// 3. Get POST Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId    = $_SESSION['user_id'];
    $mediaId   = $_POST['media_id'] ?? 0;
    $mediaType = $_POST['media_type'] ?? 'movie';
    $rating    = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review    = trim($_POST['review_text'] ?? '');

    // 4. Validation
    if (empty($mediaId)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Media ID.']);
        exit;
    }
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a star rating (1-5).']);
        exit;
    }
    if (empty($review)) {
        echo json_encode(['status' => 'error', 'message' => 'Please write a review description.']);
        exit;
    }

    try {
        // 5. Insert into Database
        // Check if user already reviewed this movie? (Optional)
        $check = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND media_id = ? AND media_type = ?");
        $check->execute([$userId, $mediaId, $mediaType]);
        
        if ($check->rowCount() > 0) {
            // Update existing review
            $stmt = $conn->prepare("UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE user_id = ? AND media_id = ? AND media_type = ?");
            $stmt->execute([$rating, $review, $userId, $mediaId, $mediaType]);
        } else {
            // Insert new review
            $stmt = $conn->prepare("INSERT INTO reviews (user_id, media_id, media_type, rating, review_text, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $mediaId, $mediaType, $rating, $review]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Review submitted successfully!']);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
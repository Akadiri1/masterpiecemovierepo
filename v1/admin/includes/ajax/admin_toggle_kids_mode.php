<?php
header('Content-Type: application/json');

// Basic bootstrap: load DB config and helpers
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Attempt to include app config / DB connection
// file is usually at v1/includes/config.php from admin/includes/ajax
$cfg = __DIR__ . '/../../../includes/config.php';
if (!file_exists($cfg)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration missing']);
    exit;
}
require_once $cfg;

// Only allow logged-in admin users
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Optional: verify admin status and not suspended
$whereAdmin['hash_id'] = $_SESSION['admin_id'];
$adminDetails = selectContent($conn, 'admin', $whereAdmin);
if (empty($adminDetails) || !is_array($adminDetails) || count($adminDetails) < 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin record not found']);
    exit;
}
if (isset($adminDetails[0]['user_status']) && $adminDetails[0]['user_status'] == 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin suspended']);
    exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$value = isset($input['value']) ? (int)$input['value'] : 0;
$value = ($value === 1) ? 1 : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user id']);
    exit;
}

try {
    // Try updating the user's is_kids_mode flag
    $stmt = $conn->prepare("UPDATE users SET is_kids_mode = :val WHERE id = :id");
    $stmt->execute([':val' => $value, ':id' => $userId]);

    // If no rows were updated, still return success (idempotent) but report nothing changed
    $rows = $stmt->rowCount();

    echo json_encode(['success' => true, 'updated' => (bool)$rows, 'message' => 'Kids Mode updated']);
    exit;

} catch (PDOException $e) {
    // If the column doesn't exist, attempt to add it and retry once
    if (stripos($e->getMessage(), 'Unknown column') !== false || stripos($e->getMessage(), 'is_kids_mode') !== false) {
        try {
            $conn->exec("ALTER TABLE users ADD COLUMN is_kids_mode TINYINT(1) DEFAULT 0 AFTER parental_pin_hash");
            $stmt = $conn->prepare("UPDATE users SET is_kids_mode = :val WHERE id = :id");
            $stmt->execute([':val' => $value, ':id' => $userId]);
            $rows = $stmt->rowCount();
            echo json_encode(['success' => true, 'updated' => (bool)$rows, 'message' => 'Kids Mode column created and updated']);
            exit;
        } catch (PDOException $e2) {
            error_log('admin_toggle_kids_mode fail: ' . $e2->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DB migration failed, please add column manually']);
            exit;
        }
    }

    // Default error
    error_log('admin_toggle_kids_mode fail: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

?>

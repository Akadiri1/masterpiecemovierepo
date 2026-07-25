<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin-login");
    exit;
}

require_once dirname(dirname(__FILE__)) . '/.env/config.php';
require_once dirname(dirname(__FILE__)) . '/v1/models/model.php';

$id = $_GET['id'] ?? null;
$table = $_GET['data'] ?? null;

if (!$id || !$table) {
    die("Invalid request");
}

// Security: limit allowed tables to prevent SQL injection
$allowed_tables = ['users', 'admin', 'blogs', 'topic', 'slider', 'reviews'];
if (!in_array($table, $allowed_tables)) {
    die("Unauthorized table");
}

// Build query dynamically from GET parameters, excluding system parameters
$exclude = ['id', 'data'];
$updates = [];
$values = [];

foreach ($_GET as $key => $val) {
    if (in_array($key, $exclude)) continue;
    // Basic sanitization of column names (letters, numbers, underscore only)
    if (preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
        $updates[] = "`$key` = ?";
        $values[] = $val;
    }
}

if (!empty($updates)) {
    $sql = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE id = ?";
    $values[] = $id;
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
    } catch (PDOException $e) {
        die("Error updating content: " . $e->getMessage());
    }
}

// Redirect back
$referer = $_SERVER['HTTP_REFERER'] ?? '/admin-view-users';
header("Location: " . $referer);
exit;
?>

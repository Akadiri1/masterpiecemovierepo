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

try {
    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
} catch (PDOException $e) {
    die("Error deleting content: " . $e->getMessage());
}

// Redirect back
$referer = $_SERVER['HTTP_REFERER'] ?? '/admin-view-users';
header("Location: " . $referer);
exit;
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Ensure session is active when called directly
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to set a Parental PIN.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

// Allow clear action
$action = $_POST['action'] ?? '';
$newPin = $_POST['new_pin'] ?? '';
$confirmPin = $_POST['confirm_pin'] ?? '';

$newPin = trim($newPin);
$confirmPin = trim($confirmPin);

if (empty($newPin) || empty($confirmPin)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide and confirm the PIN.']);
    exit;
}

if ($newPin !== $confirmPin) {
    echo json_encode(['status' => 'error', 'message' => 'PINs do not match.']);
    exit;
}

// Basic PIN criteria: numeric and between 4 and 8 digits
if (!preg_match('/^[0-9]{4,8}$/', $newPin)) {
    echo json_encode(['status' => 'error', 'message' => 'PIN must be 4-8 digits.']);
    exit;
}

// Make sure $conn exists
if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Server error (no DB).']);
    exit;
}

try {
    // Ensure column exists
    $checkStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'parental_pin_hash'");
    $checkStmt->execute();
    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        // Try to add the column
        $conn->exec("ALTER TABLE users ADD COLUMN parental_pin_hash VARCHAR(255) NULL");
    }

    if ($action === 'clear') {
        $update = $conn->prepare("UPDATE users SET parental_pin_hash = NULL WHERE id = ?");
        $update->execute([$userId]);
        echo json_encode(['status' => 'success', 'message' => 'Parental PIN removed.']);
        exit;
    }

    $hash = password_hash($newPin, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET parental_pin_hash = ? WHERE id = ?");
    $update->execute([$hash, $userId]);

    echo json_encode(['status' => 'success', 'message' => 'Parental PIN saved successfully.']);
    exit;
} catch (PDOException $e) {
    error_log('set-parental-pin error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error saving PIN.']);
    exit;
}

?>

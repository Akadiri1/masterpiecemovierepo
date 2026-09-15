<?php
// Creates, changes, removes or resets the parental PIN. Always answers with JSON.
//
// Changing or removing an existing PIN needs that PIN, and resetting a
// forgotten one needs the account password. Before, anyone in Kids Mode could
// set a new PIN from the profile page and switch straight back.

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../lib/pin_guard.php';

function pinResponse(array $body): void
{
    echo json_encode($body);
    exit;
}

function pinLockedResponse(string $field): void
{
    $wait = pinLockSeconds();
    pinResponse(['status' => 'locked', 'field' => $field, 'retry_after' => $wait, 'message' => 'Too many wrong tries. Try again in ' . pinWaitText($wait) . '.']);
}

function pinWrongResponse(string $field, string $what): void
{
    $left = pinRecordFailure();
    if ($left === 0) {
        pinLockedResponse($field);
    }
    pinResponse(['status' => 'error', 'field' => $field, 'message' => "That {$what} isn't right. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.']);
}

$unavailable = "Your PIN can't be changed right now. Please try again shortly.";

if (!isset($_SESSION['user_id'])) {
    pinResponse(['status' => 'login', 'message' => 'Please sign in to manage your parental PIN.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pinResponse(['status' => 'error', 'message' => 'Invalid request.']);
}
if (!isset($conn)) {
    pinResponse(['status' => 'error', 'message' => $unavailable]);
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? 'set';

try {
    $stmt = $conn->prepare("SELECT password, parental_pin_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        pinResponse(['status' => 'login', 'message' => 'Please sign in to manage your parental PIN.']);
    }
    $hasPin = !empty($user['parental_pin_hash']);

    // ---- Forgotten PIN: the account password removes it -------------------
    if ($action === 'reset') {
        if (pinLockSeconds()) {
            pinLockedResponse('password');
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($password === '') {
            pinResponse(['status' => 'error', 'field' => 'password', 'message' => 'Enter your account password.']);
        }
        if (empty($user['password']) || !password_verify($password, $user['password'])) {
            pinWrongResponse('password', 'password');
        }
        pinRecordSuccess();
        $conn->prepare("UPDATE users SET parental_pin_hash = NULL, is_kids_mode = 0 WHERE id = ?")->execute([$userId]);
        $_SESSION['is_kids_mode'] = false;
        $_SESSION['is_kid'] = 0;
        pinResponse(['status' => 'success', 'mode' => 'parent', 'message' => 'PIN removed and Kids Mode turned off. You can create a new PIN in your profile.']);
    }

    // ---- Changing or removing an existing PIN needs the current one --------
    if ($hasPin) {
        if (pinLockSeconds()) {
            pinLockedResponse('current_pin');
        }
        $current = trim((string) ($_POST['current_pin'] ?? ''));
        if ($current === '') {
            pinResponse(['status' => 'error', 'field' => 'current_pin', 'message' => 'Enter your current PIN.']);
        }
        if (!password_verify($current, $user['parental_pin_hash'])) {
            pinWrongResponse('current_pin', 'PIN');
        }
        pinRecordSuccess();
    }

    if ($action === 'clear') {
        $conn->prepare("UPDATE users SET parental_pin_hash = NULL WHERE id = ?")->execute([$userId]);
        pinResponse(['status' => 'success', 'message' => 'Parental PIN removed.']);
    }

    // ---- Creating or changing the PIN --------------------------------------
    $newPin = trim((string) ($_POST['new_pin'] ?? ''));
    $confirmPin = trim((string) ($_POST['confirm_pin'] ?? ''));
    if (!preg_match('/^[0-9]{4,8}$/', $newPin)) {
        pinResponse(['status' => 'error', 'field' => 'new_pin', 'message' => 'Use 4 to 8 digits for the PIN.']);
    }
    if ($newPin !== $confirmPin) {
        pinResponse(['status' => 'error', 'field' => 'confirm_pin', 'message' => "The two PINs don't match."]);
    }

    $conn->prepare("UPDATE users SET parental_pin_hash = ? WHERE id = ?")->execute([password_hash($newPin, PASSWORD_DEFAULT), $userId]);
    pinResponse(['status' => 'success', 'message' => $hasPin ? 'Parental PIN changed.' : 'Parental PIN saved.']);
} catch (PDOException $e) {
    error_log('set-parental-pin: ' . $e->getMessage());
    pinResponse(['status' => 'error', 'message' => $unavailable]);
}

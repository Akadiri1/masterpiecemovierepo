<?php
// Switches the signed-in account between the main profile and Kids Mode.
// Turning Kids Mode on needs a parental PIN to exist; turning it off needs
// that PIN. Always answers with JSON; includes/kids-mode.php shows the steps.

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../lib/pin_guard.php';

function kidsModeResponse(array $body): void
{
    echo json_encode($body);
    exit;
}

function setKidsMode(PDO $conn, int $userId, bool $on): void
{
    $conn->prepare("UPDATE users SET is_kids_mode = ? WHERE id = ?")->execute([$on ? 1 : 0, $userId]);
    $_SESSION['is_kids_mode'] = $on;
    $_SESSION['is_kid'] = $on ? 1 : 0; // older code reads this
}

$unavailable = "Kids Mode can't be changed right now. Please try again shortly.";

if (!isset($_SESSION['user_id'])) {
    kidsModeResponse(['status' => 'login', 'message' => 'Sign in to use Kids Mode.']);
}
if (!isset($conn)) {
    kidsModeResponse(['status' => 'error', 'message' => $unavailable]);
}

$userId = (int) $_SESSION['user_id'];

try {
    // Read from the database: the session copy can be stale.
    $stmt = $conn->prepare("SELECT current_plan_id, parental_pin_hash, is_kids_mode FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        kidsModeResponse(['status' => 'login', 'message' => 'Sign in to use Kids Mode.']);
    }

    $pinHash = $user['parental_pin_hash'];
    $inKidsMode = (int) ($user['is_kids_mode'] ?? 0) === 1;
    $_SESSION['is_kids_mode'] = $inKidsMode;
    $_SESSION['is_kid'] = $inKidsMode ? 1 : 0;

    // ---- Turning Kids Mode on -------------------------------------------
    if (!$inKidsMode) {
        // Premium only, unless ALLOW_FREE_KIDS is set in the config. Checked
        // only when turning it on, so nobody is stuck in Kids Mode after
        // their plan ends.
        $allowFreeKids = defined('ALLOW_FREE_KIDS') ? (bool) ALLOW_FREE_KIDS : getenv('ALLOW_FREE_KIDS') === '1';
        if (!$allowFreeKids && (int) ($user['current_plan_id'] ?? 1) <= 1) {
            kidsModeResponse(['status' => 'upgrade', 'message' => 'Kids Mode is a Premium feature.']);
        }
        // A PIN must exist first, or a child could simply switch back.
        if (empty($pinHash)) {
            kidsModeResponse(['status' => 'no_pin', 'message' => 'Create a parental PIN first.']);
        }
        setKidsMode($conn, $userId, true);
        kidsModeResponse(['status' => 'success', 'mode' => 'kid', 'message' => 'Kids Mode is on.']);
    }

    // ---- Turning Kids Mode off ------------------------------------------
    if (empty($pinHash)) {
        setKidsMode($conn, $userId, false);
        kidsModeResponse(['status' => 'success', 'mode' => 'parent', 'message' => 'Switched to the main profile.']);
    }

    if ($wait = pinLockSeconds()) {
        kidsModeResponse(['status' => 'locked', 'retry_after' => $wait, 'message' => 'Too many wrong PINs. Try again in ' . pinWaitText($wait) . '.']);
    }

    $pin = trim((string) ($_POST['parent_pin'] ?? ''));
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $pin === '') {
        kidsModeResponse(['status' => 'need_pin', 'message' => 'Enter the parental PIN.']);
    }

    if (!password_verify($pin, $pinHash)) {
        $left = pinRecordFailure();
        if ($left === 0) {
            $wait = pinLockSeconds();
            kidsModeResponse(['status' => 'locked', 'retry_after' => $wait, 'message' => 'Too many wrong PINs. Try again in ' . pinWaitText($wait) . '.']);
        }
        kidsModeResponse(['status' => 'wrong_pin', 'attempts_left' => $left, 'message' => "That PIN isn't right. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.']);
    }

    pinRecordSuccess();
    setKidsMode($conn, $userId, false);
    kidsModeResponse(['status' => 'success', 'mode' => 'parent', 'message' => 'Switched to the main profile.']);
} catch (PDOException $e) {
    error_log('switch-mode: ' . $e->getMessage());
    kidsModeResponse(['status' => 'error', 'message' => $unavailable]);
}

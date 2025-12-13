<?php

// 2. Start Session & Headers
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// 3. Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
    exit;
}

$userId = $_SESSION['user_id'];

// 4. GET FRESH DATA FROM DB (Crucial Fix)
// We fetch the plan_id directly from DB because the session might be stale or missing it.
if (isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT current_plan_id, parental_pin_hash, is_kids_mode FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            exit;
        }

        // Set variables from DB
        $currentPlanId = $user['current_plan_id'] ?? 1;
        $pinHash = $user['parental_pin_hash'];
        $dbKidsMode = $user['is_kids_mode'] ?? 0;
        
        // Sync session with DB (Self-Correction)
        $_SESSION['is_kids_mode'] = ($dbKidsMode == 1);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// 5. PREMIUM CHECK (The Gatekeeper)
// Production behavior: block free users (current_plan_id <= 1).
// For testing/dev we support a safe override. Set environment variable ALLOW_FREE_KIDS=1
// or define('ALLOW_FREE_KIDS', true) in your config to bypass this check.

// Resolve the override from a constant first, then environment variable fallback.
$allowFreeKids = false;
if (defined('ALLOW_FREE_KIDS')) {
    $allowFreeKids = (bool) ALLOW_FREE_KIDS;
} elseif (getenv('ALLOW_FREE_KIDS') !== false) {
    $allowFreeKids = (string) getenv('ALLOW_FREE_KIDS') === '1';
}

if (!$allowFreeKids && $currentPlanId <= 1) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Kids Mode is a Premium feature. Please upgrade to access.'
    ]);
    exit;
}

// 6. TOGGLE LOGIC

// Helper function to update DB
function setKidsMode($conn, $uid, $status) {
    $val = $status ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET is_kids_mode = ? WHERE id = ?");
    $stmt->execute([$val, $uid]);
}

// A. If NOT in Kids Mode -> ENTER IT
if (empty($_SESSION['is_kids_mode'])) {
    // Require a parental PIN to be set BEFORE enabling Kids Mode. This prevents kids from later switching
    // back to the parent profile and seeing adult content without a PIN.
    if (empty($pinHash)) {
        echo json_encode(['status' => 'no_pin', 'message' => 'Please set a Parental PIN before enabling Kids Mode.']);
        exit;
    }

    $_SESSION['is_kids_mode'] = true;
    $_SESSION['is_kid'] = 1; // Legacy support
    setKidsMode($conn, $userId, true);

    echo json_encode(['status' => 'success', 'message' => 'Switched to Kids Profile', 'mode' => 'kid']);
    exit;
}

// B. If IN Kids Mode -> EXIT IT
// B1. If no PIN set, exit immediately
if (empty($pinHash)) {
    $_SESSION['is_kids_mode'] = false;
    $_SESSION['is_kid'] = 0;
    setKidsMode($conn, $userId, false);

    echo json_encode(['status' => 'success', 'message' => 'Switched to Parent Profile', 'mode' => 'parent']);
    exit;
}

// B2. If PIN exists, Verify it
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parent_pin'])) {
    $enteredPin = trim($_POST['parent_pin']);
    
    if (password_verify($enteredPin, $pinHash)) {
        // Correct PIN
        $_SESSION['is_kids_mode'] = false;
        $_SESSION['is_kid'] = 0;
        setKidsMode($conn, $userId, false);
        echo json_encode(['status' => 'success', 'message' => 'Switched to Parent Profile', 'mode' => 'parent']);
    } else {
        // Wrong PIN
        echo json_encode(['status' => 'error', 'message' => 'Incorrect PIN.']);
    }
    exit;
}

// B3. Ask for PIN — respond with JSON (was previously commented out which returned an empty page)
echo json_encode(['status' => 'need_pin', 'message' => 'Parental PIN required.']);
exit;
?>
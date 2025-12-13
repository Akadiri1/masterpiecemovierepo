<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_POST['reference'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$userId = $_SESSION['user_id'];
$planId = (int)$_POST['plan_id'];
$reference = $_POST['reference'];
$secretKey = "sk_test_cf26f818cf4db08aaf9dd4552deff563464a2c3b"; // REPLACE WITH YOUR SECRET KEY

// 1. VERIFY TRANSACTION WITH PAYSTACK API
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $secretKey,
    "Cache-Control: no-cache"
]);
// Disable SSL for local testing (Remove on live)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['status' => 'error', 'message' => 'Verification connection failed.']);
    exit;
}

$result = json_decode($response, true);

// 2. CHECK IF VERIFICATION WAS SUCCESSFUL
if ($result && isset($result['data']['status']) && $result['data']['status'] === 'success') {
    
    try {
        // A. Fetch Plan Name & Duration based on ID sent from frontend
        $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) throw new Exception("Plan not found");

        $planName = $plan['name']; // e.g. "Pro"
        $duration = $plan['duration_days'];

        // Calculate Expiry Date
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration days"));

        // B. Insert into Subscriptions Table (Using your new structure)
        // Columns: id, user_id, plan_name, expires_at, status, created_at
        $subSql = "INSERT INTO subscriptions (user_id, plan_name, expires_at, status, created_at) 
                   VALUES (?, ?, ?, 'active', NOW())";
        
        $conn->prepare($subSql)->execute([$userId, $planName, $expiresAt]);

        // C. Update User Table (for quick access)
        $userSql = "UPDATE users SET current_plan_id = ? WHERE id = ?";
        $conn->prepare($userSql)->execute([$planId, $userId]);

        // D. Update Session
        $_SESSION['plan_id'] = $planId;
        $_SESSION['plan_name'] = strtolower($planName); 

        echo json_encode(['status' => 'success', 'message' => 'Payment verified! Plan activated.']);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed.']);
}
?>
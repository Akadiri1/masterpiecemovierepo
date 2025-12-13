<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
// --- HELPER: Robust HTTP Request (cURL) ---
function curlGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important for Localhost
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// 2. READ INPUT
$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? '';
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

$userData = null;

// 3. VERIFY TOKEN WITH PROVIDER
if ($provider === 'google') {
    // Verify Google Token
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
    $response = curlGet($url);
    $data = json_decode($response, true);
    
    if (isset($data['email'])) {
        $userData = [
            'email' => $data['email'],
            'name' => $data['name'] ?? '',
            'google_id' => $data['sub']
        ];
    }
} 
elseif ($provider === 'facebook') {
    // Verify Facebook Token
    $url = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . $token;
    $response = curlGet($url);
    $data = json_decode($response, true);
    
    if (isset($data['id'])) {
        $userData = [
            // FB doesn't always return email, fallback to generated ID email
            'email' => $data['email'] ?? $data['id'] . '@facebook.com',
            'name' => $data['name'] ?? '',
            'facebook_id' => $data['id']
        ];
    }
}

// 4. LOGIN OR REGISTER USER
if ($userData) {
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // --- USER EXISTS: LINK ACCOUNT & LOGIN ---
            
            // Link Google ID if missing
            if ($provider === 'google' && empty($user['google_id'])) {
                $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?")
                     ->execute([$userData['google_id'], $user['id']]);
            }
            // Link Facebook ID if missing
            if ($provider === 'facebook' && empty($user['facebook_id'])) {
                $conn->prepare("UPDATE users SET facebook_id = ? WHERE id = ?")
                     ->execute([$userData['facebook_id'], $user['id']]);
            }

        } else {
            // --- NEW USER: REGISTER ---
            
            // Generate username
            $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $userData['name']);
            if(empty($baseUsername)) $baseUsername = 'user';
            $newUsername = $baseUsername;
            
            // Ensure unique username
            while(true) {
                $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$newUsername]);
                if(!$check->fetch()) break;
                $newUsername = $baseUsername . rand(100, 999);
            }

            // Generate random password
            $randomPassword = bin2hex(random_bytes(10));
            $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (username, email, password, google_id, facebook_id, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newUsername,
                $userData['email'],
                $hashedPassword,
                $userData['google_id'] ?? null,
                $userData['facebook_id'] ?? null,
                'user'
            ]);
            
            $userId = $conn->lastInsertId();
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // CREATE SESSION
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
        $_SESSION['logged_in'] = true;

        // Restore Kids Mode
        $kidsMode = false;
        if (isset($user['is_kids_mode'])) {
            $kidsMode = (int)$user['is_kids_mode'] === 1;
        }
        $_SESSION['is_kids_mode'] = $kidsMode;
        $_SESSION['is_kid'] = $kidsMode ? 1 : 0;

        // Restore Plan
        $sessionPlanId = 1;
        if (!empty($user['current_plan_id'])) {
            $sessionPlanId = (int)$user['current_plan_id'];
        }
        
        // Fetch Plan Name
        $planName = 'free';
        try {
            $pstmt = $conn->prepare("SELECT name FROM plans WHERE id = ? LIMIT 1");
            $pstmt->execute([$sessionPlanId]);
            $p = $pstmt->fetch(PDO::FETCH_ASSOC);
            if($p) $planName = $p['name'];
        } catch (Exception $e) {}

        $_SESSION['plan_id'] = $sessionPlanId;
        $_SESSION['plan_name'] = strtolower($planName);

        // Redirect Logic
        $isAdmin = $user['is_admin'] ?? 0;
        $redirect_url = ($isAdmin == 1) ? '/admin-dashboard' : '/home';
        if (isset($_SESSION['redirect_url'])) {
            $redirect_url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => $redirect_url
        ]);

    } catch (PDOException $e) {
        error_log($e->getMessage()); 
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Token or Provider rejected connection']);
}
?>
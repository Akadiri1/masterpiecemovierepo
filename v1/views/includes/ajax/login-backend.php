<?php
header('Content-Type: application/json');

// 1. Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If client sent identity/password => treat as standard login flow
if (!empty($input['identity']) && !empty($input['password'])) {
    $identity = trim($input['identity']);
    $password = $input['password'];

    try {
        // Allow login by email OR username
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !isset($user['password']) || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        }

        // Create session
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
        $_SESSION['logged_in'] = true;

        // --- RESTORE PLAN / SUBSCRIPTION INFO INTO SESSION ---
        // Use users.current_plan_id (if present) as the canonical quick-sync source
        $sessionPlanId = 1; // default to free
        if (!empty($user['current_plan_id'])) {
            $sessionPlanId = (int)$user['current_plan_id'];
        }

        // Try to fetch plan name from plans table (fallback to 'free')
        try {
            $pstmt = $conn->prepare("SELECT name FROM plans WHERE id = ? LIMIT 1");
            $pstmt->execute([$sessionPlanId]);
            $p = $pstmt->fetch(PDO::FETCH_ASSOC);
            $planName = $p['name'] ?? 'free';
        } catch (Exception $e) {
            $planName = 'free';
        }

        $_SESSION['plan_id'] = $sessionPlanId;
        $_SESSION['plan_name'] = strtolower($planName);

        // Optional remember-me handling (best-effort): set cookie and persist hashed token if DB supports it
        $remember = !empty($input['rememberMe']);
        if ($remember) {
            try {
                $rawToken = bin2hex(random_bytes(16));
                // Try to persist to DB if column exists - silently ignore on failure
                $hashed = password_hash($rawToken, PASSWORD_BCRYPT);
                $uStmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                @$uStmt->execute([$hashed, $user['id']]);
                setcookie('remember_token', $rawToken, time() + (86400 * 30), '/', '', false, true);
            } catch (Exception $e) {
                // ignore
            }
        }

        // Redirect after successful login. Admins go to dashboard, users go to home by default.
        $isAdmin = $user['is_admin'] ?? 0;
        $redirect_url = ($isAdmin == 1) ? '/admin-dashboard' : '/';

        // If the social login request sent a 'next' parameter, respect it when safe.
        $nextCandidate = $input['next'] ?? '';
        if (!empty($nextCandidate) && is_string($nextCandidate)) {
            if (strpos($nextCandidate, '/') === 0 && stripos($nextCandidate, 'http') === false && strpos($nextCandidate, '//') === false) {
                $redirect_url = $nextCandidate;
            }
        }

        // If the client passed a 'next' parameter, prefer that as long as it's a safe internal path.
        // This lets the login page forward a new user to set-parental-pin after they authenticate.
        $nextCandidate = $input['next'] ?? '';
        if (!empty($nextCandidate) && is_string($nextCandidate)) {
            // Ensure it's a sane internal path (starts with '/', no protocol, no '//' double slashes)
            if (strpos($nextCandidate, '/') === 0 && stripos($nextCandidate, 'http') === false && strpos($nextCandidate, '//') === false) {
                $redirect_url = $nextCandidate;
            }
        }

        // --- RESTORE Kids Mode into session (if the DB stores it) ---
        $kidsMode = false;
        if (isset($user['is_kids_mode'])) {
            $kidsMode = (int)$user['is_kids_mode'] === 1;
        }
        $_SESSION['is_kids_mode'] = $kidsMode;
        $_SESSION['is_kid'] = $kidsMode ? 1 : 0;

        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
        exit;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Otherwise fall back to social login flow (provider/token)
$provider = $input['provider'] ?? '';
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

$userData = null;

// 2. Verify Google
if ($provider === 'google') {
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if (isset($data['email'])) {
        $userData = [
            'email' => $data['email'],
            'name' => $data['name'] ?? '',
            'google_id' => $data['sub']
        ];
    }
} 
// 3. Verify Facebook
elseif ($provider === 'facebook') {
    $url = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . $token;
    $response = @file_get_contents($url);
    $data = json_decode($response, true);
    
    if (isset($data['id'])) {
        $userData = [
            'email' => $data['email'] ?? $data['id'].'@facebook.com',
            'name' => $data['name'] ?? '',
            'facebook_id' => $data['id']
        ];
    }
}

// 4. Process Login / Registration
if ($userData) {
    try {
        // A. Check if user exists by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // --- USER EXISTS: LOGIN ---
            // Optional: Update Google/FB ID if missing
            if ($provider === 'google' && empty($user['google_id'])) {
                $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$userData['google_id'], $user['id']]);
            }
            // ... (similar for facebook)

        } else {
            // --- USER DOES NOT EXIST: REGISTER ---
            
            // Generate a Username (Name + Random Digits to avoid duplicates)
            $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $userData['name']);
            if(empty($baseUsername)) $baseUsername = 'user';
            
            $newUsername = $baseUsername;
            
            // Ensure username is unique
            while(true) {
                $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$newUsername]);
                if(!$check->fetch()) break; // Unique found
                $newUsername = $baseUsername . rand(100, 999);
            }

            // Generate a random password (since they use social login)
            $randomPassword = bin2hex(random_bytes(10));
            $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT);

            // Insert into DB
            $sql = "INSERT INTO users (username, email, password, google_id, facebook_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newUsername,
                $userData['email'],
                $hashedPassword,
                $userData['google_id'] ?? null,
                $userData['facebook_id'] ?? null
            ]);
            
            // Fetch the newly created user so we have ID, role, etc.
            $userId = $conn->lastInsertId();
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // --- SET SESSION (Matches your login-backend logic) ---
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        // Use null coalescing operator ?? in case these columns are null for new users
        $_SESSION['role'] = $user['role'] ?? 'user'; 
        $_SESSION['avatar_url'] = $user['avatar_url'] ?? null; 

        $_SESSION['logged_in'] = true;

        // --- RESTORE Kids Mode into session (if the DB stores it) ---
        $kidsMode = false;
        if (isset($user['is_kids_mode'])) {
            $kidsMode = (int)$user['is_kids_mode'] === 1;
        }
        $_SESSION['is_kids_mode'] = $kidsMode;
        $_SESSION['is_kid'] = $kidsMode ? 1 : 0;

        // --- RESTORE PLAN / SUBSCRIPTION INFO INTO SESSION ---
        $sessionPlanId = 1;
        if (!empty($user['current_plan_id'])) {
            $sessionPlanId = (int)$user['current_plan_id'];
        }

        try {
            $pstmt = $conn->prepare("SELECT name FROM plans WHERE id = ? LIMIT 1");
            $pstmt->execute([$sessionPlanId]);
            $p = $pstmt->fetch(PDO::FETCH_ASSOC);
            $planName = $p['name'] ?? 'free';
        } catch (Exception $e) {
            $planName = 'free';
        }

        $_SESSION['plan_id'] = $sessionPlanId;
        $_SESSION['plan_name'] = strtolower($planName);

        // --- DETERMINE REDIRECT URL ---
        // Matches your login logic: Admin goes to dashboard, User goes to home
        $isAdmin = $user['is_admin'] ?? 0;
        $redirect_url = ($isAdmin == 1) ? '/admin-dashboard' : '/home';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => $redirect_url
        ]);

    } catch (PDOException $e) {
        // Log error internally, show generic message to user
        error_log($e->getMessage()); 
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Token']);
}
?>
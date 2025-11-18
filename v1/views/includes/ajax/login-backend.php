<?php
// --- 2. SET HEADER & READ INPUT ---
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'));

// --- 3. VALIDATION ---
if (empty($input->identity) || empty($input->password)) {
    http_response_code(400); 
    echo json_encode(['message' => 'Username/email and password are required.']);
    exit;
}

$identity = $input->identity;
$password = $input->password;

// --- 4. LOGIN LOGIC ---
try {

    $sql = "SELECT * FROM users WHERE email = ? OR username = ?";

    $stmt = $conn->prepare($sql); 

    
    $stmt->execute([$identity, $identity]); 

    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            
            // --- SUCCESS: CREATE SESSION ---
            session_regenerate_id(true); 
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar_url'] = $user['avatar_url'];

            // --- "REMEMBER ME" LOGIC ---
            if (isset($input->rememberMe) && $input->rememberMe === true) {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $expires_at = time() + (86400 * 30); // 30 days
                $expires_sql_date = date("Y-m-d H:i:s", $expires_at);
                $user_id = $user['id'];

                $update_sql = "UPDATE users SET remember_token = ?, remember_token_expires_at = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$selector, $expires_sql_date, $user_id]);

                $cookie_value = $selector . ':' . $validator;
                
                setcookie('remember_me', $cookie_value, [
                    'expires' => $expires_at,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']), 
                    'httponly' => true,
                    'samesite' => 'Lax' 
                ]);
            }

            // --- SEND REDIRECT URL ---
            // Use your 'is_admin' column name from your database
            $redirect_url = ($user['is_admin'] == 1) ? '/admin-dashboard' : '/home';
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'redirect' => $redirect_url
            ]);
            
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid username or password.']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid username or password.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
?>
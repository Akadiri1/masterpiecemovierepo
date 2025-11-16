<?php
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'));

if (empty($input->identity) || empty($input->password)) {
    http_response_code(400); 
    echo json_encode(['message' => 'Username/email and password are required.']);
    exit;
}

$identity = $input->identity;
$password = $input->password;

try {
    // --- 2. Find User (PDO Style) ---
    $sql = "SELECT * FROM users WHERE email = ? OR username = ?";
    
    // THIS IS THE PDO 'prepare'
    $stmt = $conn->prepare($sql); 
    
    // THIS IS THE PDO 'execute' (replaces bind_param and execute)
    // This is line 31 (or around there)
    $stmt->execute([$identity, $identity]); 

    // --- 3. User Found, Verify Password (PDO Style) ---
    // (replaces get_result)
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // Use PDO fetch
        
        if (password_verify($password, $user['password'])) {
            // --- 4. Password Correct! ---
            session_regenerate_id(true); 
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $redirect_url = ($user['role'] === 'admin') ? '/admin-dashboard' : '/home';

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
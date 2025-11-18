<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set the response type to JSON
header('Content-Type: application/json');

// --- 2. Read Incoming JSON Data ---
$input = json_decode(file_get_contents('php://input'));

if (!$input) {
    http_response_code(400); 
    echo json_encode(['message' => 'Invalid data sent.']);
    exit;
}

// --- 3. Server-Side Validation ---
if (empty($input->username) || empty($input->email) || empty($input->password)) {
    http_response_code(400); 
    echo json_encode(['message' => 'Username, email, and password are required.']);
    exit;
}
if (!filter_var($input->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['field' => 'email', 'message' => 'Invalid email format.']);
    exit;
}
if (
    strlen($input->password) < 8 ||
    !preg_match('/[a-z]/', $input->password) ||
    !preg_match('/[A-Z]/', $input->password) ||
    !preg_match('/\d/', $input->password) ||
    !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $input->password)
) {
    http_response_code(400);
    echo json_encode(['field' => 'password', 'message' => 'Password must be 8+ chars with uppercase, lowercase, number, and special char.']);
    exit;
}

// --- 4. Process Registration ---
try {
    // --- Check for Duplicate Email ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input->email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['field' => 'email', 'message' => 'This email address is already in use.']);
        exit;
    }

    // --- Check for Duplicate Username ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$input->username]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['field' => 'username', 'message' => 'This username is already taken.']);
        exit;
    }

    // --- Create the User ---
    $hashed_password = password_hash($input->password, PASSWORD_BCRYPT);
    
    // === FIXED: Removed firstName and lastName ===
    $sql = "INSERT INTO users (username, email, password) 
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $input->username,
        $input->email,
        $hashed_password
    ]);

    // --- Send Success Response ---
    http_response_code(201); // 201 Created
    echo json_encode([
        'message' => 'Registration successful! Redirecting to login...',
        'redirect' => '/login' // === ADDED THIS LINE ===
    ]);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['message' => 'A database error occurred: ' . $e->getMessage()]);
}
?>
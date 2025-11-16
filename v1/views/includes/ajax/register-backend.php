<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// --- 2. Read Incoming JSON Data ---
$input = json_decode(file_get_contents('php://input'));

// If no data or invalid JSON was sent, return an error.
if (!$input) {
    http_response_code(400); // Bad Request
    echo json_encode(['message' => 'Invalid data sent.']);
    exit;
}

// --- 3. Server-Side Validation ---
// (Your validation code is all correct)
if (empty($input->username) || empty($input->email) || empty($input->password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['message' => 'Username, email, and password are required.']);
    exit;
}

if (!filter_var($input->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'field' => 'email',
        'message' => 'Invalid email format.'
    ]);
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
    echo json_encode([
        'field' => 'password',
        'message' => 'Password must be 8+ chars with uppercase, lowercase, number, and special char.'
    ]);
    exit;
}

// --- 4. Process Registration ---
// Now, this $conn variable will exist!
try {
    // --- Check for Duplicate Email ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input->email]);
    if ($stmt->fetch()) {
        http_response_code(409); // 409 Conflict
        echo json_encode([
            'field' => 'email',
            'message' => 'This email address is already in use.'
        ]);
        exit;
    }

    // --- Check for Duplicate Username ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$input->username]);
    if ($stmt->fetch()) {
        http_response_code(409); // 409 Conflict
        echo json_encode([
            'field' => 'username',
            'message' => 'This username is already taken.'
        ]);
        exit;
    }

    // --- Create the User ---
    $hashed_password = password_hash($input->password, PASSWORD_BCRYPT);
    $firstName = !empty($input->firstName) ? $input->firstName : NULL;
    $lastName = !empty($input->lastName) ? $input->lastName : NULL;

    $stmt = $conn->prepare(
        "INSERT INTO users (firstName, lastName, username, email, password) 
         VALUES (?, ?, ?, ?, ?)"
    );
    
    $stmt->execute([
        $firstName,
        $lastName,
        $input->username,
        $input->email,
        $hashed_password
    ]);

    // --- Send Success Response ---
    http_response_code(201); // 201 Created
    echo json_encode([
        'message' => 'Registration successful! You can now log in.'
    ]);

} catch (connException $e) {
    // --- Handle Database Errors ---
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
}
?>
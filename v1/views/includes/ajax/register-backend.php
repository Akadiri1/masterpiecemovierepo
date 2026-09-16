<?php
// Creates an account, signs it in straight away, and says where to go next:
// back to the page the person signed up from (?next=), or the home page.
// Before, a new account was sent to the sign-in page and lost its place.

ini_set('display_errors', 0);
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
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([
        $input->username,
        $input->email,
        $hashed_password
    ]);
    $userId = (int) $conn->lastInsertId();

    // --- Sign the new account in (the same session login-backend sets up) ---
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $input->username;
    $_SESSION['email'] = $input->email;
    $_SESSION['role'] = 'user';
    $_SESSION['avatar_url'] = null;
    $_SESSION['logged_in'] = true;
    $_SESSION['plan_id'] = 1;
    $_SESSION['plan_name'] = 'free';
    $_SESSION['is_kids_mode'] = false;
    $_SESSION['is_kid'] = 0;

    $returnTo = function_exists('safeReturnPath') ? safeReturnPath($input->next ?? '') : null;

    http_response_code(201); // 201 Created
    echo json_encode([
        'message' => 'Account created.',
        'redirect' => $returnTo ?? '/',
    ]);

} catch (PDOException $e) {
    error_log('Registration failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => "Your account couldn't be created right now. Please try again."]);
}

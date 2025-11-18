<?php
// FILE: api/update-profile.php (Corrected for Path and Filename)

header('Content-Type: application/json');

// It's assumed session_start() and $conn are available from your router.

// --- AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'You must be logged in to update your profile.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // --- GET CURRENT USER DATA ---
    $stmt = $conn->prepare("SELECT username, email, avatar_url FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['message' => 'User not found.']);
        exit;
    }

    $avatar_sql_path = $currentUser['avatar_url'];
    $username = $currentUser['username'];

    // --- VALIDATION ---
    if (empty(trim($_POST['email']))) {
        http_response_code(400);
        echo json_encode(['field' => 'email', 'message' => 'Email cannot be empty.']);
        exit;
    }
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['field' => 'email', 'message' => 'Invalid email format.']);
        exit;
    }

    $firstName = !empty($_POST['firstName']) ? trim($_POST['firstName']) : NULL;
    $lastName = !empty($_POST['lastName']) ? trim($_POST['lastName']) : NULL;

    // --- CONFLICT CHECK (Email) ---
    if ($email !== $currentUser['email']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['field' => 'email', 'message' => 'This email is already in use by another account.']);
            exit;
        }
    }

    // --- HANDLE FILE UPLOAD (CORRECTED PATH LOGIC) ---
    // The web path is the URL that will be stored in the database.
    $web_path_dir = '/uploads/avatars/';
    // The physical path is where the file is actually saved on the server's disk.
    // $_SERVER['DOCUMENT_ROOT'] points directly to your 'www' folder.
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $web_path_dir;

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create upload directory. Check permissions.']);
            exit;
        }
    }

    if (isset($_POST['is_remove_avatar']) && $_POST['is_remove_avatar'] == '1') {
        if ($currentUser['avatar_url'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $currentUser['avatar_url'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $currentUser['avatar_url']);
        }
        $avatar_sql_path = NULL;

    } else if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // File validation...
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            http_response_code(400);
            echo json_encode(['message' => 'File is too large. Max 2MB allowed.']);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime_type, $allowed_types)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid file type. Please use JPG, PNG, or WEBP.']);
            exit;
        }
        
        // --- FILENAME CORRECTION ---
        // Changed back to time() as requested for a simpler filename.
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
        
        // Construct paths with the new filename
        $upload_path = $upload_dir . $new_filename; // Full physical path for saving the file
        $avatar_sql_path = $web_path_dir . $new_filename; // Web path for the database

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Delete old avatar if it exists
            if ($currentUser['avatar_url'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $currentUser['avatar_url'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $currentUser['avatar_url']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to move uploaded file.']);
            exit;
        }
    }

    // --- UPDATE DATABASE ---
    $sql = "UPDATE users SET firstName = ?, lastName = ?, email = ?, avatar_url = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$firstName, $lastName, $email, $avatar_sql_path, $user_id]);

    // --- UPDATE SESSION & SEND SUCCESS ---
    $_SESSION['avatar_url'] = $avatar_sql_path;
    $fullName = trim($firstName . ' ' . $lastName) ?: $username;

    http_response_code(200);
    echo json_encode([
        'message' => 'Profile updated successfully!',
        'user' => [
            'fullName' => $fullName,
            'email' => $email,
            'username' => $username,
            'avatar_url' => $avatar_sql_path
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in update-profile.php for user {$user_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'An unexpected database error occurred. Please try again later.']);
}
<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Optional: remove any cookies used for login
// Clear both possible cookie names to be safe (older names and the new name)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: /login");
exit;
?>

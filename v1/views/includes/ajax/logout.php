<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Revoke this browser's remembered login first. Otherwise the cookie would
// sign the user straight back in on the next page load.
if (isset($conn) && function_exists('auth_remember_forget_current')) {
    auth_remember_forget_current($conn);
}

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

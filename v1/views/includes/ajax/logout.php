<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Optional: remove any cookies used for login
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: /login");
exit;
?>

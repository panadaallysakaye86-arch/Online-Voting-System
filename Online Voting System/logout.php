<?php
/**
 * logout.php
 * Secure Session Termination & Redirect
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Unset all session variables
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session data storage on the server
session_destroy();

// Start a fresh, clean session exclusively to carry the goodbye flash message
session_start();
session_regenerate_id(true);
set_flash('success', 'You have been safely logged out. See you next time!');

header("Location: " . url('login.php'));
exit;


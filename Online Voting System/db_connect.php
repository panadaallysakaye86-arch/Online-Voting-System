<?php
/**
 * db_connect.php
 * Central Database Connection & Configuration File
 *
 * NOTE FOR INFINITYFREE DEPLOYMENT:
 * Edit ONLY the variables inside the CONFIGURATION BLOCK below
 * with the MySQL details provided in your InfinityFree control panel.
 */

// ==========================================
// 1. DATABASE CONFIGURATION BLOCK
// ==========================================
$db_host = 'localhost';          // InfinityFree example: sql123.infinityfree.com
$db_name = 'online_voting_db';   // InfinityFree example: if0_12345678_online_voting_db
$db_user = 'root';               // InfinityFree example: if0_12345678
$db_pass = '';                   // Your InfinityFree vPanel password
$db_port = 3306;                 // Default MySQL port

// Environment mode: set to true when publishing live to InfinityFree
$is_production = false;

// ==========================================
// 2. ERROR REPORTING & ENVIRONMENT SETUP
// ==========================================
if ($is_production) {
    // Hide error details from public visitors; log them silently
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    // Show full errors during local XAMPP development
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ==========================================
// 3. SECURE SESSION INITIALIZATION
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters before starting
    session_set_cookie_params([
        'lifetime' => 0,               // Session lasts until browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,            // Mitigate XSS cookie theft
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Session timeout: automatically expire sessions after 30 minutes of inactivity
$session_timeout_seconds = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout_seconds)) {
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
    session_start();
    session_regenerate_id(true);
    $_SESSION['flash_message'] = [
        'type'    => 'warning',
        'message' => 'Your session has timed out due to inactivity. Please log in again.'
    ];
}
$_SESSION['last_activity'] = time();

// ==========================================
// 4. PDO CONNECTION ESTABLISHMENT
// ==========================================
$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use true prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Friendly error page without leaking sensitive credentials
    http_response_code(500);
    if ($is_production) {
        die("<h2>Database Connection Error</h2><p>Unable to connect to the database. Please try again later or contact the administrator.</p>");
    } else {
        die("<h2>Database Connection Error (Local Debug)</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>");
    }
}


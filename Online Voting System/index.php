<?php
/**
 * index.php
 * Main Application Entry Point & Role-Based Router
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Route visitors based on their authentication status and assigned role
if (!isLoggedIn()) {
    header("Location: " . url('login.php'));
    exit;
}

if (isAdmin()) {
    header("Location: " . url('admin/dashboard.php'));
    exit;
}

if (isVoter()) {
    header("Location: " . url('voter/ballot.php'));
    exit;
}

// Unrecognized or corrupted session role -> clear session and send to login
header("Location: " . url('logout.php'));
exit;


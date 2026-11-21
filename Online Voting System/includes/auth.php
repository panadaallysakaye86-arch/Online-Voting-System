<?php
/**
 * includes/auth.php
 * Session Authentication & Role Authorization Guards
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check whether the visitor is currently logged in.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Check whether the logged-in user is an administrator.
 */
function isAdmin(): bool {
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }
    $role = strtolower(trim((string)$_SESSION['role']));
    return in_array($role, ['admin', 'administrator'], true);
}

/**
 * Check whether the logged-in user is a registered voter.
 */
function isVoter(): bool {
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }
    $role = strtolower(trim((string)$_SESSION['role']));
    return in_array($role, ['voter', 'user', 'student'], true);
}

/**
 * Return current user information array from session.
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'user_id'    => $_SESSION['user_id'] ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'full_name'  => $_SESSION['full_name'] ?? null,
        'role'       => strtolower(trim((string)($_SESSION['role'] ?? ''))),
    ];
}

/**
 * Guard: Enforce authentication on protected pages.
 * Redirects unauthenticated visitors to the login page.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        if (function_exists('set_flash')) {
            set_flash('danger', 'Please log in to access this page.');
        } else {
            $_SESSION['flash_message'] = [
                'type'    => 'danger',
                'message' => 'Please log in to access this page.'
            ];
        }
        $target = function_exists('url') ? url('login.php') : '../login.php';
        header("Location: " . $target);
        exit;
    }
}

/**
 * Guard: Restrict access to administrators only.
 * If user is a voter, redirects directly to their ballot.
 * If user has an invalid session, wipes it via logout.
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        if (isVoter()) {
            if (function_exists('set_flash')) {
                set_flash('danger', 'Access denied: Administrator privileges required.');
            }
            $target = function_exists('url') ? url('voter/ballot.php') : '../voter/ballot.php';
            header("Location: " . $target);
            exit;
        }

        // Unrecognized or corrupted session role -> wipe and send to login
        $target = function_exists('url') ? url('logout.php') : '../logout.php';
        header("Location: " . $target);
        exit;
    }
}

/**
 * Guard: Restrict access to voters only.
 * If user is an admin, redirects directly to the dashboard.
 * If user has an invalid session, wipes it via logout.
 */
function requireVoter(): void {
    requireLogin();
    if (!isVoter()) {
        if (isAdmin()) {
            if (function_exists('set_flash')) {
                set_flash('info', 'Administrators do not cast votes. Redirected to Admin Dashboard.');
            }
            $target = function_exists('url') ? url('admin/dashboard.php') : '../admin/dashboard.php';
            header("Location: " . $target);
            exit;
        }

        // Unrecognized or corrupted session role -> wipe and send to login
        $target = function_exists('url') ? url('logout.php') : '../logout.php';
        header("Location: " . $target);
        exit;
    }
}


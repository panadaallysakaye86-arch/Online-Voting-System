<?php
/**
 * admin/candidate_delete.php
 * Handle Candidate Deletion (POST + CSRF & Disk Photo Cleanup)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// Only permit POST requests for data deletion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid request method for deletion.');
    header("Location: " . url('admin/candidates.php'));
    exit;
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
    header("Location: " . url('admin/candidates.php'));
    exit;
}

$candidate_id = isset($_POST['candidate_id']) && is_numeric($_POST['candidate_id']) ? (int)$_POST['candidate_id'] : 0;

if ($candidate_id <= 0) {
    set_flash('danger', 'Invalid candidate identifier.');
    header("Location: " . url('admin/candidates.php'));
    exit;
}

// Fetch candidate photo filename before deletion so we can remove the file from storage
$stmt = $pdo->prepare("SELECT full_name, photo FROM candidates WHERE candidate_id = ? LIMIT 1");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    set_flash('danger', 'Candidate does not exist or has already been removed.');
    header("Location: " . url('admin/candidates.php'));
    exit;
}

// Execute deletion in database (associated votes are purged automatically via FK ON DELETE CASCADE)
$deleteStmt = $pdo->prepare("DELETE FROM candidates WHERE candidate_id = ?");
$deleted = $deleteStmt->execute([$candidate_id]);

if ($deleted) {
    // Clean up the image file from the server uploads directory
    if (!empty($candidate['photo'])) {
        delete_candidate_photo($candidate['photo'], __DIR__ . '/../uploads');
    }
    set_flash('success', 'Candidate "' . e($candidate['full_name']) . '" and their associated photo have been permanently deleted.');
} else {
    set_flash('danger', 'Failed to delete candidate from the database.');
}

header("Location: " . url('admin/candidates.php'));
exit;


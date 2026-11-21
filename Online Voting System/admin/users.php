<?php
/**
 * admin/users.php
 * Users Management Panel: View, Search, Filter & Manage Registered Users
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$currentUserId = (int)$_SESSION['user_id'];

// ----------------------------------------------------
// 1. Process Actions (e.g. Delete User)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security token expired. Please try again.');
        header("Location: " . url('admin/users.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Add New User (Admin or Voter)
    if ($action === 'add') {
        $student_id = trim($_POST['student_id'] ?? '');
        $full_name  = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = trim($_POST['role'] ?? 'voter');

        $errors = [];

        if (empty($student_id)) {
            $errors[] = 'Voter / Admin ID is required.';
        } elseif (strlen($student_id) < 3 || strlen($student_id) > 50) {
            $errors[] = 'ID must be between 3 and 50 characters.';
        }

        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }

        if (!in_array($role, ['admin', 'voter'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        // Check for duplicate ID or Email
        if (empty($errors)) {
            $checkStmt = $pdo->prepare("SELECT student_id, email FROM users WHERE student_id = ? OR email = ? LIMIT 1");
            $checkStmt->execute([$student_id, $email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['student_id'], $student_id) === 0) {
                    $errors[] = 'The ID "' . e($student_id) . '" is already registered.';
                }
                if (strcasecmp($existing['email'], $email) === 0) {
                    $errors[] = 'The email address "' . e($email) . '" is already registered.';
                }
            }
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (student_id, full_name, email, password, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $saved = $insertStmt->execute([$student_id, $full_name, $email, $hashedPassword, $role]);

            if ($saved) {
                $roleLabel = ($role === 'admin') ? 'Administrator' : 'Voter';
                set_flash('success', 'New ' . $roleLabel . ' "' . e($full_name) . '" (' . e($student_id) . ') was created successfully.');
            } else {
                set_flash('danger', 'Failed to save user in database.');
            }
        } else {
            set_flash('danger', implode(' ', $errors));
        }

        header("Location: " . url('admin/users.php'));
        exit;
    }

    // Action 2: Delete Existing User
    if ($action === 'delete') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        // Security check: cannot delete oneself
        if ($targetUserId === $currentUserId) {
            set_flash('danger', 'You cannot delete your own logged-in administrator account.');
            header("Location: " . url('admin/users.php'));
            exit;
        }

        if ($targetUserId <= 0) {
            set_flash('danger', 'Invalid user ID.');
            header("Location: " . url('admin/users.php'));
            exit;
        }

        // Fetch user before deleting
        $fetchStmt = $pdo->prepare("SELECT full_name, student_id, role FROM users WHERE user_id = ? LIMIT 1");
        $fetchStmt->execute([$targetUserId]);
        $userToDelete = $fetchStmt->fetch();

        if ($userToDelete) {
            $delStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $delStmt->execute([$targetUserId]);
            set_flash('success', 'User "' . e($userToDelete['full_name']) . '" (' . e($userToDelete['student_id']) . ') has been removed.');
        } else {
            set_flash('danger', 'User not found or already removed.');
        }

        header("Location: " . url('admin/users.php'));
        exit;
    }
}

// ----------------------------------------------------
// 2. Search & Filter Parameters
// ----------------------------------------------------
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$votedFilter = trim($_GET['voted'] ?? '');

// ----------------------------------------------------
// 3. Overall Statistics Counters
// ----------------------------------------------------
$totalUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdminsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalVotersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$votedUsersCount = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM votes")->fetchColumn();

// ----------------------------------------------------
// 4. Query Users with Vote Counts & Activity
// ----------------------------------------------------
$sql = "
    SELECT u.user_id, u.student_id, u.full_name, u.email, u.role, u.created_at,
           COUNT(v.vote_id) AS votes_cast,
           MAX(v.voted_at) AS last_voted_at
    FROM users u
    LEFT JOIN votes v ON u.user_id = v.user_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($roleFilter === 'admin' || $roleFilter === 'voter') {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

$sql .= " GROUP BY u.user_id";

if ($votedFilter === 'yes') {
    $sql .= " HAVING votes_cast > 0";
} elseif ($votedFilter === 'no') {
    $sql .= " HAVING votes_cast = 0 AND u.role = 'voter'";
}

$sql .= " ORDER BY u.role ASC, u.user_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">
            <i class="bi bi-person-lines-fill text-primary me-2"></i> Users Management
        </h2>
        <p class="text-muted small mb-0">View all registered accounts, roles, voter credentials, and voting participation</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add New User
        </button>
        <a href="<?= url('admin/dashboard.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Overview Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #3b82f6 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Total Accounts</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalUsersCount); ?></h3>
                    <small class="text-muted">Registered in database</small>
                </div>
                <div class="text-primary stat-icon"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #10b981 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Voters</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalVotersCount); ?></h3>
                    <small class="text-success"><i class="bi bi-check-circle"></i> Eligible to vote</small>
                </div>
                <div class="text-success stat-icon"><i class="bi bi-person-check-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #ef4444 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Administrators</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalAdminsCount); ?></h3>
                    <small class="text-danger"><i class="bi bi-shield-lock"></i> Full system access</small>
                </div>
                <div class="text-danger stat-icon"><i class="bi bi-shield-fill-check"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #f59e0b !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Ballots Cast</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($votedUsersCount); ?></h3>
                    <small class="text-warning">
                        <?= $totalVotersCount > 0 ? round(($votedUsersCount / $totalVotersCount) * 100, 1) : 0; ?>% turnout
                    </small>
                </div>
                <div class="text-warning stat-icon"><i class="bi bi-envelope-check-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('admin/users.php'); ?>" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, voter ID, or email..." value="<?= e($search); ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="voter" <?= $roleFilter === 'voter' ? 'selected' : ''; ?>>Voters Only</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="voted" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="yes" <?= $votedFilter === 'yes' ? 'selected' : ''; ?>>Has Voted</option>
                    <option value="no" <?= $votedFilter === 'no' ? 'selected' : ''; ?>>Not Voted</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($roleFilter) || !empty($votedFilter)): ?>
                    <a href="<?= url('admin/users.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                <h5 class="fw-bold">No users match your criteria</h5>
                <p class="small mb-3">Try adjusting or resetting your search filters.</p>
                <a href="<?= url('admin/users.php'); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>User Information</th>
                            <th>Voter / Admin ID</th>
                            <th>Email Address</th>
                            <th class="text-center" style="width: 110px;">Role</th>
                            <th class="text-center" style="width: 140px;">Voting Activity</th>
                            <th>Registered</th>
                            <th class="text-end" style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $idx => $u): 
                            $isSelf = ((int)$u['user_id'] === $currentUserId);
                            $votesCast = (int)$u['votes_cast'];
                        ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $idx + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="candidate-avatar-placeholder" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                            <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-shaded text-danger' : 'bi-person text-primary'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?= e($u['full_name']); ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary small ms-1">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code class="fw-bold text-primary"><?= e($u['student_id']); ?></code>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= e($u['email']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?> text-uppercase">
                                        <?= e($u['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-light text-muted border">N/A (Admin)</span>
                                    <?php elseif ($votesCast > 0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1" title="Voted in <?= $votesCast; ?> position(s)">
                                            <i class="bi bi-check-circle-fill me-1"></i> Voted (<?= $votesCast; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-muted border px-2 py-1">
                                            <i class="bi bi-hourglass me-1"></i> Not Yet Voted
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M d, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View Details Modal Trigger -->
                                        <button type="button" class="btn btn-outline-secondary" title="View User Details" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['user_id']; ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if (!$isSelf): ?>
                                            <!-- Delete User Modal Trigger -->
                                            <button type="button" class="btn btn-outline-danger" title="Delete User" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?= $u['user_id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary disabled" title="Cannot delete current account">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- User Details Modal -->
                                    <div class="modal fade" id="userModal<?= $u['user_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-person-badge text-primary me-2"></i> User Profile
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded">
                                                        <div class="candidate-avatar-placeholder" style="width: 52px; height: 52px; font-size: 1.5rem;">
                                                            <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-lock-fill text-danger' : 'bi-person-fill text-primary'; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="fw-bold mb-0"><?= e($u['full_name']); ?></h5>
                                                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?> text-uppercase">
                                                                <?= e($u['role']); ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <ul class="list-group list-group-flush small">
                                                        <li class="list-group-item d-flex justify-content-between px-0">
                                                            <span class="text-muted">Voter / Admin ID:</span>
                                                            <strong class="text-dark"><?= e($u['student_id']); ?></strong>
                                                        </li>
                                                        <li class="list-group-item d-flex justify-content-between px-0">
                                                            <span class="text-muted">Email Address:</span>
                                                            <strong class="text-dark"><?= e($u['email']); ?></strong>
                                                        </li>
                                                        <li class="list-group-item d-flex justify-content-between px-0">
                                                            <span class="text-muted">Registration Date:</span>
                                                            <span><?= date('F d, Y \a\t h:i A', strtotime($u['created_at'])); ?></span>
                                                        </li>
                                                        <li class="list-group-item d-flex justify-content-between px-0">
                                                            <span class="text-muted">Ballots Cast:</span>
                                                            <strong><?= $votesCast; ?> position(s)</strong>
                                                        </li>
                                                        <?php if (!empty($u['last_voted_at'])): ?>
                                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                                <span class="text-muted">Last Vote Cast:</span>
                                                                <span><?= date('F d, Y \a\t h:i A', strtotime($u['last_voted_at'])); ?></span>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!$isSelf): ?>
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteUserModal<?= $u['user_id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Account Removal
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-2">Are you sure you want to delete user <strong><?= e($u['full_name']); ?></strong> (<?= e($u['student_id']); ?>)?</p>
                                                        <div class="alert alert-warning small mb-0">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            This will permanently delete this account. Any votes cast by this user will also be removed via cascade.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="<?= url('admin/users.php'); ?>" class="d-inline">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?= $u['user_id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash-fill me-1"></i> Delete User
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add New User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('admin/users.php'); ?>" autocomplete="off">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addUserModalLabel">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_role" class="form-label fw-semibold">Account Role <span class="text-danger">*</span></label>
                        <select name="role" id="new_role" class="form-select" required>
                            <option value="voter" selected>Voter (Can submit election ballot)</option>
                            <option value="admin">Administrator (Full admin management access)</option>
                        </select>
                        <div class="form-text small">Select whether this account will be a voter or an administrator.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_student_id" class="form-label fw-semibold">Voter ID / Admin ID <span class="text-danger">*</span></label>
                        <input type="text" name="student_id" id="new_student_id" class="form-control" placeholder="e.g. 2026-0005 or ADMIN-002" required maxlength="50">
                        <div class="form-text small">Unique login identifier.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="new_full_name" class="form-control" placeholder="e.g. Maria Santos" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="new_email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="new_email" class="form-control" placeholder="e.g. user@example.com" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="new_password" class="form-control" placeholder="Minimum 6 characters" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary password-toggle" data-password-target="new_password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.password-toggle').forEach((toggleButton) => {
        toggleButton.addEventListener('click', () => {
            const passwordInput = document.getElementById(toggleButton.dataset.passwordTarget);
            const passwordIcon = toggleButton.querySelector('i');
            const isPasswordVisible = passwordInput.type === 'text';

            passwordInput.type = isPasswordVisible ? 'password' : 'text';
            toggleButton.setAttribute('aria-label', isPasswordVisible ? 'Show password' : 'Hide password');
            toggleButton.setAttribute('aria-pressed', String(!isPasswordVisible));
            passwordIcon.className = isPasswordVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


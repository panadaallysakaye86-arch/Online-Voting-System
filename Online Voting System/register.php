<?php
/**
 * register.php
 * Voter Self-Registration Page (Role is locked to 'voter')
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: " . url('admin/dashboard.php'));
        exit;
    }
    header("Location: " . url('voter/ballot.php'));
    exit;
}

$errors = [];
$student_id = '';
$full_name  = '';
$email      = '';

// Process registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($submittedToken)) {
        $errors[] = 'Invalid security token. Please reload the form and try again.';
    } else {
        $student_id       = trim($_POST['student_id'] ?? '');
        $full_name        = trim($_POST['full_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation rules
        if (empty($student_id)) {
            $errors[] = 'Voter ID is required.';
        } elseif (strlen($student_id) < 3 || strlen($student_id) > 50) {
            $errors[] = 'Voter ID must be between 3 and 50 characters.';
        }

        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Password confirmation does not match.';
        }

        // Check for duplicates if basic validation passes
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT user_id, student_id, email FROM users WHERE student_id = ? OR email = ? LIMIT 1");
            $stmt->execute([$student_id, $email]);
            $existing = $stmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['student_id'], $student_id) === 0) {
                    $errors[] = 'This Voter ID is already registered in the system.';
                }
                if (strcasecmp($existing['email'], $email) === 0) {
                    $errors[] = 'This email address is already associated with an account.';
                }
            }
        }

        // Insert new voter
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role = 'voter'; // Explicitly lock registration to voter role only

            $stmt = $pdo->prepare("
                INSERT INTO users (student_id, full_name, email, password, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([$student_id, $full_name, $email, $hashedPassword, $role]);

            if ($success) {
                set_flash('success', 'Registration successful! You may now sign in using your Voter ID.');
                header("Location: " . url('login.php'));
                exit;
            } else {
                $errors[] = 'Failed to create your account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register Voter Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-person-plus fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Create Voter Account</h3>
                    <p class="text-muted small">Register to participate and vote in the election</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4" role="alert">
                        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i> Please correct the following errors:</div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('register.php'); ?>" autocomplete="off" novalidate>
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="student_id" class="form-label fw-semibold">Voter ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-card-text"></i></span>
                            <input type="text" name="student_id" id="student_id" class="form-control" placeholder="e.g. 2026-0004 or VOTER-001" value="<?= e($student_id); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="full_name" id="full_name" class="form-control" placeholder="First and last name" value="<?= e($full_name); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" id="email" class="form-control" placeholder="voter@example.com" value="<?= e($email); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 characters" required>
                                <button type="button" class="btn btn-outline-secondary password-toggle" data-password-target="password" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat password" required>
                                <button type="button" class="btn btn-outline-secondary password-toggle" data-password-target="confirm_password" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Register Account
                    </button>
                </form>

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

                <div class="text-center mt-4 pt-3 border-top">
                    <p class="text-muted small mb-0">Already have an account? <a href="<?= url('login.php'); ?>" class="text-decoration-none fw-semibold">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


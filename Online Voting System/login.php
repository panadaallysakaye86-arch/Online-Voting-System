<?php
/**
 * login.php
 * User Authentication (Admin & Voter)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already authenticated, redirect straight to their portal
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: " . url('admin/dashboard.php'));
        exit;
    }
    if (isVoter()) {
        header("Location: " . url('voter/ballot.php'));
        exit;
    }
    header("Location: " . url('logout.php'));
    exit;
}

$error = '';
$student_id = '';

// Handle POST login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($submittedToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $student_id = trim($_POST['student_id'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($student_id) || empty($password)) {
            $error = 'Please enter both your Voter ID and password.';
        } else {
            // Find user by unique student_id using prepared statement
            $stmt = $pdo->prepare("SELECT user_id, student_id, full_name, email, password, role FROM users WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Prevent session fixation attack
                session_regenerate_id(true);

                // Store verified user details in session
                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['role']       = strtolower(trim((string)$user['role']));
                $_SESSION['last_activity'] = time();

                set_flash('success', 'Welcome back, ' . e($user['full_name']) . '!');

                // Redirect according to role
                if (isAdmin()) {
                    header("Location: " . url('admin/dashboard.php'));
                } else {
                    header("Location: " . url('voter/ballot.php'));
                }
                exit;
            } else {
                $error = 'Invalid Voter ID or password. Please verify your credentials.';
            }
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-person-lock fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Sign In</h3>
                    <p class="text-muted small">Access the official online voting portal</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div><?= e($error); ?></div>
                    </div>
                <?php endif; ?>


                <form method="POST" action="<?= url('login.php'); ?>" autocomplete="off" novalidate>
                    <?= csrf_field(); ?>

                    <!-- Voter ID / Admin ID -->
                    <div class="mb-3">
                        <label for="student_id" class="form-label fw-semibold">
                            Voter ID / Admin ID
                        </label>

                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-card-text"></i>
                            </span>

                            <input
                                type="text"
                                name="student_id"
                                id="student_id"
                                class="form-control"
                                placeholder="Enter your Voter ID or Admin ID"
                                value="<?= e($student_id); ?>"
                                autocomplete="off"
                                required
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">
                            Password
                        </label>

                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-key"></i>
                            </span>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                placeholder="Enter your password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="togglePassword"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>
                </form>

                <script>
                    const passwordInput = document.getElementById('password');
                    const togglePassword = document.getElementById('togglePassword');
                    const togglePasswordIcon = togglePassword.querySelector('i');

                    togglePassword.addEventListener('click', () => {
                        const isPasswordVisible = passwordInput.type === 'text';
                        passwordInput.type = isPasswordVisible ? 'password' : 'text';
                        togglePassword.setAttribute('aria-label', isPasswordVisible ? 'Show password' : 'Hide password');
                        togglePassword.setAttribute('aria-pressed', String(!isPasswordVisible));
                        togglePasswordIcon.className = isPasswordVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
                    });
                </script>

                <div class="text-center mt-4 pt-3 border-top">
                    <p class="text-muted small mb-0">Don't have an account yet? <a href="<?= url('register.php'); ?>" class="text-decoration-none fw-semibold">Register as Voter</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


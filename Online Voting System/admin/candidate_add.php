<?php
/**
 * admin/candidate_add.php
 * Create Candidate Profile with Secure Photo Upload
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// Fetch positions for the dropdown
$stmt = $pdo->query("SELECT position_id, position_name FROM positions ORDER BY position_name ASC");
$positions = $stmt->fetchAll();

$errors = [];
$position_id = 0;
$full_name   = '';
$course_year = '';
$platform    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please reload and try again.';
    } else {
        $position_id = (int)($_POST['position_id'] ?? 0);
        $full_name   = trim($_POST['full_name'] ?? '');
        $course_year = trim($_POST['course_year'] ?? '');
        $platform    = trim($_POST['platform'] ?? '');

        // Validation
        if ($position_id <= 0) {
            $errors[] = 'Please select a valid election position.';
        } else {
            // Verify position actually exists
            $checkPos = $pdo->prepare("SELECT position_id FROM positions WHERE position_id = ?");
            $checkPos->execute([$position_id]);
            if (!$checkPos->fetch()) {
                $errors[] = 'Selected position does not exist.';
            }
        }

        if (empty($full_name)) {
            $errors[] = 'Candidate full name is required.';
        } elseif (strlen($full_name) > 100) {
            $errors[] = 'Candidate name cannot exceed 100 characters.';
        }

        if (empty($course_year)) {
            $errors[] = 'Affiliation / Department / Title is required.';
        }

        // Handle photo upload if provided
        $uploadedFilename = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = upload_candidate_photo($_FILES['photo'], __DIR__ . '/../uploads');
            if (!$uploadResult['success']) {
                $errors[] = $uploadResult['error'];
            } else {
                $uploadedFilename = $uploadResult['filename'];
            }
        }

        // Save candidate if no errors
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO candidates (position_id, full_name, course_year, platform, photo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $saved = $stmt->execute([$position_id, $full_name, $course_year, $platform, $uploadedFilename]);

            if ($saved) {
                set_flash('success', 'Candidate "' . e($full_name) . '" has been added successfully.');
                header("Location: " . url('admin/candidates.php'));
                exit;
            } else {
                // Cleanup uploaded image on DB error
                if ($uploadedFilename) {
                    delete_candidate_photo($uploadedFilename, __DIR__ . '/../uploads');
                }
                $errors[] = 'Failed to record candidate details in database.';
            }
        } else {
            // Cleanup uploaded photo if other validations failed
            if ($uploadedFilename) {
                delete_candidate_photo($uploadedFilename, __DIR__ . '/../uploads');
            }
        }
    }
}

$pageTitle = 'Add New Candidate';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-2">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="h3 fw-bold mb-0">Add Candidate</h2>
                <p class="text-muted small mb-0">Create a candidate entry for the upcoming elections</p>
            </div>
            <a href="<?= url('admin/candidates.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>

        <?php if (empty($positions)): ?>
            <div class="alert alert-warning shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No positions configured!</strong> You must create at least one position before adding candidates.
                <a href="<?= url('admin/positions.php'); ?>" class="alert-link ms-2">Manage Positions &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mb-4 shadow-sm" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i> Please fix the following:</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="<?= url('admin/candidate_add.php'); ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="position_id" class="form-label fw-semibold">Election Position <span class="text-danger">*</span></label>
                            <select name="position_id" id="position_id" class="form-select" required <?= empty($positions) ? 'disabled' : ''; ?>>
                                <option value="">-- Choose Position --</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= $pos['position_id']; ?>" <?= ($position_id === (int)$pos['position_id']) ? 'selected' : ''; ?>>
                                        <?= e($pos['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Candidate Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control" placeholder="e.g. Maria Santos" value="<?= e($full_name); ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="course_year" class="form-label fw-semibold">Affiliation / Department / Title <span class="text-danger">*</span></label>
                            <input type="text" name="course_year" id="course_year" class="form-control" placeholder="e.g. Operations / Regional Representative / Independent" value="<?= e($course_year); ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="platform" class="form-label fw-semibold">Platform & Vision Statement</label>
                            <textarea name="platform" id="platform" rows="4" class="form-control" placeholder="Detail the candidate's agenda, key advocacies, and election platform..."><?= e($platform); ?></textarea>
                            <div class="form-text small">This platform statement will be displayed directly to voters on the official voting ballot.</div>
                        </div>

                        <div class="col-12">
                            <label for="photo" class="form-label fw-semibold">Candidate Photo</label>
                            <input type="file" name="photo" id="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Accepts JPG, PNG, or WEBP (Max 2MB). If omitted, a default avatar placeholder will be used.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('admin/candidates.php'); ?>" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary" <?= empty($positions) ? 'disabled' : ''; ?>>
                            <i class="bi bi-check2-circle me-1"></i> Save Candidate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


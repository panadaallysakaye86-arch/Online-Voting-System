<?php
/**
 * admin/candidate_edit.php
 * Edit Existing Candidate & Update Photo
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$candidate_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch candidate record to edit
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = ? LIMIT 1");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    set_flash('danger', 'Candidate not found.');
    header("Location: " . url('admin/candidates.php'));
    exit;
}

// Fetch positions list
$stmtPos = $pdo->query("SELECT position_id, position_name FROM positions ORDER BY position_name ASC");
$positions = $stmtPos->fetchAll();

$errors = [];
$position_id = (int)$candidate['position_id'];
$full_name   = $candidate['full_name'];
$course_year = $candidate['course_year'];
$platform    = $candidate['platform'];
$currentPhoto = $candidate['photo'];

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
            $checkPos = $pdo->prepare("SELECT position_id FROM positions WHERE position_id = ?");
            $checkPos->execute([$position_id]);
            if (!$checkPos->fetch()) {
                $errors[] = 'Selected position does not exist.';
            }
        }

        if (empty($full_name)) {
            $errors[] = 'Candidate full name is required.';
        }

        if (empty($course_year)) {
            $errors[] = 'Affiliation / Department / Title is required.';
        }

        $newUploadedPhoto = null;
        $finalPhotoFilename = $currentPhoto; // Default to keeping existing photo

        // Handle replacement photo upload if supplied
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = upload_candidate_photo($_FILES['photo'], __DIR__ . '/../uploads');
            if (!$uploadResult['success']) {
                $errors[] = $uploadResult['error'];
            } else {
                $newUploadedPhoto = $uploadResult['filename'];
                $finalPhotoFilename = $newUploadedPhoto;
            }
        }

        // Update candidate in database
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE candidates 
                SET position_id = ?, full_name = ?, course_year = ?, platform = ?, photo = ?
                WHERE candidate_id = ?
            ");
            $updated = $stmt->execute([
                $position_id,
                $full_name,
                $course_year,
                $platform,
                $finalPhotoFilename,
                $candidate_id
            ]);

            if ($updated) {
                // If a new photo was uploaded, safely remove the old photo file
                if ($newUploadedPhoto && !empty($currentPhoto) && $newUploadedPhoto !== $currentPhoto) {
                    delete_candidate_photo($currentPhoto, __DIR__ . '/../uploads');
                }

                set_flash('success', 'Candidate details for "' . e($full_name) . '" updated successfully.');
                header("Location: " . url('admin/candidates.php'));
                exit;
            } else {
                if ($newUploadedPhoto) {
                    delete_candidate_photo($newUploadedPhoto, __DIR__ . '/../uploads');
                }
                $errors[] = 'Failed to update candidate record in the database.';
            }
        } else {
            if ($newUploadedPhoto) {
                delete_candidate_photo($newUploadedPhoto, __DIR__ . '/../uploads');
            }
        }
    }
}

$pageTitle = 'Edit Candidate';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-2">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="h3 fw-bold mb-0">Edit Candidate</h2>
                <p class="text-muted small mb-0">Update profile, platform, or candidate photo</p>
            </div>
            <a href="<?= url('admin/candidates.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>

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
                <form method="POST" action="<?= url('admin/candidate_edit.php?id=' . $candidate_id); ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="position_id" class="form-label fw-semibold">Election Position <span class="text-danger">*</span></label>
                            <select name="position_id" id="position_id" class="form-select" required>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= $pos['position_id']; ?>" <?= ($position_id === (int)$pos['position_id']) ? 'selected' : ''; ?>>
                                        <?= e($pos['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Candidate Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control" value="<?= e($full_name); ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="course_year" class="form-label fw-semibold">Affiliation / Department / Title <span class="text-danger">*</span></label>
                            <input type="text" name="course_year" id="course_year" class="form-control" value="<?= e($course_year); ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="platform" class="form-label fw-semibold">Platform & Vision Statement</label>
                            <textarea name="platform" id="platform" rows="4" class="form-control"><?= e($platform); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Current Photo</label>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <?php if (!empty($currentPhoto) && file_exists(__DIR__ . '/../uploads/' . $currentPhoto)): ?>
                                    <img src="<?= url('uploads/' . e($currentPhoto)); ?>" alt="Candidate Photo" class="rounded border" style="width: 80px; height: 80px; object-fit: cover;">
                                    <div class="small text-muted">
                                        Current file: <code><?= e($currentPhoto); ?></code><br>
                                        Leave the file field below empty to keep this photo.
                                    </div>
                                <?php else: ?>
                                    <div class="candidate-avatar-placeholder" style="width: 80px; height: 80px; font-size: 2rem;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <div class="small text-muted">No photo uploaded yet.</div>
                                <?php endif; ?>
                            </div>

                            <label for="photo" class="form-label fw-semibold">Upload New Photo (Optional)</label>
                            <input type="file" name="photo" id="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Accepts JPG, PNG, or WEBP (Max 2MB). Uploading a new image automatically replaces the existing one.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('admin/candidates.php'); ?>" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Update Candidate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


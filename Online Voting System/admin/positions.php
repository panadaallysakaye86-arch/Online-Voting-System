<?php
/**
 * admin/positions.php
 * Positions CRUD Management (Create, Read, Update, Delete with CSRF)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// ----------------------------------------------------
// 1. Process POST Actions: Add, Edit, Delete
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header("Location: " . url('admin/positions.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action A: Add New Position
    if ($action === 'add') {
        $name = trim($_POST['position_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            set_flash('danger', 'Position name cannot be empty.');
        } elseif (strlen($name) > 100) {
            set_flash('danger', 'Position name cannot exceed 100 characters.');
        } else {
            // Check for duplicate position name
            $checkStmt = $pdo->prepare("SELECT position_id FROM positions WHERE position_name = ? LIMIT 1");
            $checkStmt->execute([$name]);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'A position named "' . e($name) . '" already exists.');
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO positions (position_name, description) VALUES (?, ?)");
                $insertStmt->execute([$name, $description]);
                set_flash('success', 'Position "' . e($name) . '" has been created successfully.');
            }
        }
        header("Location: " . url('admin/positions.php'));
        exit;
    }

    // Action B: Edit Existing Position
    if ($action === 'edit') {
        $position_id = (int)($_POST['position_id'] ?? 0);
        $name = trim($_POST['position_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($position_id <= 0 || empty($name)) {
            set_flash('danger', 'Invalid position data submitted.');
        } else {
            // Check for duplicate name under another ID
            $checkStmt = $pdo->prepare("SELECT position_id FROM positions WHERE position_name = ? AND position_id != ? LIMIT 1");
            $checkStmt->execute([$name, $position_id]);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'Another position is already using the name "' . e($name) . '".');
            } else {
                $updateStmt = $pdo->prepare("UPDATE positions SET position_name = ?, description = ? WHERE position_id = ?");
                $updateStmt->execute([$name, $description, $position_id]);
                set_flash('success', 'Position "' . e($name) . '" updated successfully.');
            }
        }
        header("Location: " . url('admin/positions.php'));
        exit;
    }

    // Action C: Delete Position
    if ($action === 'delete') {
        $position_id = (int)($_POST['position_id'] ?? 0);

        if ($position_id <= 0) {
            set_flash('danger', 'Invalid position ID.');
        } else {
            // Get position name and candidate count before deleting
            $infoStmt = $pdo->prepare("
                SELECT p.position_name, COUNT(c.candidate_id) AS cand_count 
                FROM positions p
                LEFT JOIN candidates c ON p.position_id = c.position_id
                WHERE p.position_id = ?
                GROUP BY p.position_id
            ");
            $infoStmt->execute([$position_id]);
            $posInfo = $infoStmt->fetch();

            if ($posInfo) {
                // Also retrieve candidate photo filenames to purge them from disk
                $photoStmt = $pdo->prepare("SELECT photo FROM candidates WHERE position_id = ? AND photo IS NOT NULL");
                $photoStmt->execute([$position_id]);
                $photos = $photoStmt->fetchAll();
                foreach ($photos as $ph) {
                    delete_candidate_photo($ph['photo'], __DIR__ . '/../uploads');
                }

                // Delete position (cascades to candidates and votes in DB)
                $delStmt = $pdo->prepare("DELETE FROM positions WHERE position_id = ?");
                $delStmt->execute([$position_id]);

                set_flash('success', 'Position "' . e($posInfo['position_name']) . '" and all associated candidate records were removed.');
            } else {
                set_flash('danger', 'Position not found.');
            }
        }
        header("Location: " . url('admin/positions.php'));
        exit;
    }
}

// ----------------------------------------------------
// 2. Query Positions with Candidate & Vote Statistics
// ----------------------------------------------------
$sql = "
    SELECT p.position_id, p.position_name, p.description, p.created_at,
           COUNT(DISTINCT c.candidate_id) AS total_candidates,
           COUNT(DISTINCT v.vote_id) AS total_votes
    FROM positions p
    LEFT JOIN candidates c ON p.position_id = c.position_id
    LEFT JOIN votes v ON p.position_id = v.position_id
    GROUP BY p.position_id
    ORDER BY p.position_id ASC
";
$positions = $pdo->query($sql)->fetchAll();

$pageTitle = 'Manage Election Positions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">Election Positions</h2>
        <p class="text-muted small mb-0">Create and configure leadership roles available on the voting ballot</p>
    </div>
    <div class="mt-2 mt-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPositionModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Position
        </button>
    </div>
</div>

<!-- Positions Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($positions)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>
                <h5 class="fw-bold">No positions found</h5>
                <p class="small mb-3">Get started by defining election positions such as President, Vice President, etc.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                    <i class="bi bi-plus-lg me-1"></i> Add First Position
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Position Name</th>
                            <th>Role Description</th>
                            <th class="text-center" style="width: 140px;">Candidates</th>
                            <th class="text-center" style="width: 130px;">Votes Cast</th>
                            <th class="text-end" style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $index => $pos): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $index + 1; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($pos['position_name']); ?></div>
                                    <div class="text-muted small">Created: <?= date('M d, Y', strtotime($pos['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        <?= e($pos['description'] ?: 'No role description provided.'); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="<?= url('admin/candidates.php?position_id=' . $pos['position_id']); ?>" class="badge bg-primary text-decoration-none rounded-pill px-3 py-2">
                                        <?= (int)$pos['total_candidates']; ?> candidate<?= (int)$pos['total_candidates'] === 1 ? '' : 's'; ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill px-3 py-2">
                                        <?= (int)$pos['total_votes']; ?> vote<?= (int)$pos['total_votes'] === 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" title="Edit Position" data-bs-toggle="modal" data-bs-target="#editModal<?= $pos['position_id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" title="Delete Position" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $pos['position_id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Edit Position Modal -->
                                    <div class="modal fade" id="editModal<?= $pos['position_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content">
                                                <form method="POST" action="<?= url('admin/positions.php'); ?>">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="position_id" value="<?= $pos['position_id']; ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Position</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Position Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="position_name" class="form-control" value="<?= e($pos['position_name']); ?>" required maxlength="100">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Description / Duties</label>
                                                            <textarea name="description" rows="3" class="form-control"><?= e($pos['description']); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="bi bi-check2 me-1"></i> Update Position
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Position Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?= $pos['position_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Position Deletion</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-2">Are you sure you want to delete position <strong><?= e($pos['position_name']); ?></strong>?</p>
                                                    <?php if ((int)$pos['total_candidates'] > 0 || (int)$pos['total_votes'] > 0): ?>
                                                        <div class="alert alert-danger small mb-0">
                                                            <i class="bi bi-exclamation-octagon-fill me-1"></i>
                                                            <strong>Warning:</strong> This position currently contains <strong><?= (int)$pos['total_candidates']; ?> candidate(s)</strong> and <strong><?= (int)$pos['total_votes']; ?> vote(s)</strong>. Deleting this position will permanently delete all associated candidates, photos, and votes in cascade!
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning small mb-0">
                                                            This action cannot be undone.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="POST" action="<?= url('admin/positions.php'); ?>">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="position_id" value="<?= $pos['position_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash-fill me-1"></i> Delete Position
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Position Modal -->
<div class="modal fade" id="addPositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('admin/positions.php'); ?>">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i> Add New Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_position_name" class="form-label fw-semibold">Position Name <span class="text-danger">*</span></label>
                        <input type="text" name="position_name" id="new_position_name" class="form-control" placeholder="e.g. President / Director / Chairperson" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="new_description" class="form-label fw-semibold">Role Description / Duties</label>
                        <textarea name="description" id="new_description" rows="3" class="form-control" placeholder="Brief explanation of duties and qualifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Position
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


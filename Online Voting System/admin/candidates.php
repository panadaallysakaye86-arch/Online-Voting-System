<?php
/**
 * admin/candidates.php
 * Candidates Management: Table with Search, Position Filter & Delete Modal
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// Capture filter and search query parameters
$search = trim($_GET['search'] ?? '');
$filterPosition = isset($_GET['position_id']) && is_numeric($_GET['position_id']) ? (int)$_GET['position_id'] : 0;

// Fetch all available positions for the filter dropdown
$stmtPos = $pdo->query("SELECT position_id, position_name FROM positions ORDER BY position_name ASC");
$allPositions = $stmtPos->fetchAll();

// Build dynamic filtered query using prepared statement
$sql = "
    SELECT c.candidate_id, c.position_id, c.full_name, c.course_year, c.platform, c.photo, c.created_at,
           p.position_name
    FROM candidates c
    JOIN positions p ON c.position_id = p.position_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.full_name LIKE ? OR c.course_year LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterPosition > 0) {
    $sql .= " AND c.position_id = ?";
    $params[] = $filterPosition;
}

$sql .= " ORDER BY p.position_name ASC, c.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

$pageTitle = 'Manage Candidates';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">Candidate Management</h2>
        <p class="text-muted small mb-0">Manage running candidates, position assignments, platforms, and official photos</p>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="<?= url('admin/candidate_add.php'); ?>" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-1"></i> Add New Candidate
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('admin/candidates.php'); ?>" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by candidate name or affiliation/department..." value="<?= e($search); ?>">
                </div>
            </div>

            <div class="col-md-4">
                <select name="position_id" class="form-select">
                    <option value="0">All Positions</option>
                    <?php foreach ($allPositions as $pos): ?>
                        <option value="<?= $pos['position_id']; ?>" <?= ($filterPosition === (int)$pos['position_id']) ? 'selected' : ''; ?>>
                            <?= e($pos['position_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || $filterPosition > 0): ?>
                    <a href="<?= url('admin/candidates.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Candidates Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($candidates)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                <h5 class="fw-bold">No candidates found</h5>
                <p class="small mb-3">Try clearing search filters or add a new candidate to get started.</p>
                <a href="<?= url('admin/candidate_add.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Candidate
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Photo</th>
                            <th>Candidate Details</th>
                            <th>Running Position</th>
                            <th>Platform Summary</th>
                            <th>Date Added</th>
                            <th class="text-end" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $cand): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/../uploads/' . $cand['photo'])): ?>
                                        <img src="<?= url('uploads/' . e($cand['photo'])); ?>" alt="<?= e($cand['full_name']); ?>" class="candidate-avatar">
                                    <?php else: ?>
                                        <div class="candidate-avatar-placeholder">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($cand['full_name']); ?></div>
                                    <div class="text-muted small"><?= e($cand['course_year']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                        <?= e($cand['position_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $platform = trim($cand['platform'] ?? '');
                                        $excerpt = mb_strimwidth($platform, 0, 75, '...');
                                    ?>
                                    <div class="small text-muted mb-1"><?= e($excerpt ?: 'No platform statement provided.'); ?></div>
                                    <?php if (mb_strlen($platform) > 75): ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" data-bs-toggle="modal" data-bs-target="#platformModal<?= $cand['candidate_id']; ?>">
                                            Read full platform &rarr;
                                        </button>

                                        <!-- Platform Modal -->
                                        <div class="modal fade" id="platformModal<?= $cand['candidate_id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Platform: <?= e($cand['full_name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="text-muted small mb-2">Running for <strong><?= e($cand['position_name']); ?></strong> (<?= e($cand['course_year']); ?>)</p>
                                                        <div class="p-3 bg-light rounded border" style="white-space: pre-wrap;"><?= e($cand['platform']); ?></div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M d, Y', strtotime($cand['created_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('admin/candidate_edit.php?id=' . $cand['candidate_id']); ?>" class="btn btn-outline-secondary" title="Edit Candidate">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" title="Delete Candidate" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $cand['candidate_id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?= $cand['candidate_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Deletion</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-2">Are you sure you want to delete candidate <strong><?= e($cand['full_name']); ?></strong>?</p>
                                                    <div class="alert alert-warning small mb-0">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        This will also permanently delete the candidate's uploaded photo and remove any associated votes in cascade.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="POST" action="<?= url('admin/candidate_delete.php'); ?>" class="d-inline">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="candidate_id" value="<?= $cand['candidate_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash-fill me-1"></i> Delete Candidate
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


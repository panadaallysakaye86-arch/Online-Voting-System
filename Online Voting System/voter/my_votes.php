<?php
/**
 * voter/my_votes.php
 * Official Voter Receipt: Table of Submitted Votes
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireVoter();

$user_id = (int)$_SESSION['user_id'];

// 1. Fetch total available positions
$totStmt = $pdo->query("SELECT COUNT(*) FROM positions");
$totalPositions = (int)$totStmt->fetchColumn();

// 2. Fetch all votes cast by this voter
$stmt = $pdo->prepare("
    SELECT v.vote_id, v.voted_at, 
           p.position_name, p.position_id,
           c.full_name AS candidate_name, c.course_year, c.photo
    FROM votes v
    JOIN positions p ON v.position_id = p.position_id
    JOIN candidates c ON v.candidate_id = c.candidate_id
    WHERE v.user_id = ?
    ORDER BY p.position_id ASC
");
$stmt->execute([$user_id]);
$myVotes = $stmt->fetchAll();

$votedCount = count($myVotes);

$pageTitle = 'My Voting Receipt';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">Official Voting Receipt</h2>
        <p class="text-muted small mb-0">A verifiable record of the votes cast under your voter credentials</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <?php if ($votedCount < $totalPositions && $electionSchedule['is_open']): ?>
            <a href="<?= url('voter/ballot.php'); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-ui-checks me-1"></i> Continue Voting (<?= $votedCount; ?>/<?= $totalPositions; ?>)
            </a>
        <?php endif; ?>
        <?php if (!empty($myVotes)): ?>
            <button type="button" class="btn btn-dark btn-sm" onclick="window.print();">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($myVotes)): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <div class="p-3 bg-light rounded-circle text-muted d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 72px; height: 72px;">
            <i class="bi bi-receipt-cutoff fs-2"></i>
        </div>
        <h4 class="fw-bold">No Votes Recorded Yet</h4>
        <p class="text-muted small mb-3">You have not submitted any votes for the active election cycle.</p>
        <div>
            <a href="<?= url('voter/ballot.php'); ?>" class="btn btn-primary">
                <i class="bi bi-ui-checks me-1"></i> Go to Official Ballot
            </a>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <span class="badge bg-success-subtle text-success border border-success mb-1">
                    <i class="bi bi-shield-check me-1"></i> Verified Submission
                </span>
                <h5 class="fw-bold mb-0">Ballot Reference: #VC-<?= str_pad((string)$user_id, 5, '0', STR_PAD_LEFT); ?></h5>
            </div>
            <div class="text-end text-muted small mt-2 mt-sm-0">
                <div>Voter: <strong><?= e($_SESSION['full_name']); ?></strong> (<?= e($_SESSION['student_id']); ?>)</div>
                <div>Status: <strong><?= $votedCount; ?> of <?= $totalPositions; ?></strong> positions voted</div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Contested Position</th>
                            <th>Selected Candidate</th>
                            <th>Affiliation / Details</th>
                            <th>Date & Time Cast</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myVotes as $idx => $vote): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $idx + 1; ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <?= e($vote['position_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($vote['photo']) && file_exists(__DIR__ . '/../uploads/' . $vote['photo'])): ?>
                                            <img src="<?= url('uploads/' . e($vote['photo'])); ?>" alt="Candidate" class="candidate-avatar" style="width: 36px; height: 36px;">
                                        <?php else: ?>
                                            <div class="candidate-avatar-placeholder" style="width: 36px; height: 36px; font-size: 1rem;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="fw-bold text-dark"><?= e($vote['candidate_name']); ?></span>
                                    </div>
                                </td>
                                <td class="text-muted small">
                                    <?= e($vote['course_year']); ?>
                                </td>
                                <td class="text-muted small">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('M d, Y - h:i:s A', strtotime($vote['voted_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-all me-1"></i> Recorded
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-light p-3 small text-muted d-flex justify-content-between align-items-center">
            <span><i class="bi bi-info-circle me-1"></i> This receipt confirms your vote was officially stored in the database.</span>
            <?php if ($votedCount < $totalPositions && $electionSchedule['is_open']): ?>
                <a href="<?= url('voter/ballot.php'); ?>" class="btn btn-sm btn-primary">
                    Vote Remaining Positions &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<?php
/**
 * voter/ballot.php
 * Official Election Ballot: Candidates Grouped by Position
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce voter privileges
requireVoter();

$user_id = (int)$_SESSION['user_id'];
$electionSchedule = get_election_schedule($pdo);

// 1. Fetch positions ordered by position_id
$posStmt = $pdo->query("SELECT position_id, position_name, description FROM positions ORDER BY position_id ASC");
$positions = $posStmt->fetchAll();

// 2. Fetch candidates grouped by position_id
$candStmt = $pdo->query("
    SELECT candidate_id, position_id, full_name, course_year, platform, photo 
    FROM candidates 
    ORDER BY position_id ASC, full_name ASC
");
$allCandidates = $candStmt->fetchAll();
$candidatesByPosition = [];
foreach ($allCandidates as $cand) {
    $candidatesByPosition[$cand['position_id']][] = $cand;
}

// 3. Fetch which positions this user has ALREADY voted for
$voteStmt = $pdo->prepare("
    SELECT v.position_id, v.candidate_id, c.full_name AS candidate_name 
    FROM votes v
    JOIN candidates c ON v.candidate_id = c.candidate_id
    WHERE v.user_id = ?
");
$voteStmt->execute([$user_id]);
$existingVotes = [];
foreach ($voteStmt->fetchAll() as $row) {
    $existingVotes[$row['position_id']] = $row;
}

// Calculate voting progress
$totalPositionsCount = count($positions);
$votedPositionsCount = count($existingVotes);
$allVoted = ($totalPositionsCount > 0 && $votedPositionsCount >= $totalPositionsCount);

$pageTitle = 'Official Ballot';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Ballot Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">Official Election Ballot</h2>
        <p class="text-muted small mb-0">Select your preferred candidate for each position below</p>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="<?= url('voter/my_votes.php'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-receipt me-1"></i> My Voting Receipt (<?= $votedPositionsCount; ?>/<?= $totalPositionsCount; ?>)
        </a>
    </div>
</div>

<!-- Election Schedule Notice -->
<?php if ($electionSchedule['status'] === 'upcoming'): ?>
    <div class="alert alert-info shadow-sm d-flex align-items-center gap-3">
        <i class="bi bi-calendar-clock fs-2 flex-shrink-0"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">Voting Has Not Started Yet</h5>
            <p class="mb-0 small">The election will open on <strong><?= date('F d, Y \a\t h:i A', strtotime($electionSchedule['start'])); ?></strong>. You can review the candidates below, but ballot submission is disabled until the window opens.</p>
        </div>
    </div>
<?php elseif ($electionSchedule['status'] === 'ended'): ?>
    <div class="alert alert-dark shadow-sm d-flex align-items-center gap-3">
        <i class="bi bi-lock-fill fs-2 flex-shrink-0"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">Election Has Concluded</h5>
            <p class="mb-0 small">Voting officially closed on <strong><?= date('F d, Y \a\t h:i A', strtotime($electionSchedule['end'])); ?></strong>. Ballots can no longer be cast.</p>
        </div>
    </div>
<?php elseif ($allVoted): ?>
    <div class="alert alert-success shadow-sm d-flex align-items-center gap-3">
        <i class="bi bi-check-circle-fill fs-2 text-success flex-shrink-0"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">You Have Cast All Your Votes!</h5>
            <p class="mb-0 small">Thank you for participating. You have voted for every available position. You can inspect your submitted choices anytime on your receipt page.</p>
            <a href="<?= url('voter/my_votes.php'); ?>" class="btn btn-success btn-sm mt-2">
                <i class="bi bi-receipt me-1"></i> View My Voting Receipt
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($positions)): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-inbox fs-1 text-muted mb-2"></i>
        <h5 class="fw-bold">No Election Positions Active</h5>
        <p class="text-muted small">Please check back once the election administrator has configured the ballot.</p>
    </div>
<?php else: ?>

    <form id="ballotForm" method="POST" action="<?= url('voter/vote_submit.php'); ?>">
        <?= csrf_field(); ?>

        <?php foreach ($positions as $index => $pos): 
            $posId = (int)$pos['position_id'];
            $hasVotedThis = isset($existingVotes[$posId]);
            $candidates = $candidatesByPosition[$posId] ?? [];
        ?>
            <div class="card border-0 shadow-sm mb-4 <?= $hasVotedThis ? 'bg-light bg-opacity-50' : ''; ?>">
                <!-- Position Header -->
                <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-secondary mb-1">Position #<?= $index + 1; ?></span>
                        <h4 class="fw-bold text-dark mb-0"><?= e($pos['position_name']); ?></h4>
                        <?php if (!empty($pos['description'])): ?>
                            <small class="text-muted"><?= e($pos['description']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($hasVotedThis): ?>
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                <i class="bi bi-check2-circle me-1"></i> Voted: <?= e($existingVotes[$posId]['candidate_name']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2">
                                <i class="bi bi-dash-circle me-1"></i> Not Yet Voted
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Candidates Grid -->
                <div class="card-body pt-0">
                    <?php if (empty($candidates)): ?>
                        <div class="p-4 text-center text-muted bg-white rounded border">
                            <i class="bi bi-person-x fs-2 d-block mb-1"></i>
                            No candidates currently running for this position.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($candidates as $cand): 
                                $candId = (int)$cand['candidate_id'];
                                $isChosen = ($hasVotedThis && (int)$existingVotes[$posId]['candidate_id'] === $candId);
                                $radioId = "vote_{$posId}_{$candId}";
                            ?>
                                <div class="col-md-6 col-lg-4">
                                    <label for="<?= $radioId; ?>" class="w-100 h-100 text-decoration-none">
                                        <div class="card h-100 candidate-card <?= $isChosen ? 'border-success bg-success bg-opacity-10' : ''; ?> <?= $hasVotedThis ? 'opacity-75' : ''; ?>">
                                            
                                            <!-- Candidate Photo -->
                                            <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/../uploads/' . $cand['photo'])): ?>
                                                <img src="<?= url('uploads/' . e($cand['photo'])); ?>" alt="<?= e($cand['full_name']); ?>" class="candidate-thumb">
                                            <?php else: ?>
                                                <div class="candidate-thumb d-flex align-items-center justify-content-center text-muted">
                                                    <i class="bi bi-person fs-1"></i>
                                                </div>
                                            <?php endif; ?>

                                            <div class="card-body d-flex flex-column p-3">
                                                <div class="d-flex align-items-start justify-content-between mb-2">
                                                    <div>
                                                        <h5 class="fw-bold mb-0 text-dark"><?= e($cand['full_name']); ?></h5>
                                                        <small class="text-primary fw-semibold"><?= e($cand['course_year']); ?></small>
                                                    </div>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="radio" 
                                                               name="votes[<?= $posId; ?>]" 
                                                               id="<?= $radioId; ?>" 
                                                               value="<?= $candId; ?>"
                                                               <?= $isChosen ? 'checked' : ''; ?>
                                                               <?= ($hasVotedThis || !$electionSchedule['is_open']) ? 'disabled' : ''; ?>>
                                                    </div>
                                                </div>

                                                <!-- Platform statement -->
                                                <div class="mt-auto pt-2 border-top">
                                                    <div class="small text-muted mb-0" style="font-size: 0.85rem;">
                                                        <strong>Platform:</strong>
                                                        <?= e(mb_strimwidth($cand['platform'] ?? 'No statement provided.', 0, 110, '...')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Submit Ballot Bar -->
        <?php if ($electionSchedule['is_open'] && !$allVoted): ?>
            <div class="card border-0 shadow-sm sticky-bottom mb-4 bg-white">
                <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="fw-bold text-dark">Ready to finalize your ballot?</div>
                        <small class="text-muted">Review your selections carefully before submitting.</small>
                    </div>
                    <button type="button" class="btn btn-success btn-lg px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#confirmVoteModal">
                        <i class="bi bi-envelope-check me-2"></i> Submit Official Ballot
                    </button>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmVoteModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold">
                                <i class="bi bi-shield-lock-fill me-2"></i> Confirm Vote Submission
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Are you sure you are ready to submit your vote?</p>
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>Important:</strong> Votes cast are final and cannot be modified or withdrawn once recorded.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back & Review</button>
                            <button type="button" class="btn btn-success" onclick="document.getElementById('ballotForm').submit();">
                                <i class="bi bi-check-circle-fill me-1"></i> Yes, Cast My Ballot
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </form>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


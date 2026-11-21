<?php
/**
 * admin/results.php
 * Election Results & Tally Board: Candidate Votes, Percentages & Leader Badges
 * (Admins can view anytime; Voters can view once the election has ended)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access control: User must be logged in
requireLogin();

$electionSchedule = get_election_schedule($pdo);

// Voter access restriction: Voters can view results ONLY after election concludes
if (isVoter() && $electionSchedule['status'] !== 'ended') {
    set_flash('info', 'Election results are locked during active voting and will become available once voting officially concludes on ' . date('M d, Y h:i A', strtotime($electionSchedule['end'])) . '.');
    header("Location: " . url('voter/ballot.php'));
    exit;
}

// 1. Fetch total statistics
$totalVotersStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter'");
$totalEligibleVoters = (int)$totalVotersStmt->fetchColumn();

$totalVotedStmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM votes");
$actualVoters = (int)$totalVotedStmt->fetchColumn();

$turnoutPct = ($totalEligibleVoters > 0) ? round(($actualVoters / $totalEligibleVoters) * 100, 1) : 0;

$totalVotesCastStmt = $pdo->query("SELECT COUNT(*) FROM votes");
$totalVotesCast = (int)$totalVotesCastStmt->fetchColumn();

// 2. Fetch positions with their candidates and vote counts
$positionsStmt = $pdo->query("SELECT position_id, position_name, description FROM positions ORDER BY position_id ASC");
$positions = $positionsStmt->fetchAll();

// Group tally by position
$resultsByPosition = [];

foreach ($positions as $pos) {
    $posId = (int)$pos['position_id'];

    // Query all candidates for this position with their vote count
    $candStmt = $pdo->prepare("
        SELECT c.candidate_id, c.full_name, c.course_year, c.photo, c.platform,
               COUNT(v.vote_id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.position_id = ?
        WHERE c.position_id = ?
        GROUP BY c.candidate_id
        ORDER BY vote_count DESC, c.full_name ASC
    ");
    $candStmt->execute([$posId, $posId]);
    $candidates = $candStmt->fetchAll();

    // Calculate total votes cast for this position
    $posTotalVotes = 0;
    $maxVotes = 0;
    foreach ($candidates as $cand) {
        $count = (int)$cand['vote_count'];
        $posTotalVotes += $count;
        if ($count > $maxVotes) {
            $maxVotes = $count;
        }
    }

    $resultsByPosition[] = [
        'position'        => $pos,
        'candidates'      => $candidates,
        'pos_total_votes' => $posTotalVotes,
        'max_votes'       => $maxVotes,
    ];
}

$pageTitle = 'Election Results';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">
            <i class="bi bi-trophy-fill text-warning me-2"></i>
            <?= ($electionSchedule['status'] === 'ended') ? 'Final Certified Results' : 'Live Election Tally'; ?>
        </h2>
        <p class="text-muted small mb-0">Real-time vote aggregation, percentages, and leading candidates per contested role</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <?php if (isAdmin()): ?>
            <a href="<?= url('admin/settings.php'); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Election Schedule
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Turnout & Schedule Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold">Election Status</span>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge <?= e($electionSchedule['badgeClass']); ?> fs-6">
                        <?= e($electionSchedule['label']); ?>
                    </span>
                </div>
                <small class="text-muted d-block mt-2">
                    Window: <?= date('M d, Y', strtotime($electionSchedule['start'])); ?> &mdash; <?= date('M d, Y', strtotime($electionSchedule['end'])); ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold">Voter Turnout</span>
                <h4 class="fw-bold mb-0 text-primary mt-1"><?= $turnoutPct; ?>%</h4>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $turnoutPct; ?>%;"></div>
                </div>
                <small class="text-muted d-block mt-1"><?= $actualVoters; ?> of <?= $totalEligibleVoters; ?> registered voters cast ballots</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold">Total Ballots Processed</span>
                <h4 class="fw-bold mb-0 text-dark mt-1"><?= number_format($totalVotesCast); ?></h4>
                <small class="text-muted d-block mt-2">Across <?= count($positions); ?> contested council positions</small>
            </div>
        </div>
    </div>
</div>

<!-- Position Results List -->
<?php if (empty($resultsByPosition)): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-bar-chart fs-1 text-muted mb-2"></i>
        <h5 class="fw-bold">No Positions to Tally</h5>
        <p class="text-muted small">No positions or votes have been recorded in the system.</p>
    </div>
<?php else: ?>
    <?php foreach ($resultsByPosition as $group): 
        $pos = $group['position'];
        $candidates = $group['candidates'];
        $posTotal = $group['pos_total_votes'];
        $maxVotes = $group['max_votes'];
    ?>
        <div class="card border-0 shadow-sm mb-4">
            <!-- Position Header -->
            <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold text-dark mb-0"><?= e($pos['position_name']); ?></h4>
                    <?php if (!empty($pos['description'])): ?>
                        <small class="text-muted"><?= e($pos['description']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="mt-2 mt-sm-0">
                    <span class="badge bg-light text-dark border px-3 py-2">
                        Total Votes: <strong><?= number_format($posTotal); ?></strong>
                    </span>
                </div>
            </div>

            <!-- Position Candidates Tally -->
            <div class="card-body pt-0">
                <?php if (empty($candidates)): ?>
                    <div class="p-3 text-center text-muted bg-light rounded">
                        No registered candidates for this position.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">Rank</th>
                                    <th style="width: 60px;">Photo</th>
                                    <th>Candidate</th>
                                    <th>Affiliation / Department</th>
                                    <th style="width: 35%;">Vote Share</th>
                                    <th class="text-end" style="width: 120px;">Votes Cast</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $rank => $cand): 
                                    $votes = (int)$cand['vote_count'];
                                    $percentage = ($posTotal > 0) ? round(($votes / $posTotal) * 100, 1) : 0;
                                    $isLeader = ($votes > 0 && $votes === $maxVotes);
                                    $isWinner = ($isLeader && $electionSchedule['status'] === 'ended');
                                ?>
                                    <tr class="<?= $isWinner ? 'table-success bg-opacity-25' : ($isLeader ? 'bg-primary bg-opacity-10' : ''); ?>">
                                        <td class="fw-bold text-muted">
                                            <?php if ($isWinner): ?>
                                                <i class="bi bi-trophy-fill text-warning fs-5" title="Elected"></i>
                                            <?php elseif ($isLeader): ?>
                                                <i class="bi bi-star-fill text-warning" title="Leading"></i>
                                            <?php else: ?>
                                                <?= $rank + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/../uploads/' . $cand['photo'])): ?>
                                                <img src="<?= url('uploads/' . e($cand['photo'])); ?>" alt="Candidate" class="candidate-avatar">
                                            <?php else: ?>
                                                <div class="candidate-avatar-placeholder">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold text-dark"><?= e($cand['full_name']); ?></span>
                                                <?php if ($isWinner): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check2-circle me-1"></i> Winner / Elected
                                                    </span>
                                                <?php elseif ($isLeader): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="bi bi-arrow-up-circle me-1"></i> Leading
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-muted small"><?= e($cand['course_year']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1 vote-progress" style="height: 14px;">
                                                    <div class="progress-bar <?= $isWinner ? 'bg-success' : ($isLeader ? 'bg-primary' : 'bg-secondary'); ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $percentage; ?>%;" 
                                                         aria-valuenow="<?= $percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="small fw-bold text-muted" style="min-width: 48px;"><?= $percentage; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold fs-6">
                                            <?= number_format($votes); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

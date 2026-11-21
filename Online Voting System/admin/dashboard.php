<?php
/**
 * admin/dashboard.php
 * Administrator Control Center & Overview Analytics
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin privileges
requireAdmin();

// 1. Gather statistical overview metrics
try {
    // Total registered voters
    $stmtVoters = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter'");
    $totalVoters = (int)$stmtVoters->fetchColumn();

    // Total candidates running
    $stmtCandidates = $pdo->query("SELECT COUNT(*) FROM candidates");
    $totalCandidates = (int)$stmtCandidates->fetchColumn();

    // Total available positions
    $stmtPositions = $pdo->query("SELECT COUNT(*) FROM positions");
    $totalPositions = (int)$stmtPositions->fetchColumn();

    // Total vote ballots cast
    $stmtVotes = $pdo->query("SELECT COUNT(*) FROM votes");
    $totalVotes = (int)$stmtVotes->fetchColumn();

    // Number of unique voters who participated
    $stmtVotedUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM votes");
    $participatedVoters = (int)$stmtVotedUsers->fetchColumn();

    $turnoutPct = ($totalVoters > 0) ? round(($participatedVoters / $totalVoters) * 100, 1) : 0;

    // Recent 5 votes submitted
    $stmtRecent = $pdo->query("
        SELECT v.vote_id, v.voted_at, u.student_id, u.full_name AS voter_name, 
               p.position_name, c.full_name AS candidate_name
        FROM votes v
        JOIN users u ON v.user_id = u.user_id
        JOIN positions p ON v.position_id = p.position_id
        JOIN candidates c ON v.candidate_id = c.candidate_id
        ORDER BY v.voted_at DESC
        LIMIT 5
    ");
    $recentVotes = $stmtRecent->fetchAll();
} catch (PDOException $e) {
    $totalVoters = $totalCandidates = $totalPositions = $totalVotes = $participatedVoters = 0;
    $turnoutPct = 0;
    $recentVotes = [];
}

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold mb-0">Election Dashboard</h2>
        <p class="text-muted small mb-0">Overview of voter turnout, candidates, and real-time tallies</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <a href="<?= url('admin/candidate_add.php'); ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i> Add Candidate
        </a>
        <a href="<?= url('admin/positions.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> Manage Positions
        </a>
    </div>
</div>

<!-- Election Schedule Status Banner -->
<div class="card mb-4 border-0 shadow-sm bg-white">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between p-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-light rounded-circle text-primary">
                <i class="bi bi-calendar-event fs-3"></i>
            </div>
            <div>
                <span class="badge <?= e($electionSchedule['badgeClass']); ?> mb-1">
                    <?= e($electionSchedule['label']); ?>
                </span>
                <h5 class="fw-bold mb-1"><?= e($electionSchedule['title']); ?></h5>
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock me-1"></i> Window: 
                    <strong><?= date('M d, Y h:i A', strtotime($electionSchedule['start'])); ?></strong> to 
                    <strong><?= date('M d, Y h:i A', strtotime($electionSchedule['end'])); ?></strong>
                </p>
            </div>
        </div>
        <a href="<?= url('admin/settings.php'); ?>" class="btn btn-outline-primary btn-sm mt-3 mt-md-0">
            <i class="bi bi-gear me-1"></i> Change Schedule
        </a>
    </div>
</div>

<!-- 4 Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #3b82f6 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Total Voters</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalVoters); ?></h3>
                    <a href="<?= url('admin/users.php'); ?>" class="text-decoration-none small text-muted">Manage users &rarr;</a>
                </div>
                <div class="text-primary stat-icon"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #10b981 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Candidates</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalCandidates); ?></h3>
                    <a href="<?= url('admin/candidates.php'); ?>" class="text-decoration-none small text-muted">View all &rarr;</a>
                </div>
                <div class="text-success stat-icon"><i class="bi bi-person-badge-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #8b5cf6 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Positions</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalPositions); ?></h3>
                    <a href="<?= url('admin/positions.php'); ?>" class="text-decoration-none small text-muted">Configure &rarr;</a>
                </div>
                <div class="text-info stat-icon"><i class="bi bi-diagram-3-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm stat-card" style="border-left-color: #f59e0b !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Total Ballots Cast</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalVotes); ?></h3>
                    <a href="<?= url('admin/results.php'); ?>" class="text-decoration-none small text-muted">Live results &rarr;</a>
                </div>
                <div class="text-warning stat-icon"><i class="bi bi-envelope-paper-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Voter Turnout Progress & Recent Activity -->
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart me-1"></i> Voter Participation</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold">Turnout Rate</span>
                    <span class="text-primary fw-bold"><?= $turnoutPct; ?>%</span>
                </div>
                <div class="progress mb-4" style="height: 14px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $turnoutPct; ?>%;" aria-valuenow="<?= $turnoutPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="bi bi-person-check text-success me-2"></i>Voters Participated</span>
                        <span class="badge bg-success rounded-pill"><?= $participatedVoters; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="bi bi-hourglass text-warning me-2"></i>Yet to Vote</span>
                        <span class="badge bg-secondary rounded-pill"><?= max(0, $totalVoters - $participatedVoters); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="bi bi-people text-primary me-2"></i>Total Eligible Voters</span>
                        <span class="badge bg-dark rounded-pill"><?= $totalVoters; ?></span>
                    </li>
                </ul>

                <div class="mt-4 text-center">
                    <a href="<?= url('admin/results.php'); ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-graph-up me-1"></i> View Live Election Tally
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-activity me-1"></i> Recent Votes Cast</h5>
                <span class="badge bg-light text-muted border">Latest 5</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentVotes)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No votes have been recorded yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Voter</th>
                                    <th>Position</th>
                                    <th>Candidate</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentVotes as $rv): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($rv['voter_name']); ?></div>
                                            <div class="text-muted small"><?= e($rv['student_id']); ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($rv['position_name']); ?></span></td>
                                        <td class="text-primary fw-semibold"><?= e($rv['candidate_name']); ?></td>
                                        <td class="text-muted small"><?= date('M d, h:i A', strtotime($rv['voted_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


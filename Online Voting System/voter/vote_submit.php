<?php
/**
 * voter/vote_submit.php
 * Transaction-Safe Ballot Processing & Duplicate Vote Prevention
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce voter privileges
requireVoter();

// Accept only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Invalid request method for ballot submission.');
    header("Location: " . url('voter/ballot.php'));
    exit;
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (invalid CSRF token). Please try submitting your ballot again.');
    header("Location: " . url('voter/ballot.php'));
    exit;
}

// Enforce election schedule window
$electionSchedule = get_election_schedule($pdo);
if (!$electionSchedule['is_open']) {
    set_flash('danger', 'Ballot submission is closed. Current status: ' . $electionSchedule['label']);
    header("Location: " . url('voter/ballot.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$votes = $_POST['votes'] ?? [];

// Validate that votes array is present and non-empty
if (!is_array($votes) || empty($votes)) {
    set_flash('warning', 'Please select at least one candidate before submitting your ballot.');
    header("Location: " . url('voter/ballot.php'));
    exit;
}

try {
    // Begin atomic transaction
    $pdo->beginTransaction();

    $preparedCandidateCheck = $pdo->prepare("SELECT candidate_id FROM candidates WHERE candidate_id = ? AND position_id = ? LIMIT 1");
    $preparedVoteCheck = $pdo->prepare("SELECT vote_id FROM votes WHERE user_id = ? AND position_id = ? LIMIT 1");
    $preparedInsert = $pdo->prepare("INSERT INTO votes (user_id, position_id, candidate_id, voted_at) VALUES (?, ?, ?, NOW())");

    $recordedVotesCount = 0;

    foreach ($votes as $positionId => $candidateId) {
        $posId = (int)$positionId;
        $candId = (int)$candidateId;

        // Skip non-numeric or unselected inputs
        if ($posId <= 0 || $candId <= 0) {
            continue;
        }

        // 1. Verify candidate genuinely belongs to this position
        $preparedCandidateCheck->execute([$candId, $posId]);
        if (!$preparedCandidateCheck->fetch()) {
            $pdo->rollBack();
            set_flash('danger', 'Invalid candidate selection detected for position #' . $posId . '. Ballot rejected.');
            header("Location: " . url('voter/ballot.php'));
            exit;
        }

        // 2. Verify user has not already voted for this position
        $preparedVoteCheck->execute([$user_id, $posId]);
        if ($preparedVoteCheck->fetch()) {
            $pdo->rollBack();
            set_flash('danger', 'Double-voting blocked: You have already cast a vote for one of these positions.');
            header("Location: " . url('voter/ballot.php'));
            exit;
        }

        // 3. Insert vote record
        $preparedInsert->execute([$user_id, $posId, $candId]);
        $recordedVotesCount++;
    }

    if ($recordedVotesCount === 0) {
        $pdo->rollBack();
        set_flash('warning', 'No valid candidate selections were processed. Please try again.');
        header("Location: " . url('voter/ballot.php'));
        exit;
    }

    // Commit all votes simultaneously
    $pdo->commit();

    set_flash('success', 'Your official ballot containing ' . $recordedVotesCount . ' selection(s) has been securely recorded!');
    header("Location: " . url('voter/my_votes.php'));
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle duplicate key error code 23000 (enforced by UNIQUE(user_id, position_id) on DB level)
    if ($e->getCode() == '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
        set_flash('danger', 'Duplicate vote prevented: You have already cast a vote for one or more of these positions.');
    } else {
        set_flash('danger', 'An error occurred while saving your ballot: ' . ($is_production ? 'Database error.' : e($e->getMessage())));
    }

    header("Location: " . url('voter/ballot.php'));
    exit;
}


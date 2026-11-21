<?php


require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// ----------------------------------------------------
// HANDLE FORM SUBMISSIONS
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Your session has expired. Please try again.');
        header("Location: " . url('admin/settings.php'));
        exit;
    }

    $action = $_POST['action'] ?? 'save';

    // ------------------------------------------------
    // START VOTING IMMEDIATELY
    // Voting will remain open for 48 hours.
    // ------------------------------------------------

    if ($action === 'preset_active') {

        $newStart = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $newEnd   = date('Y-m-d H:i:s', strtotime('+48 hours'));

        set_setting($pdo, 'election_start', $newStart);
        set_setting($pdo, 'election_end', $newEnd);

        set_flash(
            'success',
            'Voting has started successfully. The voting period is set to 48 hours.'
        );

        header("Location: " . url('admin/settings.php'));
        exit;
    }

    // ------------------------------------------------
    // CLOSE VOTING IMMEDIATELY
    // ------------------------------------------------

    if ($action === 'preset_ended') {

        $newStart = date('Y-m-d H:i:s', strtotime('-2 days'));
        $newEnd   = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        set_setting($pdo, 'election_start', $newStart);
        set_setting($pdo, 'election_end', $newEnd);

        set_flash(
            'success',
            'Voting has been closed. Final results are now available to voters.'
        );

        header("Location: " . url('admin/settings.php'));
        exit;
    }

    // ------------------------------------------------
    // SAVE ELECTION SETTINGS
    // ------------------------------------------------

    if ($action === 'save') {

        $title = trim($_POST['election_title'] ?? '');

        $startInput = trim($_POST['election_start'] ?? '');
        $endInput   = trim($_POST['election_end'] ?? '');

        $startTime = strtotime($startInput);
        $endTime   = strtotime($endInput);

        if (empty($title)) {

            set_flash('danger', 'Please enter an election name.');

        } elseif (!$startTime || !$endTime) {

            set_flash('danger', 'Please enter valid voting dates and times.');

        } elseif ($endTime <= $startTime) {

            set_flash(
                'danger',
                'The voting end date and time must be later than the start date and time.'
            );

        } else {

            $formattedStart = date('Y-m-d H:i:s', $startTime);
            $formattedEnd   = date('Y-m-d H:i:s', $endTime);

            set_setting($pdo, 'election_title', $title);
            set_setting($pdo, 'election_start', $formattedStart);
            set_setting($pdo, 'election_end', $formattedEnd);

            set_flash(
                'success',
                'Election settings have been saved successfully.'
            );
        }

        header("Location: " . url('admin/settings.php'));
        exit;
    }
}

// ----------------------------------------------------
// LOAD CURRENT ELECTION SETTINGS
// ----------------------------------------------------

$currentTitle = get_setting(
    $pdo,
    'election_title',
    'General Online Voting'
);

$currentStart = get_setting(
    $pdo,
    'election_start',
    '2026-01-01 08:00:00'
);

$currentEnd = get_setting(
    $pdo,
    'election_end',
    '2026-12-31 17:00:00'
);

// Format dates for datetime-local inputs
$startInputValue = date(
    'Y-m-d\TH:i',
    strtotime($currentStart) ?: time()
);

$endInputValue = date(
    'Y-m-d\TH:i',
    strtotime($currentEnd) ?: time() + 86400
);

// Get current voting status
$scheduleStatus = get_election_schedule($pdo);

// Page title
$pageTitle = 'Election Settings';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =========================================
     PAGE HEADER
========================================= -->

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">

    <div>
        <h2 class="h3 fw-bold mb-0">
            Election Settings
        </h2>

        <p class="text-muted small mb-0">
            Manage your election name, voting schedule, and voting period.
        </p>
    </div>

    <div class="mt-2 mt-md-0">
        <a href="<?= url('admin/dashboard.php'); ?>"
           class="btn btn-outline-secondary btn-sm">

            <i class="bi bi-arrow-left me-1"></i>
            Back to Dashboard

        </a>
    </div>

</div>


<!-- =========================================
     CURRENT VOTING STATUS
========================================= -->

<div class="card mb-4 border-0 shadow-sm bg-white">

    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">

        <!-- Election Information -->

        <div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                <span class="badge <?= e($scheduleStatus['badgeClass']); ?> fs-6">
                    <?= e($scheduleStatus['label']); ?>
                </span>

                <span class="text-muted small">
                    Current Time: <?= date('M d, Y h:i A'); ?>
                </span>

            </div>

            <h4 class="fw-bold text-dark mb-1">
                <?= e($currentTitle); ?>
            </h4>

            <p class="text-muted small mb-0">

                <i class="bi bi-calendar-range me-1"></i>

                Voting Period:

                <strong>
                    <?= date('M d, Y - h:i A', strtotime($currentStart)); ?>
                </strong>

                to

                <strong>
                    <?= date('M d, Y - h:i A', strtotime($currentEnd)); ?>
                </strong>

            </p>

        </div>


        <!-- Quick Voting Actions -->

        <div class="d-flex flex-wrap gap-2">

            <!-- Start Voting -->

            <form method="POST"
                  action="<?= url('admin/settings.php'); ?>"
                  class="d-inline"
                  onsubmit="return confirm('Are you sure you want to start voting now? This will set a new 48-hour voting period.');">

                <?= csrf_field(); ?>

                <input type="hidden"
                       name="action"
                       value="preset_active">

                <button type="submit"
                        class="btn btn-success btn-sm"
                        title="Start voting immediately for 48 hours">

                    <i class="bi bi-play-circle-fill me-1"></i>
                    Start Voting

                </button>

            </form>


            <!-- Close Voting -->

            <form method="POST"
                  action="<?= url('admin/settings.php'); ?>"
                  class="d-inline"
                  onsubmit="return confirm('Are you sure you want to close voting immediately?');">

                <?= csrf_field(); ?>

                <input type="hidden"
                       name="action"
                       value="preset_ended">

                <button type="submit"
                        class="btn btn-dark btn-sm"
                        title="Close voting immediately">

                    <i class="bi bi-stop-circle-fill me-1"></i>
                    Close Voting

                </button>

            </form>

        </div>

    </div>

</div>


<!-- =========================================
     VOTING SETTINGS
========================================= -->

<div class="row g-4">

    <!-- LEFT: VOTING SCHEDULE FORM -->

    <div class="col-lg-8">

        <div class="card border-0 shadow-sm">

            <!-- Card Header -->

            <div class="card-header bg-white border-bottom py-3">

                <h5 class="fw-bold mb-0">

                    <i class="bi bi-calendar-check me-2 text-primary"></i>
                    Voting Schedule

                </h5>

            </div>


            <!-- Card Body -->

            <div class="card-body p-4">

                <form method="POST"
                      action="<?= url('admin/settings.php'); ?>"
                      novalidate>

                    <?= csrf_field(); ?>

                    <input type="hidden"
                           name="action"
                           value="save">


                    <!-- Election Name -->

                    <div class="mb-4">

                        <label for="election_title"
                               class="form-label fw-semibold">

                            Election Name
                            <span class="text-danger">*</span>

                        </label>

                        <input type="text"
                               name="election_title"
                               id="election_title"
                               class="form-control"
                               value="<?= e($currentTitle); ?>"
                               placeholder="Enter election name"
                               required
                               maxlength="150">

                        <div class="form-text">
                            This name will appear on the ballot and voter receipts.
                        </div>

                    </div>


                    <!-- Voting Dates -->

                    <div class="row g-3 mb-4">

                        <!-- Start Date -->

                        <div class="col-md-6">

                            <label for="election_start"
                                   class="form-label fw-semibold">

                                Voting Start Date & Time
                                <span class="text-danger">*</span>

                            </label>

                            <input type="datetime-local"
                                   name="election_start"
                                   id="election_start"
                                   class="form-control"
                                   value="<?= e($startInputValue); ?>"
                                   required>

                            <div class="form-text">
                                Voting will open at this date and time.
                            </div>

                        </div>


                        <!-- End Date -->

                        <div class="col-md-6">

                            <label for="election_end"
                                   class="form-label fw-semibold">

                                Voting End Date & Time
                                <span class="text-danger">*</span>

                            </label>

                            <input type="datetime-local"
                                   name="election_end"
                                   id="election_end"
                                   class="form-control"
                                   value="<?= e($endInputValue); ?>"
                                   required>

                            <div class="form-text">
                                Voting will close automatically at this date and time.
                            </div>

                        </div>

                    </div>


                    <!-- Save Button -->

                    <div class="d-flex justify-content-end">

                        <button type="submit"
                                class="btn btn-primary px-4 fw-semibold">

                            <i class="bi bi-save2 me-1"></i>
                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- RIGHT: VOTING INSTRUCTIONS -->

    <div class="col-lg-4">

        <div class="card border-0 shadow-sm bg-light">

            <!-- Information Header -->

            <div class="card-header bg-light border-bottom py-3">

                <h6 class="fw-bold mb-0 text-dark">

                    <i class="bi bi-info-circle-fill text-primary me-2"></i>
                    How Voting Works

                </h6>

            </div>


            <!-- Information Body -->

            <div class="card-body small text-muted">


                <!-- Before Voting -->

                <div class="mb-3">

                    <strong class="text-dark d-block mb-1">
                        1. Before Voting Starts
                    </strong>

                    Voters can view candidates but cannot submit their ballots.

                </div>


                <!-- During Voting -->

                <div class="mb-3">

                    <strong class="text-dark d-block mb-1">
                        2. While Voting Is Open
                    </strong>

                    Voters can select candidates and submit their ballots.
                    Each voter can only vote once per position.

                </div>


                <!-- After Voting -->

                <div>

                    <strong class="text-dark d-block mb-1">
                        3. After Voting Closes
                    </strong>

                    Ballot submission is disabled, and election results
                    become available to voters.

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<?php
/**
 * includes/header.php
 * Sidebar Layout & Role-Based Navigation
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$electionSchedule = get_election_schedule($pdo);
$pageTitle = $pageTitle ?? $electionSchedule['title'];

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$showSidebar = isLoggedIn();

function sidebarActive(string $page, string $currentPage): string {
    return $page === $currentPage ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle); ?> | Online Voting System</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css'); ?>">
</head>

<body class="<?= isLoggedIn() ? 'authenticated-layout' : 'guest-layout'; ?>">

<?php if ($showSidebar): ?>

<!-- =====================================
     LEFT SIDEBAR
====================================== -->
<aside class="offcanvas-lg offcanvas-start sidebar"
       tabindex="-1"
       id="appSidebar"
       aria-labelledby="sidebarLabel">

    <!-- Sidebar Brand -->
    <div class="sidebar-header">
        <a href="<?= url('index.php'); ?>" class="sidebar-brand">
            <i class="bi bi-box-seam-fill"></i>
            <span id="sidebarLabel">Online Voting</span>
        </a>

        <button type="button"
                class="btn-close btn-close-white d-lg-none"
                data-bs-dismiss="offcanvas"
                data-bs-target="#appSidebar"
                aria-label="Close">
        </button>
    </div>

    <!-- Sidebar Navigation -->
    <div class="sidebar-body">

        <?php if (isAdmin()): ?>

            <div class="sidebar-section-label">ADMINISTRATION</div>

            <a href="<?= url('admin/dashboard.php'); ?>"
               class="sidebar-link <?= sidebarActive('dashboard.php', $currentPage); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= url('admin/positions.php'); ?>"
               class="sidebar-link <?= sidebarActive('positions.php', $currentPage); ?>">
                <i class="bi bi-diagram-3"></i>
                <span>Positions</span>
            </a>

            <a href="<?= url('admin/candidates.php'); ?>"
               class="sidebar-link <?= sidebarActive('candidates.php', $currentPage); ?>">
                <i class="bi bi-people"></i>
                <span>Candidates</span>
            </a>

            <a href="<?= url('admin/results.php'); ?>"
               class="sidebar-link <?= sidebarActive('results.php', $currentPage); ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Results</span>
            </a>

            <a href="<?= url('admin/settings.php'); ?>"
               class="sidebar-link <?= sidebarActive('settings.php', $currentPage); ?>">
                <i class="bi bi-calendar-event"></i>
                <span>Schedule</span>
            </a>

            <a href="<?= url('admin/users.php'); ?>"
               class="sidebar-link <?= sidebarActive('users.php', $currentPage); ?>">
                <i class="bi bi-person-lines-fill"></i>
                <span>Users</span>
            </a>

        <?php elseif (isVoter()): ?>

            <div class="sidebar-section-label">VOTER MENU</div>

            <a href="<?= url('voter/ballot.php'); ?>"
               class="sidebar-link <?= sidebarActive('ballot.php', $currentPage); ?>">
                <i class="bi bi-ui-checks"></i>
                <span>Official Ballot</span>
            </a>

            <a href="<?= url('voter/my_votes.php'); ?>"
               class="sidebar-link <?= sidebarActive('my_votes.php', $currentPage); ?>">
                <i class="bi bi-receipt"></i>
                <span>My Votes</span>
            </a>

            <?php if ($electionSchedule['status'] === 'ended'): ?>
                <a href="<?= url('admin/results.php'); ?>"
                   class="sidebar-link <?= sidebarActive('results.php', $currentPage); ?>">
                    <i class="bi bi-trophy"></i>
                    <span>Final Results</span>
                </a>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user-icon">
            <i class="bi bi-person-circle"></i>
        </div>

        <div class="sidebar-user-info">
            <strong><?= e($_SESSION['full_name'] ?? 'User'); ?></strong>
            <small><?= e($_SESSION['role'] ?? ''); ?></small>
        </div>
    </div>

</aside>

<?php endif; ?>


<!-- =====================================
     MAIN CONTENT AREA
====================================== -->

<?php if ($showSidebar): ?>
    <div class="sidebar-backdrop d-lg-none"
         data-bs-dismiss="offcanvas"
         data-bs-target="#appSidebar">
    </div>
<?php endif; ?>

<!-- Top Header -->
<header class="top-header">

    <div class="d-flex align-items-center gap-3">

        <?php if ($showSidebar): ?>
            <button class="btn sidebar-toggle d-lg-none"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#appSidebar"
                    aria-controls="appSidebar">
                <i class="bi bi-list fs-3"></i>
            </button>
        <?php endif; ?>

        <div class="top-header-title">
            <?= e($pageTitle); ?>
        </div>

    </div>

    <div class="ms-auto">

        <?php if (isLoggedIn()): ?>

            <div class="dropdown">
                <button class="btn user-menu-button dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                    <i class="bi bi-person-circle fs-5"></i>

                    <span class="d-none d-sm-inline">
                        <?= e($_SESSION['full_name'] ?? 'User'); ?>
                    </span>

                    <span class="badge <?= isAdmin() ? 'bg-danger' : 'bg-primary'; ?>">
                        <?= e($_SESSION['role'] ?? ''); ?>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow">

                    <li class="dropdown-header">
                        Signed in as
                        <strong><?= e($_SESSION['student_id'] ?? ''); ?></strong>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <a class="dropdown-item text-danger"
                           href="<?= url('logout.php'); ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Logout
                        </a>
                    </li>

                </ul>
            </div>

        <?php else: ?>

            <a href="<?= url('login.php'); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Login
            </a>

        <?php endif; ?>

    </div>

</header>

<!-- Page Content -->
<main class="app-main">
    <div class="container-fluid py-4">

        <?php display_flash(); ?>

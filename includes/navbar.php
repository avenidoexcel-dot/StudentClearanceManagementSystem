<?php
$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
?>
<div class="app-wrapper">
    <!-- Sidebar Dimmer Backdrop for Mobile Devices -->
    <div id="sidebarBackdrop" class="sidebar-backdrop no-print"></div>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="sidebar-logo-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="sidebar-title">ClearanceHub</div>
                    <div class="sidebar-subtitle">Student Portal</div>
                </div>
            </div>
            <button id="mobileSidebarClose" class="sidebar-close-btn no-print" type="button" aria-label="Close Navigation">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <?php if ($user && $user['role'] === 'student'): ?>
                <div class="nav-section-title">Student Menu</div>
                <a href="<?= url('student/dashboard.php') ?>" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>My Clearance</span>
                </a>
                <a href="<?= url('student/request.php') ?>" class="nav-link <?= $current_page === 'request.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Submit Request</span>
                </a>
                <a href="<?= url('student/certificate.php') ?>" class="nav-link <?= $current_page === 'certificate.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-award"></i>
                    <span>Official Certificate</span>
                </a>

            <?php elseif ($user && $user['role'] === 'instructor'): ?>
                <div class="nav-section-title">Instructor Menu</div>
                <a href="<?= url('instructor/dashboard.php') ?>" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>My Classes & Students</span>
                </a>

            <?php elseif ($user && $user['role'] === 'signatory'): ?>
                <div class="nav-section-title">Department Menu</div>
                <a href="<?= url('signatory/dashboard.php') ?>" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-building-columns"></i>
                    <span>Office Approvals</span>
                </a>

            <?php elseif ($user && $user['role'] === 'admin'): ?>
                <div class="nav-section-title">Admin Dashboard</div>
                <a href="<?= url('admin/dashboard.php') ?>" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Overview & Analytics</span>
                </a>
                <div class="nav-section-title">Management</div>
                <a href="<?= url('admin/periods.php') ?>" class="nav-link <?= $current_page === 'periods.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Clearance Periods</span>
                </a>
                <a href="<?= url('admin/departments.php') ?>" class="nav-link <?= $current_page === 'departments.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-building-user"></i>
                    <span>Offices & Signatories</span>
                </a>
                <a href="<?= url('admin/subjects.php') ?>" class="nav-link <?= $current_page === 'subjects.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-book-bookmark"></i>
                    <span>Subjects & Instructors</span>
                </a>
                <a href="<?= url('admin/users.php') ?>" class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>User Accounts</span>
                </a>
                <a href="<?= url('admin/reports.php') ?>" class="nav-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Master Reports</span>
                </a>
            <?php endif; ?>

            <div class="nav-section-title">Public</div>
            <a href="<?= url('verify.php') ?>" class="nav-link <?= $current_page === 'verify.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Verify Certificate</span>
            </a>
        </nav>

        <?php if ($user): ?>
            <div class="sidebar-footer">
                <div class="user-mini-card">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['name'] ?: $user['username'], 0, 1)) ?>
                    </div>
                    <div style="flex: 1; overflow: hidden;">
                        <div class="user-meta-name"><?= e($user['name'] ?: $user['username']) ?></div>
                        <div class="user-meta-role"><?= e($user['role']) ?></div>
                    </div>
                    <a href="<?= url('auth/logout.php') ?>" title="Logout" style="color: #ef4444; font-size: 16px; padding: 6px;">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Main Page Content Area -->
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button id="mobileSidebarToggle" class="mobile-toggle-btn no-print" type="button" aria-label="Toggle navigation menu">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div class="topbar-brand-mobile">
                    <div class="sidebar-logo-icon-sm">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <span class="brand-text">ClearanceHub</span>
                </div>
                <div class="topbar-title">
                    <h2><?= e($page_title) ?></h2>
                    <?php if (!empty($page_subtitle)): ?>
                        <p class="topbar-subtitle"><?= e($page_subtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="topbar-actions">
                <?php if ($user): ?>
                    <div class="topbar-user-badge">
                        <div class="topbar-avatar"><?= strtoupper(substr($user['name'] ?: $user['username'], 0, 1)) ?></div>
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= e($user['name'] ?: $user['username']) ?></span>
                            <span class="badge badge-primary badge-sm"><i class="fa-solid fa-id-badge"></i> <?= e(ucfirst($user['role'])) ?></span>
                        </div>
                    </div>
                    <a href="<?= url('auth/logout.php') ?>" class="btn btn-outline btn-sm no-print logout-btn" title="Logout">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="logout-label">Logout</span>
                    </a>
                <?php else: ?>
                    <a href="<?= url('auth/login.php') ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-user-lock"></i> Log In
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="content-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-auto-dismiss">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'error' ? 'fa-circle-xmark' : 'fa-circle-info') ?>"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

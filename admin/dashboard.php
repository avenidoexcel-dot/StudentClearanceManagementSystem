<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$user = current_user();

// Global Stats
$totalStudents   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalInstructors= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetchColumn();
$totalSignatories= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'signatory'")->fetchColumn();
$totalOffices    = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE is_active = 1")->fetchColumn();

// Active Period Stats
$activePeriod = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1")->fetch();

$totalRequests = 0;
$clearedCount  = 0;
$inProgressCount = 0;
$recentRequests = [];

if ($activePeriod) {
    $reqStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN overall_status = 'cleared' THEN 1 ELSE 0 END) as cleared,
            SUM(CASE WHEN overall_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
        FROM clearance_requests
        WHERE period_id = ?
    ");
    $reqStats->execute([$activePeriod['id']]);
    $row = $reqStats->fetch();

    $totalRequests   = (int)($row['total'] ?? 0);
    $clearedCount    = (int)($row['cleared'] ?? 0);
    $inProgressCount = (int)($row['in_progress'] ?? 0);

    // Recent Clearance Requests
    $recentStmt = $pdo->prepare("
        SELECT cr.*, u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.section
        FROM clearance_requests cr
        JOIN users u ON cr.student_id = u.id
        WHERE cr.period_id = ?
        ORDER BY cr.created_at DESC
        LIMIT 8
    ");
    $recentStmt->execute([$activePeriod['id']]);
    $recentRequests = $recentStmt->fetchAll();
}

$completionRate = ($totalRequests > 0) ? round(($clearedCount / $totalRequests) * 100) : 0;

$page_title = "Admin Clearance Overview";
$page_subtitle = "Institutional clearance metrics, periods, and department activity";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- High Level Statistics -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
        <div>
            <div class="stat-val"><?= $totalStudents ?></div>
            <div class="stat-label">Registered Students</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stat-val"><?= $clearedCount ?> <span style="font-size: 14px; color: var(--text-muted); font-weight: normal;">/ <?= $totalRequests ?></span></div>
            <div class="stat-label">Students Fully Cleared</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa-solid fa-spinner"></i></div>
        <div>
            <div class="stat-val"><?= $inProgressCount ?></div>
            <div class="stat-label">Clearance In Progress</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div>
            <div class="stat-val"><?= $totalInstructors ?></div>
            <div class="stat-label">Faculty Instructors</div>
        </div>
    </div>
</div>

<!-- Active Period & Quick Actions -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Active Period Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i>
                <span>Active Clearance Term</span>
            </div>
            <?php if ($activePeriod): ?>
                <span class="badge badge-success"><i class="fa-solid fa-circle-dot"></i> Active</span>
            <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
            <?php endif; ?>
        </div>

        <?php if ($activePeriod): ?>
            <div style="margin-bottom: 16px;">
                <h3 style="font-size: 20px; color: #1e1b4b;"><?= e($activePeriod['name']) ?></h3>
                <p style="font-size: 14px; color: var(--text-muted); margin-top: 4px;">
                    Academic Year: <strong><?= e($activePeriod['academic_year']) ?></strong> | Semester: <strong><?= e($activePeriod['semester']) ?></strong>
                </p>
                <p style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                    Timeline: <?= format_datetime($activePeriod['start_date'], 'M d, Y') ?> to <?= format_datetime($activePeriod['end_date'], 'M d, Y') ?>
                </p>
            </div>

            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    <span>Institutional Clearance Progress</span>
                    <span><?= $clearedCount ?> of <?= $totalRequests ?> Students Cleared (<?= $completionRate ?>%)</span>
                </div>
                <div class="progress-container" style="height: 16px;">
                    <div class="progress-bar" style="width: <?= $completionRate ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 24px 0; text-align: center; color: var(--text-muted);">
                <p>No active clearance period configured.</p>
                <a href="<?= BASE_URL ?>admin/periods.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> Set Up Clearance Period
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Shortcuts Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-bolt" style="color: var(--accent);"></i>
                <span>Quick Actions</span>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="<?= BASE_URL ?>admin/periods.php" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-calendar-days" style="width: 20px; color: var(--primary);"></i>
                <span>Manage Clearance Periods</span>
            </a>
            <a href="<?= BASE_URL ?>admin/departments.php" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-building-user" style="width: 20px; color: var(--secondary);"></i>
                <span>Manage Departments & Offices</span>
            </a>
            <a href="<?= BASE_URL ?>admin/subjects.php" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-book-bookmark" style="width: 20px; color: #10b981;"></i>
                <span>Assign Subjects & Instructors</span>
            </a>
            <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-users-gear" style="width: 20px; color: #f59e0b;"></i>
                <span>User Accounts & Roles</span>
            </a>
            <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-file-invoice" style="width: 20px; color: #ec4899;"></i>
                <span>Master Reports & Exports</span>
            </a>
        </div>
    </div>
</div>

<!-- Recent Clearance Requests Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
            <span>Recent Student Clearance Submissions</span>
        </div>
        <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline btn-sm">
            View All in Master Reports <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($recentRequests)): ?>
        <div style="text-align: center; padding: 32px; color: var(--text-muted);">
            <p>No clearance submissions recorded for the current period yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking Code</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Program & Section</th>
                        <th>Submission Date</th>
                        <th>Overall Status</th>
                        <th style="text-align: right;">Certificate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td><code style="font-weight: 700; color: #4338ca;"><?= e($req['clearance_code']) ?></code></td>
                            <td><?= e($req['student_id']) ?></td>
                            <td><strong><?= e($req['last_name'] . ', ' . $req['first_name']) ?></strong></td>
                            <td><?= e($req['course'] . ' ' . $req['year_level'] . ' - ' . $req['section']) ?></td>
                            <td><?= format_datetime($req['created_at']) ?></td>
                            <td><?= render_status_badge($req['overall_status']) ?></td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL ?>student/certificate.php?request_id=<?= $req['id'] ?>" class="btn btn-outline btn-sm" target="_blank">
                                    <i class="fa-solid fa-certificate"></i> Inspect
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

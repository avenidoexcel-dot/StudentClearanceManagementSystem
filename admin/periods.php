<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

// Handle Set Active
if (isset($_GET['set_active'])) {
    $periodId = (int)$_GET['set_active'];
    $pdo->query("UPDATE clearance_periods SET is_active = 0");
    $updateStmt = $pdo->prepare("UPDATE clearance_periods SET is_active = 1 WHERE id = ?");
    $updateStmt->execute([$periodId]);
    set_flash('success', 'Clearance period activated successfully.');
    redirect('admin/periods');
}

// Handle Add Period
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/periods');
    $name       = trim($_POST['name'] ?? '');
    $acad_year  = trim($_POST['academic_year'] ?? '');
    $semester   = trim($_POST['semester'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $set_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || empty($acad_year) || empty($semester) || empty($start_date) || empty($end_date)) {
        $error = 'Please fill out all required period fields.';
    } else {
        if ($set_active) {
            $pdo->query("UPDATE clearance_periods SET is_active = 0");
        }

        $stmt = $pdo->prepare("
            INSERT INTO clearance_periods (name, academic_year, semester, start_date, end_date, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $acad_year, $semester, $start_date, $end_date, $set_active]);
        set_flash('success', "Clearance period '{$name}' created successfully.");
        redirect('admin/periods');
    }
}

$periods = $pdo->query("
    SELECT cp.*, COUNT(cr.id) as request_count 
    FROM clearance_periods cp
    LEFT JOIN clearance_requests cr ON cp.id = cr.period_id
    GROUP BY cp.id
    ORDER BY cp.id DESC
")->fetchAll();

$page_title = "Manage Clearance Periods";
$page_subtitle = "Create and schedule academic clearance terms for your university";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="grid-2-1">
    <!-- Periods Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i>
                <span>Academic Clearance Terms</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Term Name</th>
                        <th>Academic Year & Sem</th>
                        <th>Duration</th>
                        <th>Requests</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><?= e($p['academic_year']) ?> (<?= e($p['semester']) ?>)</td>
                            <td style="font-size: 13px;">
                                <?= format_datetime($p['start_date'], 'M d, Y') ?> - <?= format_datetime($p['end_date'], 'M d, Y') ?>
                            </td>
                            <td><span class="badge badge-primary"><?= (int)$p['request_count'] ?></span></td>
                            <td>
                                <?php if ($p['is_active']): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if (!$p['is_active']): ?>
                                    <a href="<?= url('admin/periods?set_active=' . $p['id']) ?>" class="btn btn-outline btn-sm" onclick="return confirm('Activate this clearance period?');">
                                        <i class="fa-solid fa-toggle-on"></i> Activate
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #10b981; font-weight: 600;">Current Term</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Period Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-plus-circle" style="color: var(--success);"></i>
                <span>Create New Term</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= url('admin/periods') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="name">Term Title *</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. 2nd Semester Clearance 2026-2027">
            </div>

            <div class="form-group">
                <label class="form-label" for="academic_year">Academic Year *</label>
                <input type="text" name="academic_year" id="academic_year" class="form-control" required placeholder="e.g. 2026-2027">
            </div>

            <div class="form-group">
                <label class="form-label" for="semester">Semester *</label>
                <select name="semester" id="semester" class="form-control" required>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer / Mid-Year">Summer / Mid-Year</option>
                    <option value="Graduation Clearance">Graduation Clearance</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="start_date">Start Date *</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="end_date">End Date *</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Set as Active Clearance Period</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-floppy-disk"></i> Save Period
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

// Fetch periods for filter
$periods = $pdo->query("SELECT * FROM clearance_periods ORDER BY id DESC")->fetchAll();
$selectedPeriod = (int)($_GET['period_id'] ?? 0);
if (!$selectedPeriod) {
    foreach ($periods as $p) {
        if ($p['is_active']) { $selectedPeriod = $p['id']; break; }
    }
}

$statusFilter = $_GET['status'] ?? '';
$courseFilter = $_GET['course'] ?? '';
$search       = trim($_GET['search'] ?? '');

$sql = "
    SELECT cr.*, cp.name as period_name,
           u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.section, u.email,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id) +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id) as total_reqs,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id AND status = 'cleared') +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id AND status = 'cleared') as cleared_reqs,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id AND status = 'deficient') +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id AND status = 'deficient') as deficient_reqs
    FROM clearance_requests cr
    JOIN clearance_periods cp ON cr.period_id = cp.id
    JOIN users u ON cr.student_id = u.id
    WHERE 1=1
";
$params = [];

if ($selectedPeriod) {
    $sql .= " AND cr.period_id = ?";
    $params[] = $selectedPeriod;
}
if (!empty($statusFilter)) {
    $sql .= " AND cr.overall_status = ?";
    $params[] = $statusFilter;
}
if (!empty($courseFilter)) {
    $sql .= " AND u.course = ?";
    $params[] = $courseFilter;
}
if (!empty($search)) {
    $sql .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR cr.clearance_code LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY cr.overall_status ASC, u.last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Summary counters
$total = count($records);
$cleared = 0;
$in_prog = 0;
foreach ($records as $r) {
    if ($r['overall_status'] === 'cleared') $cleared++;
    else $in_prog++;
}

$page_title = "Master Clearance Reports & Tracking";
$page_subtitle = "Comprehensive institutional clearance roster, audit trail, and verification";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Filter Bar -->
<div class="card no-print" style="padding: 18px 24px;">
    <form action="" method="GET" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label" style="font-size: 12px;">Clearance Period</label>
            <select name="period_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $selectedPeriod ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> <?= $p['is_active'] ? '(Active)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="width: 160px;">
            <label class="form-label" style="font-size: 12px;">Status</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="cleared" <?= $statusFilter === 'cleared' ? 'selected' : '' ?>>Cleared Only</option>
                <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress Only</option>
            </select>
        </div>

        <div style="width: 150px;">
            <label class="form-label" style="font-size: 12px;">Program / Course</label>
            <select name="course" class="form-control" onchange="this.form.submit()">
                <option value="">All Programs</option>
                <option value="BSIT" <?= $courseFilter === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                <option value="BSIS" <?= $courseFilter === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                <option value="BSCS" <?= $courseFilter === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label class="form-label" style="font-size: 12px;">Search Student</label>
            <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="ID, name, or code...">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="reports.php" class="btn btn-outline">Reset</a>
            <button type="button" onclick="window.print()" class="btn btn-outline">
                <i class="fa-solid fa-print"></i> Print Roster
            </button>
        </div>
    </form>
</div>

<!-- Report Summary Cards -->
<div class="stat-grid no-print">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-list-check"></i></div>
        <div>
            <div class="stat-val"><?= $total ?></div>
            <div class="stat-label">Filtered Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-stamp"></i></div>
        <div>
            <div class="stat-val"><?= $cleared ?></div>
            <div class="stat-label">Fully Cleared Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <div class="stat-val"><?= $in_prog ?></div>
            <div class="stat-label">Pending / In Progress</div>
        </div>
    </div>
</div>

<!-- Master Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-file-invoice" style="color: var(--primary);"></i>
            <span>Master Clearance Evaluation Roster</span>
        </div>
        <span style="font-size: 13px; color: var(--text-muted);"><?= $total ?> record(s) found</span>
    </div>

    <?php if (empty($records)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p>No student clearance records matched your filter criteria.</p>
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
                        <th>Cleared Requirements</th>
                        <th>Overall Status</th>
                        <th>Date Completed</th>
                        <th class="no-print" style="text-align: right;">Certificate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><code style="font-weight: 700; color: #4338ca;"><?= e($r['clearance_code']) ?></code></td>
                            <td><strong><?= e($r['student_id']) ?></strong></td>
                            <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
                            <td><?= e($r['course'] . ' ' . $r['year_level'] . ' - ' . $r['section']) ?></td>
                            <td>
                                <div>
                                    <span style="font-weight: 700; color: #10b981;"><?= (int)$r['cleared_reqs'] ?></span>
                                    <span style="color: var(--text-muted);">/ <?= (int)$r['total_reqs'] ?></span>
                                    <?php if ($r['deficient_reqs'] > 0): ?>
                                        <span class="badge badge-danger" style="font-size: 10px; margin-left: 6px;">
                                            <?= $r['deficient_reqs'] ?> Deficient
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= render_status_badge($r['overall_status']) ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= format_datetime($r['completed_at']) ?>
                            </td>
                            <td class="no-print" style="text-align: right;">
                                <a href="<?= BASE_URL ?>student/certificate.php?request_id=<?= $r['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                                    <i class="fa-solid fa-file-lines"></i> Certificate
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

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('signatory');
$user = current_user();

$deptId = (int)($user['department_id'] ?? 0);

// Fetch department details
$deptStmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$deptStmt->execute([$deptId]);
$department = $deptStmt->fetch();

if (!$department) {
    die("Signatory account is not linked to any active department.");
}

// Fetch active clearance period
$periodStmt = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1");
$activePeriod = $periodStmt->fetch();

$studentsList = [];
$stats = ['total' => 0, 'cleared' => 0, 'pending' => 0, 'deficient' => 0];

if ($activePeriod) {
    $listStmt = $pdo->prepare("
        SELECT cs.id as status_id, cs.status, cs.remarks, cs.proof_file, cs.updated_at,
               cr.id as request_id, cr.clearance_code, cr.created_at as request_date,
               u.id as student_user_id, u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.section, u.email
        FROM clearance_signatory_status cs
        JOIN clearance_requests cr ON cs.request_id = cr.id
        JOIN users u ON cr.student_id = u.id
        WHERE cs.department_id = ? AND cr.period_id = ?
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    $listStmt->execute([$deptId, $activePeriod['id']]);
    $studentsList = $listStmt->fetchAll();

    foreach ($studentsList as $st) {
        $stats['total']++;
        if ($st['status'] === 'cleared') {
            $stats['cleared']++;
        } elseif ($st['status'] === 'deficient') {
            $stats['deficient']++;
        } else {
            $stats['pending']++;
        }
    }
}

$page_title = e($department['name']) . " Signatory Portal";
$page_subtitle = "Review and sign off department clearance requirements for students";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<?php if (!$activePeriod): ?>
    <div class="card" style="text-align: center; padding: 48px 24px;">
        <i class="fa-solid fa-calendar-xmark" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
        <h3>No Active Clearance Period</h3>
        <p style="color: var(--text-muted); max-width: 500px; margin: 8px auto 0;">No active clearance term is currently open. Check with system administrator.</p>
    </div>
<?php else: ?>

    <!-- Department Summary Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-val"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <div class="stat-val"><?= $stats['cleared'] ?></div>
                <div class="stat-label">Students Cleared</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-clock"></i></div>
            <div>
                <div class="stat-val"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-circle-exclamation"></i></div>
            <div>
                <div class="stat-val"><?= $stats['deficient'] ?></div>
                <div class="stat-label">Flagged Deficiencies</div>
            </div>
        </div>
    </div>

    <!-- Student Clearance Table for this Office -->
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 14px;">
            <div>
                <div class="card-title">
                    <i class="fa-solid fa-building-circle-check" style="color: var(--primary);"></i>
                    <span><?= e($department['name']) ?> (<?= e($department['code']) ?>) Queue</span>
                </div>
                <span style="font-size: 13px; color: var(--text-muted);"><?= e($department['description']) ?></span>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <div style="position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 12px;"></i>
                    <input type="text" data-table-search="#signatoryTable" class="form-control btn-sm" style="padding-left: 28px; width: 220px;" placeholder="Search student...">
                </div>

                <form action="update_status.php" method="POST" onsubmit="return confirm('Clear ALL pending students for <?= e($department['name']) ?>?');">
                    <input type="hidden" name="action" value="batch_approve">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-check-double"></i> Clear All Pending
                    </button>
                </form>
            </div>
        </div>

        <?php if (empty($studentsList)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fa-regular fa-folder-open" style="font-size: 36px; margin-bottom: 10px; color: #cbd5e1;"></i>
                <p>No student clearance requests received for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="signatoryTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Program & Section</th>
                            <th>Proof / Receipt</th>
                            <th>Clearance Status</th>
                            <th>Deficiency Remarks</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentsList as $st): ?>
                            <tr>
                                <td><strong style="color: #4338ca;"><?= e($st['student_id']) ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($st['last_name'] . ', ' . $st['first_name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);">Code: <?= e($st['clearance_code']) ?></div>
                                </td>
                                <td><?= e($st['course'] . ' ' . $st['year_level'] . ' - ' . $st['section']) ?></td>
                                <td>
                                    <?php if (!empty($st['proof_file'])): ?>
                                        <a href="<?= BASE_URL ?>uploads/proofs/<?= e($st['proof_file']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 11px;">
                                            <i class="fa-solid fa-paperclip"></i> View Proof
                                        </a>
                                    <?php elseif ($department['requires_proof']): ?>
                                        <span style="font-size: 11px; color: #ea580c; font-weight: 600;">
                                            <i class="fa-solid fa-clock"></i> No Proof Yet
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #cbd5e1;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= render_status_badge($st['status']) ?></td>
                                <td>
                                    <?php if ($st['status'] === 'deficient'): ?>
                                        <span style="color: var(--danger); font-size: 13px; font-weight: 500;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <?= e($st['remarks']) ?>
                                        </span>
                                    <?php elseif (!empty($st['remarks'])): ?>
                                        <span style="font-size: 13px; color: var(--text-muted);"><?= e($st['remarks']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <!-- Approve Action -->
                                        <form action="update_status.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="single_update">
                                            <input type="hidden" name="status_id" value="<?= $st['status_id'] ?>">
                                            <input type="hidden" name="status" value="cleared">
                                            <button type="submit" class="btn btn-success btn-sm" title="Mark as Cleared" <?= $st['status'] === 'cleared' ? 'disabled' : '' ?>>
                                                <i class="fa-solid fa-check"></i> Clear
                                            </button>
                                        </form>

                                        <!-- Mark Deficient Modal Trigger -->
                                        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#signatoryDefModal"
                                                data-status-id="<?= $st['status_id'] ?>"
                                                data-student-name="<?= e($st['first_name'] . ' ' . $st['last_name']) ?>"
                                                data-current-remarks="<?= e($st['remarks']) ?>"
                                                title="Flag deficiency">
                                            <i class="fa-solid fa-flag"></i> Deficient
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<!-- Signatory Deficiency Modal -->
<div class="modal-overlay" id="signatoryDefModal">
    <div class="modal-container">
        <form action="update_status.php" method="POST">
            <input type="hidden" name="action" value="single_update">
            <input type="hidden" name="status" value="deficient">
            <input type="hidden" name="status_id" id="sigModalStatusId">

            <div class="modal-header">
                <h3 style="font-size: 16px; font-weight: 700;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i>
                    Flag Deficiency for <span data-field="studentName" id="sigModalStudentName" style="color: var(--primary);"></span>
                </h3>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sigModalRemarks">Reason / Requirement Needed for Compliance *</label>
                    <textarea name="remarks" id="sigModalRemarks" class="form-control" rows="4" required placeholder="Specify what the student must comply with (e.g. Settle tuition balance of PHP 1,200 at Cashier, Return book 'Clean Code' to Library, Submit PSA Birth Certificate)..."></textarea>
                    <small style="font-size: 12px; color: var(--text-muted); margin-top: 4px; display: block;">
                        The student will immediately see this notice and can take action / upload proof.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-flag"></i> Save Deficiency
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const defButtons = document.querySelectorAll('[data-modal-target="#signatoryDefModal"]');
    defButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('sigModalStatusId').value = btn.dataset.statusId;
            document.getElementById('sigModalStudentName').innerText = btn.dataset.studentName;
            document.getElementById('sigModalRemarks').value = btn.dataset.currentRemarks || '';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

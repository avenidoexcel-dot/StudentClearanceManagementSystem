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
        SELECT cs.id as status_id, cs.status, cs.remarks, cs.proof_file, cs.student_note, cs.submitted_at, cs.updated_at,
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

                <form action="<?= url('signatory/update_status.php') ?>" method="POST" onsubmit="return confirm('Clear ALL pending students for <?= e($department['name']) ?>?');">
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
                            <th>Passed Compliance / Proof</th>
                            <th>Clearance Status</th>
                            <th>Deficiencies (What is Lacking)</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentsList as $st): ?>
                            <tr>
                                <td>
                                    <strong style="color: #4338ca;"><?= e($st['student_id']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">Code: <?= e($st['clearance_code']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($st['last_name'] . ', ' . $st['first_name']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?= e($st['email']) ?></div>
                                </td>
                                <td><?= e($st['course'] . ' ' . $st['year_level'] . ' - ' . $st['section']) ?></td>
                                <td>
                                    <?php if (!empty($st['proof_file']) || !empty($st['student_note'])): ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php if (!empty($st['proof_file'])): ?>
                                                <a href="<?= url('uploads/proofs/' . $st['proof_file']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; width: fit-content;">
                                                    <i class="fa-solid fa-paperclip"></i> View Proof
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($st['student_note'])): ?>
                                                <div style="font-size: 11px; color: #334155; background: rgba(241, 245, 249, 0.85); border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 4px; max-width: 220px;" title="<?= e($st['student_note']) ?>">
                                                    <strong>Note:</strong> <?= e(mb_strimwidth($st['student_note'], 0, 45, '...')) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($st['submitted_at'])): ?>
                                                <span style="font-size: 10px; color: var(--text-muted);">
                                                    <i class="fa-regular fa-clock"></i> <?= format_datetime($st['submitted_at'], 'M d, Y h:i A') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($department['requires_proof']): ?>
                                        <span style="font-size: 11px; color: #ea580c; font-weight: 600; background: #fff7ed; padding: 3px 8px; border-radius: 4px; border: 1px solid #fed7aa; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-clock"></i> Proof Required (None Yet)
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #94a3b8; font-style: italic;">No submission passed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= render_status_badge($st['status']) ?></td>
                                <td>
                                    <?php if ($st['status'] === 'deficient'): ?>
                                        <div style="color: var(--danger); font-size: 12px; font-weight: 600; background: rgba(254, 242, 242, 0.85); border: 1px solid rgba(254, 202, 202, 0.85); border-radius: 4px; padding: 6px 10px; max-width: 240px;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <strong>Lacking:</strong> <?= e($st['remarks'] ?: 'Action or proof required') ?>
                                        </div>
                                    <?php elseif (!empty($st['remarks'])): ?>
                                        <span style="font-size: 12px; color: var(--text-muted);"><?= e($st['remarks']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <!-- Review Compliance Modal Button -->
                                        <button type="button" class="btn btn-outline btn-sm sig-review-btn"
                                                data-modal-target="#sigReviewModal"
                                                data-status-id="<?= $st['status_id'] ?>"
                                                data-student-id="<?= e($st['student_id']) ?>"
                                                data-student-name="<?= e($st['first_name'] . ' ' . $st['last_name']) ?>"
                                                data-student-program="<?= e($st['course'] . ' ' . $st['year_level'] . ' - ' . $st['section']) ?>"
                                                data-clearance-code="<?= e($st['clearance_code']) ?>"
                                                data-proof-file="<?= e($st['proof_file'] ?? '') ?>"
                                                data-proof-url="<?= !empty($st['proof_file']) ? url('uploads/proofs/' . $st['proof_file']) : '' ?>"
                                                data-student-note="<?= e($st['student_note'] ?? '') ?>"
                                                data-submitted-at="<?= !empty($st['submitted_at']) ? format_datetime($st['submitted_at'], 'M d, Y h:i A') : '' ?>"
                                                data-status="<?= e($st['status']) ?>"
                                                data-remarks="<?= e($st['remarks'] ?? '') ?>"
                                                title="Inspect compliance and check what is passed or lacking">
                                            <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i> Review
                                        </button>

                                        <!-- Quick Approve Action -->
                                        <form action="<?= url('signatory/update_status.php') ?>" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="single_update">
                                            <input type="hidden" name="status_id" value="<?= $st['status_id'] ?>">
                                            <input type="hidden" name="status" value="cleared">
                                            <button type="submit" class="btn btn-success btn-sm" title="Mark as Cleared" <?= $st['status'] === 'cleared' ? 'disabled' : '' ?>>
                                                <i class="fa-solid fa-check"></i> Clear
                                            </button>
                                        </form>

                                        <!-- Quick Mark Deficient Modal Trigger -->
                                        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#signatoryDefModal"
                                                data-status-id="<?= $st['status_id'] ?>"
                                                data-student-name="<?= e($st['first_name'] . ' ' . $st['last_name']) ?>"
                                                data-current-remarks="<?= e($st['remarks']) ?>"
                                                title="Flag deficiency">
                                            <i class="fa-solid fa-flag"></i>
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

<!-- Comprehensive Signatory Review Compliance Modal -->
<div class="modal-overlay" id="sigReviewModal">
    <div class="modal-container" style="max-width: 650px;">
        <form action="<?= url('signatory/update_status.php') ?>" method="POST">
            <input type="hidden" name="action" value="single_update">
            <input type="hidden" name="status_id" id="sigRevStatusId">

            <div class="modal-header">
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i>
                        <span>Inspect Compliance: <span id="sigRevStudentName" style="color: var(--primary);"></span></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        ID: <strong id="sigRevStudentId"></strong> &bull; Program: <span id="sigRevStudentProgram"></span> &bull; Code: <span id="sigRevClearanceCode"></span>
                    </div>
                </div>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <!-- 1. Department Guidelines -->
                <div style="background: rgba(238, 242, 255, 0.75); border: 1px solid rgba(199, 210, 254, 0.8); border-radius: var(--radius-md); padding: 14px;">
                    <div style="font-size: 12px; font-weight: 700; color: #3730a3; text-transform: uppercase; margin-bottom: 6px;">
                        <i class="fa-solid fa-building"></i> Office Requirements: <?= e($department['name']) ?> (<?= e($department['code']) ?>)
                    </div>
                    <div style="font-size: 13px; line-height: 1.5; color: #1e293b;">
                        <?= !empty($department['description']) ? e($department['description']) : 'Verify that all obligations and official clearances with this office are fully settled.' ?>
                    </div>
                    <?php if (!empty($department['requires_proof'])): ?>
                        <div style="margin-top: 8px; font-size: 12px; color: #c2410c; font-weight: 600;">
                            <i class="fa-solid fa-asterisk"></i> Official Document / Receipt Attachment is Mandatory for this Office.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. What Student Passed / Submitted -->
                <div style="background: rgba(248, 250, 252, 0.9); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: var(--radius-md); padding: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase;">
                            <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i> Student's Compliance Submission:
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);" id="sigRevSubmittedAt"></div>
                    </div>

                    <!-- File link box -->
                    <div id="sigRevFileSection" style="margin-bottom: 10px;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Attached Proof / Document:</div>
                        <div id="sigRevFileContent"></div>
                    </div>

                    <!-- Note box -->
                    <div id="sigRevNoteSection">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Student Compliance Note / Details:</div>
                        <div id="sigRevNoteContent" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 13px; line-height: 1.5; color: #1e293b; white-space: pre-line;"></div>
                    </div>
                </div>

                <!-- 3. Signatory Evaluation Decision -->
                <div>
                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Evaluation Decision *</label>
                    <div style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="cleared" id="sigRevStatusCleared">
                            <span style="font-weight: 600; color: var(--success);"><i class="fa-solid fa-circle-check"></i> Cleared / Approved</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="deficient" id="sigRevStatusDeficient">
                            <span style="font-weight: 600; color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Flag Deficient (Specify Lacking)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="pending" id="sigRevStatusPending">
                            <span style="font-weight: 600; color: #ea580c;"><i class="fa-solid fa-clock"></i> Keep Pending</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sigRevRemarks">
                            Reason / What is Lacking (Instructions for Student)
                            <small style="color: var(--text-muted); font-weight: normal;">(Required if marking Deficient)</small>
                        </label>
                        <textarea name="remarks" id="sigRevRemarks" class="form-control" rows="3" placeholder="If deficient, clearly explain what is lacking (e.g. 'Pay remaining balance of PHP 500 at Cashier Window 2', 'Return borrowed book'). If cleared, optional notes."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Evaluation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Signatory Deficiency Modal -->
<div class="modal-overlay" id="signatoryDefModal">
    <div class="modal-container">
        <form action="<?= url('signatory/update_status.php') ?>" method="POST">
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
                    <label class="form-label" for="sigModalRemarks">Reason / What is Lacking for Compliance *</label>
                    <textarea name="remarks" id="sigModalRemarks" class="form-control" rows="4" required placeholder="Specify what the student must comply with (e.g. Settle tuition balance of PHP 1,200 at Cashier, Return book 'Clean Code' to Library, Submit PSA Birth Certificate)..."></textarea>
                    <small style="font-size: 12px; color: var(--text-muted); margin-top: 4px; display: block;">
                        The student will immediately see this notice on their dashboard so they can take action / comply.
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
    // Review Compliance Modal
    const sigRevButtons = document.querySelectorAll('.sig-review-btn');
    sigRevButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('sigRevStatusId').value = btn.dataset.statusId || '';
            document.getElementById('sigRevStudentName').innerText = btn.dataset.studentName || '';
            document.getElementById('sigRevStudentId').innerText = btn.dataset.studentId || '';
            document.getElementById('sigRevStudentProgram').innerText = btn.dataset.studentProgram || '';
            document.getElementById('sigRevClearanceCode').innerText = btn.dataset.clearanceCode || '';
            document.getElementById('sigRevSubmittedAt').innerText = btn.dataset.submittedAt ? ('Submitted: ' + btn.dataset.submittedAt) : 'No submission recorded';
            document.getElementById('sigRevRemarks').value = btn.dataset.remarks || '';

            // Status radio
            const currentStatus = btn.dataset.status || 'pending';
            if (currentStatus === 'cleared') {
                document.getElementById('sigRevStatusCleared').checked = true;
            } else if (currentStatus === 'deficient') {
                document.getElementById('sigRevStatusDeficient').checked = true;
            } else {
                document.getElementById('sigRevStatusPending').checked = true;
            }

            // File section
            const fileUrl = btn.dataset.proofUrl;
            const fileName = btn.dataset.proofFile;
            const fileContent = document.getElementById('sigRevFileContent');
            if (fileUrl && fileName) {
                fileContent.innerHTML = `<a href="${fileUrl}" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-paperclip"></i> Open & View Proof: <strong>${fileName}</strong></a>`;
            } else {
                fileContent.innerHTML = '<span style="font-size: 12px; color: #94a3b8; font-style: italic;">No proof file attached.</span>';
            }

            // Note section
            const noteText = btn.dataset.studentNote;
            const noteContent = document.getElementById('sigRevNoteContent');
            if (noteText && noteText.trim() !== '') {
                noteContent.innerText = noteText;
            } else {
                noteContent.innerHTML = '<span style="color: #94a3b8; font-style: italic;">No compliance note entered by student.</span>';
            }
        });
    });

    // Fill specific modal fields when clicking quick deficient button
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

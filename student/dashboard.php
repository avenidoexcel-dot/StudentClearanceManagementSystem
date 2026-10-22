<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('student');
$user = current_user();

// 1. Fetch current active clearance period
$periodStmt = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1");
$activePeriod = $periodStmt->fetch();

// Ensure requirements column exists
ensure_clearance_requirements_column($pdo);

// 2. Fetch student's clearance request for active period
$clearanceRequest = null;
$deptStatusList   = [];
$subjStatusList   = [];
$stats            = ['total' => 0, 'cleared' => 0, 'deficient' => 0, 'pending' => 0, 'percentage' => 0];

if ($activePeriod) {
    $reqStmt = $pdo->prepare("
        SELECT * FROM clearance_requests 
        WHERE student_id = ? AND period_id = ? 
        LIMIT 1
    ");
    $reqStmt->execute([$user['id'], $activePeriod['id']]);
    $clearanceRequest = $reqStmt->fetch();

    if ($clearanceRequest) {
        $reqId = $clearanceRequest['id'];

        // Automatically re-evaluate overall clearance status
        check_and_update_overall_clearance($pdo, $reqId);

        // Fetch refreshed request
        $reqStmt->execute([$user['id'], $activePeriod['id']]);
        $clearanceRequest = $reqStmt->fetch();

        // Fetch Department Statuses
        $deptStmt = $pdo->prepare("
            SELECT cs.*, d.name AS department_name, d.code AS department_code, d.requires_proof,
                   CONCAT(u.first_name, ' ', u.last_name) AS signatory_name
            FROM clearance_signatory_status cs
            JOIN departments d ON cs.department_id = d.id
            LEFT JOIN users u ON cs.signatory_id = u.id
            WHERE cs.request_id = ?
            ORDER BY d.id ASC
        ");
        $deptStmt->execute([$reqId]);
        $deptStatusList = $deptStmt->fetchAll();

        // Fetch Subject Statuses
        $subjStmt = $pdo->prepare("
            SELECT css.*, s.code AS subject_code, s.name AS subject_name, s.clearance_requirements,
                   CONCAT(u.first_name, ' ', u.last_name) AS instructor_name,
                   u.email AS instructor_email
            FROM clearance_subject_status css
            JOIN subjects s ON css.subject_id = s.id
            JOIN users u ON css.instructor_id = u.id
            WHERE css.request_id = ?
            ORDER BY s.code ASC
        ");
        $subjStmt->execute([$reqId]);
        $subjStatusList = $subjStmt->fetchAll();

        // Compute statistics
        foreach (array_merge($deptStatusList, $subjStatusList) as $item) {
            $stats['total']++;
            if ($item['status'] === 'cleared') {
                $stats['cleared']++;
            } elseif ($item['status'] === 'deficient') {
                $stats['deficient']++;
            } else {
                $stats['pending']++;
            }
        }

        if ($stats['total'] > 0) {
            $stats['percentage'] = round(($stats['cleared'] / $stats['total']) * 100);
        }
    }
}

$page_title = "Student Clearance Dashboard";
$page_subtitle = "Track your clearance progress across all departments and subject instructors";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<?php if (!$activePeriod): ?>
    <div class="card" style="text-align: center; padding: 48px 24px;">
        <i class="fa-solid fa-calendar-xmark" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
        <h3>No Active Clearance Period</h3>
        <p style="color: var(--text-muted); max-width: 500px; margin: 8px auto 0;">The school administration has not opened an active clearance period yet. Please check back later or contact the Registrar.</p>
    </div>

<?php elseif (!$clearanceRequest): ?>
    <!-- Notice to Initiate Clearance Request -->
    <div class="card" style="padding: 36px; border: 2px dashed #818cf8; background: #eef2ff;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div>
                <span class="badge badge-primary" style="margin-bottom: 10px;">
                    <i class="fa-solid fa-bullhorn"></i> Clearance Period is Now Open
                </span>
                <h3 style="color: #312e81; font-size: 22px;"><?= e($activePeriod['name']) ?></h3>
                <p style="color: #4338ca; margin-top: 6px; font-size: 14px;">
                    Academic Year: <strong><?= e($activePeriod['academic_year']) ?></strong> | Semester: <strong><?= e($activePeriod['semester']) ?></strong>
                </p>
                <p style="color: #6366f1; font-size: 13px; margin-top: 4px;">
                    Period: <?= format_datetime($activePeriod['start_date'], 'M d, Y') ?> to <?= format_datetime($activePeriod['end_date'], 'M d, Y') ?>
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>student/request.php" class="btn btn-primary" style="padding: 14px 24px; font-size: 15px; box-shadow: 0 4px 15px rgba(67, 56, 202, 0.4);">
                    <i class="fa-solid fa-paper-plane"></i> Submit Clearance Request Now
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Student Overview & Progress Bar -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fa-solid fa-bars-progress"></i>
            </div>
            <div>
                <div class="stat-val"><?= $stats['percentage'] ?>%</div>
                <div class="stat-label">Overall Completion</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="stat-val"><?= $stats['cleared'] ?> / <?= $stats['total'] ?></div>
                <div class="stat-label">Requirements Cleared</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <div>
                <div class="stat-val"><?= $stats['deficient'] ?></div>
                <div class="stat-label">Pending Deficiencies</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div>
                <div class="stat-val"><?= $stats['pending'] ?></div>
                <div class="stat-label">Awaiting Sign-off</div>
            </div>
        </div>
    </div>

    <!-- Overall Status Banner -->
    <div class="card" style="border-left: 6px solid <?= $clearanceRequest['overall_status'] === 'cleared' ? 'var(--success)' : 'var(--primary)' ?>;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                    <h3 style="font-size: 18px; margin: 0;"><?= e($activePeriod['name']) ?></h3>
                    <?= render_status_badge($clearanceRequest['overall_status']) ?>
                </div>
                <p style="font-size: 13px; color: var(--text-muted);">
                    Clearance Tracking Code: <code style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-weight: 700; color: #4338ca;"><?= e($clearanceRequest['clearance_code']) ?></code>
                    | Submitted on <?= format_datetime($clearanceRequest['created_at']) ?>
                </p>
            </div>
            <div>
                <?php if ($clearanceRequest['overall_status'] === 'cleared'): ?>
                    <a href="<?= BASE_URL ?>student/certificate.php" class="btn btn-success" target="_blank" style="box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                        <i class="fa-solid fa-award"></i> View Official Clearance Certificate
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline btn-sm" disabled title="Will unlock once 100% cleared">
                        <i class="fa-solid fa-lock"></i> Certificate Locked (Pending Clearance)
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Visual Progress Bar -->
        <div style="margin-top: 18px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">
                <span>Clearance Progress</span>
                <span><?= $stats['cleared'] ?> of <?= $stats['total'] ?> Completed (<?= $stats['percentage'] ?>%)</span>
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: <?= $stats['percentage'] ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- 1. ACADEMIC / SUBJECT INSTRUCTORS CLEARANCE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-chalkboard-user" style="color: var(--primary);"></i>
                <span>Subject Instructors Clearance (<?= count($subjStatusList) ?> Subjects)</span>
            </div>
            <span style="font-size: 13px; color: var(--text-muted);">Each enrolled subject instructor reviews your project submissions and class standing</span>
        </div>

        <?php if (empty($subjStatusList)): ?>
            <div style="text-align: center; padding: 24px; color: var(--text-muted);">
                <i class="fa-solid fa-circle-info" style="font-size: 24px; margin-bottom: 8px;"></i>
                <p>No enrolled subjects linked for this clearance period. Contact your Program Chair / Dean.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Assigned Instructor</th>
                            <th>Status</th>
                            <th>Clearance Requirements</th>
                            <th>Remarks / Deficiencies</th>
                            <th>Date Signed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjStatusList as $subj): ?>
                            <tr>
                                <td><strong style="color: var(--primary);"><?= e($subj['subject_code']) ?></strong></td>
                                <td><?= e($subj['subject_name']) ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-regular fa-user" style="color: #94a3b8; font-size: 12px;"></i>
                                        <span><?= e($subj['instructor_name']) ?></span>
                                    </div>
                                    <?php if (!empty($subj['instructor_email'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-left: 20px;"><?= e($subj['instructor_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= render_status_badge($subj['status']) ?></td>
                                <td>
                                    <?php if (!empty($subj['clearance_requirements'])): ?>
                                        <button type="button" class="btn btn-outline btn-sm view-req-btn"
                                                data-modal-target="#viewSubjectReqModal"
                                                data-subject-code="<?= e($subj['subject_code']) ?>"
                                                data-subject-name="<?= e($subj['subject_name']) ?>"
                                                data-instructor-name="<?= e($subj['instructor_name']) ?>"
                                                data-requirements="<?= e($subj['clearance_requirements']) ?>">
                                            <i class="fa-solid fa-clipboard-list" style="color: var(--primary);"></i> View Requirements
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #94a3b8;">
                                            <i class="fa-regular fa-circle-check"></i> Standard Standing
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($subj['status'] === 'deficient'): ?>
                                        <div style="color: var(--danger); font-size: 13px; font-weight: 500;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <?= e($subj['remarks'] ?: 'Action required by instructor') ?>
                                        </div>
                                    <?php elseif (!empty($subj['remarks'])): ?>
                                        <span style="font-size: 13px; color: var(--text-muted);"><?= e($subj['remarks']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= format_datetime($subj['updated_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. ADMINISTRATIVE OFFICES SIGNATORIES -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-building-columns" style="color: var(--secondary);"></i>
                <span>Administrative Offices Signatories (<?= count($deptStatusList) ?> Offices)</span>
            </div>
            <span style="font-size: 13px; color: var(--text-muted);">Library, Cashier, Registrar, Guidance, Dept Head, and Student Affairs</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Office / Department</th>
                        <th>Signatory Officer</th>
                        <th>Status</th>
                        <th>Proof / Attachment</th>
                        <th>Remarks / Deficiencies</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptStatusList as $dept): ?>
                        <tr>
                            <td>
                                <strong><?= e($dept['department_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($dept['department_code']) ?></div>
                            </td>
                            <td>
                                <?= e($dept['signatory_name'] ?: 'Designated Signatory') ?>
                            </td>
                            <td><?= render_status_badge($dept['status']) ?></td>
                            <td>
                                <?php if (!empty($dept['proof_file'])): ?>
                                    <a href="<?= BASE_URL ?>uploads/proofs/<?= e($dept['proof_file']) ?>" target="_blank" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-paperclip"></i> View Proof
                                    </a>
                                <?php elseif ($dept['requires_proof']): ?>
                                    <span style="font-size: 12px; color: #ea580c; font-weight: 500;">
                                        <i class="fa-solid fa-asterisk"></i> Proof Required
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #94a3b8;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dept['status'] === 'deficient'): ?>
                                    <div style="color: var(--danger); font-size: 13px; font-weight: 500;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> <?= e($dept['remarks'] ?: 'Compliance required') ?>
                                    </div>
                                <?php elseif (!empty($dept['remarks'])): ?>
                                    <span style="font-size: 13px; color: var(--text-muted);"><?= e($dept['remarks']) ?></span>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>student/upload_proof.php?status_id=<?= $dept['id'] ?>" class="btn btn-outline btn-sm" title="Upload receipt or compliance document">
                                    <i class="fa-solid fa-upload"></i> Upload
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- Modal for Viewing Subject Clearance Requirements -->
<div class="modal-overlay" id="viewSubjectReqModal">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <div>
                <h3 style="font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i>
                    <span>Clearance Requirements: <strong id="modalSubjectCode" style="color: var(--primary);"></strong></span>
                </h3>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    <span id="modalSubjectName"></span> &bull; Instructor: <strong id="modalInstructorName"></strong>
                </div>
            </div>
            <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
        </div>

        <div class="modal-body">
            <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #3730a3; display: flex; align-items: flex-start; gap: 10px;">
                <i class="fa-solid fa-circle-info" style="font-size: 16px; margin-top: 2px;"></i>
                <div>
                    Please ensure you have fulfilled all items listed below before requesting clearance sign-off from your instructor.
                </div>
            </div>

            <label class="form-label" style="font-weight: 700; color: #1e293b;">Required Submissions & Compliance Checklist:</label>
            <div id="modalRequirementsContent" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 16px; font-size: 13px; line-height: 1.7; color: #1e293b; white-space: pre-line; max-height: 350px; overflow-y: auto;">
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" data-modal-close class="btn btn-primary">
                <i class="fa-solid fa-check"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const viewButtons = document.querySelectorAll('.view-req-btn');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('modalSubjectCode').innerText = btn.dataset.subjectCode || '';
            document.getElementById('modalSubjectName').innerText = btn.dataset.subjectName || '';
            document.getElementById('modalInstructorName').innerText = btn.dataset.instructorName || '';
            document.getElementById('modalRequirementsContent').innerText = btn.dataset.requirements || 'No specific requirements listed.';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('instructor');
$user = current_user();

// 1. Fetch active clearance period
$periodStmt = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1");
$activePeriod = $periodStmt->fetch();

// Ensure requirements column exists
ensure_clearance_requirements_column($pdo);

// 2. Fetch instructor's subjects
$subjects = [];
$studentsList = [];
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);

if ($activePeriod) {
    $subjStmt = $pdo->prepare("
        SELECT s.*, COUNT(css.id) as student_count,
               SUM(CASE WHEN css.status = 'cleared' THEN 1 ELSE 0 END) as cleared_count,
               SUM(CASE WHEN css.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN css.status = 'deficient' THEN 1 ELSE 0 END) as deficient_count
        FROM subjects s
        LEFT JOIN clearance_subject_status css ON s.id = css.subject_id
        WHERE s.instructor_id = ? AND s.period_id = ?
        GROUP BY s.id
        ORDER BY s.code ASC
    ");
    $subjStmt->execute([$user['id'], $activePeriod['id']]);
    $subjects = $subjStmt->fetchAll();

    if (!empty($subjects)) {
        if (!$selectedSubjectId) {
            $selectedSubjectId = $subjects[0]['id'];
        }

        // Fetch students under the selected subject
        $studStmt = $pdo->prepare("
            SELECT css.id as status_id, css.status, css.remarks, css.proof_file, css.student_note, css.submitted_at, css.updated_at,
                   cr.id as request_id, cr.clearance_code,
                   u.id as student_user_id, u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.section, u.email
            FROM clearance_subject_status css
            JOIN clearance_requests cr ON css.request_id = cr.id
            JOIN users u ON cr.student_id = u.id
            WHERE css.subject_id = ? AND css.instructor_id = ?
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        $studStmt->execute([$selectedSubjectId, $user['id']]);
        $studentsList = $studStmt->fetchAll();
    }
}

// Find current selected subject details
$currentSubject = null;
foreach ($subjects as $s) {
    if ($s['id'] == $selectedSubjectId) {
        $currentSubject = $s;
        break;
    }
}

$page_title = "Instructor Clearance Portal";
$page_subtitle = "Review and approve students enrolled in your academic subjects";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<?php if (!$activePeriod): ?>
    <div class="card" style="text-align: center; padding: 48px 24px;">
        <i class="fa-solid fa-calendar-xmark" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
        <h3>No Active Clearance Period</h3>
        <p style="color: var(--text-muted); max-width: 500px; margin: 8px auto 0;">No active clearance term is currently open. Check with your Department Head or Admin.</p>
    </div>

<?php elseif (empty($subjects)): ?>
    <div class="card" style="text-align: center; padding: 48px 24px;">
        <i class="fa-solid fa-book-open-reader" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
        <h3>No Subjects Assigned</h3>
        <p style="color: var(--text-muted); max-width: 500px; margin: 8px auto 0;">You have not been assigned to any subjects for <?= e($activePeriod['name']) ?> yet. Please contact the administrator.</p>
    </div>

<?php else: ?>
    <!-- Subject Selection Pills -->
    <div style="display: flex; gap: 12px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 8px;">
        <?php foreach ($subjects as $subj): ?>
            <a href="<?= url('instructor/dashboard?subject_id=' . $subj['id']) ?>" class="btn <?= $subj['id'] == $selectedSubjectId ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: var(--radius-full); padding: 8px 18px;">
                <i class="fa-solid fa-book"></i>
                <span><strong><?= e($subj['code']) ?></strong> - <?= e($subj['name']) ?></span>
                <span class="badge" style="background: rgba(255,255,255,0.25); color: inherit; margin-left: 6px;">
                    <?= (int)$subj['student_count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Summary Stats for Selected Subject -->
    <?php if ($currentSubject): ?>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$currentSubject['student_count'] ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$currentSubject['cleared_count'] ?></div>
                    <div class="stat-label">Students Cleared</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber"><i class="fa-solid fa-user-clock"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$currentSubject['pending_count'] ?></div>
                    <div class="stat-label">Pending Clearance</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-user-xmark"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$currentSubject['deficient_count'] ?></div>
                    <div class="stat-label">With Deficiencies</div>
                </div>
            </div>
        </div>

        <!-- Clearance Requirements Card for This Subject -->
        <div class="card" style="border-left: 5px solid var(--primary); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 280px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span class="badge badge-primary"><i class="fa-solid fa-clipboard-list"></i> Clearance Requirements</span>
                        <strong style="font-size: 14px; color: #334155;"><?= e($currentSubject['code']) ?> — <?= e($currentSubject['name']) ?></strong>
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                        These compliance guidelines and requirements are shown directly to students on their clearance dashboard:
                    </p>
                    <?php if (!empty($currentSubject['clearance_requirements'])): ?>
                        <div style="background: rgba(248, 250, 252, 0.65); border: 1px solid rgba(226, 232, 240, 0.75); border-radius: 8px; padding: 14px 18px; font-size: 13px; line-height: 1.7; color: #1e293b; white-space: pre-line;">
                            <?= e($currentSubject['clearance_requirements']) ?>
                        </div>
                    <?php else: ?>
                        <div style="background: rgba(255, 251, 235, 0.7); border: 1px dashed rgba(245, 158, 11, 0.6); border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #b45309; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-circle-info" style="font-size: 16px;"></i>
                            <span>No clearance requirements set yet. Students will only see standard clearance pending. Click <strong>Set Requirements</strong> to specify requirements for this subject.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-outline" data-modal-target="#editRequirementsModal"
                            data-subject-id="<?= $currentSubject['id'] ?>"
                            data-subject-code="<?= e($currentSubject['code']) ?>"
                            data-subject-name="<?= e($currentSubject['name']) ?>"
                            data-current-requirements="<?= e($currentSubject['clearance_requirements'] ?? '') ?>">
                        <i class="fa-solid fa-pen-to-square"></i> <?= !empty($currentSubject['clearance_requirements']) ? 'Edit Requirements' : 'Set Requirements' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Student List Table -->
        <div class="card">
            <div class="card-header" style="flex-wrap: wrap; gap: 14px;">
                <div>
                    <div class="card-title">
                        <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
                        <span><?= e($currentSubject['code']) ?>: <?= e($currentSubject['name']) ?> Class List</span>
                    </div>
                    <span style="font-size: 13px; color: var(--text-muted);">Manage and sign off subject requirements</span>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <div style="position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 12px;"></i>
                        <input type="text" data-table-search="#studentsTable" class="form-control btn-sm" style="padding-left: 28px; width: 220px;" placeholder="Search student name or ID...">
                    </div>

                    <form action="<?= url('instructor/update_status') ?>" method="POST" onsubmit="return confirm('Are you sure you want to approve ALL pending students in this class?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="batch_approve">
                        <input type="hidden" name="subject_id" value="<?= $currentSubject['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-check-double"></i> Approve All Pending
                        </button>
                    </form>
                </div>
            </div>

            <?php if (empty($studentsList)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fa-regular fa-folder-open" style="font-size: 36px; margin-bottom: 10px; color: #cbd5e1;"></i>
                    <p>No students have submitted a clearance request for this subject yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Program & Section</th>
                                <th>Passed Compliance</th>
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
                                                        <i class="fa-solid fa-paperclip"></i> View Attached File
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
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #94a3b8; font-style: italic;">No submission passed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= render_status_badge($st['status']) ?></td>
                                    <td>
                                        <?php if ($st['status'] === 'deficient'): ?>
                                            <div style="color: var(--danger); font-size: 12px; font-weight: 600; background: rgba(254, 242, 242, 0.85); border: 1px solid rgba(254, 202, 202, 0.85); border-radius: 4px; padding: 6px 10px; max-width: 240px;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Lacking:</strong> <?= e($st['remarks'] ?: 'Action required by instructor') ?>
                                            </div>
                                        <?php elseif (!empty($st['remarks'])): ?>
                                            <span style="font-size: 12px; color: var(--text-muted);"><?= e($st['remarks']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                                            <!-- Review Compliance Button (Opens Inspection Modal) -->
                                            <button type="button" class="btn btn-outline btn-sm review-comp-btn"
                                                    data-modal-target="#reviewComplianceModal"
                                                    data-status-id="<?= $st['status_id'] ?>"
                                                    data-subject-id="<?= $currentSubject['id'] ?>"
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
                                                    title="Inspect compliance and compare against requirements">
                                                <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i> Review
                                            </button>

                                            <!-- Quick Approve Form -->
                                            <form action="<?= url('instructor/update_status') ?>" method="POST" style="display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="single_update">
                                                <input type="hidden" name="status_id" value="<?= $st['status_id'] ?>">
                                                <input type="hidden" name="status" value="cleared">
                                                <input type="hidden" name="subject_id" value="<?= $currentSubject['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Quick Clear" <?= $st['status'] === 'cleared' ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-check"></i> Clear
                                                </button>
                                            </form>

                                            <!-- Quick Mark Deficient Button -->
                                            <button type="button" class="btn btn-danger btn-sm" data-modal-target="#deficiencyModal"
                                                    data-status-id="<?= $st['status_id'] ?>"
                                                    data-student-name="<?= e($st['first_name'] . ' ' . $st['last_name']) ?>"
                                                    data-current-remarks="<?= e($st['remarks']) ?>"
                                                    data-subject-id="<?= $currentSubject['id'] ?>"
                                                    title="Flag as deficient with notes">
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
<?php endif; ?>

<!-- Comprehensive Review Compliance Modal -->
<div class="modal-overlay" id="reviewComplianceModal">
    <div class="modal-container" style="max-width: 650px;">
        <form action="<?= url('instructor/update_status') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="single_update">
            <input type="hidden" name="status_id" id="revStatusId">
            <input type="hidden" name="subject_id" id="revSubjectId">

            <div class="modal-header">
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i>
                        <span>Inspect Compliance: <span id="revStudentName" style="color: var(--primary);"></span></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        ID: <strong id="revStudentId"></strong> &bull; Program: <span id="revStudentProgram"></span> &bull; Code: <span id="revClearanceCode"></span>
                    </div>
                </div>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <!-- 1. Subject Requirements Baseline -->
                <div style="background: rgba(238, 242, 255, 0.75); border: 1px solid rgba(199, 210, 254, 0.8); border-radius: var(--radius-md); padding: 14px;">
                    <div style="font-size: 12px; font-weight: 700; color: #3730a3; text-transform: uppercase; margin-bottom: 6px;">
                        <i class="fa-solid fa-list-ol"></i> Your Subject Requirements (<?= $currentSubject ? e($currentSubject['code']) : '' ?>):
                    </div>
                    <div style="font-size: 13px; line-height: 1.6; color: #1e293b; white-space: pre-line;">
                        <?= ($currentSubject && !empty($currentSubject['clearance_requirements'])) ? e($currentSubject['clearance_requirements']) : 'Standard academic standing (all coursework, exams, and laboratory units must be completed).' ?>
                    </div>
                </div>

                <!-- 2. What Student Passed / Submitted -->
                <div style="background: rgba(248, 250, 252, 0.9); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: var(--radius-md); padding: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase;">
                            <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i> Student's Compliance Submission:
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);" id="revSubmittedAt"></div>
                    </div>

                    <!-- File link box -->
                    <div id="revFileSection" style="margin-bottom: 10px;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Attached Proof / Document:</div>
                        <div id="revFileContent"></div>
                    </div>

                    <!-- Note box -->
                    <div id="revNoteSection">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Student Compliance Note / Details:</div>
                        <div id="revNoteContent" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 13px; line-height: 1.5; color: #1e293b; white-space: pre-line;"></div>
                    </div>
                </div>

                <!-- 3. Instructor Evaluation Form -->
                <div>
                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Evaluation Decision *</label>
                    <div style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="cleared" id="revStatusCleared">
                            <span style="font-weight: 600; color: var(--success);"><i class="fa-solid fa-circle-check"></i> Cleared / Satisfied</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="deficient" id="revStatusDeficient">
                            <span style="font-weight: 600; color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Flag Deficient (Specify Lacking)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="status" value="pending" id="revStatusPending">
                            <span style="font-weight: 600; color: #ea580c;"><i class="fa-solid fa-clock"></i> Keep Pending</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="revRemarks">
                            Remarks / What is Lacking (Instructions for Student)
                            <small style="color: var(--text-muted); font-weight: normal;">(Required if marking Deficient)</small>
                        </label>
                        <textarea name="remarks" id="revRemarks" class="form-control" rows="3" placeholder="If deficient, clearly describe what is lacking (e.g. 'Missing Lab Report #5, unreturned Arduino kit #2'). If cleared, optional remarks."></textarea>
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

<!-- Deficiency Modal (Quick Action) -->
<div class="modal-overlay" id="deficiencyModal">
    <div class="modal-container">
        <form action="<?= url('instructor/update_status') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="single_update">
            <input type="hidden" name="status" value="deficient">
            <input type="hidden" name="status_id" id="modalStatusId">
            <input type="hidden" name="subject_id" id="modalSubjectId">

            <div class="modal-header">
                <h3 style="font-size: 16px; font-weight: 700;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i>
                    Flag Deficiency for <span data-field="studentName" id="modalStudentName" style="color: var(--primary);"></span>
                </h3>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="modalRemarks">Deficiency Remarks / What is Lacking for Student *</label>
                    <textarea name="remarks" id="modalRemarks" class="form-control" rows="4" required placeholder="Specify what the student lacks (e.g. Incomplete machine problem #3, unreturned lab module, lacking quiz score)..."></textarea>
                    <small style="font-size: 12px; color: var(--text-muted); margin-top: 4px; display: block;">
                        The student will immediately see this notice on their dashboard so they can comply.
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

<!-- Edit Subject Clearance Requirements Modal -->
<div class="modal-overlay" id="editRequirementsModal">
    <div class="modal-container" style="max-width: 600px;">
        <form action="<?= url('instructor/update_status') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_requirements">
            <input type="hidden" name="subject_id" id="reqSubjectId" value="<?= $currentSubject ? $currentSubject['id'] : '' ?>">

            <div class="modal-header">
                <h3 style="font-size: 16px; font-weight: 700;">
                    <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i>
                    Set Clearance Requirements for <span id="reqSubjectCode" style="color: var(--primary);"><?= $currentSubject ? e($currentSubject['code']) : '' ?></span>
                </h3>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="clearanceRequirementsText">Clearance Criteria & Requirements for Students *</label>
                    <textarea name="clearance_requirements" id="clearanceRequirementsText" class="form-control" rows="6" placeholder="List items students must accomplish before you sign their clearance, for example:
1. Complete all laboratory machine problems #1 to #5
2. Submit final Capstone project code and documentation
3. Return all borrowed equipment and sensor kits
4. Settle laboratory breakage dues (if any)"><?= $currentSubject ? e($currentSubject['clearance_requirements'] ?? '') : '' ?></textarea>
                    <small style="font-size: 12px; color: var(--text-muted); margin-top: 6px; display: block;">
                        <i class="fa-solid fa-circle-info"></i> Students enrolled in this course will see this checklist directly on their clearance dashboard.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Requirements
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Review Compliance Modal
    const revButtons = document.querySelectorAll('.review-comp-btn');
    revButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('revStatusId').value = btn.dataset.statusId || '';
            document.getElementById('revSubjectId').value = btn.dataset.subjectId || '';
            document.getElementById('revStudentName').innerText = btn.dataset.studentName || '';
            document.getElementById('revStudentId').innerText = btn.dataset.studentId || '';
            document.getElementById('revStudentProgram').innerText = btn.dataset.studentProgram || '';
            document.getElementById('revClearanceCode').innerText = btn.dataset.clearanceCode || '';
            document.getElementById('revSubmittedAt').innerText = btn.dataset.submittedAt ? ('Submitted: ' + btn.dataset.submittedAt) : 'No submission recorded';
            document.getElementById('revRemarks').value = btn.dataset.remarks || '';

            // Status radio
            const currentStatus = btn.dataset.status || 'pending';
            if (currentStatus === 'cleared') {
                document.getElementById('revStatusCleared').checked = true;
            } else if (currentStatus === 'deficient') {
                document.getElementById('revStatusDeficient').checked = true;
            } else {
                document.getElementById('revStatusPending').checked = true;
            }

            // File section
            const fileUrl = btn.dataset.proofUrl;
            const fileName = btn.dataset.proofFile;
            const fileContent = document.getElementById('revFileContent');
            if (fileUrl && fileName) {
                fileContent.innerHTML = `<a href="${fileUrl}" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-paperclip"></i> Open & View File: <strong>${fileName}</strong></a>`;
            } else {
                fileContent.innerHTML = '<span style="font-size: 12px; color: #94a3b8; font-style: italic;">No file attached.</span>';
            }

            // Note section
            const noteText = btn.dataset.studentNote;
            const noteContent = document.getElementById('revNoteContent');
            if (noteText && noteText.trim() !== '') {
                noteContent.innerText = noteText;
            } else {
                noteContent.innerHTML = '<span style="color: #94a3b8; font-style: italic;">No compliance note entered by student.</span>';
            }
        });
    });

    // Fill specific modal fields when clicking quick deficient button
    const defButtons = document.querySelectorAll('[data-modal-target="#deficiencyModal"]');
    defButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('modalStatusId').value = btn.dataset.statusId;
            document.getElementById('modalSubjectId').value = btn.dataset.subjectId;
            document.getElementById('modalStudentName').innerText = btn.dataset.studentName;
            document.getElementById('modalRemarks').value = btn.dataset.currentRemarks || '';
        });
    });

    // Fill edit requirements modal
    const reqButtons = document.querySelectorAll('[data-modal-target="#editRequirementsModal"]');
    reqButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('reqSubjectId').value = btn.dataset.subjectId;
            document.getElementById('reqSubjectCode').innerText = btn.dataset.subjectCode;
            document.getElementById('clearanceRequirementsText').value = btn.dataset.currentRequirements || '';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

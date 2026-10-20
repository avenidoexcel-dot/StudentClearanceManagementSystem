<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('instructor');
$user = current_user();

// 1. Fetch active clearance period
$periodStmt = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1");
$activePeriod = $periodStmt->fetch();

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
            SELECT css.id as status_id, css.status, css.remarks, css.updated_at,
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
            <a href="?subject_id=<?= $subj['id'] ?>" class="btn <?= $subj['id'] == $selectedSubjectId ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: var(--radius-full); padding: 8px 18px;">
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

                    <form action="update_status.php" method="POST" onsubmit="return confirm('Are you sure you want to approve ALL pending students in this class?');">
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
                                        <div style="font-size: 12px; color: var(--text-muted);"><?= e($st['email']) ?></div>
                                    </td>
                                    <td><?= e($st['course'] . ' ' . $st['year_level'] . ' - ' . $st['section']) ?></td>
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
                                            <!-- Approve Form -->
                                            <form action="update_status.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="single_update">
                                                <input type="hidden" name="status_id" value="<?= $st['status_id'] ?>">
                                                <input type="hidden" name="status" value="cleared">
                                                <input type="hidden" name="subject_id" value="<?= $currentSubject['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Clear this student" <?= $st['status'] === 'cleared' ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-check"></i> Clear
                                                </button>
                                            </form>

                                            <!-- Mark Deficient Button (triggers Modal) -->
                                            <button type="button" class="btn btn-danger btn-sm" data-modal-target="#deficiencyModal"
                                                    data-status-id="<?= $st['status_id'] ?>"
                                                    data-student-name="<?= e($st['first_name'] . ' ' . $st['last_name']) ?>"
                                                    data-current-remarks="<?= e($st['remarks']) ?>"
                                                    data-subject-id="<?= $currentSubject['id'] ?>"
                                                    title="Mark as deficient with notes">
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
<?php endif; ?>

<!-- Deficiency Modal -->
<div class="modal-overlay" id="deficiencyModal">
    <div class="modal-container">
        <form action="update_status.php" method="POST">
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
                    <label class="form-label" for="modalRemarks">Deficiency Remarks / Instructions for Student *</label>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Fill specific modal fields when clicking deficient button
    const defButtons = document.querySelectorAll('[data-modal-target="#deficiencyModal"]');
    defButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('modalStatusId').value = btn.dataset.statusId;
            document.getElementById('modalSubjectId').value = btn.dataset.subjectId;
            document.getElementById('modalStudentName').innerText = btn.dataset.studentName;
            document.getElementById('modalRemarks').value = btn.dataset.currentRemarks || '';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

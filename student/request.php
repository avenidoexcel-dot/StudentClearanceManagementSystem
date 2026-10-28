<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('student');
$user = current_user();

// 1. Get active clearance period
$periodStmt = $pdo->query("SELECT * FROM clearance_periods WHERE is_active = 1 LIMIT 1");
$activePeriod = $periodStmt->fetch();

if (!$activePeriod) {
    set_flash('error', 'There is no active clearance period open at the moment.');
    redirect('student/dashboard.php');
}

// 2. Check if request already exists
$checkStmt = $pdo->prepare("SELECT id FROM clearance_requests WHERE student_id = ? AND period_id = ?");
$checkStmt->execute([$user['id'], $activePeriod['id']]);
$existingRequest = $checkStmt->fetch();

if ($existingRequest) {
    set_flash('info', 'You already have an active clearance request for this period.');
    redirect('student/dashboard.php');
}

// 3. Process Request Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('student/request.php');

    $note = trim($_POST['note'] ?? '');

    try {
        $pdo->beginTransaction();

        // Generate unique clearance tracking code
        $clearanceCode = 'CLR-' . date('Y') . '-' . strtoupper(substr(md5(uniqid((string)$user['id'], true)), 0, 8));

        // Insert into clearance_requests
        $insertReq = $pdo->prepare("
            INSERT INTO clearance_requests (student_id, period_id, clearance_code, overall_status, submission_note)
            VALUES (?, ?, ?, 'in_progress', ?)
        ");
        $insertReq->execute([$user['id'], $activePeriod['id'], $clearanceCode, $note]);
        $requestId = (int)$pdo->lastInsertId();

        // Seed Department Requirements (all active departments)
        $deptStmt = $pdo->query("SELECT id FROM departments WHERE is_active = 1 ORDER BY id ASC");
        $departments = $deptStmt->fetchAll();

        $insertDeptStatus = $pdo->prepare("
            INSERT INTO clearance_signatory_status (request_id, department_id, status)
            VALUES (?, ?, 'pending')
        ");
        foreach ($departments as $dept) {
            $insertDeptStatus->execute([$requestId, $dept['id']]);
        }

        // Seed Subject Requirements (Student's enrolled subjects for this period)
        $subjStmt = $pdo->prepare("
            SELECT s.id, s.instructor_id 
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.student_id = ? AND ss.period_id = ?
        ");
        $subjStmt->execute([$user['id'], $activePeriod['id']]);
        $enrolledSubjects = $subjStmt->fetchAll();

        // If student had no pre-enrolled subjects in student_subjects table,
        // automatically associate all subjects in the period for demonstration
        if (empty($enrolledSubjects)) {
            $allSubjStmt = $pdo->prepare("SELECT id, instructor_id FROM subjects WHERE period_id = ?");
            $allSubjStmt->execute([$activePeriod['id']]);
            $enrolledSubjects = $allSubjStmt->fetchAll();

            // Also record in student_subjects
            $enrollStmt = $pdo->prepare("
                INSERT IGNORE INTO student_subjects (student_id, subject_id, period_id)
                VALUES (?, ?, ?)
            ");
            foreach ($enrolledSubjects as $sub) {
                $enrollStmt->execute([$user['id'], $sub['id'], $activePeriod['id']]);
            }
        }

        $insertSubjStatus = $pdo->prepare("
            INSERT INTO clearance_subject_status (request_id, subject_id, instructor_id, status)
            VALUES (?, ?, ?, 'pending')
        ");
        foreach ($enrolledSubjects as $sub) {
            $insertSubjStatus->execute([$requestId, $sub['id'], $sub['instructor_id']]);
        }

        $pdo->commit();

        set_flash('success', 'Your clearance request has been successfully submitted! Tracking Code: ' . $clearanceCode);
        redirect('student/dashboard.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to submit clearance request: ' . $e->getMessage();
    }
}

// Fetch preview of requirements to show student what will be generated
$deptPreview = $pdo->query("SELECT name, code FROM departments WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$subjPreview = $pdo->prepare("
    SELECT s.code, s.name, s.clearance_requirements, CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
    FROM subjects s
    JOIN users u ON s.instructor_id = u.id
    WHERE s.period_id = ?
    ORDER BY s.code ASC
");
$subjPreview->execute([$activePeriod['id']]);
$subjectsList = $subjPreview->fetchAll();

$page_title = "Submit Clearance Request";
$page_subtitle = "Confirm and initiate your clearance evaluation for the current academic period";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-file-signature" style="color: var(--primary);"></i>
                <span>Clearance Application: <?= e($activePeriod['name']) ?></span>
            </div>
            <span class="badge badge-primary"><?= e($activePeriod['academic_year']) ?></span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Student Profile Review -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 18px; margin-bottom: 24px;">
            <h4 style="font-size: 15px; margin-bottom: 12px; color: #334155;">
                <i class="fa-regular fa-id-card"></i> Student Information
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;">
                <div><span style="color: #64748b;">Full Name:</span> <strong><?= e($user['name']) ?></strong></div>
                <div><span style="color: #64748b;">Student ID:</span> <strong><?= e($user['student_id'] ?: 'N/A') ?></strong></div>
                <div><span style="color: #64748b;">Course / Program:</span> <strong><?= e($user['course'] ?: 'BSIT') ?></strong></div>
                <div><span style="color: #64748b;">Year & Section:</span> <strong><?= e($user['year_level'] . ' - ' . $user['section']) ?></strong></div>
            </div>
        </div>

        <!-- Requirements Summary -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; margin-bottom: 12px; color: #334155;">
                <i class="fa-solid fa-list-check"></i> Signatories Checklist to be Assigned:
            </h4>
            <div class="grid-2">
                <!-- Departments List -->
                <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px;">
                    <div style="font-weight: 700; font-size: 13px; color: #4338ca; margin-bottom: 8px;">
                        <i class="fa-solid fa-building-columns"></i> Administrative Offices (<?= count($deptPreview) ?>)
                    </div>
                    <ul style="font-size: 13px; list-style-type: none; padding-left: 0; line-height: 1.8;">
                        <?php foreach ($deptPreview as $d): ?>
                            <li><i class="fa-regular fa-circle-check" style="color: #10b981; font-size: 11px;"></i> <?= e($d['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Subjects List -->
                <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px;">
                    <div style="font-weight: 700; font-size: 13px; color: #0284c7; margin-bottom: 8px;">
                        <i class="fa-solid fa-chalkboard-user"></i> Subject Instructors (<?= count($subjectsList) ?>)
                    </div>
                    <ul style="font-size: 13px; list-style-type: none; padding-left: 0; line-height: 1.8;">
                        <?php foreach ($subjectsList as $s): ?>
                            <li style="margin-bottom: 6px;">
                                <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                                    <div>
                                        <i class="fa-regular fa-circle-check" style="color: #10b981; font-size: 11px;"></i> 
                                        <strong><?= e($s['code']) ?></strong> - <?= e($s['instructor_name']) ?>
                                    </div>
                                    <?php if (!empty($s['clearance_requirements'])): ?>
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 10px; padding: 2px 6px;">
                                            <i class="fa-solid fa-list-check"></i> Has Requirements
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <form action="<?= url('student/request.php') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="note">Submission Note / Remarks (Optional)</label>
                <textarea name="note" id="note" class="form-control" placeholder="Any additional information or comments for your instructors and department signatories..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="<?= url('student/dashboard.php') ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 11px 24px;">
                    <i class="fa-solid fa-paper-plane"></i> Confirm & Submit Clearance
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

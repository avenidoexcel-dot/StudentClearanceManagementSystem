<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$user = current_user();

// Admin can view any student's certificate by passing ?request_id=X
$requestId = (int)($_GET['request_id'] ?? 0);

if ($requestId && $user['role'] === 'admin') {
    $reqStmt = $pdo->prepare("
        SELECT cr.*, cp.name AS period_name, cp.academic_year, cp.semester,
               u.first_name, u.last_name, u.student_id, u.course, u.year_level, u.section
        FROM clearance_requests cr
        JOIN clearance_periods cp ON cr.period_id = cp.id
        JOIN users u ON cr.student_id = u.id
        WHERE cr.id = ?
    ");
    $reqStmt->execute([$requestId]);
    $request = $reqStmt->fetch();
} else {
    // Current student's active request
    $reqStmt = $pdo->prepare("
        SELECT cr.*, cp.name AS period_name, cp.academic_year, cp.semester,
               u.first_name, u.last_name, u.student_id, u.course, u.year_level, u.section
        FROM clearance_requests cr
        JOIN clearance_periods cp ON cr.period_id = cp.id
        JOIN users u ON cr.student_id = u.id
        WHERE cr.student_id = ? AND cp.is_active = 1
        ORDER BY cr.id DESC LIMIT 1
    ");
    $reqStmt->execute([$user['id']]);
    $request = $reqStmt->fetch();
}

if (!$request) {
    set_flash('error', 'No clearance record found.');
    redirect($user['role'] === 'student' ? 'student/dashboard' : 'admin/dashboard');
}

// Fetch all department sign-offs
$deptStmt = $pdo->prepare("
    SELECT cs.*, d.name AS department_name, d.code AS department_code,
           CONCAT(u.first_name, ' ', u.last_name) AS signatory_name
    FROM clearance_signatory_status cs
    JOIN departments d ON cs.department_id = d.id
    LEFT JOIN users u ON cs.signatory_id = u.id
    WHERE cs.request_id = ?
    ORDER BY d.id ASC
");
$deptStmt->execute([$request['id']]);
$departments = $deptStmt->fetchAll();

// Fetch all subject sign-offs
$subjStmt = $pdo->prepare("
    SELECT css.*, s.code AS subject_code, s.name AS subject_name,
           CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
    FROM clearance_subject_status css
    JOIN subjects s ON css.subject_id = s.id
    JOIN users u ON css.instructor_id = u.id
    WHERE css.request_id = ?
    ORDER BY s.code ASC
");
$subjStmt->execute([$request['id']]);
$subjects = $subjStmt->fetchAll();

$isCleared = ($request['overall_status'] === 'cleared');

$page_title = "Official Student Clearance Certificate";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="no-print" style="max-width: 900px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= url('student/dashboard') ?>" class="btn btn-outline">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <div style="display: flex; gap: 10px;">
        <?php if ($isCleared): ?>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> Print Official Certificate
            </button>
        <?php else: ?>
            <span class="badge badge-warning" style="font-size: 13px; padding: 8px 14px;">
                <i class="fa-solid fa-lock"></i> Certificate Preview (Status: In Progress)
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Official Certificate Layout -->
<div class="certificate-wrapper">
    <div class="certificate-header">
        <div style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 8px;">
            <div style="width: 50px; height: 50px; background: #4338ca; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div class="school-name">State University of Science & Technology</div>
                <div class="school-sub">Office of the University Registrar & Student Affairs</div>
            </div>
        </div>
        <div class="certificate-title">OFFICIAL STUDENT CLEARANCE CERTIFICATE</div>
        <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-top: 4px;">
            <?= e($request['period_name']) ?> (A.Y. <?= e($request['academic_year']) ?> - <?= e($request['semester']) ?>)
        </div>
    </div>

    <!-- Student Profile Data -->
    <div class="student-info-grid">
        <div class="info-item">
            <span class="info-label">Student Name:</span>
            <span class="info-value"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Student ID:</span>
            <span class="info-value"><?= e($request['student_id']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Degree Program:</span>
            <span class="info-value"><?= e($request['course'] ?: 'BSIT') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Year & Section:</span>
            <span class="info-value"><?= e($request['year_level'] . ' - ' . $request['section']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Tracking Code:</span>
            <span class="info-value" style="font-family: monospace; color: #4338ca;"><?= e($request['clearance_code']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Status:</span>
            <span class="info-value"><?= $isCleared ? '<span style="color: #059669; font-weight: 700;">OFFICIALLY CLEARED</span>' : '<span style="color: #d97706; font-weight: 700;">IN PROGRESS</span>' ?></span>
        </div>
    </div>

    <!-- 1. Enrolled Subjects & Instructor Signatures -->
    <div style="margin-bottom: 24px;">
        <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px;">
            I. Subject Instructors Clearance
        </h4>
        <table class="table" style="font-size: 12px;">
            <thead>
                <tr>
                    <th style="padding: 6px 10px;">Subject Code</th>
                    <th style="padding: 6px 10px;">Course Title</th>
                    <th style="padding: 6px 10px;">Instructor</th>
                    <th style="padding: 6px 10px; text-align: center;">Status</th>
                    <th style="padding: 6px 10px;">Date Cleared</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td style="padding: 6px 10px;"><strong><?= e($s['subject_code']) ?></strong></td>
                        <td style="padding: 6px 10px;"><?= e($s['subject_name']) ?></td>
                        <td style="padding: 6px 10px;"><?= e($s['instructor_name']) ?></td>
                        <td style="padding: 6px 10px; text-align: center;">
                            <?= $s['status'] === 'cleared' ? 'CLEARED' : strtoupper($s['status']) ?>
                        </td>
                        <td style="padding: 6px 10px;"><?= format_datetime($s['updated_at'], 'M d, Y') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. Administrative Signatories -->
    <div style="margin-bottom: 24px;">
        <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px;">
            II. Administrative Offices Signatories
        </h4>
        <table class="table" style="font-size: 12px;">
            <thead>
                <tr>
                    <th style="padding: 6px 10px;">Office / Department</th>
                    <th style="padding: 6px 10px;">Authorized Signatory</th>
                    <th style="padding: 6px 10px; text-align: center;">Status</th>
                    <th style="padding: 6px 10px;">Date Cleared</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr>
                        <td style="padding: 6px 10px;"><strong><?= e($d['department_name']) ?></strong></td>
                        <td style="padding: 6px 10px;"><?= e($d['signatory_name'] ?: 'Office In-Charge') ?></td>
                        <td style="padding: 6px 10px; text-align: center;">
                            <?= $d['status'] === 'cleared' ? 'CLEARED' : strtoupper($d['status']) ?>
                        </td>
                        <td style="padding: 6px 10px;"><?= format_datetime($d['updated_at'], 'M d, Y') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Certification Statement -->
    <div style="font-size: 12px; font-style: italic; color: #475569; margin-top: 18px; line-height: 1.6; text-align: justify;">
        This document certifies that the aforementioned student has fulfilled all academic, property, and financial obligations across all departments and faculty members for the stated term. This digital clearance certificate was processed electronically via ClearanceHub.
    </div>

    <!-- Seal and Verification Footer -->
    <div class="seal-box">
        <div class="qr-placeholder">
            <div class="qr-box">
                <i class="fa-solid fa-qrcode"></i>
            </div>
            <div style="font-size: 11px; color: #475569;">
                <div><strong>Scan to Verify Authenticity</strong></div>
                <div>Code: <code><?= e($request['clearance_code']) ?></code></div>
                <div>Verify: <?= url('verify?code=' . urlencode($request['clearance_code'])) ?></div>
            </div>
        </div>

        <div style="text-align: right; font-size: 11px; color: #475569;">
            <div>Date Generated: <?= date('F d, Y h:i A') ?></div>
            <?php if ($request['completed_at']): ?>
                <div>Date Cleared: <?= format_datetime($request['completed_at'], 'F d, Y') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

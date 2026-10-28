<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('student');
$user = current_user();

ensure_compliance_columns($pdo);

$type = $_GET['type'] ?? (isset($_GET['status_id']) ? 'department' : 'subject');
$type = in_array($type, ['subject', 'department']) ? $type : 'subject';
$id = (int)($_GET['id'] ?? $_GET['status_id'] ?? 0);

$record = null;
$error = '';

if ($type === 'subject') {
    // Validate subject requirement belongs to this student
    $stmt = $pdo->prepare("
        SELECT css.*, s.code AS subject_code, s.name AS subject_name, s.clearance_requirements,
               CONCAT(u.first_name, ' ', u.last_name) AS instructor_name,
               u.email AS instructor_email, cr.student_id
        FROM clearance_subject_status css
        JOIN subjects s ON css.subject_id = s.id
        JOIN users u ON css.instructor_id = u.id
        JOIN clearance_requests cr ON css.request_id = cr.id
        WHERE css.id = ? AND cr.student_id = ?
    ");
    $stmt->execute([$id, $user['id']]);
    $record = $stmt->fetch();
} else {
    // Validate department requirement belongs to this student
    $stmt = $pdo->prepare("
        SELECT cs.*, d.name AS department_name, d.code AS department_code, d.description AS department_desc,
               d.requires_proof, CONCAT(u.first_name, ' ', u.last_name) AS signatory_name,
               cr.student_id
        FROM clearance_signatory_status cs
        JOIN departments d ON cs.department_id = d.id
        LEFT JOIN users u ON cs.signatory_id = u.id
        JOIN clearance_requests cr ON cs.request_id = cr.id
        WHERE cs.id = ? AND cr.student_id = ?
    ");
    $stmt->execute([$id, $user['id']]);
    $record = $stmt->fetch();
}

if (!$record) {
    set_flash('error', 'Clearance requirement record not found or access unauthorized.');
    redirect('student/dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("student/upload_proof?type={$type}&id={$id}");

    $studentNote = trim($_POST['student_note'] ?? '');
    $uploadedFileName = null;

    // Handle file upload if provided
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['proof_file']['tmp_name'];
        $fileName = $_FILES['proof_file']['name'];
        $fileSize = $_FILES['proof_file']['size'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt'];

        if (!in_array($fileExt, $allowedExts)) {
            $error = 'Invalid file format. Allowed formats: JPG, PNG, WEBP, PDF, DOC, DOCX, ZIP, TXT.';
        } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            $error = 'File size exceeds maximum allowed limit of 10MB.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/proofs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $prefix = ($type === 'subject') ? 'subj_' . $record['subject_id'] : 'dept_' . $record['department_id'];
            $newFileName = 'proof_' . $prefix . '_' . $record['request_id'] . '_' . time() . '.' . $fileExt;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $uploadedFileName = $newFileName;
            } else {
                $error = 'Failed to save uploaded file. Please check folder write permissions.';
            }
        }
    }

    // Require at least a file or a compliance note if no previous submission exists
    $hasExistingProof = !empty($record['proof_file']);
    $hasExistingNote  = !empty($record['student_note']);

    if (!$error) {
        if ($type === 'department' && !empty($record['requires_proof']) && !$uploadedFileName && !$hasExistingProof) {
            $error = 'This department strictly requires an uploaded proof document or receipt.';
        } elseif (!$uploadedFileName && $studentNote === '' && !$hasExistingProof && !$hasExistingNote) {
            $error = 'Please provide either a compliance note or upload a supporting file/document.';
        }
    }

    if (!$error) {
        // If the item was deficient, re-submitting sets status back to pending so instructor/signatory re-evaluates
        $newStatus = ($record['status'] === 'deficient') ? 'pending' : $record['status'];

        if ($type === 'subject') {
            if ($uploadedFileName) {
                $updateStmt = $pdo->prepare("
                    UPDATE clearance_subject_status
                    SET proof_file = ?, student_note = ?, submitted_at = NOW(), status = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$uploadedFileName, $studentNote, $newStatus, $id]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE clearance_subject_status
                    SET student_note = ?, submitted_at = NOW(), status = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$studentNote, $newStatus, $id]);
            }
            $targetLabel = $record['subject_code'] . ' (' . $record['subject_name'] . ')';
        } else {
            if ($uploadedFileName) {
                $updateStmt = $pdo->prepare("
                    UPDATE clearance_signatory_status
                    SET proof_file = ?, student_note = ?, submitted_at = NOW(), status = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$uploadedFileName, $studentNote, $newStatus, $id]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE clearance_signatory_status
                    SET student_note = ?, submitted_at = NOW(), status = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$studentNote, $newStatus, $id]);
            }
            $targetLabel = $record['department_name'];
        }

        check_and_update_overall_clearance($pdo, $record['request_id']);

        $resubmitMsg = ($record['status'] === 'deficient') ? ' Deficiencies addressed and resubmitted for re-verification.' : '';
        set_flash('success', 'Compliance details submitted successfully for ' . $targetLabel . '.' . $resubmitMsg);
        redirect('student/dashboard');
    }
}

$page_title = ($type === 'subject') ? "Submit Subject Compliance" : "Submit Office Proof / Compliance";
$page_subtitle = "Pass your compliance documents, receipts, or notes for evaluation";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 16px;">
        <a href="<?= url('student/dashboard') ?>" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
            <div class="card-title">
                <i class="fa-solid <?= $type === 'subject' ? 'fa-book-open-reader' : 'fa-building-columns' ?>" style="color: var(--primary);"></i>
                <span>
                    <?= $type === 'subject' ? 'Subject Compliance: ' . e($record['subject_code']) : 'Department Clearance: ' . e($record['department_name']) ?>
                </span>
            </div>
            <div>
                <?= render_status_badge($record['status']) ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Deficiency Notice Alert (What is lacking) -->
        <?php if ($record['status'] === 'deficient'): ?>
            <div class="alert alert-error" style="border-left: 5px solid var(--danger); background: rgba(239, 68, 68, 0.08); padding: 16px; margin-bottom: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px; color: var(--danger); margin-top: 2px;"></i>
                    <div style="flex: 1;">
                        <strong style="color: var(--danger); font-size: 14px; display: block; margin-bottom: 4px;">
                            Deficiency Flagged — What is Lacking:
                        </strong>
                        <div style="font-size: 13px; color: #991b1b; line-height: 1.6; background: rgba(255, 255, 255, 0.7); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.2); white-space: pre-line;">
                            <?= e($record['remarks'] ?: 'Action or document required. Please comply as instructed.') ?>
                        </div>
                        <div style="font-size: 12px; color: #b91c1c; margin-top: 6px;">
                            <i class="fa-solid fa-info-circle"></i> Once you submit your updated compliance or proof below, the status will automatically return to <strong>Pending</strong> for re-evaluation.
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Requirements / Context Panel -->
        <div style="background: rgba(248, 250, 252, 0.85); border: 1px solid rgba(226, 232, 240, 0.8); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;">
            <?php if ($type === 'subject'): ?>
                <div style="margin-bottom: 8px;">
                    <strong style="font-size: 14px; color: #1e293b;"><?= e($record['subject_name']) ?></strong>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                        Assigned Instructor: <strong><?= e($record['instructor_name']) ?></strong> (<?= e($record['instructor_email']) ?>)
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                        <i class="fa-solid fa-clipboard-list" style="color: var(--primary);"></i> Instructor's Clearance Requirements:
                    </div>
                    <?php if (!empty($record['clearance_requirements'])): ?>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-sm); padding: 12px 14px; font-size: 13px; line-height: 1.6; color: #334155; white-space: pre-line;">
                            <?= e($record['clearance_requirements']) ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 13px; color: var(--text-muted); font-style: italic;">
                            Standard academic standing (no custom instructions listed).
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 8px;">
                    <strong style="font-size: 14px; color: #1e293b;"><?= e($record['department_name']) ?> (<?= e($record['department_code']) ?>)</strong>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                        Signatory Officer: <strong><?= e($record['signatory_name'] ?: 'Designated Signatory') ?></strong>
                    </div>
                </div>
                <?php if (!empty($record['department_desc'])): ?>
                    <p style="font-size: 13px; color: #475569; margin-top: 8px; line-height: 1.5;">
                        <?= e($record['department_desc']) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($record['requires_proof'])): ?>
                    <div style="display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: #ea580c; font-weight: 600; background: #fff7ed; padding: 4px 10px; border-radius: var(--radius-full); border: 1px solid #fed7aa;">
                        <i class="fa-solid fa-asterisk"></i> Official Document / Receipt Attachment Required
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Currently Passed / Submitted Info -->
        <?php if (!empty($record['proof_file']) || !empty($record['student_note'])): ?>
            <div style="background: rgba(240, 253, 244, 0.7); border: 1px solid rgba(187, 247, 208, 0.8); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Your Previously Submitted Compliance:</span>
                    <?php if (!empty($record['submitted_at'])): ?>
                        <span style="font-size: 11px; font-weight: normal; color: #15803d; margin-left: auto;">
                            Submitted: <?= format_datetime($record['submitted_at']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($record['proof_file'])): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #dcfce7; border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 8px;">
                        <div style="font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-paperclip" style="color: var(--primary);"></i>
                            <span style="font-weight: 500; color: #1e293b;"><?= e($record['proof_file']) ?></span>
                        </div>
                        <a href="<?= url('uploads/proofs/' . $record['proof_file']) ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> View Attached File
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['student_note'])): ?>
                    <div style="background: #ffffff; border: 1px solid #dcfce7; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; color: #334155; line-height: 1.5;">
                        <strong style="color: #15803d; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px;">Student Compliance Note:</strong>
                        <?= nl2br(e($record['student_note'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Submission Form -->
        <form action="<?= url('student/upload_proof?type=' . $type . '&id=' . $id) ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="proof_file">
                    Attach Supporting Document / File
                    <?php if ($type === 'department' && !empty($record['requires_proof']) && empty($record['proof_file'])): ?>
                        <span style="color: var(--danger);">* (Required)</span>
                    <?php else: ?>
                        <span style="color: var(--text-muted); font-size: 12px;">(Optional if explanatory note provided)</span>
                    <?php endif; ?>
                </label>
                <input type="file" name="proof_file" id="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.zip,.txt">
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                    Allowed: JPG, PNG, WEBP, PDF, DOCX, ZIP, TXT (Maximum: 10MB). Examples: Official Receipt, Lab Project Report, Screen capture of completion, Return voucher.
                </small>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label class="form-label" for="student_note">
                    Compliance Note / Explanation
                    <span style="color: var(--text-muted); font-size: 12px;">(Provide details of what you completed or passed)</span>
                </label>
                <textarea name="student_note" id="student_note" class="form-control" rows="4" placeholder="Detail what you complied to satisfy the requirement (e.g. 'Submitted final project report to instructor email, paid remaining lab fee balance at cashier with OR #987654, returned sensor kit #2')."><?= e($record['student_note'] ?? '') ?></textarea>
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                    The instructor or signatory officer will read this note when reviewing your clearance.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="<?= url('student/dashboard') ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <?= (!empty($record['proof_file']) || !empty($record['student_note'])) ? 'Update Compliance Submission' : 'Submit Compliance' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

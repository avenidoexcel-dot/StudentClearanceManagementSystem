<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('student');
$user = current_user();

$statusId = (int)($_GET['status_id'] ?? 0);

// Validate that this requirement belongs to this student
$stmt = $pdo->prepare("
    SELECT cs.*, d.name AS department_name, d.code AS department_code, cr.student_id
    FROM clearance_signatory_status cs
    JOIN departments d ON cs.department_id = d.id
    JOIN clearance_requests cr ON cs.request_id = cr.id
    WHERE cs.id = ? AND cr.student_id = ?
");
$stmt->execute([$statusId, $user['id']]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('error', 'Clearance requirement not found or unauthorized.');
    header('Location: ' . BASE_URL . 'student/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['proof_file']['tmp_name'];
        $fileName = $_FILES['proof_file']['name'];
        $fileSize = $_FILES['proof_file']['size'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (!in_array($fileExt, $allowedExts)) {
            $error = 'Invalid file format. Only JPG, PNG, WEBP, and PDF files are allowed.';
        } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
            $error = 'File size exceeds maximum allowed limit of 5MB.';
        } else {
            $newFileName = 'proof_' . $record['request_id'] . '_' . $record['department_id'] . '_' . time() . '.' . $fileExt;
            $destination = __DIR__ . '/../uploads/proofs/' . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $updateStmt = $pdo->prepare("
                    UPDATE clearance_signatory_status
                    SET proof_file = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$newFileName, $statusId]);

                set_flash('success', 'Document/Proof uploaded successfully for ' . $record['department_name'] . '.');
                header('Location: ' . BASE_URL . 'student/dashboard.php');
                exit;
            } else {
                $error = 'Failed to save uploaded file. Check folder write permissions.';
            }
        }
    } else {
        $error = 'Please select a valid file to upload.';
    }
}

$page_title = "Upload Proof / Receipt";
$page_subtitle = "Submit proof of payment or compliance documents for departmental review";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i>
                <span>Upload Proof for <?= e($record['department_name']) ?></span>
            </div>
            <span class="badge badge-primary"><?= e($record['department_code']) ?></span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($record['proof_file'])): ?>
            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: var(--radius-md); padding: 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 13px;">
                    <i class="fa-solid fa-file-check" style="color: var(--success); margin-right: 6px;"></i>
                    Current file: <strong><?= e($record['proof_file']) ?></strong>
                </div>
                <a href="<?= BASE_URL ?>uploads/proofs/<?= e($record['proof_file']) ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-eye"></i> View Current
                </a>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label" for="proof_file">Select File (JPG, PNG, PDF - Max 5MB)</label>
                <input type="file" name="proof_file" id="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                    Example: Official Receipt from Cashier, Library clearance stamp, or validated payment slip.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="<?= BASE_URL ?>student/dashboard.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload & Submit
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

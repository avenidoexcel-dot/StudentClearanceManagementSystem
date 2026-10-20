<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('instructor');
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'instructor/dashboard.php');
    exit;
}

$action    = $_POST['action'] ?? '';
$subjectId = (int)($_POST['subject_id'] ?? 0);

if ($action === 'single_update') {
    $statusId = (int)($_POST['status_id'] ?? 0);
    $status   = in_array($_POST['status'] ?? '', ['cleared', 'deficient', 'pending']) ? $_POST['status'] : 'pending';
    $remarks  = trim($_POST['remarks'] ?? '');

    // Verify ownership: instructor must be the instructor of this record
    $verifyStmt = $pdo->prepare("
        SELECT id, request_id FROM clearance_subject_status 
        WHERE id = ? AND instructor_id = ?
    ");
    $verifyStmt->execute([$statusId, $user['id']]);
    $record = $verifyStmt->fetch();

    if ($record) {
        $updateStmt = $pdo->prepare("
            UPDATE clearance_subject_status 
            SET status = ?, remarks = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$status, $remarks, $statusId]);

        // Recalculate student overall clearance
        check_and_update_overall_clearance($pdo, $record['request_id']);

        set_flash('success', "Student clearance status updated to " . ucfirst($status) . ".");
    } else {
        set_flash('error', 'Unauthorized or record not found.');
    }

} elseif ($action === 'batch_approve') {
    // Approve all pending students for this subject
    $stmt = $pdo->prepare("
        SELECT id, request_id FROM clearance_subject_status
        WHERE subject_id = ? AND instructor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$subjectId, $user['id']]);
    $pendingList = $stmt->fetchAll();

    if (!empty($pendingList)) {
        $updateStmt = $pdo->prepare("
            UPDATE clearance_subject_status
            SET status = 'cleared', remarks = 'Cleared by instructor', updated_at = NOW()
            WHERE subject_id = ? AND instructor_id = ? AND status = 'pending'
        ");
        $updateStmt->execute([$subjectId, $user['id']]);

        // Recalculate overall status for each affected student
        foreach ($pendingList as $item) {
            check_and_update_overall_clearance($pdo, $item['request_id']);
        }

        set_flash('success', "Successfully cleared " . count($pendingList) . " pending students for this subject.");
    } else {
        set_flash('info', 'No pending students found to batch approve.');
    }
}

header('Location: ' . BASE_URL . 'instructor/dashboard.php?subject_id=' . $subjectId);
exit;

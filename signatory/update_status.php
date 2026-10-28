<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('signatory');
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('signatory/dashboard.php');
}

require_csrf('signatory/dashboard.php');

$action = $_POST['action'] ?? '';
$deptId = (int)($user['department_id'] ?? 0);

if ($action === 'single_update') {
    $statusId = (int)($_POST['status_id'] ?? 0);
    $status   = in_array($_POST['status'] ?? '', ['cleared', 'deficient', 'pending']) ? $_POST['status'] : 'pending';
    $remarks  = trim($_POST['remarks'] ?? '');

    // Verify record belongs to this signatory's department
    $stmt = $pdo->prepare("
        SELECT id, request_id FROM clearance_signatory_status
        WHERE id = ? AND department_id = ?
    ");
    $stmt->execute([$statusId, $deptId]);
    $record = $stmt->fetch();

    if ($record) {
        if ($status === 'cleared' && $remarks === '') {
            $remarks = 'Cleared by department officer';
        } elseif ($status === 'deficient' && $remarks === '') {
            $remarks = 'Lacking compliance requirements or fee payment';
        }

        $updateStmt = $pdo->prepare("
            UPDATE clearance_signatory_status
            SET status = ?, remarks = ?, signatory_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$status, $remarks, $user['id'], $statusId]);

        // Recalculate student overall clearance
        check_and_update_overall_clearance($pdo, $record['request_id']);

        set_flash('success', "Clearance requirement marked as " . ucfirst($status) . ".");
    } else {
        set_flash('error', 'Unauthorized or record not found.');
    }

} elseif ($action === 'batch_approve') {
    // Approve all pending students for this department
    $stmt = $pdo->prepare("
        SELECT id, request_id FROM clearance_signatory_status
        WHERE department_id = ? AND status = 'pending'
    ");
    $stmt->execute([$deptId]);
    $pendingList = $stmt->fetchAll();

    if (!empty($pendingList)) {
        $updateStmt = $pdo->prepare("
            UPDATE clearance_signatory_status
            SET status = 'cleared', remarks = 'Cleared by department officer', signatory_id = ?, updated_at = NOW()
            WHERE department_id = ? AND status = 'pending'
        ");
        $updateStmt->execute([$user['id'], $deptId]);

        foreach ($pendingList as $item) {
            check_and_update_overall_clearance($pdo, $item['request_id']);
        }

        set_flash('success', "Successfully cleared " . count($pendingList) . " pending student clearance requests.");
    } else {
        set_flash('info', 'No pending requests found to batch approve.');
    }
}

redirect('signatory/dashboard.php');

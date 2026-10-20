<?php
/**
 * Utility & Presentation Helpers
 */

/**
 * HTML Escaping helper
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Render status badge
 */
function render_status_badge(string $status): string {
    $status = strtolower($status);
    switch ($status) {
        case 'cleared':
        case 'approved':
            return '<span class="badge badge-success"><i class="fa fa-check-circle"></i> Cleared</span>';
        case 'deficient':
        case 'rejected':
            return '<span class="badge badge-danger"><i class="fa fa-exclamation-circle"></i> Deficient</span>';
        case 'in_progress':
            return '<span class="badge badge-primary"><i class="fa fa-spinner fa-spin"></i> In Progress</span>';
        case 'pending':
        default:
            return '<span class="badge badge-warning"><i class="fa fa-clock"></i> Pending</span>';
    }
}

/**
 * Format Date helper
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Recalculate student clearance status
 * If all subjects and all departments are 'cleared', overall_status becomes 'cleared'
 * If any is deficient, overall_status becomes 'in_progress' (or keeps track)
 */
function check_and_update_overall_clearance(PDO $pdo, int $request_id): string {
    // 1. Check departments clearance count
    $stmtDept = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dept,
            SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared_dept,
            SUM(CASE WHEN status = 'deficient' THEN 1 ELSE 0 END) as deficient_dept
        FROM clearance_signatory_status
        WHERE request_id = ?
    ");
    $stmtDept->execute([$request_id]);
    $deptStats = $stmtDept->fetch();

    // 2. Check subjects clearance count
    $stmtSubj = $pdo->prepare("
        SELECT 
            COUNT(*) as total_subj,
            SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared_subj,
            SUM(CASE WHEN status = 'deficient' THEN 1 ELSE 0 END) as deficient_subj
        FROM clearance_subject_status
        WHERE request_id = ?
    ");
    $stmtSubj->execute([$request_id]);
    $subjStats = $stmtSubj->fetch();

    $totalRequirements = (int)($deptStats['total_dept'] ?? 0) + (int)($subjStats['total_subj'] ?? 0);
    $clearedRequirements = (int)($deptStats['cleared_dept'] ?? 0) + (int)($subjStats['cleared_subj'] ?? 0);

    $newStatus = 'in_progress';
    $completedAt = null;

    if ($totalRequirements > 0 && $clearedRequirements === $totalRequirements) {
        $newStatus = 'cleared';
        $completedAt = date('Y-m-d H:i:s');
    }

    $updateStmt = $pdo->prepare("
        UPDATE clearance_requests
        SET overall_status = ?, completed_at = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $completedAt, $request_id]);

    return $newStatus;
}

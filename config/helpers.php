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
 * Generate standard absolute URL for the application
 */
function url(string $path = ''): string {
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = defined('BASE_URL') ? BASE_URL : '/';
    return $base . ltrim($path, '/');
}

/**
 * Generate standard static asset URL
 */
function asset(string $path = ''): string {
    return url($path);
}

/**
 * Perform standard HTTP redirection and terminate script execution
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/**
 * Get current request URL
 */
function current_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $protocol . $host . $uri;
}

/**
 * Check if the given route or page matches the current executing request
 */
function is_active_route(string $route): bool {
    $current = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $routeClean = trim($route, '/');
    if ($routeClean === '') {
        return basename($current) === 'index.php';
    }
    return (substr($current, -strlen($routeClean)) === $routeClean) || (basename($current) === basename($routeClean));
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

/**
 * Ensure clearance_requirements column exists on subjects table (Non-destructive self-migration)
 */
function ensure_clearance_requirements_column(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `subjects` LIKE 'clearance_requirements'");
        if (!$stmt || $stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `subjects` ADD COLUMN `clearance_requirements` TEXT NULL AFTER `name`");
        }
    } catch (\Throwable $e) {
        // Silently proceed if table does not exist yet or ALTER fails
    }
}

/**
 * Ensure compliance tracking columns exist on clearance_subject_status and clearance_signatory_status
 * (Non-destructive self-migration for student submissions & compliance checking)
 */
function ensure_compliance_columns(PDO $pdo): void {
    static $checkedCompliance = false;
    if ($checkedCompliance) return;
    $checkedCompliance = true;

    ensure_clearance_requirements_column($pdo);

    try {
        // 1. clearance_subject_status columns
        $subCols = $pdo->query("SHOW COLUMNS FROM `clearance_subject_status`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('proof_file', $subCols)) {
            $pdo->exec("ALTER TABLE `clearance_subject_status` ADD COLUMN `proof_file` VARCHAR(255) NULL AFTER `remarks`");
        }
        if (!in_array('student_note', $subCols)) {
            $pdo->exec("ALTER TABLE `clearance_subject_status` ADD COLUMN `student_note` TEXT NULL AFTER `proof_file`");
        }
        if (!in_array('submitted_at', $subCols)) {
            $pdo->exec("ALTER TABLE `clearance_subject_status` ADD COLUMN `submitted_at` DATETIME NULL AFTER `student_note`");
        }

        // 2. clearance_signatory_status columns
        $sigCols = $pdo->query("SHOW COLUMNS FROM `clearance_signatory_status`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('student_note', $sigCols)) {
            $pdo->exec("ALTER TABLE `clearance_signatory_status` ADD COLUMN `student_note` TEXT NULL AFTER `proof_file`");
        }
        if (!in_array('submitted_at', $sigCols)) {
            $pdo->exec("ALTER TABLE `clearance_signatory_status` ADD COLUMN `submitted_at` DATETIME NULL AFTER `student_note`");
        }
    } catch (\Throwable $e) {
        // Silently proceed if tables do not exist yet or ALTER fails
    }
}

// Automatically ensure schema on connection
if (isset($pdo) && $pdo instanceof PDO) {
    ensure_compliance_columns($pdo);
}


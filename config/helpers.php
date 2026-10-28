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
    ensure_rate_limiter_table($pdo);
}

/**
 * ==========================================================
 * CSRF (CROSS-SITE REQUEST FORGERY) SECURITY LAYER
 * ==========================================================
 */

/**
 * Generate or retrieve the current session CSRF token
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Render a standard hidden CSRF form input field
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '" autocomplete="off">';
    }
}

/**
 * Verify a given CSRF token against session
 */
if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
    }
}

/**
 * Validate submitted CSRF token using timing-attack safe comparison
 */
if (!function_exists('validate_csrf')) {
    function validate_csrf(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($submitted) || empty($sessionToken)) {
            return false;
        }
        return hash_equals($sessionToken, $submitted);
    }
}

/**
 * Enforce CSRF token validity on POST requests or abort safely
 */
if (!function_exists('require_csrf')) {
    function require_csrf(?string $redirectBack = null): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validate_csrf()) {
                set_flash('error', 'Security token expired or invalid request (CSRF check failed). Please try again.');
                if ($redirectBack) {
                    redirect($redirectBack);
                }
                if (!empty($_SERVER['HTTP_REFERER'])) {
                    header('Location: ' . $_SERVER['HTTP_REFERER']);
                    exit;
                }
                redirect('');
            }
        }
    }
}

/**
 * ==========================================================
 * RATE LIMITER (BRUTE-FORCE DEFENSE)
 * ==========================================================
 */

/**
 * Accurately resolve and sanitize client IP address
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($parts[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return substr($ip, 0, 45);
}

/**
 * Non-destructive auto-migration for login_attempts table
 */
function ensure_rate_limiter_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `username` VARCHAR(100) NOT NULL,
                `attempted_at` DATETIME NOT NULL,
                INDEX `idx_ip_time` (`ip_address`, `attempted_at`),
                INDEX `idx_user_time` (`username`, `attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (\Throwable $e) {
        // Silently proceed if database is unavailable or restricted
    }
}

/**
 * Check if the client IP or username has exceeded failed login limits
 * @return array ['allowed' => bool, 'wait_seconds' => int, 'remaining_attempts' => int]
 */
function check_login_rate_limit(?PDO $pdo, string $ip, string $username, int $maxAttempts = 5, int $lockoutWindow = 900): array {
    if ($pdo !== null) {
        ensure_rate_limiter_table($pdo);
        try {
            // Purge expired attempts outside the lockout window
            $purgeStmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `attempted_at` < (NOW() - INTERVAL ? SECOND)");
            $purgeStmt->execute([$lockoutWindow]);

            // Count recent failed attempts for this IP OR username
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total, MAX(`attempted_at`) AS last_attempt
                FROM `login_attempts`
                WHERE (`ip_address` = ? OR `username` = ?) AND `attempted_at` >= (NOW() - INTERVAL ? SECOND)
            ");
            $stmt->execute([$ip, $username, $lockoutWindow]);
            $row = $stmt->fetch();

            $totalAttempts = (int)($row['total'] ?? 0);
            if ($totalAttempts >= $maxAttempts) {
                $lastTime = !empty($row['last_attempt']) ? strtotime($row['last_attempt']) : time();
                $elapsed = time() - $lastTime;
                $waitSeconds = max(1, $lockoutWindow - $elapsed);
                return [
                    'allowed' => false,
                    'wait_seconds' => $waitSeconds,
                    'remaining_attempts' => 0
                ];
            }

            return [
                'allowed' => true,
                'wait_seconds' => 0,
                'remaining_attempts' => max(0, $maxAttempts - $totalAttempts)
            ];
        } catch (\Throwable $e) {
            // Fall through to session fallback
        }
    }

    // Session fallback if database is inaccessible or null
    if (session_status() === PHP_SESSION_NONE) session_start();
    $attempts = $_SESSION['rate_limit_attempts'] ?? 0;
    $lastAttempt = $_SESSION['rate_limit_last'] ?? 0;
    if (time() - $lastAttempt > $lockoutWindow) {
        $attempts = 0;
    }
    if ($attempts >= $maxAttempts) {
        $waitSeconds = max(1, $lockoutWindow - (time() - $lastAttempt));
        return ['allowed' => false, 'wait_seconds' => $waitSeconds, 'remaining_attempts' => 0];
    }
    return ['allowed' => true, 'wait_seconds' => 0, 'remaining_attempts' => max(0, $maxAttempts - $attempts)];
}

/**
 * Record a failed login attempt
 */
function record_failed_login(?PDO $pdo, string $ip, string $username): void {
    if ($pdo !== null) {
        ensure_rate_limiter_table($pdo);
        try {
            $stmt = $pdo->prepare("INSERT INTO `login_attempts` (`ip_address`, `username`, `attempted_at`) VALUES (?, ?, NOW())");
            $stmt->execute([$ip, $username]);
            return;
        } catch (\Throwable $e) {
            // Fall through to session backup
        }
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['rate_limit_attempts'] = ($_SESSION['rate_limit_attempts'] ?? 0) + 1;
    $_SESSION['rate_limit_last'] = time();
}

/**
 * Clear failed login attempts after successful authentication
 */
function clear_login_rate_limit(?PDO $pdo, string $ip, string $username): void {
    if ($pdo !== null) {
        ensure_rate_limiter_table($pdo);
        try {
            $stmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `ip_address` = ? OR `username` = ?");
            $stmt->execute([$ip, $username]);
        } catch (\Throwable $e) {
            // Ignore DB error
        }
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['rate_limit_attempts'], $_SESSION['rate_limit_last']);
}



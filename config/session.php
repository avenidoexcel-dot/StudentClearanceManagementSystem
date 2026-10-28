<?php
/**
 * Session Management & Access Control
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user details
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'],
        'username'      => $_SESSION['username'] ?? '',
        'role'          => $_SESSION['role'] ?? '',
        'name'          => $_SESSION['name'] ?? '',
        'student_id'    => $_SESSION['student_id'] ?? null,
        'department_id' => $_SESSION['department_id'] ?? null,
        'department_name'=> $_SESSION['department_name'] ?? null,
        'course'        => $_SESSION['course'] ?? null,
        'year_level'    => $_SESSION['year_level'] ?? null,
        'section'       => $_SESSION['section'] ?? null,
    ];
}

require_once __DIR__ . '/helpers.php';

/**
 * Enforce login
 */
function require_login(string $redirect = 'auth/login.php') {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        redirect($redirect);
    }
}

/**
 * Enforce specific role(s)
 */
function require_role($allowed_roles) {
    require_login();
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    $current_role = $_SESSION['role'] ?? '';
    if (!in_array($current_role, $allowed_roles, true)) {
        set_flash('error', 'Unauthorized access for your account role.');
        // Redirect to their respective dashboard
        if ($current_role === 'student') {
            redirect('student/dashboard.php');
        } elseif ($current_role === 'instructor') {
            redirect('instructor/dashboard.php');
        } elseif ($current_role === 'signatory') {
            redirect('signatory/dashboard.php');
        } elseif ($current_role === 'admin') {
            redirect('admin/dashboard.php');
        } else {
            redirect('auth/login.php');
        }
    }
}

/**
 * Set flash alert message
 */
function set_flash(string $type, string $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // success, error, warning, info
        'message' => $message,
    ];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF token helpers
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

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
    }
}

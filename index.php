<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'student';
    header("Location: " . BASE_URL . "{$role}/dashboard.php");
    exit;
}

// Redirect to login page for seamless entry
header("Location: " . BASE_URL . "auth/login.php");
exit;

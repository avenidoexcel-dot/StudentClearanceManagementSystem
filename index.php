<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'student';
    redirect("{$role}/dashboard.php");
}

// Redirect to login page for seamless entry
redirect("auth/login.php");

<?php
/**
 * Database Connection & Environment Config
 * Compatible with XAMPP (Local) and InfinityFree (Production)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==============================================================================
// 1. ENVIRONMENT CONFIGURATION
// ==============================================================================
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local  = in_array($http_host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($http_host, '192.168.') || str_starts_with($http_host, '10.');

if ($is_local) {
    // Localhost XAMPP Settings
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'clearance_db');
} else {
    // InfinityFree / Live Server Settings
    // Replace these values with your MySQL details from InfinityFree Control Panel:
    define('DB_HOST', 'sql100.infinityfree.com'); // Example: sqlXXX.infinityfree.com or sqlXXX.epizy.com
    define('DB_USER', 'epiz_xxxxxx');           // Example: epiz_12345678
    define('DB_PASS', 'your_vpanel_password');  // Your hosting password
    define('DB_NAME', 'epiz_xxxxxx_clearance'); // Example: epiz_12345678_clearance
}

// ==============================================================================
// 2. BASE URL RESOLUTION
// Automatically detects whether hosted in subfolder /clearance or root domain
// ==============================================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Find base folder up to /clearance or webroot
$parts = explode('/', trim($script_dir, '/'));
$base_path = '';
if (in_array('clearance', $parts)) {
    $index = array_search('clearance', $parts);
    $base_path = '/' . implode('/', array_slice($parts, 0, $index + 1)) . '/';
} else {
    $base_path = '/';
}
define('BASE_URL', rtrim($protocol . $http_host . $base_path, '/') . '/');

// ==============================================================================
// 3. PDO CONNECTION
// ==============================================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("<div style='font-family: sans-serif; padding: 25px; max-width: 650px; margin: 40px auto; border-radius: 8px; background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239;'>
        <h2 style='margin-top: 0;'>⚠️ Database Connection Error</h2>
        <p>Could not connect to MySQL database <strong>" . htmlspecialchars(DB_NAME) . "</strong> on host <strong>" . htmlspecialchars(DB_HOST) . "</strong>.</p>
        <p style='font-size: 14px; background: #ffe4e6; padding: 10px; border-radius: 5px; font-family: monospace;'>" . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Setup Tips:</strong></p>
        <ul style='font-size: 14px; line-height: 1.6;'>
            <li>If running locally on XAMPP: Make sure MySQL is started in the XAMPP Control Panel and database <code>clearance_db</code> is imported.</li>
            <li>If running on InfinityFree: Update the <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, and <code>DB_NAME</code> in <code>config/db.php</code>.</li>
        </ul>
    </div>");
}

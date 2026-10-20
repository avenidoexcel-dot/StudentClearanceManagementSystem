<?php
/**
 * Database Connection & Environment Config
 * Supports GitHub Secrets, .env, server config files, and automatic local XAMPP fallback
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check for server configuration files (Safe: ignored by Git)
$serverConfigFiles = [
    dirname(__DIR__) . '/config.production.php',
    dirname(__DIR__) . '/config.local.php',
    dirname(__DIR__) . '/db_credentials.php',
    __DIR__ . '/config.production.php',
    __DIR__ . '/db_credentials.php',
];

foreach ($serverConfigFiles as $cfg) {
    if (file_exists($cfg)) {
        require_once $cfg;
        break;
    }
}

// 2. Parse .env file if it exists (Safe: ignored by Git)
$envFiles = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/.env.local',
    dirname(__DIR__) . '/.env.production'
];

foreach ($envFiles as $envFile) {
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }
        break;
    }
}

// 3. Resolve Environment Credentials
// Hierarchy: Defined Constants -> Environment/Secrets ($_ENV/getenv) -> Localhost Defaults
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local  = in_array($http_host, ['localhost', '127.0.0.1', '::1']) 
             || str_starts_with($http_host, '192.168.') 
             || str_starts_with($http_host, '10.');

// Host
if (!defined('DB_HOST')) {
    $envHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? ''));
    define('DB_HOST', $envHost ?: ($is_local ? 'localhost' : 'localhost'));
}

// User
if (!defined('DB_USER')) {
    $envUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? ''));
    define('DB_USER', $envUser ?: ($is_local ? 'root' : 'root'));
}

// Password
if (!defined('DB_PASS')) {
    $envPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? '')))));
    define('DB_PASS', $envPass !== '' ? $envPass : ($is_local ? '' : ''));
}

// Database Name
if (!defined('DB_NAME')) {
    $envName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? ''));
    define('DB_NAME', $envName ?: ($is_local ? 'clearance_db' : 'clearance_db'));
}

// ==============================================================================
// 4. BASE URL RESOLUTION
// ==============================================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
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
// 5. PDO CONNECTION
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
        <p><strong>Database Credentials Loaded From:</strong></p>
        <ul style='font-size: 13px; line-height: 1.6;'>
            <li>Host: <code>" . htmlspecialchars(DB_HOST) . "</code></li>
            <li>User: <code>" . htmlspecialchars(DB_USER) . "</code></li>
            <li>Database: <code>" . htmlspecialchars(DB_NAME) . "</code></li>
        </ul>
        <p style='font-size: 13px; color: #64748b;'>You can provide your credentials in a <code>.env</code> file or <code>config.production.php</code> (ignored by Git for safety).</p>
    </div>");
}

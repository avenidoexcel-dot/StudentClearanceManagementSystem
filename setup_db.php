<?php
/**
 * One-Click Web Database Installer
 * Useful for local XAMPP and InfinityFree setup
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sqlFile = __DIR__ . '/database.sql';

if (!file_exists($sqlFile)) {
    die("Error: database.sql not found.");
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $sql = file_get_contents($sqlFile);

        // Remove CREATE DATABASE and USE statements for live shared hosts like InfinityFree
        // where database is pre-created by the hosting provider
        $isLive = !in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);
        if ($isLive) {
            $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
            $sql = preg_replace('/USE[^;]+;/i', '', $sql);
        }

        // Execute batch SQL
        $pdo->exec($sql);
        $message = "Database tables and sample seed data successfully installed!";
    } catch (PDOException $e) {
        $error = "Database setup error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClearanceHub Database Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #0f172a; padding: 40px 20px; }
        .box { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #4338ca; color: #fff; }
        .btn-primary:hover { background: #3730a3; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
        th, td { padding: 8px 12px; border: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>

<div class="box">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="width: 52px; height: 52px; background: #4338ca; color: #fff; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px;">
            <i class="fa-solid fa-database"></i>
        </div>
        <h2 style="margin: 0;">ClearanceHub Database Setup</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 6px;">One-click installer for XAMPP Localhost & InfinityFree Hosting</p>
    </div>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?= url('auth/login.php') ?>" class="btn btn-primary">Go to Login Page &rarr;</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$message): ?>
        <p style="font-size: 14px; line-height: 1.6; color: #334155;">
            Clicking the button below will automatically initialize all required tables (Users, Departments, Instructors, Clearance Requests, Subject Signatories) and populate demo test accounts:
        </p>

        <table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Username</th>
                    <th>Password</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>Admin</strong></td><td><code>admin</code></td><td><code>admin123</code></td></tr>
                <tr><td><strong>Signatory (Cashier)</strong></td><td><code>cashier</code></td><td><code>signatory123</code></td></tr>
                <tr><td><strong>Signatory (Library)</strong></td><td><code>lib_officer</code></td><td><code>signatory123</code></td></tr>
                <tr><td><strong>Faculty Instructor</strong></td><td><code>prof_ramirez</code></td><td><code>instructor123</code></td></tr>
                <tr><td><strong>Student</strong></td><td><code>student1</code></td><td><code>student123</code></td></tr>
            </tbody>
        </table>

        <form action="<?= url('setup_db.php') ?>" method="POST" style="margin-top: 24px; text-align: center;">
            <button type="submit" name="install" value="1" class="btn btn-primary" style="width: 100%; padding: 14px;">
                <i class="fa-solid fa-play"></i> Run Database Installer Now
            </button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>

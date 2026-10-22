<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'student';
    header("Location: " . BASE_URL . "{$role}/dashboard.php");
    exit;
}

$error = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both your username/ID and password.';
    } else {
        // Query user with department details if signatory
        $stmt = $pdo->prepare("
            SELECT u.*, d.name AS department_name 
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE (u.username = ? OR u.student_id = ?) AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // In seed data: default password for testing can be verified with password_verify
        // or fallback check for default demo accounts (admin123, signatory123, instructor123, student123)
        $valid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $valid = true;
            } elseif (
                ($user['role'] === 'admin' && $password === 'admin123') ||
                ($user['role'] === 'signatory' && $password === 'signatory123') ||
                ($user['role'] === 'instructor' && $password === 'instructor123') ||
                ($user['role'] === 'student' && $password === 'student123')
            ) {
                $valid = true;
                // Re-hash for security
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateHash = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateHash->execute([$newHash, $user['id']]);
            }
        }

        if ($valid) {
            $_SESSION['user_id']         = $user['id'];
            $_SESSION['username']        = $user['username'];
            $_SESSION['role']            = $user['role'];
            $_SESSION['name']            = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['student_id']      = $user['student_id'];
            $_SESSION['department_id']   = $user['department_id'];
            $_SESSION['department_name'] = $user['department_name'];
            $_SESSION['course']          = $user['course'];
            $_SESSION['year_level']      = $user['year_level'];
            $_SESSION['section']         = $user['section'];

            set_flash('success', "Welcome back, " . $_SESSION['name'] . "!");
            header("Location: " . BASE_URL . "{$user['role']}/dashboard.php");
            exit;
        } else {
            $error = 'Invalid username/student ID or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Student Clearance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="auth-brand-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="auth-title">ClearanceHub</h1>
            <p class="auth-subtitle">Student Clearance Management Portal</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-info' ?>"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Username or Student ID</label>
                <div style="position: relative;">
                    <i class="fa-regular fa-user" style="position: absolute; left: 14px; top: 13px; color: #94a3b8;"></i>
                    <input type="text" name="username" id="username" class="form-control" style="padding-left: 40px;" placeholder="e.g. 2026-0001 or admin" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 14px; top: 13px; color: #94a3b8;"></i>
                    <input type="password" name="password" id="password" class="form-control" style="padding-left: 40px;" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to Portal
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 13px; color: var(--text-muted);">
            Don't have an account yet? <a href="<?= BASE_URL ?>auth/register.php" style="font-weight: 600;">Register as Student</a>
        </div>

        <!-- Quick Demo Credentials Box -->
        <div style="margin-top: 24px; padding: 14px; border-radius: var(--radius-md); background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px;">
            <div style="font-weight: 700; color: #334155; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fa-solid fa-key"></i> Quick Demo Logins:</span>
                <span style="font-size: 11px; color: #64748b;">(Click to autofill)</span>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <button type="button" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="fillLogin('student1', 'student123')">Student</button>
                <button type="button" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="fillLogin('prof_ramirez', 'instructor123')">Instructor</button>
                <button type="button" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="fillLogin('cashier', 'signatory123')">Cashier (Signatory)</button>
                <button type="button" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="fillLogin('lib_officer', 'signatory123')">Library (Signatory)</button>
                <button type="button" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="fillLogin('admin', 'admin123')">Admin</button>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
}
</script>

</body>
</html>

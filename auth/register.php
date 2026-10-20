<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'student';
    header("Location: " . BASE_URL . "{$role}/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $section    = trim($_POST['section'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_pw) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check uniqueness of student_id and username
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR student_id = ?");
        $checkStmt->execute([$username, $student_id]);
        if ($checkStmt->fetch()) {
            $error = 'Username or Student ID is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (username, password, role, first_name, last_name, email, student_id, course, year_level, section, status)
                VALUES (?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $success = $insertStmt->execute([
                $username,
                $hashedPassword,
                $first_name,
                $last_name,
                $email,
                $student_id,
                $course,
                $year_level,
                $section
            ]);

            if ($success) {
                set_flash('success', 'Registration successful! You may now log in.');
                header('Location: ' . BASE_URL . 'auth/login.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | ClearanceHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body style="background: radial-gradient(circle at top right, #e0e7ff 0%, #f8fafc 60%);">

<div class="auth-wrapper" style="padding: 40px 20px;">
    <div class="auth-card" style="max-width: 600px;">
        <div class="auth-brand">
            <div class="auth-brand-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 class="auth-title">Student Registration</h1>
            <p class="auth-subtitle">Create your student clearance account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name *</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required value="<?= e($_POST['first_name'] ?? '') ?>" placeholder="e.g. Juan">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required value="<?= e($_POST['last_name'] ?? '') ?>" placeholder="e.g. Dela Cruz">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="student_id">Student ID / Number *</label>
                    <input type="text" name="student_id" id="student_id" class="form-control" required value="<?= e($_POST['student_id'] ?? '') ?>" placeholder="e.g. 2026-0123">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="student@school.edu.ph">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="course">Program / Course *</label>
                    <select name="course" id="course" class="form-control" required>
                        <option value="">Select Course</option>
                        <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>BS Information Technology</option>
                        <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>BS Information Systems</option>
                        <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>BS Computer Science</option>
                        <option value="BSED" <?= ($_POST['course'] ?? '') === 'BSED' ? 'selected' : '' ?>>Bachelor of Secondary Education</option>
                        <option value="BSBA" <?= ($_POST['course'] ?? '') === 'BSBA' ? 'selected' : '' ?>>BS Business Administration</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="year_level">Year Level *</label>
                    <select name="year_level" id="year_level" class="form-control" required>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="section">Section</label>
                    <input type="text" name="section" id="section" class="form-control" value="<?= e($_POST['section'] ?? 'A') ?>" placeholder="e.g. A">
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border); margin: 18px 0;">

            <div class="form-group">
                <label class="form-label" for="username">Username *</label>
                <input type="text" name="username" id="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>" placeholder="Choose a username">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="password">Password (min 6 chars) *</label>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                <i class="fa-solid fa-user-check"></i> Complete Registration
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center; font-size: 13px; color: var(--text-muted);">
            Already have an account? <a href="<?= BASE_URL ?>auth/login.php" style="font-weight: 600;">Sign in here</a>
        </div>
    </div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

// Handle status toggle / password reset
if (isset($_GET['reset_pw'])) {
    $uid = (int)$_GET['reset_pw'];
    $defaultPass = password_hash('password123', PASSWORD_DEFAULT);
    $resetStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $resetStmt->execute([$defaultPass, $uid]);
    set_flash('success', 'User password has been reset to "password123".');
    redirect('admin/users');
}

if (isset($_GET['toggle_status'])) {
    $uid = (int)$_GET['toggle_status'];
    $toggleStmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
    $toggleStmt->execute([$uid]);
    set_flash('success', 'User account status updated.');
    redirect('admin/users');
}

// Handle Add User
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/users');

    $role          = $_POST['role'] ?? 'student';
    $first_name    = trim($_POST['first_name'] ?? '');
    $last_name     = trim($_POST['last_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';
    $student_id    = trim($_POST['student_id'] ?? '') ?: null;
    $department_id = (int)($_POST['department_id'] ?? 0) ?: null;
    $course        = trim($_POST['course'] ?? '') ?: null;
    $year_level    = trim($_POST['year_level'] ?? '') ?: null;
    $section       = trim($_POST['section'] ?? '') ?: null;

    if (empty($first_name) || empty($last_name) || empty($username) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Username is already taken.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $insert = $pdo->prepare("
                INSERT INTO users (username, password, role, first_name, last_name, email, student_id, department_id, course, year_level, section, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $insert->execute([$username, $hashed, $role, $first_name, $last_name, $email, $student_id, $department_id, $course, $year_level, $section]);
            set_flash('success', "User '{$username}' ({$role}) created successfully.");
            redirect('admin/users');
        }
    }
}

// Filter users
$roleFilter = $_GET['role'] ?? '';
$sql = "
    SELECT u.*, d.name as department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
";
$params = [];
if (!empty($roleFilter)) {
    $sql .= " WHERE u.role = ?";
    $params[] = $roleFilter;
}
$sql .= " ORDER BY u.role ASC, u.last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = "User Accounts & Roles";
$page_subtitle = "Manage credentials, departments, and roles for students, faculty, and signatories";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Role Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="<?= url('admin/users') ?>" class="btn btn-sm <?= empty($roleFilter) ? 'btn-primary' : 'btn-outline' ?>">All Users</a>
    <a href="<?= url('admin/users?role=student') ?>" class="btn btn-sm <?= $roleFilter === 'student' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa-solid fa-user-graduate"></i> Students</a>
    <a href="<?= url('admin/users?role=instructor') ?>" class="btn btn-sm <?= $roleFilter === 'instructor' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa-solid fa-chalkboard-user"></i> Faculty Instructors</a>
    <a href="<?= url('admin/users?role=signatory') ?>" class="btn btn-sm <?= $roleFilter === 'signatory' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa-solid fa-building-user"></i> Department Signatories</a>
    <a href="<?= url('admin/users?role=admin') ?>" class="btn btn-sm <?= $roleFilter === 'admin' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa-solid fa-shield-halved"></i> Administrators</a>
</div>

<div class="grid-2-1">
    <!-- Users List Table -->
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 10px;">
            <div class="card-title">
                <i class="fa-solid fa-users" style="color: var(--primary);"></i>
                <span>Registered Users (<?= count($users) ?>)</span>
            </div>
            <input type="text" data-table-search="#usersTable" class="form-control btn-sm" style="width: 200px;" placeholder="Filter user...">
        </div>

        <div class="table-responsive">
            <table class="table" id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username / ID</th>
                        <th>Role</th>
                        <th>Department / Program</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['last_name'] . ', ' . $u['first_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($u['email']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= e($u['username']) ?></div>
                                <?php if ($u['student_id']): ?>
                                    <div style="font-size: 11px; color: #4338ca; font-weight: 600;">ID: <?= e($u['student_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : ($u['role'] === 'instructor' ? 'badge-info' : ($u['role'] === 'signatory' ? 'badge-warning' : 'badge-success')) ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td style="font-size: 13px;">
                                <?php if ($u['role'] === 'signatory'): ?>
                                    <i class="fa-solid fa-building" style="color: #64748b; font-size: 11px;"></i> <?= e($u['department_name'] ?: 'Unassigned') ?>
                                <?php elseif ($u['role'] === 'student'): ?>
                                    <?= e($u['course'] . ' ' . $u['year_level']) ?>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $u['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 4px;">
                                    <a href="<?= url('admin/users?reset_pw=' . $u['id']) ?>" class="btn btn-outline btn-sm" title="Reset password to password123" onclick="return confirm('Reset this password to password123?');">
                                        <i class="fa-solid fa-key"></i>
                                    </a>
                                    <a href="<?= url('admin/users?toggle_status=' . $u['id']) ?>" class="btn btn-outline btn-sm" title="Toggle active status">
                                        <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-user-plus" style="color: var(--success);"></i>
                <span>Add User Account</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= url('admin/users' . (!empty($roleFilter) ? '?role=' . urlencode($roleFilter) : '')) ?>" method="POST" id="userForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="userRole">Account Role *</label>
                <select name="role" id="userRole" class="form-control" required onchange="toggleRoleFields(this.value)">
                    <option value="student">Student</option>
                    <option value="instructor">Faculty Instructor</option>
                    <option value="signatory">Department Signatory</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name *</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required placeholder="First name">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required placeholder="Last name">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="user@school.edu.ph">
            </div>

            <!-- Signatory Specific Field -->
            <div class="form-group" id="deptField" style="display: none;">
                <label class="form-label" for="department_id">Office / Department *</label>
                <select name="department_id" id="department_id" class="form-control">
                    <option value="">Select Office</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Student Specific Fields -->
            <div id="studentFields">
                <div class="form-group">
                    <label class="form-label" for="student_id">Student ID Number</label>
                    <input type="text" name="student_id" id="student_id" class="form-control" placeholder="e.g. 2026-0045">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="course">Program / Course</label>
                        <input type="text" name="course" id="course" class="form-control" placeholder="e.g. BSIT">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="year_level">Year Level</label>
                        <input type="text" name="year_level" id="year_level" class="form-control" placeholder="e.g. 3rd Year">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="username">Username *</label>
                <input type="text" name="username" id="username" class="form-control" required placeholder="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password *</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-user-check"></i> Create Account
            </button>
        </form>
    </div>
</div>

<script>
function toggleRoleFields(role) {
    const studentFields = document.getElementById('studentFields');
    const deptField = document.getElementById('deptField');

    if (role === 'student') {
        studentFields.style.display = 'block';
        deptField.style.display = 'none';
    } else if (role === 'signatory') {
        studentFields.style.display = 'none';
        deptField.style.display = 'block';
    } else {
        studentFields.style.display = 'none';
        deptField.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

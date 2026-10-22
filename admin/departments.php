<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

$error = '';

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code           = strtoupper(trim($_POST['code'] ?? ''));
    $name           = trim($_POST['name'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $requires_proof = isset($_POST['requires_proof']) ? 1 : 0;

    if (empty($code) || empty($name)) {
        $error = 'Department code and name are required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM departments WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetch()) {
            $error = "Department code '{$code}' already exists.";
        } else {
            $insert = $pdo->prepare("
                INSERT INTO departments (code, name, description, requires_proof, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $insert->execute([$code, $name, $description, $requires_proof]);
            set_flash('success', "Department '{$name}' created successfully.");
            header('Location: ' . BASE_URL . 'admin/departments.php');
            exit;
        }
    }
}

// Fetch all departments with assigned signatory count
$departments = $pdo->query("
    SELECT d.*, 
           GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name, ' (', u.username, ')') SEPARATOR ', ') as signatories
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.id AND u.role = 'signatory'
    GROUP BY d.id
    ORDER BY d.id ASC
")->fetchAll();

$page_title = "Offices & Administrative Signatories";
$page_subtitle = "Configure university administrative offices and designate clearance signatories";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="grid-2-1">
    <!-- Departments Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-building-columns" style="color: var(--primary);"></i>
                <span>Configured Offices & Departments</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Office / Department Name</th>
                        <th>Assigned Signatory Users</th>
                        <th>Proof Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><strong style="color: var(--primary);"><?= e($d['code']) ?></strong></td>
                            <td>
                                <div><strong><?= e($d['name']) ?></strong></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?= e($d['description']) ?></div>
                            </td>
                            <td style="font-size: 13px;">
                                <?= $d['signatories'] ? e($d['signatories']) : '<span style="color: #94a3b8; font-style: italic;">No signatory account assigned</span>' ?>
                            </td>
                            <td>
                                <?= $d['requires_proof'] ? '<span class="badge badge-warning"><i class="fa-solid fa-file-arrow-up"></i> Required</span>' : '<span style="font-size: 12px; color: #94a3b8;">Optional</span>' ?>
                            </td>
                            <td>
                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Active</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Department Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-plus-circle" style="color: var(--success);"></i>
                <span>Add Department</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" for="code">Office Code *</label>
                <input type="text" name="code" id="code" class="form-control" required placeholder="e.g. CLINIC, ALUMNI, DEAN">
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Office Name *</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. University Health Services">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Clearance Scope / Description</label>
                <textarea name="description" id="description" class="form-control" placeholder="What requirements does this office check?"></textarea>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="requires_proof" value="1">
                    <span>Require students to upload receipt/proof</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-building-circle-check"></i> Save Department
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

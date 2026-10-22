<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

// Fetch active clearance periods
$periods = $pdo->query("SELECT * FROM clearance_periods ORDER BY id DESC")->fetchAll();
$activePeriod = null;
foreach ($periods as $p) {
    if ($p['is_active']) { $activePeriod = $p; break; }
}

// Fetch all instructors
$instructors = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'instructor' AND status = 'active' ORDER BY last_name ASC")->fetchAll();

// Ensure clearance_requirements column exists
ensure_clearance_requirements_column($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code                   = strtoupper(trim($_POST['code'] ?? ''));
    $name                   = trim($_POST['name'] ?? '');
    $instructor_id          = (int)($_POST['instructor_id'] ?? 0);
    $period_id              = (int)($_POST['period_id'] ?? 0);
    $clearance_requirements = trim($_POST['clearance_requirements'] ?? '');

    if (empty($code) || empty($name) || !$instructor_id || !$period_id) {
        $error = 'Please fill out all subject details and assign an instructor.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO subjects (code, name, clearance_requirements, instructor_id, period_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $name, $clearance_requirements !== '' ? $clearance_requirements : null, $instructor_id, $period_id]);
        set_flash('success', "Subject '{$code}' created and assigned to instructor.");
        header('Location: ' . BASE_URL . 'admin/subjects.php');
        exit;
    }
}

// Fetch existing subjects
$subjects = $pdo->query("
    SELECT s.*, cp.name as period_name,
           CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
           u.email as instructor_email,
           COUNT(css.id) as student_count
    FROM subjects s
    JOIN clearance_periods cp ON s.period_id = cp.id
    JOIN users u ON s.instructor_id = u.id
    LEFT JOIN clearance_subject_status css ON s.id = css.subject_id
    GROUP BY s.id
    ORDER BY cp.id DESC, s.code ASC
")->fetchAll();

$page_title = "Subjects & Subject Instructors";
$page_subtitle = "Assign faculty instructors to academic course subjects for student clearance sign-offs";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="grid-2-1">
    <!-- Subjects List -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-book-bookmark" style="color: var(--primary);"></i>
                <span>Enrolled Subjects & Assigned Faculty</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Assigned Instructor</th>
                        <th>Term</th>
                        <th>Students</th>
                        <th>Requirements</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No subjects added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $s): ?>
                            <tr>
                                <td><strong style="color: var(--primary);"><?= e($s['code']) ?></strong></td>
                                <td><?= e($s['name']) ?></td>
                                <td>
                                    <div><strong><?= e($s['instructor_name']) ?></strong></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($s['instructor_email']) ?></div>
                                </td>
                                <td><span style="font-size: 12px; color: var(--text-muted);"><?= e($s['period_name']) ?></span></td>
                                <td><span class="badge badge-primary"><?= (int)$s['student_count'] ?></span></td>
                                <td>
                                    <?php if (!empty($s['clearance_requirements'])): ?>
                                        <span class="badge badge-success" title="<?= e($s['clearance_requirements']) ?>">
                                            <i class="fa-solid fa-clipboard-check"></i> Configured
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--text-muted);">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Subject Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-plus-circle" style="color: var(--success);"></i>
                <span>Assign New Subject</span>
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
                <label class="form-label" for="period_id">Clearance Period *</label>
                <select name="period_id" id="period_id" class="form-control" required>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['is_active'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> <?= $p['is_active'] ? '(Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Subject Code *</label>
                <input type="text" name="code" id="code" class="form-control" required placeholder="e.g. IT314, CS101">
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Subject Title *</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. Mobile Application Development">
            </div>

            <div class="form-group">
                <label class="form-label" for="instructor_id">Assigned Faculty Instructor *</label>
                <select name="instructor_id" id="instructor_id" class="form-control" required>
                    <option value="">Select Faculty Instructor</option>
                    <?php foreach ($instructors as $inst): ?>
                        <option value="<?= $inst['id'] ?>">
                            <?= e($inst['first_name'] . ' ' . $inst['last_name']) ?> (<?= e($inst['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($instructors)): ?>
                    <small style="color: var(--danger); font-size: 11px;">No instructor accounts found. Create one under Users first.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="clearance_requirements">Clearance Requirements / Checklist (Optional)</label>
                <textarea name="clearance_requirements" id="clearance_requirements" class="form-control" rows="3" placeholder="e.g. 1. Submit final project code, 2. Return lab kits"></textarea>
                <small style="font-size: 11px; color: var(--text-muted);">Assigned instructors can also view and edit this from their portal.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-book-medical"></i> Save & Assign Subject
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

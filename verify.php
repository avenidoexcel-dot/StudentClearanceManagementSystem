<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

$code = trim($_GET['code'] ?? '');
$record = null;
$searched = false;

if (!empty($code)) {
    $searched = true;
    $stmt = $pdo->prepare("
        SELECT cr.*, cp.name as period_name, cp.academic_year, cp.semester,
               u.first_name, u.last_name, u.student_id, u.course, u.year_level, u.section
        FROM clearance_requests cr
        JOIN clearance_periods cp ON cr.period_id = cp.id
        JOIN users u ON cr.student_id = u.id
        WHERE cr.clearance_code = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $record = $stmt->fetch();
}

$page_title = "Verify Clearance Certificate";
$page_subtitle = "Verify the authenticity of digital student clearance certificates";
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div style="max-width: 700px; margin: 0 auto;">
    <!-- Verification Search Box -->
    <div class="card" style="text-align: center; padding: 36px 24px;">
        <div style="width: 56px; height: 56px; background: rgba(99, 102, 241, 0.15); color: #4338ca; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px; border: 1px solid rgba(99, 102, 241, 0.3); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h3 style="font-size: 22px; margin-bottom: 6px;">Certificate Verification</h3>
        <p style="color: var(--text-muted); font-size: 14px; max-width: 480px; margin: 0 auto 24px auto;">
            Enter the unique clearance tracking code printed on the certificate to check its official institutional validity.
        </p>

        <form action="" method="GET" class="verify-form" style="display: flex; gap: 10px; max-width: 460px; margin: 0 auto; flex-wrap: wrap;">
            <input type="text" name="code" class="form-control" required placeholder="e.g. CLR-2026-A1B2C3D4" value="<?= e($code) ?>" style="font-family: monospace; text-transform: uppercase; flex: 1; min-width: 200px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                <i class="fa-solid fa-magnifying-glass"></i> Verify
            </button>
        </form>
    </div>

    <!-- Verification Result -->
    <?php if ($searched): ?>
        <?php if ($record): ?>
            <div class="card" style="border: 2px solid <?= $record['overall_status'] === 'cleared' ? 'var(--success)' : 'var(--warning)' ?>;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--border);">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: <?= $record['overall_status'] === 'cleared' ? 'var(--success-bg)' : 'var(--warning-bg)' ?>; color: <?= $record['overall_status'] === 'cleared' ? 'var(--success-text)' : 'var(--warning-text)' ?>; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                        <i class="fa-solid <?= $record['overall_status'] === 'cleared' ? 'fa-circle-check' : 'fa-hourglass-half' ?>"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 17px; margin-bottom: 2px;">
                            <?= $record['overall_status'] === 'cleared' ? 'Official & Genuine Certificate' : 'Clearance In Progress' ?>
                        </h4>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Tracking Code: <code style="color: #4338ca; font-weight: 700;"><?= e($record['clearance_code']) ?></code>
                        </div>
                    </div>
                </div>

                <div class="grid-2" style="font-size: 14px;">
                    <div><span style="color: #64748b;">Student Name:</span> <strong><?= e($record['first_name'] . ' ' . $record['last_name']) ?></strong></div>
                    <div><span style="color: #64748b;">Student ID:</span> <strong><?= e($record['student_id']) ?></strong></div>
                    <div><span style="color: #64748b;">Program & Year:</span> <strong><?= e($record['course'] . ' ' . $record['year_level'] . ' - ' . $record['section']) ?></strong></div>
                    <div><span style="color: #64748b;">Clearance Period:</span> <strong><?= e($record['period_name']) ?></strong></div>
                    <div><span style="color: #64748b;">Academic Year:</span> <strong><?= e($record['academic_year']) ?> (<?= e($record['semester']) ?>)</strong></div>
                    <div><span style="color: #64748b;">Status:</span> <?= render_status_badge($record['overall_status']) ?></div>
                </div>

                <?php if ($record['overall_status'] === 'cleared'): ?>
                    <div style="margin-top: 20px; padding: 12px; background: #ecfdf5; border-radius: var(--radius-md); font-size: 13px; color: #065f46; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-stamp" style="font-size: 18px;"></i>
                        <span>This student has officially completed all clearance requirements and has been signed off by all instructors and university offices on <strong><?= format_datetime($record['completed_at']) ?></strong>.</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card" style="border: 2px solid var(--danger); text-align: center; padding: 32px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 36px; color: var(--danger); margin-bottom: 12px;"></i>
                <h4 style="color: var(--danger);">Certificate Code Not Found</h4>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 6px;">
                    No clearance record matches tracking code <strong><?= e($code) ?></strong>. Please verify the code and try again.
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

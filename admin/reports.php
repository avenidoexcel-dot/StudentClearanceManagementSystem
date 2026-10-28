<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

// Fetch periods for filter
$periods = $pdo->query("SELECT * FROM clearance_periods ORDER BY id DESC")->fetchAll();
$selectedPeriod = (int)($_GET['period_id'] ?? 0);
if (!$selectedPeriod) {
    foreach ($periods as $p) {
        if ($p['is_active']) { $selectedPeriod = $p['id']; break; }
    }
}

$statusFilter = $_GET['status'] ?? '';
$courseFilter = $_GET['course'] ?? '';
$search       = trim($_GET['search'] ?? '');

$sql = "
    SELECT cr.*, cp.name as period_name,
           u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.section, u.email,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id) +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id) as total_reqs,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id AND status = 'cleared') +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id AND status = 'cleared') as cleared_reqs,
           (SELECT COUNT(*) FROM clearance_signatory_status WHERE request_id = cr.id AND status = 'deficient') +
           (SELECT COUNT(*) FROM clearance_subject_status WHERE request_id = cr.id AND status = 'deficient') as deficient_reqs
    FROM clearance_requests cr
    JOIN clearance_periods cp ON cr.period_id = cp.id
    JOIN users u ON cr.student_id = u.id
    WHERE 1=1
";
$params = [];

if ($selectedPeriod) {
    $sql .= " AND cr.period_id = ?";
    $params[] = $selectedPeriod;
}
if (!empty($statusFilter)) {
    $sql .= " AND cr.overall_status = ?";
    $params[] = $statusFilter;
}
if (!empty($courseFilter)) {
    $sql .= " AND u.course = ?";
    $params[] = $courseFilter;
}
if (!empty($search)) {
    $sql .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR cr.clearance_code LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY cr.overall_status ASC, u.last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Preload subject and department compliance data for all records
$requestIds = array_column($records, 'id');
$subjectsByRequest = [];
$departmentsByRequest = [];

if (!empty($requestIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($requestIds), '?'));

    $subStmt = $pdo->prepare("
        SELECT css.request_id, css.status, css.remarks, css.proof_file, css.student_note, css.submitted_at,
               s.code as subject_code, s.name as subject_name, s.clearance_requirements,
               CONCAT(u.first_name, ' ', u.last_name) as instructor_name
        FROM clearance_subject_status css
        JOIN subjects s ON css.subject_id = s.id
        JOIN users u ON css.instructor_id = u.id
        WHERE css.request_id IN ($inPlaceholders)
        ORDER BY s.code ASC
    ");
    $subStmt->execute($requestIds);
    foreach ($subStmt->fetchAll() as $row) {
        $subjectsByRequest[$row['request_id']][] = $row;
    }

    $deptStmt = $pdo->prepare("
        SELECT cs.request_id, cs.status, cs.remarks, cs.proof_file, cs.student_note, cs.submitted_at,
               d.name as department_name, d.code as department_code, d.requires_proof,
               CONCAT(u.first_name, ' ', u.last_name) as signatory_name
        FROM clearance_signatory_status cs
        JOIN departments d ON cs.department_id = d.id
        LEFT JOIN users u ON cs.signatory_id = u.id
        WHERE cs.request_id IN ($inPlaceholders)
        ORDER BY d.id ASC
    ");
    $deptStmt->execute($requestIds);
    foreach ($deptStmt->fetchAll() as $row) {
        $departmentsByRequest[$row['request_id']][] = $row;
    }
}

// Summary counters
$total = count($records);
$cleared = 0;
$in_prog = 0;
foreach ($records as $r) {
    if ($r['overall_status'] === 'cleared') $cleared++;
    else $in_prog++;
}

$page_title = "Master Clearance Reports & Tracking";
$page_subtitle = "Comprehensive institutional clearance roster, audit trail, and verification";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Filter Bar -->
<div class="card no-print" style="padding: 18px 24px;">
    <form action="<?= url('admin/reports') ?>" method="GET" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label" style="font-size: 12px;">Clearance Period</label>
            <select name="period_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $selectedPeriod ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> <?= $p['is_active'] ? '(Active)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="width: 160px;">
            <label class="form-label" style="font-size: 12px;">Status</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="cleared" <?= $statusFilter === 'cleared' ? 'selected' : '' ?>>Cleared Only</option>
                <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress Only</option>
            </select>
        </div>

        <div style="width: 150px;">
            <label class="form-label" style="font-size: 12px;">Program / Course</label>
            <select name="course" class="form-control" onchange="this.form.submit()">
                <option value="">All Programs</option>
                <option value="BSIT" <?= $courseFilter === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                <option value="BSIS" <?= $courseFilter === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                <option value="BSCS" <?= $courseFilter === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label class="form-label" style="font-size: 12px;">Search Student</label>
            <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="ID, name, or code...">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="<?= url('admin/reports') ?>" class="btn btn-outline">Reset</a>
            <button type="button" onclick="window.print()" class="btn btn-outline">
                <i class="fa-solid fa-print"></i> Print Roster
            </button>
        </div>
    </form>
</div>

<!-- Report Summary Cards -->
<div class="stat-grid no-print">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-list-check"></i></div>
        <div>
            <div class="stat-val"><?= $total ?></div>
            <div class="stat-label">Filtered Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-stamp"></i></div>
        <div>
            <div class="stat-val"><?= $cleared ?></div>
            <div class="stat-label">Fully Cleared Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <div class="stat-val"><?= $in_prog ?></div>
            <div class="stat-label">Pending / In Progress</div>
        </div>
    </div>
</div>

<!-- Master Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-file-invoice" style="color: var(--primary);"></i>
            <span>Master Clearance Evaluation Roster</span>
        </div>
        <span style="font-size: 13px; color: var(--text-muted);"><?= $total ?> record(s) found</span>
    </div>

    <?php if (empty($records)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p>No student clearance records matched your filter criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking Code</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Program & Section</th>
                        <th>Cleared Requirements</th>
                        <th>Overall Status</th>
                        <th>Date Completed</th>
                        <th class="no-print" style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): 
                        $auditData = [
                            'student_id'     => $r['student_id'],
                            'student_name'   => $r['first_name'] . ' ' . $r['last_name'],
                            'program'        => $r['course'] . ' ' . $r['year_level'] . ' - ' . $r['section'],
                            'clearance_code' => $r['clearance_code'],
                            'overall_status' => $r['overall_status'],
                            'subjects'       => $subjectsByRequest[$r['id']] ?? [],
                            'departments'    => $departmentsByRequest[$r['id']] ?? []
                        ];
                    ?>
                        <tr>
                            <td><code style="font-weight: 700; color: #4338ca;"><?= e($r['clearance_code']) ?></code></td>
                            <td><strong><?= e($r['student_id']) ?></strong></td>
                            <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
                            <td><?= e($r['course'] . ' ' . $r['year_level'] . ' - ' . $r['section']) ?></td>
                            <td>
                                <div>
                                    <span style="font-weight: 700; color: #10b981;"><?= (int)$r['cleared_reqs'] ?></span>
                                    <span style="color: var(--text-muted);">/ <?= (int)$r['total_reqs'] ?></span>
                                    <?php if ($r['deficient_reqs'] > 0): ?>
                                        <span class="badge badge-danger" style="font-size: 10px; margin-left: 6px;">
                                            <?= $r['deficient_reqs'] ?> Deficient
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= render_status_badge($r['overall_status']) ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= format_datetime($r['completed_at']) ?>
                            </td>
                            <td class="no-print" style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <button type="button" class="btn btn-outline btn-sm audit-btn"
                                            data-modal-target="#complianceAuditModal"
                                            data-audit='<?= htmlspecialchars(json_encode($auditData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Inspect compliance submissions, proof files, and deficient items">
                                        <i class="fa-solid fa-clipboard-check" style="color: var(--primary);"></i> Audit
                                    </button>
                                    <a href="<?= url('student/certificate?request_id=' . $r['id']) ?>" target="_blank" class="btn btn-outline btn-sm" title="View or Print Certificate">
                                        <i class="fa-solid fa-file-lines"></i> Certificate
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Master Compliance Audit Modal for Administration & Staff -->
<div class="modal-overlay" id="complianceAuditModal">
    <div class="modal-container" style="max-width: 850px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <div>
                <h3 style="font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clipboard-list" style="color: var(--primary);"></i>
                    <span>Institutional Compliance Audit: <strong id="auditStudentName" style="color: var(--primary);"></strong></span>
                </h3>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    ID: <strong id="auditStudentId"></strong> &bull; Program: <span id="auditProgram"></span> &bull; Clearance Code: <code id="auditClearanceCode" style="font-weight: 700; color: #4338ca;"></code>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="auditOverallBadge"></span>
                <button type="button" data-modal-close class="btn btn-outline btn-sm" style="border: none;">&times;</button>
            </div>
        </div>

        <div class="modal-body" style="overflow-y: auto; display: flex; flex-direction: column; gap: 20px; padding: 20px;">
            <!-- 1. Subject Clearance Section -->
            <div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; font-weight: 700; color: #1e293b;">
                    <i class="fa-solid fa-book-open-reader" style="color: var(--primary);"></i>
                    <span>Academic Subjects Compliance & Requirements</span>
                </div>
                <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px;">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Subject</th>
                                <th>Instructor</th>
                                <th>Clearance Requirements</th>
                                <th>Passed Compliance</th>
                                <th>Status</th>
                                <th>Lacking / Deficiencies</th>
                            </tr>
                        </thead>
                        <tbody id="auditSubjectsTableBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Administrative Offices Section -->
            <div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; font-weight: 700; color: #1e293b;">
                    <i class="fa-solid fa-building-columns" style="color: var(--secondary);"></i>
                    <span>Administrative Offices Signatories Compliance</span>
                </div>
                <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px;">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Office</th>
                                <th>Signatory Officer</th>
                                <th>Passed Compliance / Proof</th>
                                <th>Status</th>
                                <th>Lacking / Deficiencies</th>
                            </tr>
                        </thead>
                        <tbody id="auditDeptsTableBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" data-modal-close class="btn btn-primary">
                <i class="fa-solid fa-check"></i> Done Reviewing
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const uploadBaseUrl = '<?= url("uploads/proofs/") ?>';

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderStatusBadgeHtml(status) {
        switch (status) {
            case 'cleared':
                return '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Cleared</span>';
            case 'deficient':
                return '<span class="badge badge-danger"><i class="fa-solid fa-circle-exclamation"></i> Deficient</span>';
            case 'pending':
                return '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>';
            default:
                return `<span class="badge badge-secondary">${escapeHtml(status)}</span>`;
        }
    }

    const auditButtons = document.querySelectorAll('.audit-btn');
    auditButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const data = JSON.parse(btn.dataset.audit);
                document.getElementById('auditStudentName').innerText = data.student_name || '';
                document.getElementById('auditStudentId').innerText = data.student_id || '';
                document.getElementById('auditProgram').innerText = data.program || '';
                document.getElementById('auditClearanceCode').innerText = data.clearance_code || '';
                document.getElementById('auditOverallBadge').innerHTML = renderStatusBadgeHtml(data.overall_status);

                // Populate subjects
                const subTbody = document.getElementById('auditSubjectsTableBody');
                subTbody.innerHTML = '';
                if (!data.subjects || data.subjects.length === 0) {
                    subTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 14px;">No subjects linked.</td></tr>';
                } else {
                    data.subjects.forEach(s => {
                        let complianceHtml = '<span style="color: #94a3b8; font-style: italic;">None submitted</span>';
                        if (s.proof_file || s.student_note) {
                            complianceHtml = '<div style="display: flex; flex-direction: column; gap: 3px;">';
                            if (s.proof_file) {
                                complianceHtml += `<a href="${uploadBaseUrl}${encodeURIComponent(s.proof_file)}" target="_blank" class="btn btn-outline btn-sm" style="padding: 2px 6px; font-size: 10px; width: fit-content;"><i class="fa-solid fa-paperclip"></i> View File</a>`;
                            }
                            if (s.student_note) {
                                complianceHtml += `<div style="font-size: 10px; color: #334155; background: #f1f5f9; padding: 2px 6px; border-radius: 3px;" title="${escapeHtml(s.student_note)}"><strong>Note:</strong> ${escapeHtml(s.student_note.substring(0, 35))}${s.student_note.length > 35 ? '...' : ''}</div>`;
                            }
                            complianceHtml += '</div>';
                        }

                        let deficiencyHtml = '—';
                        if (s.status === 'deficient') {
                            deficiencyHtml = `<div style="color: var(--danger); font-weight: 600; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 4px 6px;"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Lacking:</strong> ${escapeHtml(s.remarks || 'Action required')}</div>`;
                        } else if (s.remarks) {
                            deficiencyHtml = `<span style="color: #64748b;">${escapeHtml(s.remarks)}</span>`;
                        }

                        const reqsHtml = s.clearance_requirements 
                            ? `<div style="color: #334155; white-space: pre-line; max-height: 80px; overflow-y: auto;">${escapeHtml(s.clearance_requirements)}</div>`
                            : '<span style="color: #94a3b8; font-style: italic;">Standard standing</span>';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${escapeHtml(s.subject_code)}</strong><div style="font-size: 11px; color: #64748b;">${escapeHtml(s.subject_name)}</div></td>
                            <td>${escapeHtml(s.instructor_name)}</td>
                            <td>${reqsHtml}</td>
                            <td>${complianceHtml}</td>
                            <td>${renderStatusBadgeHtml(s.status)}</td>
                            <td>${deficiencyHtml}</td>
                        `;
                        subTbody.appendChild(tr);
                    });
                }

                // Populate departments
                const deptTbody = document.getElementById('auditDeptsTableBody');
                deptTbody.innerHTML = '';
                if (!data.departments || data.departments.length === 0) {
                    deptTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 14px;">No departments linked.</td></tr>';
                } else {
                    data.departments.forEach(d => {
                        let complianceHtml = '<span style="color: #94a3b8; font-style: italic;">None submitted</span>';
                        if (d.proof_file || d.student_note) {
                            complianceHtml = '<div style="display: flex; flex-direction: column; gap: 3px;">';
                            if (d.proof_file) {
                                complianceHtml += `<a href="${uploadBaseUrl}${encodeURIComponent(d.proof_file)}" target="_blank" class="btn btn-outline btn-sm" style="padding: 2px 6px; font-size: 10px; width: fit-content;"><i class="fa-solid fa-paperclip"></i> View Proof</a>`;
                            }
                            if (d.student_note) {
                                complianceHtml += `<div style="font-size: 10px; color: #334155; background: #f1f5f9; padding: 2px 6px; border-radius: 3px;" title="${escapeHtml(d.student_note)}"><strong>Note:</strong> ${escapeHtml(d.student_note.substring(0, 35))}${d.student_note.length > 35 ? '...' : ''}</div>`;
                            }
                            complianceHtml += '</div>';
                        } else if (d.requires_proof) {
                            complianceHtml = '<span style="font-size: 10px; color: #ea580c; font-weight: 600;"><i class="fa-solid fa-asterisk"></i> Proof Required</span>';
                        }

                        let deficiencyHtml = '—';
                        if (d.status === 'deficient') {
                            deficiencyHtml = `<div style="color: var(--danger); font-weight: 600; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 4px 6px;"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Lacking:</strong> ${escapeHtml(d.remarks || 'Action required')}</div>`;
                        } else if (d.remarks) {
                            deficiencyHtml = `<span style="color: #64748b;">${escapeHtml(d.remarks)}</span>`;
                        }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${escapeHtml(d.department_name)}</strong> <span style="font-size: 10px; color: #64748b;">(${escapeHtml(d.department_code)})</span></td>
                            <td>${escapeHtml(d.signatory_name || 'Designated Signatory')}</td>
                            <td>${complianceHtml}</td>
                            <td>${renderStatusBadgeHtml(d.status)}</td>
                            <td>${deficiencyHtml}</td>
                        `;
                        deptTbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error("Failed to parse audit data:", err);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

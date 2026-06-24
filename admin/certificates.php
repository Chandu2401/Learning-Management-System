<?php
/**
 * admin/certificates.php — All Issued Certificates
 * Placement: lms-project/admin/certificates.php
 * Action   : CREATE
 */

define('BASE_URL', '../');
$currentPage = 'certificates';

require_once '../config/db.php';
require_once 'includes/auth.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Flash messages ─────────────────────────────────────────────
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Revoke (DELETE) handler ────────────────────────────────────
if (isset($_GET['revoke']) && isset($_GET['csrf'])) {
    $revokeId = (int)$_GET['revoke'];
    if ($revokeId > 0 && hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        $del = $conn->prepare("DELETE FROM certificates WHERE id = ? LIMIT 1");
        $del->bind_param('i', $revokeId);
        if ($del->execute() && $del->affected_rows > 0) {
            $_SESSION['flash_success'] = 'Certificate revoked successfully.';
        } else {
            $_SESSION['flash_error'] = 'Could not revoke certificate. It may have already been removed.';
        }
        $del->close();
        // Regenerate CSRF after action
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['flash_error'] = 'Security check failed. Please try again.';
    }
    header('Location: certificates.php');
    exit();
}

// ── Check table ────────────────────────────────────────────────
$tblCheck  = $conn->query("SHOW TABLES LIKE 'certificates'");
$tblExists = $tblCheck && $tblCheck->num_rows > 0;

$certificates = [];
$totalCount   = 0;
$dbError      = '';

if ($tblExists) {
    // Search filter
    $search = trim($_GET['search'] ?? '');
    $where  = '';
    $params = [];
    $types  = '';

    if ($search !== '') {
        $like    = '%' . $search . '%';
        $where   = 'WHERE u.name LIKE ? OR c.title LIKE ? OR cert.certificate_no LIKE ?';
        $params  = [$like, $like, $like];
        $types   = 'sss';
    }

    // Count
    $cntSQL  = "SELECT COUNT(*) AS cnt FROM certificates cert
                INNER JOIN users u ON u.id = cert.student_id
                INNER JOIN courses c ON c.id = cert.course_id
                $where";
    $cntStmt = $conn->prepare($cntSQL);
    if ($cntStmt === false) {
        $dbError = 'Count query error: ' . $conn->error;
    } else {
        if ($types) $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $totalCount = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
        $cntStmt->close();

        // Fetch
        $dataSQL = "
            SELECT cert.id, cert.certificate_no, cert.issued_at,
                   u.id   AS student_id,
                   u.name AS student_name,
                   u.email AS student_email,
                   c.id   AS course_id,
                   c.title AS course_title,
                   c.level, c.category
            FROM certificates cert
            INNER JOIN users u   ON u.id = cert.student_id
            INNER JOIN courses c ON c.id = cert.course_id
            $where
            ORDER BY cert.issued_at DESC
            LIMIT 200
        ";
        $dataStmt = $conn->prepare($dataSQL);
        if ($dataStmt === false) {
            $dbError = 'Data query error: ' . $conn->error;
        } else {
            if ($types) $dataStmt->bind_param($types, ...$params);
            $dataStmt->execute();
            $certificates = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $dataStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .cert-no {
            font-family: 'Courier New', monospace;
            font-size: .75rem; font-weight: 700;
            background: var(--brand-light); color: var(--brand);
            border: 1px solid #bfdbfe; border-radius: 5px;
            padding: .2em .55em; white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">
    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Certificates</div>
                <div class="page-breadcrumb">
                    Admin Panel &rsaquo; Certificates
                    <span style="margin-left:.4rem;background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .55em;font-size:.75rem;font-weight:700;">
                        <?= $totalCount ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="lms-body">

        <?php if ($flashSuccess): ?>
        <div class="lms-alert lms-alert-success" data-autohide>
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="lms-alert lms-alert-danger" data-autohide>
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($flashError) ?>
        </div>
        <?php endif; ?>

        <?php if ($dbError): ?>
        <div class="lms-alert lms-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <strong>Database Error:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
        <?php endif; ?>

        <?php if (!$tblExists): ?>
        <div class="lms-alert lms-alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Certificates table not found. Run <strong>config/certificates.sql</strong> in phpMyAdmin first.
        </div>

        <?php else: ?>

        <!-- Stat -->
        <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(165px,1fr));margin-bottom:1.5rem;">
            <div class="stat-card">
                <div class="stat-icon-wrap si-purple"><i class="bi bi-award-fill"></i></div>
                <div>
                    <div class="stat-label">Total Issued</div>
                    <div class="stat-value"><?= $totalCount ?></div>
                </div>
            </div>
        </div>

        <!-- Card -->
        <div class="lms-card">
            <div class="lms-card-header" style="flex-wrap:wrap;gap:.75rem;">
                <h5 class="lms-card-title"><i class="bi bi-award-fill"></i> Issued Certificates</h5>
                <form method="GET" action="certificates.php" class="filter-bar ms-auto">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="search-input"
                               placeholder="Search student or course…"
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn-lms-primary" style="padding:.52rem 1rem;">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if (!empty($_GET['search'])): ?>
                    <a href="certificates.php" class="btn-lms-outline" style="padding:.52rem .9rem;">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Certificate No.</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Level</th>
                            <th>Category</th>
                            <th>Issued Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($certificates)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-award"></i></div>
                                    <h5><?= !empty($_GET['search']) ? 'No certificates match your search' : 'No certificates issued yet' ?></h5>
                                    <p>Certificates are auto-issued when a student completes all lessons in a course.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($certificates as $i => $cert): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i+1 ?></td>
                            <td>
                                <span class="cert-no"><?= htmlspecialchars($cert['certificate_no']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight:600;font-size:.875rem;">
                                    <?= htmlspecialchars($cert['student_name']) ?>
                                </div>
                                <div style="font-size:.72rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($cert['student_email']) ?>
                                </div>
                            </td>
                            <td style="font-size:.875rem;font-weight:500;">
                                <?= htmlspecialchars($cert['course_title']) ?>
                            </td>
                            <td>
                                <span class="lms-badge badge-<?= $cert['level'] ?>">
                                    <?= ucfirst($cert['level']) ?>
                                </span>
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted);">
                                <?= htmlspecialchars($cert['category'] ?? '—') ?>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= date('d M Y', strtotime($cert['issued_at'])) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;align-items:center;">
                                    <a href="../view-certificate.php?id=<?= $cert['id'] ?>"
                                       target="_blank"
                                       class="btn-icon view" title="View Certificate">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <a href="certificates.php?revoke=<?= $cert['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                       class="btn-icon delete"
                                       title="Revoke this certificate"
                                       data-confirm="Revoke certificate <?= htmlspecialchars(addslashes($cert['certificate_no'])) ?> issued to <?= htmlspecialchars(addslashes($cert['student_name'])) ?>? This cannot be undone.">
                                        <i class="bi bi-shield-x"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
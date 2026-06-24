<?php
/**
 * my-certificate.php — Student Certificate List
 * Placement: lms-project/my-certificate.php
 * Action   : CREATE
 */

$currentPage = 'certificates';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Check certificates table exists
$tblCheck  = $conn->query("SHOW TABLES LIKE 'certificates'");
$tblExists = $tblCheck && $tblCheck->num_rows > 0;

$certificates = [];

if ($tblExists) {
    $stmt = $conn->prepare("
        SELECT cert.id, cert.certificate_no, cert.issued_at,
               c.id AS course_id, c.title AS course_title,
               c.category, c.level, c.image
        FROM certificates cert
        INNER JOIN courses c ON c.id = cert.course_id
        WHERE cert.student_id = ?
        ORDER BY cert.issued_at DESC
    ");
    if ($stmt === false) {
        die('Query error: ' . $conn->error);
    }
    $stmt->bind_param('i', $authUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $certificates[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates — LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
    <style>
        .cert-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
            height: 100%;
            display: flex; flex-direction: column;
        }
        .cert-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .cert-card-header {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #3b82f6 100%);
            padding: 1.5rem 1.5rem 1.25rem;
            position: relative; overflow: hidden;
        }
        .cert-card-header::before {
            content: '';
            position: absolute; top: -30px; right: -30px;
            width: 120px; height: 120px;
            background: rgba(255,255,255,.08); border-radius: 50%;
        }
        .cert-card-header::after {
            content: '';
            position: absolute; bottom: -20px; right: 40px;
            width: 80px; height: 80px;
            background: rgba(255,255,255,.05); border-radius: 50%;
        }
        .cert-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,.15);
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #fff;
            margin-bottom: .75rem; position: relative; z-index: 1;
        }
        .cert-title {
            font-family: 'Sora', sans-serif;
            font-size: .95rem; font-weight: 700;
            color: #fff; line-height: 1.35;
            position: relative; z-index: 1;
        }
        .cert-card-body { padding: 1.1rem 1.25rem; flex: 1; }
        .cert-no {
            font-family: 'Courier New', monospace;
            font-size: .78rem; font-weight: 700;
            background: var(--brand-light);
            color: var(--brand);
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: .3rem .65rem;
            display: inline-block;
            margin-bottom: .75rem;
            letter-spacing: .04em;
        }
        .cert-meta {
            font-size: .78rem; color: var(--text-muted);
            display: flex; flex-direction: column; gap: .35rem;
        }
        .cert-meta span { display: flex; align-items: center; gap: .4rem; }
        .cert-card-footer {
            padding: .9rem 1.25rem;
            border-top: 1px solid var(--border);
            background: #fafafa;
            display: flex; gap: .6rem;
        }
        .btn-view-cert {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem 1rem; border-radius: var(--radius-sm);
            font-size: .82rem; font-weight: 600; text-decoration: none;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff; transition: opacity .2s;
            box-shadow: 0 2px 8px rgba(37,99,235,.25);
        }
        .btn-view-cert:hover { color: #fff; opacity: .9; }
        .btn-download-cert {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem 1rem; border-radius: var(--radius-sm);
            font-size: .82rem; font-weight: 600; text-decoration: none;
            background: transparent; color: var(--brand);
            border: 1.5px solid var(--brand);
            transition: background .2s;
        }
        .btn-download-cert:hover { background: var(--brand-light); color: var(--brand); }

        /* Empty state ribbon */
        .ribbon-banner {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #bbf7d0;
            border-radius: var(--radius);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .ribbon-icon { font-size: 3rem; color: #16a34a; margin-bottom: 1rem; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/student-sidebar.php'; ?>

<div class="st-main">
    <header class="st-topbar">
        <div class="st-topbar-left">
            <button class="st-sidebar-toggle" id="stToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="st-page-title">My Certificates</div>
                <div class="st-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &rsaquo; Certificates
                </div>
            </div>
        </div>
        <div class="st-topbar-right">
            <a href="my-courses.php" class="btn-outline-brand" style="font-size:.82rem;">
                <i class="bi bi-book-fill"></i> My Courses
            </a>
        </div>
    </header>

    <main class="st-body">

        <?php if ($flashSuccess): ?>
        <div class="st-alert st-alert-success">
            <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
        </div>
        <?php endif; ?>

        <?php if (!$tblExists): ?>
        <!-- Table missing guide -->
        <div class="st-alert st-alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Certificates table not found. Please run <strong>config/certificates.sql</strong> in phpMyAdmin.
        </div>

        <?php elseif (empty($certificates)): ?>
        <!-- No certificates yet -->
        <div class="ribbon-banner">
            <div class="ribbon-icon"><i class="bi bi-award"></i></div>
            <h5 style="font-family:'Sora',sans-serif;font-weight:700;color:var(--text);margin-bottom:.5rem;">
                No certificates yet
            </h5>
            <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:1.25rem;">
                Complete all lessons and pass the quiz to earn your certificate.
            </p>
            <a href="browse-courses.php" class="btn-brand">
                <i class="bi bi-compass-fill"></i> Browse Courses
            </a>
        </div>

        <?php else: ?>
        <!-- Certificate count -->
        <div class="st-stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:1.5rem;">
            <div class="st-stat-card">
                <div class="st-stat-icon si-purple"><i class="bi bi-award-fill"></i></div>
                <div>
                    <div class="st-stat-label">Certificates Earned</div>
                    <div class="st-stat-value"><?= count($certificates) ?></div>
                </div>
            </div>
        </div>

        <!-- Certificate grid -->
        <div class="row g-3">
            <?php foreach ($certificates as $cert): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="cert-card">
                    <div class="cert-card-header">
                        <div class="cert-icon"><i class="bi bi-award-fill"></i></div>
                        <div class="cert-title"><?= htmlspecialchars($cert['course_title']) ?></div>
                    </div>
                    <div class="cert-card-body">
                        <div class="cert-no">
                            <i class="bi bi-hash"></i> <?= htmlspecialchars($cert['certificate_no']) ?>
                        </div>
                        <div class="cert-meta">
                            <?php if ($cert['category']): ?>
                            <span>
                                <i class="bi bi-folder-fill"></i>
                                <?= htmlspecialchars($cert['category']) ?>
                            </span>
                            <?php endif; ?>
                            <span>
                                <i class="bi bi-bar-chart-fill"></i>
                                <?= ucfirst($cert['level']) ?>
                            </span>
                            <span>
                                <i class="bi bi-calendar-check-fill"></i>
                                Issued: <?= date('d M Y', strtotime($cert['issued_at'])) ?>
                            </span>
                        </div>
                    </div>
                    <div class="cert-card-footer">
                        <a href="view-certificate.php?id=<?= $cert['id'] ?>"
                           class="btn-view-cert">
                            <i class="bi bi-eye-fill"></i> View
                        </a>
                        <a href="download-certificate.php?id=<?= $cert['id'] ?>"
                           class="btn-download-cert">
                            <i class="bi bi-download"></i> Download PDF
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const stSidebar = document.getElementById('stSidebar');
const stToggle  = document.getElementById('stToggle');
const stOverlay = document.getElementById('stOverlay');
stToggle?.addEventListener('click', () => { stSidebar.classList.toggle('open'); stOverlay.classList.toggle('show'); });
stOverlay?.addEventListener('click', () => { stSidebar.classList.remove('open'); stOverlay.classList.remove('show'); });
</script>
</body>
</html>
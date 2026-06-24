<?php
/**
 * admin/reports.php — Analytics & Reports (Read-Only)
 * ─────────────────────────────────────────────────────
 * Placement: lms-project/admin/reports.php
 *
 * Read-only analytics dashboard. No edit/delete actions anywhere
 * on this page. Uses only existing tables — no schema changes.
 *
 * Sections:
 *  1. Top-level stat cards (Users, Students, Courses, Enrollments,
 *     Certificates Issued)
 *  2. Course Analytics — enrollments / completed / completion %
 *  3. Quiz Analytics — attempts / pass / fail / pass %
 *  4. Certificate Analytics — certificates issued per course
 *  5. Recent Activity — last 5 enrollments, quiz attempts,
 *     certificates
 */

define('BASE_URL', '../');
$currentPage = 'reports';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Table existence guards (same defensive pattern used project-wide) ──
$hasEnrollments  = $conn->query("SHOW TABLES LIKE 'enrollments'")->num_rows > 0;
$hasLessons      = $conn->query("SHOW TABLES LIKE 'lessons'")->num_rows > 0;
$hasQuizzes      = $conn->query("SHOW TABLES LIKE 'quizzes'")->num_rows > 0;
$hasAttempts     = $conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0;
$hasCertificates = $conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0;

// ── 1. Top-level stat cards ─────────────────────────────────────
$statTotalUsers    = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$statTotalStudents = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
$statTotalCourses  = (int)$conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];

$statTotalEnrollments = 0;
if ($hasEnrollments) {
    $statTotalEnrollments = (int)$conn->query(
        "SELECT COUNT(*) AS c FROM enrollments WHERE status != 'dropped'"
    )->fetch_assoc()['c'];
}

$statTotalCertificates = 0;
if ($hasCertificates) {
    $statTotalCertificates = (int)$conn->query(
        "SELECT COUNT(*) AS c FROM certificates"
    )->fetch_assoc()['c'];
}

// ── 2. Course Analytics ──────────────────────────────────────────
// Enrollments, completed count, and completion % per course.
$courseAnalytics = [];
if ($hasEnrollments) {
    $sql = "
        SELECT
            c.id,
            c.title,
            COUNT(e.id) AS total_enrollments,
            SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM courses c
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.status != 'dropped'
        GROUP BY c.id, c.title
        ORDER BY total_enrollments DESC, c.title ASC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $total     = (int)$row['total_enrollments'];
            $completed = (int)$row['completed_count'];
            $row['completion_pct'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
            $courseAnalytics[] = $row;
        }
    }
}

// ── 3. Quiz Analytics ────────────────────────────────────────────
// Attempts, pass count, fail count, and pass % per quiz.
$quizAnalytics = [];
if ($hasQuizzes && $hasAttempts) {
    $sql = "
        SELECT
            q.id,
            q.title,
            c.title AS course_title,
            COUNT(qa.id) AS total_attempts,
            SUM(CASE WHEN qa.result = 'pass' THEN 1 ELSE 0 END) AS pass_count,
            SUM(CASE WHEN qa.result = 'fail' THEN 1 ELSE 0 END) AS fail_count
        FROM quizzes q
        INNER JOIN courses c ON c.id = q.course_id
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id
        GROUP BY q.id, q.title, c.title
        ORDER BY total_attempts DESC, q.title ASC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $total = (int)$row['total_attempts'];
            $pass  = (int)$row['pass_count'];
            $row['pass_pct'] = $total > 0 ? round(($pass / $total) * 100, 1) : 0.0;
            $quizAnalytics[] = $row;
        }
    }
}

// ── 4. Certificate Analytics — issued per course ────────────────
$certAnalytics = [];
if ($hasCertificates) {
    $sql = "
        SELECT
            c.id,
            c.title,
            COUNT(cert.id) AS cert_count
        FROM courses c
        INNER JOIN certificates cert ON cert.course_id = c.id
        GROUP BY c.id, c.title
        ORDER BY cert_count DESC, c.title ASC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $certAnalytics[] = $row;
        }
    }
}

// ── 5. Recent Activity ───────────────────────────────────────────
$recentEnrollments = [];
if ($hasEnrollments) {
    $sql = "
        SELECT e.id, e.status, e.enrolled_at,
               u.name AS student_name,
               c.title AS course_title
        FROM enrollments e
        INNER JOIN users   u ON u.id = e.student_id
        INNER JOIN courses c ON c.id = e.course_id
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ";
    $res = $conn->query($sql);
    if ($res) {
        $recentEnrollments = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$recentAttempts = [];
if ($hasAttempts) {
    $sql = "
        SELECT qa.id, qa.percentage, qa.result, qa.attempted_at,
               u.name AS student_name,
               q.title AS quiz_title,
               c.title AS course_title
        FROM quiz_attempts qa
        INNER JOIN users   u ON u.id = qa.student_id
        INNER JOIN quizzes q ON q.id = qa.quiz_id
        INNER JOIN courses c ON c.id = q.course_id
        ORDER BY qa.attempted_at DESC
        LIMIT 5
    ";
    $res = $conn->query($sql);
    if ($res) {
        $recentAttempts = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$recentCertificates = [];
if ($hasCertificates) {
    $sql = "
        SELECT cert.id, cert.certificate_no, cert.issued_at,
               u.name AS student_name,
               c.title AS course_title
        FROM certificates cert
        INNER JOIN users   u ON u.id = cert.student_id
        INNER JOIN courses c ON c.id = cert.course_id
        ORDER BY cert.issued_at DESC
        LIMIT 5
    ";
    $res = $conn->query($sql);
    if ($res) {
        $recentCertificates = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .pct-bar { background:#e2e8f0;border-radius:20px;height:6px;overflow:hidden;min-width:60px; }
        .pct-fill-good { background:linear-gradient(90deg,#16a34a,#4ade80);height:100%;border-radius:20px; }
        .pct-fill-mid  { background:linear-gradient(90deg,#d97706,#fbbf24);height:100%;border-radius:20px; }
        .pct-fill-low  { background:linear-gradient(90deg,#dc2626,#f87171);height:100%;border-radius:20px; }
        .activity-list { list-style:none;margin:0;padding:0; }
        .activity-item {
            display:flex;align-items:flex-start;gap:.65rem;
            padding:.65rem 0;border-bottom:1px solid var(--border);
        }
        .activity-item:last-child { border-bottom:none; }
        .activity-icon {
            width:34px;height:34px;border-radius:50%;flex-shrink:0;
            display:flex;align-items:center;justify-content:center;font-size:.85rem;
        }
        .activity-body { flex:1;min-width:0; }
        .activity-title { font-size:.84rem;font-weight:600;color:var(--text);line-height:1.3; }
        .activity-sub { font-size:.74rem;color:var(--text-muted);margin-top:.1rem; }
        .activity-time { font-size:.7rem;color:var(--text-muted);white-space:nowrap;margin-left:.5rem; }
        .report-section-title {
            font-family:'Sora',sans-serif;font-weight:700;font-size:1rem;
            margin:1.75rem 0 .85rem;display:flex;align-items:center;gap:.5rem;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <!-- Top Bar -->
    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Reports</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo; Reports
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <span class="lms-badge" style="background:var(--brand-light);color:var(--brand);">
                <i class="bi bi-eye-fill"></i> Read-only
            </span>
        </div>
    </header>

    <main class="lms-body">

        <!-- ════════════════ 1. STAT CARDS ════════════════ -->
        <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));">

            <div class="stat-card">
                <div class="stat-icon-wrap si-blue"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $statTotalUsers ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="bi bi-person-fill"></i></div>
                <div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value"><?= $statTotalStudents ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-teal"><i class="bi bi-book-fill"></i></div>
                <div>
                    <div class="stat-label">Total Courses</div>
                    <div class="stat-value"><?= $statTotalCourses ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-amber"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="stat-label">Total Enrollments</div>
                    <div class="stat-value"><?= $statTotalEnrollments ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-purple"><i class="bi bi-award-fill"></i></div>
                <div>
                    <div class="stat-label">Certificates Issued</div>
                    <div class="stat-value"><?= $statTotalCertificates ?></div>
                </div>
            </div>

        </div>

        <!-- ════════════════ 2. COURSE ANALYTICS ════════════════ -->
        <div class="report-section-title">
            <i class="bi bi-bar-chart-line-fill"></i> Course Analytics
        </div>
        <div class="lms-card">
            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course Name</th>
                            <th width="140">Total Enrollments</th>
                            <th width="140">Completed Students</th>
                            <th width="160">Completion %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$hasEnrollments || empty($courseAnalytics)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-bar-chart"></i></div>
                                    <h5>No course data yet</h5>
                                    <p>Course analytics will appear once students start enrolling.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courseAnalytics as $i => $row):
                            $pct = (float)$row['completion_pct'];
                            $fillClass = $pct >= 70 ? 'pct-fill-good' : ($pct >= 40 ? 'pct-fill-mid' : 'pct-fill-low');
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= (int)$row['total_enrollments'] ?></td>
                            <td><?= (int)$row['completed_count'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="pct-bar" style="flex:1;">
                                        <div class="<?= $fillClass ?>" style="width:<?= min(100, $pct) ?>%"></div>
                                    </div>
                                    <span style="font-size:.78rem;font-weight:700;min-width:42px;text-align:right;">
                                        <?= number_format($pct, 1) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════ 3. QUIZ ANALYTICS ════════════════ -->
        <div class="report-section-title">
            <i class="bi bi-patch-question-fill"></i> Quiz Analytics
        </div>
        <div class="lms-card">
            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Quiz Name</th>
                            <th>Course</th>
                            <th width="100">Attempts</th>
                            <th width="100">Pass Count</th>
                            <th width="100">Fail Count</th>
                            <th width="160">Pass %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$hasQuizzes || !$hasAttempts || empty($quizAnalytics)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-patch-question"></i></div>
                                    <h5>No quiz data yet</h5>
                                    <p>Quiz analytics will appear once students start taking quizzes.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quizAnalytics as $i => $row):
                            $pct = (float)$row['pass_pct'];
                            $fillClass = $pct >= 70 ? 'pct-fill-good' : ($pct >= 40 ? 'pct-fill-mid' : 'pct-fill-low');
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($row['title']) ?></td>
                            <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars($row['course_title']) ?></td>
                            <td><?= (int)$row['total_attempts'] ?></td>
                            <td style="color:#15803d;font-weight:600;"><?= (int)$row['pass_count'] ?></td>
                            <td style="color:#b91c1c;font-weight:600;"><?= (int)$row['fail_count'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="pct-bar" style="flex:1;">
                                        <div class="<?= $fillClass ?>" style="width:<?= min(100, $pct) ?>%"></div>
                                    </div>
                                    <span style="font-size:.78rem;font-weight:700;min-width:42px;text-align:right;">
                                        <?= number_format($pct, 1) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════ 4. CERTIFICATE ANALYTICS ════════════════ -->
        <div class="report-section-title">
            <i class="bi bi-award-fill"></i> Certificate Analytics — Issued Per Course
        </div>
        <div class="lms-card">
            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course Name</th>
                            <th width="180">Certificates Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$hasCertificates || empty($certAnalytics)): ?>
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-award"></i></div>
                                    <h5>No certificates issued yet</h5>
                                    <p>Certificates are issued once a student completes all lessons and passes the course quiz.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($certAnalytics as $i => $row): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($row['title']) ?></td>
                            <td>
                                <span class="lms-badge" style="background:var(--brand-light);color:var(--brand);">
                                    <i class="bi bi-award-fill"></i> <?= (int)$row['cert_count'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════ 5. RECENT ACTIVITY ════════════════ -->
        <div class="report-section-title">
            <i class="bi bi-clock-history"></i> Recent Activity
        </div>
        <div class="row g-4">

            <!-- Recent Enrollments -->
            <div class="col-lg-4">
                <div class="lms-card" style="height:100%;">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-mortarboard-fill"></i> Recent Enrollments</h5>
                    </div>
                    <div class="lms-card-body">
                        <?php if (empty($recentEnrollments)): ?>
                            <p style="color:var(--text-muted);font-size:.82rem;margin:0;">No enrollments yet.</p>
                        <?php else: ?>
                            <ul class="activity-list">
                                <?php foreach ($recentEnrollments as $e): ?>
                                <li class="activity-item">
                                    <div class="activity-icon" style="background:#dbeafe;color:#1d4ed8;">
                                        <i class="bi bi-mortarboard-fill"></i>
                                    </div>
                                    <div class="activity-body">
                                        <div class="activity-title"><?= htmlspecialchars($e['student_name']) ?></div>
                                        <div class="activity-sub">
                                            <?= htmlspecialchars($e['course_title']) ?>
                                            &middot;
                                            <span class="lms-badge" style="font-size:.65rem;padding:.05em .5em;background:<?= $e['status']==='completed' ? '#dcfce7' : '#fef3c7' ?>;color:<?= $e['status']==='completed' ? '#15803d' : '#92400e' ?>;">
                                                <?= ucfirst($e['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="activity-time"><?= date('d M', strtotime($e['enrolled_at'])) ?></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Quiz Attempts -->
            <div class="col-lg-4">
                <div class="lms-card" style="height:100%;">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-patch-question-fill"></i> Recent Quiz Attempts</h5>
                    </div>
                    <div class="lms-card-body">
                        <?php if (empty($recentAttempts)): ?>
                            <p style="color:var(--text-muted);font-size:.82rem;margin:0;">No quiz attempts yet.</p>
                        <?php else: ?>
                            <ul class="activity-list">
                                <?php foreach ($recentAttempts as $a): ?>
                                <li class="activity-item">
                                    <div class="activity-icon" style="background:<?= $a['result']==='pass' ? '#dcfce7' : '#fee2e2' ?>;color:<?= $a['result']==='pass' ? '#15803d' : '#b91c1c' ?>;">
                                        <i class="bi bi-<?= $a['result']==='pass' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                    </div>
                                    <div class="activity-body">
                                        <div class="activity-title"><?= htmlspecialchars($a['student_name']) ?></div>
                                        <div class="activity-sub">
                                            <?= htmlspecialchars($a['quiz_title']) ?>
                                            &middot; <?= number_format((float)$a['percentage'], 1) ?>%
                                        </div>
                                    </div>
                                    <div class="activity-time"><?= date('d M', strtotime($a['attempted_at'])) ?></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Certificates -->
            <div class="col-lg-4">
                <div class="lms-card" style="height:100%;">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-award-fill"></i> Recent Certificates</h5>
                    </div>
                    <div class="lms-card-body">
                        <?php if (empty($recentCertificates)): ?>
                            <p style="color:var(--text-muted);font-size:.82rem;margin:0;">No certificates issued yet.</p>
                        <?php else: ?>
                            <ul class="activity-list">
                                <?php foreach ($recentCertificates as $c): ?>
                                <li class="activity-item">
                                    <div class="activity-icon" style="background:#f3e8ff;color:#7e22ce;">
                                        <i class="bi bi-award-fill"></i>
                                    </div>
                                    <div class="activity-body">
                                        <div class="activity-title"><?= htmlspecialchars($c['student_name']) ?></div>
                                        <div class="activity-sub"><?= htmlspecialchars($c['course_title']) ?></div>
                                    </div>
                                    <div class="activity-time"><?= date('d M', strtotime($c['issued_at'])) ?></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
/**
 * admin/index.php — Admin Dashboard
 * ─────────────────────────────────
 * Placement: lms-project/admin/index.php
 */

define('BASE_URL', '../');
$currentPage = 'dashboard';

require_once '../config/db.php';
require_once 'includes/auth.php';
require_once '../includes/site-info.php';

// ── Fetch stat counts ──────────────────────────────────────────
$stats = [];

// Total courses
$r = $conn->query("SELECT COUNT(*) AS cnt FROM courses");
$stats['total_courses'] = (int)$r->fetch_assoc()['cnt'];

// Active courses
$r = $conn->query("SELECT COUNT(*) AS cnt FROM courses WHERE status = 'active'");
$stats['active_courses'] = (int)$r->fetch_assoc()['cnt'];

// Total users
$r = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$stats['total_users'] = (int)$r->fetch_assoc()['cnt'];

// Total students
$r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'student'");
$stats['total_students'] = (int)$r->fetch_assoc()['cnt'];

// Total enrollments (with table guard)
$stats['total_enrollments'] = 0;
$ec = $conn->query("SHOW TABLES LIKE 'enrollments'");
if ($ec && $ec->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE status != 'dropped'");
    $stats['total_enrollments'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

// Total quizzes + attempts (with table guard)
$stats['total_quizzes']   = 0;
$stats['total_attempts']  = 0;
$qc = $conn->query("SHOW TABLES LIKE 'quizzes'");
if ($qc && $qc->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM quizzes");
    $stats['total_quizzes'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM quiz_attempts");
    $stats['total_attempts'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

// Total certificates issued (with table guard)
$stats['total_certificates'] = 0;
$cc = $conn->query("SHOW TABLES LIKE 'certificates'");
if ($cc && $cc->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM certificates");
    $stats['total_certificates'] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

// Recent 6 courses
$recentCourses = $conn->query("
    SELECT c.id, c.title, c.category, c.level, c.status, c.price, c.is_free,
           c.image, c.created_at, u.name AS author
    FROM courses c
    LEFT JOIN users u ON u.id = c.created_by
    ORDER BY c.created_at DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <!-- Top Bar -->
    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Dashboard</div>
                <div class="page-breadcrumb">Admin Panel &rsaquo; Overview</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="add-course.php" class="btn-lms-primary">
                <i class="bi bi-plus-lg"></i> <span>New Course</span>
            </a>
        </div>
    </header>

    <!-- Body -->
    <main class="lms-body">

        <!-- Welcome banner -->
        <div style="background:linear-gradient(135deg,#1e40af,#2563eb,#3b82f6);border-radius:14px;padding:1.75rem 2rem;color:#fff;margin-bottom:1.75rem;position:relative;overflow:hidden;animation:fadeUp .35s ease both;">
            <div style="position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:rgba(255,255,255,.06);border-radius:50%;"></div>
            <div style="position:absolute;bottom:-60px;right:120px;width:140px;height:140px;background:rgba(255,255,255,.04);border-radius:50%;"></div>
            <h2 style="font-family:'Sora',sans-serif;font-size:1.35rem;font-weight:700;margin-bottom:.3rem;">
                Good <?= (date('G') < 12) ? 'morning' : ((date('G') < 18) ? 'afternoon' : 'evening') ?>,
                <?= htmlspecialchars($_SESSION['user_name']) ?>!
            </h2>
            <p style="opacity:.8;font-size:.9rem;margin:0;">
                Here's what's happening on your platform today — <?= date('l, F j, Y') ?>.
            </p>
        </div>

        <!-- Institute Information Card -->
        <div class="lms-card" style="margin-bottom:1.75rem;">
            <div class="lms-card-body" style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                <img src="<?= htmlspecialchars(site_logo_url('../')) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>"
                     style="width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid var(--border);flex-shrink:0;">
                <div style="flex:1;min-width:200px;">
                    <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:1.05rem;color:var(--text);">
                        <?= htmlspecialchars(SITE_NAME) ?>
                    </div>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;">
                        <?= htmlspecialchars(SITE_TAGLINE) ?>
                    </div>
                </div>
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.82rem;color:var(--text-muted);">
                    <div><i class="bi bi-telephone-fill" style="color:var(--brand);margin-right:.4rem;"></i><?= htmlspecialchars(SITE_PHONE) ?></div>
                    <div><i class="bi bi-envelope-fill" style="color:var(--brand);margin-right:.4rem;"></i><?= htmlspecialchars(SITE_EMAIL) ?></div>
                </div>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon-wrap si-blue"><i class="bi bi-book-fill"></i></div>
                <div>
                    <div class="stat-label">Total Courses</div>
                    <div class="stat-value"><?= $stats['total_courses'] ?></div>
                    <div class="stat-sub"><?= $stats['active_courses'] ?> active</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $stats['total_users'] ?></div>
                    <div class="stat-sub"><?= $stats['total_students'] ?> students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-amber"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="stat-label">Enrollments</div>
                    <div class="stat-value"><?= $stats['total_enrollments'] ?></div>
                    <div class="stat-sub">active &amp; completed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-teal"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="stat-label">Quizzes</div>
                        <div class="stat-value"><?= $stats['total_quizzes'] ?></div>
                        <div class="stat-sub"><?= $stats['total_attempts'] ?> attempts</div>
                </div>
            </div>
        </div>

        <!-- Recent Courses Table -->
        <div class="lms-card">
            <div class="lms-card-header">
                <h5 class="lms-card-title"><i class="bi bi-clock-history"></i> Recent Courses</h5>
                <a href="course-list.php" class="btn-lms-outline">
                    <i class="bi bi-arrow-right"></i> View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentCourses->num_rows === 0): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                                    <h5>No courses yet</h5>
                                    <p>Start by adding your first course.</p>
                                    <a href="add-course.php" class="btn-lms-primary"><i class="bi bi-plus-lg"></i> Add Course</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($c = $recentCourses->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($c['image']) && file_exists('../' . $c['image'])): ?>
                                    <img src="../<?= htmlspecialchars($c['image']) ?>"
                                         alt="<?= htmlspecialchars($c['title']) ?>"
                                         class="course-thumb">
                                <?php else: ?>
                                    <div class="course-thumb-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($c['title']) ?></div>
                                <div style="font-size:.72rem;color:var(--text-muted);">by <?= htmlspecialchars($c['author'] ?? '—') ?></div>
                            </td>
                            <td><?= htmlspecialchars($c['category'] ?? '—') ?></td>
                            <td>
                                <span class="lms-badge badge-<?= $c['level'] ?>">
                                    <?= ucfirst($c['level']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($c['is_free']): ?>
                                    <span class="lms-badge badge-free">Free</span>
                                <?php else: ?>
                                    <span style="font-weight:600;">₹<?= number_format($c['price'], 2) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lms-badge badge-<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted);font-size:.8rem;">
                                <?= date('d M Y', strtotime($c['created_at'])) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:.35rem;">
                                    <a href="edit-course.php?id=<?= $c['id'] ?>" class="btn-icon edit" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="delete-course.php?id=<?= $c['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?? '' ?>"
                                       class="btn-icon delete"
                                       title="Delete"
                                       data-confirm="Delete '<?= htmlspecialchars(addslashes($c['title'])) ?>'? This cannot be undone.">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php render_site_footer('../'); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
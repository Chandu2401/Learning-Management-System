<?php
/**
 * dashboard.php — LMS Dashboard (Protected Page)
 * Only accessible to authenticated users.
 * Role-aware: shows different content for admin vs student.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── AUTH GUARD ───────────────────────────────────────────────
// If not logged in, redirect to login immediately.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?unauthorized=1");
    exit();
}

// ── ROLE GUARD — Admin belongs in the admin panel ─────────────
if (($_SESSION['user_role'] ?? '') === 'admin') {
    header("Location: admin/index.php");
    exit();
}

// ── Pull session data (student only beyond this point) ────────
$userName  = htmlspecialchars($_SESSION['user_name']);
$userEmail = htmlspecialchars($_SESSION['user_email']);
$userRole  = htmlspecialchars($_SESSION['user_role']);

// ── Optional: fetch fresh data from DB ───────────────────────
require_once 'config/db.php';
require_once 'includes/site-info.php';

$stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // User no longer exists in DB — force logout
    header("Location: logout.php");
    exit();
}

// Format join date
$joinDate = date('F j, Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= htmlspecialchars(SITE_NAME) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #2563eb;
            --brand-dark:    #1e40af;
            --brand-light:   #eff6ff;
            --sidebar-bg:    #0f172a;
            --sidebar-text:  #94a3b8;
            --sidebar-active:#2563eb;
            --surface:       #ffffff;
            --bg:            #f1f5f9;
            --text-main:     #1e293b;
            --text-muted:    #64748b;
            --border:        #e2e8f0;
            --radius:        12px;
            --shadow:        0 2px 12px rgba(0,0,0,.07);
            --sidebar-w:     260px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform .3s ease;
        }

        .sidebar-brand {
            padding: 1.5rem 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.07);
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .brand-icon i { color: #fff; font-size: 1.2rem; }
        .brand-name {
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
        }
        .brand-name span { color: #60a5fa; }

        /* Nav */
        .sidebar-nav { flex: 1; padding: 1.25rem .75rem; overflow-y: auto; }
        .nav-section-label {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #475569;
            padding: .5rem .75rem .4rem;
            margin-top: .5rem;
        }
        .nav-item a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem .9rem;
            border-radius: 8px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: .875rem;
            font-weight: 500;
            transition: background .15s, color .15s;
        }
        .nav-item a:hover { background: rgba(255,255,255,.06); color: #e2e8f0; }
        .nav-item a.active { background: rgba(37,99,235,.25); color: #93c5fd; }
        .nav-item a i { font-size: 1rem; width: 18px; text-align: center; }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 1rem .75rem;
            border-top: 1px solid rgba(255,255,255,.07);
        }
        .sidebar-user {
            display: flex; align-items: center; gap: .75rem;
            padding: .6rem .75rem;
            border-radius: 8px;
            background: rgba(255,255,255,.05);
        }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: .85rem;
            color: #fff;
            flex-shrink: 0;
        }
        .user-info .name { font-size: .8rem; font-weight: 600; color: #e2e8f0; line-height: 1.2; }
        .user-info .role { font-size: .72rem; color: #60a5fa; text-transform: capitalize; }
        .btn-logout-sm {
            margin-left: auto;
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color .2s;
            padding: 0;
        }
        .btn-logout-sm:hover { color: #f87171; }

        /* ── Main content ── */
        .main-content {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top bar */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0; z-index: 50;
        }
        .topbar-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .topbar-actions { display: flex; align-items: center; gap: 1rem; }
        .btn-topbar {
            background: none;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .4rem .85rem;
            font-size: .825rem;
            color: var(--text-muted);
            cursor: pointer;
            display: flex; align-items: center; gap: .4rem;
            transition: background .15s, color .15s;
        }
        .btn-topbar:hover { background: var(--bg); color: var(--text-main); }
        .btn-topbar.danger:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

        /* Page body */
        .page-body { padding: 2rem; flex: 1; }

        /* Institute welcome section */
        .institute-welcome {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow);
            animation: fadeUp .35s ease both;
        }
        .institute-welcome-logo {
            width: 52px; height: 52px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid var(--border);
        }
        .institute-welcome-label {
            font-size: .75rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .institute-welcome-name {
            font-family: 'Sora', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--brand-primary);
            letter-spacing: .01em;
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #3b82f6 100%);
            border-radius: var(--radius);
            padding: 2rem 2rem 1.75rem;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.75rem;
            animation: fadeUp .4s ease both;
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,.07);
            border-radius: 50%;
        }
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
        }
        .welcome-banner h2 {
            font-family: 'Sora', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: .3rem;
        }
        .welcome-banner p { font-size: .9rem; opacity: .8; }
        .role-badge {
            display: inline-block;
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 20px;
            padding: .2rem .75rem;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-top: .75rem;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Stat cards */
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.4rem 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1.1rem;
            animation: fadeUp .4s ease both;
        }
        .stat-card:nth-child(2) { animation-delay: .07s; }
        .stat-card:nth-child(3) { animation-delay: .14s; }
        .stat-card:nth-child(4) { animation-delay: .21s; }

        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .stat-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon.green  { background: #dcfce7; color: #16a34a; }
        .stat-icon.purple { background: #f3e8ff; color: #9333ea; }
        .stat-icon.amber  { background: #fef3c7; color: #d97706; }

        .stat-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
        .stat-value { font-family: 'Sora', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }

        /* Section cards */
        .section-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeUp .4s .2s ease both;
        }
        .section-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-header h5 {
            font-family: 'Sora', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }

        /* Profile info */
        .info-row {
            display: flex;
            align-items: center;
            padding: .85rem 1.5rem;
            border-bottom: 1px solid var(--border);
            font-size: .875rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 130px; font-weight: 600; color: var(--text-muted); flex-shrink: 0; }
        .info-value { color: var(--text-main); }

        /* Admin users table */
        .table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-weight: 600; }
        .table td { font-size: .875rem; vertical-align: middle; }
        .badge-role { font-size: .72rem; padding: .3em .65em; border-radius: 6px; font-weight: 600; }

        /* Mobile toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .4rem .6rem;
            color: var(--text-main);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar-toggle { display: flex; align-items: center; }
            .page-body { padding: 1.25rem; }
            .topbar { padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;"></div>
        <span class="brand-name" style="font-size:.92rem;line-height:1.25;"><?= htmlspecialchars(SITE_NAME) ?></span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a href="dashboard.php" class="active">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="browse-courses.php">
                    <i class="bi bi-compass-fill"></i> Browse Courses
                </a>
            </li>
            <li class="nav-item">
                <a href="my-courses.php">
                    <i class="bi bi-book-fill"></i> My Courses
                </a>
            </li>
            <li class="nav-item">
                <a href="my-quizzes.php">
                    <i class="bi bi-patch-question-fill"></i> My Quizzes
                </a>
            </li>
            <li class="nav-item">
                <a href="my-certificate.php">
                    <i class="bi bi-award-fill"></i> My Certificates
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Account</div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a href="logout.php" onclick="return confirm('Log out of your account?')">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="role"><?= htmlspecialchars($user['role']) ?></div>
            </div>
            <a href="logout.php" class="btn-logout-sm" title="Logout"
               onclick="return confirm('Log out?')">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ── Main content ── -->
<div class="main-content">

    <!-- Top bar -->
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
            <span class="topbar-title">Dashboard</span>
        </div>
        <div class="topbar-actions">
            <button class="btn-topbar"><i class="bi bi-bell"></i> Notifications</button>
            <a href="logout.php" class="btn-topbar danger"
               onclick="return confirm('Log out of your account?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </header>

    <!-- Page body -->
    <main class="page-body">

        <!-- Institute Welcome Section -->
        <div class="institute-welcome">
            <img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" class="institute-welcome-logo">
            <div>
                <div class="institute-welcome-label">Welcome to</div>
                <div class="institute-welcome-name"><?= htmlspecialchars(SITE_NAME) ?></div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Hello, <?= $userName ?>! 👋</h2>
            <p>Welcome to your Learning Dashboard. Here's what's happening today.</p>
            <span class="role-badge"><i class="bi bi-shield-check me-1"></i><?= $userRole ?></span>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <?php
                // ── Student stats from DB ──────────────────────
                $stEnrolled = 0; $stCompleted = 0; $stInProgress = 0; $stCertificates = 0;
                $eCheck = $conn->query("SHOW TABLES LIKE 'enrollments'");
                if ($eCheck && $eCheck->num_rows > 0) {
                    $r = $conn->prepare("SELECT COUNT(*) AS c FROM enrollments WHERE student_id=? AND status='active'");
                    $r->bind_param('i', $_SESSION['user_id']); $r->execute();
                    $stEnrolled = (int)$r->get_result()->fetch_assoc()['c']; $r->close();

                    $r = $conn->prepare("SELECT COUNT(*) AS c FROM enrollments WHERE student_id=? AND status='completed'");
                    $r->bind_param('i', $_SESSION['user_id']); $r->execute();
                    $stCompleted = (int)$r->get_result()->fetch_assoc()['c']; $r->close();

                    $lpCheck = $conn->query("SHOW TABLES LIKE 'lesson_progress'");
                    if ($lpCheck && $lpCheck->num_rows > 0) {
                        $r = $conn->prepare("SELECT COUNT(DISTINCT e.course_id) AS c FROM enrollments e INNER JOIN lesson_progress lp ON lp.student_id=e.student_id AND lp.course_id=e.course_id WHERE e.student_id=? AND e.status='active'");
                        $r->bind_param('i', $_SESSION['user_id']); $r->execute();
                        $stInProgress = (int)$r->get_result()->fetch_assoc()['c']; $r->close();
                    }
                }
                $certCheck = $conn->query("SHOW TABLES LIKE 'certificates'");
                if ($certCheck && $certCheck->num_rows > 0) {
                    $r = $conn->prepare("SELECT COUNT(*) AS c FROM certificates WHERE student_id=?");
                    $r->bind_param('i', $_SESSION['user_id']); $r->execute();
                    $stCertificates = (int)$r->get_result()->fetch_assoc()['c']; $r->close();
                }
            ?>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi bi-book-fill"></i></div>
                    <div><div class="stat-label">Enrolled</div><div class="stat-value"><?= $stEnrolled ?></div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <div><div class="stat-label">In Progress</div><div class="stat-value"><?= $stInProgress ?></div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-trophy-fill"></i></div>
                    <div><div class="stat-label">Completed</div><div class="stat-value"><?= $stCompleted ?></div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="bi bi-award-fill"></i></div>
                    <div><div class="stat-label">Certificates</div><div class="stat-value"><?= $stCertificates ?></div></div>
                </div>
            </div>
        </div>

        <!-- Bottom row: Profile + Quick Actions -->
        <div class="row g-3">

            <!-- Profile Card -->
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h5><i class="bi bi-person-fill me-2 text-primary"></i>My Profile</h5>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Role</span>
                        <span class="info-value">
                            <span class="badge badge-role bg-success">
                                <?= ucfirst($userRole) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?= $joinDate ?></span>
                    </div>
                </div>
            </div>

            <!-- Student: Quick Actions -->
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h5><i class="bi bi-lightning-fill me-2 text-warning"></i>Quick Actions</h5>
                    </div>
                    <div class="p-3 d-flex flex-column gap-2">
                        <a href="browse-courses.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-2">
                            <i class="bi bi-compass-fill"></i> Browse Courses
                        </a>
                        <a href="my-courses.php" class="btn btn-outline-success text-start d-flex align-items-center gap-2">
                            <i class="bi bi-book-fill"></i> My Courses
                        </a>
                        <a href="my-quizzes.php" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
                            <i class="bi bi-patch-question-fill"></i> My Quizzes
                        </a>
                        <a href="my-certificate.php" class="btn btn-outline-warning text-start d-flex align-items-center gap-2">
                            <i class="bi bi-award-fill"></i> My Certificates
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php render_site_footer(''); ?>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile sidebar toggle
    const sidebar       = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
</script>
</body>
</html>
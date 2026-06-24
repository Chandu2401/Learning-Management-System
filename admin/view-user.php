<?php
/**
 * admin/view-user.php — View User Details
 * ─────────────────────────────────────────
 * Placement: lms-project/admin/view-user.php
 *
 * Displays full profile details for a single user.
 * Shows enrollment count and quiz attempt stats if tables exist.
 */

define('BASE_URL', '../');
$currentPage = 'users';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Validate ID ────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit();
}

// ── Fetch user ─────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: users.php');
    exit();
}

// ── Optional stats (gracefully skip if tables missing) ────────
$enrollCount  = 0;
$quizCount    = 0;
$certCount    = 0;

$eCheck = $conn->query("SHOW TABLES LIKE 'enrollments'");
if ($eCheck && $eCheck->num_rows > 0) {
    $r = $conn->prepare("SELECT COUNT(*) AS c FROM enrollments WHERE student_id = ?");
    $r->bind_param('i', $id); $r->execute();
    $enrollCount = (int)$r->get_result()->fetch_assoc()['c']; $r->close();
}

$qCheck = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
if ($qCheck && $qCheck->num_rows > 0) {
    $r = $conn->prepare("SELECT COUNT(*) AS c FROM quiz_attempts WHERE student_id = ?");
    $r->bind_param('i', $id); $r->execute();
    $quizCount = (int)$r->get_result()->fetch_assoc()['c']; $r->close();
}

$cCheck = $conn->query("SHOW TABLES LIKE 'certificates'");
if ($cCheck && $cCheck->num_rows > 0) {
    $r = $conn->prepare("SELECT COUNT(*) AS c FROM certificates WHERE student_id = ?");
    $r->bind_param('i', $id); $r->execute();
    $certCount = (int)$r->get_result()->fetch_assoc()['c']; $r->close();
}

$isSelf    = ($id === (int)$_SESSION['user_id']);
$isAdmin   = ($user['role'] === 'admin');
$joinDate  = date('F j, Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">User Profile</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="users.php">Users</a> &rsaquo;
                    <?= htmlspecialchars($user['name']) ?>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="users.php" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> <span>Back to Users</span>
            </a>
            <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn-lms-primary">
                <i class="bi bi-pencil-fill"></i> <span>Edit User</span>
            </a>
            <?php if (!$isSelf): ?>
            <a href="delete-user.php?id=<?= $user['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?? '' ?>"
               class="btn-lms-primary"
               style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 3px 12px rgba(220,38,38,.3);"
               data-confirm="Permanently delete '<?= htmlspecialchars(addslashes($user['name'])) ?>'? This cannot be undone.">
                <i class="bi bi-trash-fill"></i> <span>Delete User</span>
            </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="lms-body">

        <div class="row g-4">

            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="lms-card" style="overflow:visible;">
                    <div class="lms-card-body" style="text-align:center;padding:2rem 1.5rem;">

                        <!-- Avatar -->
                        <div style="
                            width:80px;height:80px;border-radius:50%;margin:0 auto 1rem;
                            background:<?= $isAdmin ? 'linear-gradient(135deg,#dc2626,#991b1b)' : 'linear-gradient(135deg,#16a34a,#15803d)' ?>;
                            display:flex;align-items:center;justify-content:center;
                            font-family:'Sora',sans-serif;font-weight:700;font-size:2rem;color:#fff;
                            box-shadow:0 6px 20px <?= $isAdmin ? 'rgba(220,38,38,.3)' : 'rgba(22,163,74,.3)' ?>;
                        ">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>

                        <h5 style="font-family:'Sora',sans-serif;font-weight:700;margin-bottom:.25rem;">
                            <?= htmlspecialchars($user['name']) ?>
                            <?php if ($isSelf): ?>
                                <span style="font-size:.65rem;font-weight:700;color:var(--brand);background:var(--brand-light);border-radius:20px;padding:.1em .55em;display:inline-block;vertical-align:middle;">YOU</span>
                            <?php endif; ?>
                        </h5>

                        <div style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem;">
                            <?= htmlspecialchars($user['email']) ?>
                        </div>

                        <?php if ($isAdmin): ?>
                            <span class="lms-badge" style="background:#fee2e2;color:#991b1b;font-size:.75rem;">
                                <i class="bi bi-shield-fill"></i> Administrator
                            </span>
                        <?php else: ?>
                            <span class="lms-badge" style="background:#dcfce7;color:#15803d;font-size:.75rem;">
                                <i class="bi bi-person-fill"></i> Student
                            </span>
                        <?php endif; ?>

                    </div>

                    <div style="border-top:1px solid var(--border);">
                        <div style="padding:.85rem 1.5rem;display:flex;justify-content:space-between;border-bottom:1px solid var(--border);font-size:.875rem;">
                            <span style="color:var(--text-muted);font-weight:600;">User ID</span>
                            <span style="font-weight:700;">#<?= $user['id'] ?></span>
                        </div>
                        <div style="padding:.85rem 1.5rem;font-size:.875rem;">
                            <span style="color:var(--text-muted);font-weight:600;">Joined</span>
                            <span style="float:right;font-weight:600;"><?= $joinDate ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats + Info -->
            <div class="col-lg-8">

                <!-- Mini stat row -->
                <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.25rem;">
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-blue"><i class="bi bi-book-fill"></i></div>
                        <div>
                            <div class="stat-label">Enrollments</div>
                            <div class="stat-value"><?= $enrollCount ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-amber"><i class="bi bi-patch-question-fill"></i></div>
                        <div>
                            <div class="stat-label">Quiz Attempts</div>
                            <div class="stat-value"><?= $quizCount ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-purple"><i class="bi bi-award-fill"></i></div>
                        <div>
                            <div class="stat-label">Certificates</div>
                            <div class="stat-value"><?= $certCount ?></div>
                        </div>
                    </div>
                </div>

                <!-- Account Details -->
                <div class="lms-card">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-info-circle-fill"></i> Account Details</h5>
                    </div>
                    <div class="lms-card-body">
                        <table style="width:100%;border-collapse:collapse;">
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:.7rem 0;font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;width:35%;">Full Name</td>
                                <td style="padding:.7rem 0;font-size:.875rem;font-weight:600;"><?= htmlspecialchars($user['name']) ?></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:.7rem 0;font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Email</td>
                                <td style="padding:.7rem 0;font-size:.875rem;"><?= htmlspecialchars($user['email']) ?></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:.7rem 0;font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Role</td>
                                <td style="padding:.7rem 0;">
                                    <?php if ($isAdmin): ?>
                                        <span class="lms-badge" style="background:#fee2e2;color:#991b1b;">
                                            <i class="bi bi-shield-fill"></i> Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="lms-badge" style="background:#dcfce7;color:#15803d;">
                                            <i class="bi bi-person-fill"></i> Student
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:.7rem 0;font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Member Since</td>
                                <td style="padding:.7rem 0;font-size:.875rem;"><?= $joinDate ?></td>
                            </tr>
                        </table>
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
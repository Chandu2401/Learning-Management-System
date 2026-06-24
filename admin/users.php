<?php
/**
 * admin/users.php — All Users List
 * ─────────────────────────────────
 * Placement: lms-project/admin/users.php
 *
 * Features:
 *  - Stat cards: Total Users, Admins, Students
 *  - Searchable, paginated user table
 *  - Role filter (All / Admin / Student)
 *  - Role badges (red = admin, green = student)
 *  - View and Delete actions
 *  - Cannot delete the currently logged-in admin
 */

define('BASE_URL', '../');
$currentPage = 'users';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Flash messages ─────────────────────────────────────────────
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── CSRF token ─────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Filters ────────────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role']   ?? '');
if (!in_array($filterRole, ['admin', 'student'])) $filterRole = '';

// ── Pagination ─────────────────────────────────────────────────
$perPage      = 15;
$currentPage_ = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage_ - 1) * $perPage;

// ── Build WHERE clause ─────────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $like     = '%' . $search . '%';
    $where[]  = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if (!empty($filterRole)) {
    $where[]  = "u.role = ?";
    $params[] = $filterRole;
    $types   .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count for pagination ───────────────────────────────────────
$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users u $whereSQL");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = (int)$countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = (int)ceil($totalRows / $perPage);
$countStmt->close();

// ── Fetch users ────────────────────────────────────────────────
$dataParams = array_merge($params, [$perPage, $offset]);
$dataTypes  = $types . 'ii';

$dataStmt = $conn->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.created_at
     FROM users u
     $whereSQL
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?"
);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$users = $dataStmt->get_result();
$dataStmt->close();

// ── Stat counts ────────────────────────────────────────────────
$statTotal    = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$statAdmins   = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$statStudents = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];

// ── Pagination URL helper ──────────────────────────────────────
function pageUrl(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}

$loggedInId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
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
                <div class="page-title">All Users</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo; Users
                    <span style="margin-left:.5rem;background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .6em;font-size:.75rem;font-weight:700;">
                        <?= $totalRows ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="lms-body">

        <!-- Flash alerts -->
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

        <!-- Stat Cards -->
        <div class="stat-grid">

            <div class="stat-card">
                <div class="stat-icon-wrap si-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $statTotal ?></div>
                    <div class="stat-sub">All registered accounts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-rose">
                    <i class="bi bi-shield-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Admins</div>
                    <div class="stat-value"><?= $statAdmins ?></div>
                    <div class="stat-sub">Administrator accounts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-green">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Students</div>
                    <div class="stat-value"><?= $statStudents ?></div>
                    <div class="stat-sub">Enrolled learners</div>
                </div>
            </div>

        </div>

        <!-- Users Table Card -->
        <div class="lms-card">

            <!-- Filter Bar -->
            <div class="lms-card-header" style="flex-wrap:wrap;gap:.75rem;">
                <h5 class="lms-card-title">
                    <i class="bi bi-people-fill"></i> Users
                </h5>
                <form method="GET" action="users.php" class="filter-bar ms-auto">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="search-input"
                               placeholder="Search by name or email…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="role" class="lms-select" style="width:140px;">
                        <option value="">All Roles</option>
                        <option value="admin"   <?= $filterRole === 'admin'   ? 'selected' : '' ?>>Admin</option>
                        <option value="student" <?= $filterRole === 'student' ? 'selected' : '' ?>>Student</option>
                    </select>
                    <button type="submit" class="btn-lms-primary" style="padding:.52rem 1rem;">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if ($search || $filterRole): ?>
                    <a href="users.php" class="btn-lms-outline" style="padding:.52rem .9rem;" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th width="44">Avatar</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($users->num_rows === 0): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
                                    <h5><?= ($search || $filterRole) ? 'No users match your search' : 'No users found' ?></h5>
                                    <p><?= ($search || $filterRole) ? 'Try adjusting the search or role filter.' : 'Users will appear here once they register.' ?></p>
                                    <?php if ($search || $filterRole): ?>
                                        <a href="users.php" class="btn-lms-outline">Clear Filters</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = $offset + 1; while ($u = $users->fetch_assoc()): ?>
                        <?php $isSelf = ($u['id'] == $loggedInId); ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.8rem;"><?= $rowNum++ ?></td>

                            <!-- Avatar -->
                            <td>
                                <div style="
                                    width:36px;height:36px;border-radius:50%;
                                    background:<?= $u['role'] === 'admin' ? 'linear-gradient(135deg,#dc2626,#991b1b)' : 'linear-gradient(135deg,#16a34a,#15803d)' ?>;
                                    display:flex;align-items:center;justify-content:center;
                                    font-family:'Sora',sans-serif;font-weight:700;font-size:.8rem;color:#fff;
                                ">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                            </td>

                            <!-- Name -->
                            <td>
                                <div style="font-weight:600;">
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span style="font-size:.68rem;font-weight:700;color:var(--brand);background:var(--brand-light);border-radius:20px;padding:.1em .55em;margin-left:.3rem;">YOU</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Email -->
                            <td style="color:var(--text-muted);font-size:.85rem;">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>

                            <!-- Role Badge -->
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="lms-badge" style="background:#fee2e2;color:#991b1b;">
                                        <i class="bi bi-shield-fill"></i> Admin
                                    </span>
                                <?php else: ?>
                                    <span class="lms-badge" style="background:#dcfce7;color:#15803d;">
                                        <i class="bi bi-person-fill"></i> Student
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Joined Date -->
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= date('d M Y', strtotime($u['created_at'])) ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div style="display:flex;gap:.3rem;align-items:center;">

                                    <!-- View -->
                                    <a href="view-user.php?id=<?= $u['id'] ?>"
                                       class="btn-icon view"
                                       title="View user details">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>

                                    <!-- Edit -->
                                    <a href="edit-user.php?id=<?= $u['id'] ?>"
                                       class="btn-icon edit"
                                       title="Edit user">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>

                                    <!-- Delete -->
                                    <?php if ($isSelf): ?>
                                        <button class="btn-icon delete"
                                                title="You cannot delete your own account"
                                                disabled
                                                style="opacity:.35;cursor:not-allowed;">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="delete-user.php?id=<?= $u['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                           class="btn-icon delete"
                                           title="Delete user"
                                           data-confirm="Permanently delete '<?= htmlspecialchars(addslashes($u['name'])) ?>'? This cannot be undone.">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.8rem;color:var(--text-muted);">
                    Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> users
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" style="gap:.25rem;">
                        <li class="page-item <?= $currentPage_ <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= pageUrl($currentPage_ - 1) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($p = max(1, $currentPage_ - 2); $p <= min($totalPages, $currentPage_ + 2); $p++): ?>
                        <li class="page-item <?= $p === $currentPage_ ? 'active' : '' ?>">
                            <a class="page-link" href="<?= pageUrl($p) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage_ >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= pageUrl($currentPage_ + 1) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div><!-- /.lms-card -->

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
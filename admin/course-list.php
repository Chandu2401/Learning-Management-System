<?php
/**
 * admin/course-list.php — All Courses List
 * ─────────────────────────────────────────
 * Placement: lms-project/admin/course-list.php
 */

define('BASE_URL', '../');
$currentPage = 'courses';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Flash messages from redirects ──────────────────────────────
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Filters ────────────────────────────────────────────────────
$filterStatus   = trim($_GET['status']   ?? '');
$filterLevel    = trim($_GET['level']    ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$search         = trim($_GET['search']   ?? '');

// ── Pagination ─────────────────────────────────────────────────
$perPage     = 10;
$currentPage_ = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage_ - 1) * $perPage;

// ── Build WHERE clause dynamically ────────────────────────────
$where  = [];
$params = [];
$types  = '';

if (!empty($filterStatus) && in_array($filterStatus, ['active','inactive','draft'])) {
    $where[]  = "c.status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}
if (!empty($filterLevel) && in_array($filterLevel, ['beginner','intermediate','advanced'])) {
    $where[]  = "c.level = ?";
    $params[] = $filterLevel;
    $types   .= 's';
}
if (!empty($filterCategory)) {
    $where[]  = "c.category = ?";
    $params[] = $filterCategory;
    $types   .= 's';
}
if (!empty($search)) {
    $like     = '%' . $search . '%';
    $where[]  = "(c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count total for pagination ────────────────────────────────
$countSQL  = "SELECT COUNT(*) AS cnt FROM courses c $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($types && $params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = (int)$countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = (int)ceil($totalRows / $perPage);
$countStmt->close();

// ── Fetch courses ─────────────────────────────────────────────
$dataSQL  = "
    SELECT c.id, c.title, c.slug, c.category, c.level, c.status,
           c.price, c.is_free, c.image, c.duration, c.total_lessons,
           c.created_at, u.name AS author
    FROM courses c
    LEFT JOIN users u ON u.id = c.created_by
    $whereSQL
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$dataParams = array_merge($params, [$perPage, $offset]);
$dataTypes  = $types . 'ii';

$dataStmt = $conn->prepare($dataSQL);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$courses = $dataStmt->get_result();
$dataStmt->close();

// ── Unique categories for filter dropdown ─────────────────────
$cats = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($row = $cats->fetch_assoc()) $categories[] = $row['category'];

// Build pagination URL helper
function pageUrl(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course List — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php
$currentPage = 'courses';  // fix variable collision with pagination
include 'includes/sidebar.php';
?>

<div class="lms-main">

    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">All Courses</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo; Courses
                    <span style="margin-left:.5rem;background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .6em;font-size:.75rem;font-weight:700;">
                        <?= $totalRows ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="add-course.php" class="btn-lms-primary">
                <i class="bi bi-plus-lg"></i> <span>Add Course</span>
            </a>
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

        <!-- Courses Card -->
        <div class="lms-card">

            <!-- Filter Bar -->
            <div class="lms-card-header" style="flex-wrap:wrap;gap:.75rem;">
                <h5 class="lms-card-title"><i class="bi bi-book-fill"></i> Courses</h5>
                <form method="GET" action="course-list.php" class="filter-bar ms-auto">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="search-input"
                               placeholder="Search courses…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="status" class="lms-select" style="width:130px;">
                        <option value="">All Status</option>
                        <?php foreach (['active','inactive','draft'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="level" class="lms-select" style="width:140px;">
                        <option value="">All Levels</option>
                        <?php foreach (['beginner','intermediate','advanced'] as $l): ?>
                        <option value="<?= $l ?>" <?= $filterLevel === $l ? 'selected' : '' ?>>
                            <?= ucfirst($l) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($categories): ?>
                    <select name="category" class="lms-select" style="width:150px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $filterCategory === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <button type="submit" class="btn-lms-primary" style="padding:.52rem 1rem;">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if ($search || $filterStatus || $filterLevel || $filterCategory): ?>
                    <a href="course-list.php" class="btn-lms-outline" style="padding:.52rem .9rem;">
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
                            <th width="70">Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Lessons</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="110">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="courseTableBody">
                    <?php if ($courses->num_rows === 0): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                                    <h5><?= ($search || $filterStatus || $filterLevel) ? 'No courses match your filters' : 'No courses yet' ?></h5>
                                    <p><?= ($search || $filterStatus || $filterLevel) ? 'Try adjusting the filters above.' : 'Click "Add Course" to create your first one.' ?></p>
                                    <?php if (!$search && !$filterStatus && !$filterLevel): ?>
                                        <a href="add-course.php" class="btn-lms-primary">
                                            <i class="bi bi-plus-lg"></i> Add Course
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = $offset + 1; while ($c = $courses->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.8rem;"><?= $rowNum++ ?></td>
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
                                <div style="font-weight:600;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($c['title']) ?>
                                </div>
                                <div style="font-size:.72rem;color:var(--text-muted);">
                                    by <?= htmlspecialchars($c['author'] ?? '—') ?>
                                </div>
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted);">
                                <?= htmlspecialchars($c['category'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="lms-badge badge-<?= $c['level'] ?>">
                                    <?= ucfirst($c['level']) ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?= (int)$c['total_lessons'] ?>
                            </td>
                            <td>
                                <?php if ($c['is_free']): ?>
                                    <span class="lms-badge badge-free">Free</span>
                                <?php else: ?>
                                    <span style="font-weight:600;font-size:.85rem;">
                                        ₹<?= number_format($c['price'], 2) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lms-badge badge-<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= date('d M Y', strtotime($c['created_at'])) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;align-items:center;">
                                    <a href="edit-course.php?id=<?= $c['id'] ?>"
                                       class="btn-icon edit" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="delete-course.php?id=<?= $c['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?? '' ?>"
                                       class="btn-icon delete"
                                       title="Delete"
                                       data-confirm="Permanently delete '<?= htmlspecialchars(addslashes($c['title'])) ?>'? This action cannot be undone.">
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

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.8rem;color:var(--text-muted);">
                    Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> courses
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
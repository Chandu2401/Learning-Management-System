<?php
/**
 * browse-courses.php — Course Catalog for Students
 * Placement: lms-project/browse-courses.php
 *
 * Shows all active courses.
 * Students can enroll directly from this page.
 * Already-enrolled courses show a "Go to Course" button.
 */

$currentPage = 'browse';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle Enroll POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {

    $courseId = (int)($_POST['course_id'] ?? 0);

    // CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: browse-courses.php');
        exit();
    }

    if ($courseId <= 0) {
        $_SESSION['flash_error'] = 'Invalid course.';
        header('Location: browse-courses.php');
        exit();
    }

    // Verify course exists and is active
    $chk = $conn->prepare("SELECT id, title FROM courses WHERE id = ? AND status = 'active' LIMIT 1");
    $chk->bind_param('i', $courseId);
    $chk->execute();
    $courseRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$courseRow) {
        $_SESSION['flash_error'] = 'Course not found or no longer available.';
        header('Location: browse-courses.php');
        exit();
    }

    // Check existing enrollment (any status) — re-enroll support
    $dup = $conn->prepare(
        "SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1"
    );
    $dup->bind_param('ii', $authUserId, $courseId);
    $dup->execute();
    $existingEnrollment = $dup->get_result()->fetch_assoc();
    $dup->close();

    if ($existingEnrollment && in_array($existingEnrollment['status'], ['active', 'completed'], true)) {
        $_SESSION['flash_info'] = 'You are already enrolled in this course.';
        header('Location: my-courses.php');
        exit();
    }

    if ($existingEnrollment && $existingEnrollment['status'] === 'dropped') {
        // Reactivate the existing (dropped) enrollment instead of inserting a new row.
        // Preserves enrollment history (same row/id), avoids violating the
        // UNIQUE KEY (student_id, course_id) constraint.
        $reactivate = $conn->prepare(
            "UPDATE enrollments SET status = 'active', enrolled_at = NOW()
             WHERE id = ? AND student_id = ? AND course_id = ?"
        );
        $reactivate->bind_param('iii', $existingEnrollment['id'], $authUserId, $courseId);

        if ($reactivate->execute()) {
            $_SESSION['flash_success'] = 'Successfully re-enrolled in <strong>' .
                htmlspecialchars($courseRow['title']) . '</strong>!';
            header('Location: my-courses.php');
        } else {
            $_SESSION['flash_error'] = 'Enrollment failed. Please try again.';
            header('Location: browse-courses.php');
        }
        $reactivate->close();
        exit();
    }

    // No existing enrollment row at all — insert a fresh one
    $ins = $conn->prepare(
        "INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active')"
    );
    $ins->bind_param('ii', $authUserId, $courseId);

    if ($ins->execute()) {
        $_SESSION['flash_success'] = 'Successfully enrolled in <strong>' .
            htmlspecialchars($courseRow['title']) . '</strong>!';
        header('Location: my-courses.php');
    } else {
        // Unique key violation = already enrolled (race condition)
        if ($conn->errno === 1062) {
            $_SESSION['flash_info'] = 'You are already enrolled in this course.';
            header('Location: my-courses.php');
        } else {
            $_SESSION['flash_error'] = 'Enrollment failed. Please try again.';
            header('Location: browse-courses.php');
        }
    }
    $ins->close();
    exit();
}

// ── Filters ────────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$filterLevel = trim($_GET['level']    ?? '');
$filterCat   = trim($_GET['category'] ?? '');

// ── Build WHERE ────────────────────────────────────────────────
$where  = ["c.status = 'active'"];
$params = [];
$types  = '';

if ($search !== '') {
    $like    = '%' . $search . '%';
    $where[] = '(c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)';
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}
if ($filterLevel !== '' && in_array($filterLevel, ['beginner','intermediate','advanced'], true)) {
    $where[]  = 'c.level = ?';
    $params[] = $filterLevel;
    $types   .= 's';
}
if ($filterCat !== '') {
    $where[]  = 'c.category = ?';
    $params[] = $filterCat;
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Fetch courses ──────────────────────────────────────────────
$sql = "
    SELECT c.id, c.title, c.description,
           c.image, c.category, c.level, c.price, c.is_free,
           c.duration, c.total_lessons,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enroll_count
    FROM courses c
    $whereSQL
    ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Query error: ' . $conn->error);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result();
$stmt->close();

// ── Fetch student's enrolled course IDs ────────────────────────
$enrolledIds = [];
$eStmt = $conn->prepare(
    "SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active'"
);
$eStmt->bind_param('i', $authUserId);
$eStmt->execute();
$eRes = $eStmt->get_result();
while ($row = $eRes->fetch_assoc()) {
    $enrolledIds[] = (int)$row['course_id'];
}
$eStmt->close();

// ── Categories for filter dropdown ────────────────────────────
$catRes = $conn->query(
    "SELECT DISTINCT category FROM courses WHERE status='active' AND category IS NOT NULL AND category != '' ORDER BY category"
);
$categories = [];
while ($r = $catRes->fetch_assoc()) $categories[] = $r['category'];

$totalCourses = $courses->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses — LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/student-sidebar.php'; ?>

<div class="st-main">

    <!-- Topbar -->
    <header class="st-topbar">
        <div class="st-topbar-left">
            <button class="st-sidebar-toggle" id="stToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="st-page-title">Browse Courses</div>
                <div class="st-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &rsaquo; Browse
                    <span style="margin-left:.4rem;background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .55em;font-size:.72rem;font-weight:700;">
                        <?= $totalCourses ?>
                    </span>
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

        <!-- Flash alerts -->
        <?php if ($flashSuccess): ?>
        <div class="st-alert st-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= $flashSuccess ?></span>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="st-alert st-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?= htmlspecialchars($flashError) ?></span>
        </div>
        <?php endif; ?>

        <!-- Filter bar -->
        <div class="st-card" style="margin-bottom:1.5rem;">
            <div class="st-card-body" style="padding:1rem 1.25rem;">
                <form method="GET" action="browse-courses.php"
                      style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
                    <!-- Search -->
                    <div class="st-search-wrap" style="flex:1;min-width:200px;">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="st-search-input"
                               placeholder="Search courses…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <!-- Level -->
                    <select name="level"
                            style="padding:.52rem .75rem;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.875rem;outline:none;background:var(--surface);">
                        <option value="">All Levels</option>
                        <?php foreach (['beginner','intermediate','advanced'] as $lvl): ?>
                        <option value="<?= $lvl ?>" <?= $filterLevel === $lvl ? 'selected' : '' ?>>
                            <?= ucfirst($lvl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Category -->
                    <?php if ($categories): ?>
                    <select name="category"
                            style="padding:.52rem .75rem;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.875rem;outline:none;background:var(--surface);">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $filterCat === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <button type="submit" class="btn-brand" style="padding:.52rem 1.1rem;">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <?php if ($search || $filterLevel || $filterCat): ?>
                    <a href="browse-courses.php" class="btn-outline-brand" style="padding:.5rem .9rem;">
                        <i class="bi bi-x-lg"></i> Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Course grid -->
        <?php if ($totalCourses === 0): ?>
        <div class="st-empty">
            <div class="st-empty-icon"><i class="bi bi-journal-x"></i></div>
            <h5><?= ($search || $filterLevel || $filterCat) ? 'No courses match your filters' : 'No courses available yet' ?></h5>
            <p><?= ($search || $filterLevel || $filterCat) ? 'Try clearing your filters.' : 'Check back soon!' ?></p>
            <?php if ($search || $filterLevel || $filterCat): ?>
            <a href="browse-courses.php" class="btn-brand"><i class="bi bi-x-circle"></i> Clear Filters</a>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <div class="row g-3">
            <?php while ($c = $courses->fetch_assoc()):
                $isEnrolled = in_array((int)$c['id'], $enrolledIds, true);
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="course-card">
                    <!-- Image -->
                    <a href="course-details.php?id=<?= $c['id'] ?>" style="text-decoration:none;">
                        <?php if (!empty($c['image']) && file_exists($c['image'])): ?>
                        <img src="<?= htmlspecialchars($c['image']) ?>"
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             class="course-card-img" style="display:block;">
                        <?php else: ?>
                        <div class="course-card-img-placeholder">
                            <i class="bi bi-book-fill"></i>
                        </div>
                        <?php endif; ?>
                    </a>

                    <!-- Body -->
                    <div class="course-card-body">
                        <div class="course-card-cat">
                            <?= htmlspecialchars($c['category'] ?? 'General') ?>
                        </div>
                        <a href="course-details.php?id=<?= $c['id'] ?>" style="text-decoration:none;">
                            <div class="course-card-title"><?= htmlspecialchars($c['title']) ?></div>
                        </a>
                        <div class="course-card-desc">
                            <?= htmlspecialchars(substr($c['description'], 0, 120)) ?>
                        </div>
                        <div class="course-card-meta">
                            <span class="lms-badge badge-<?= $c['level'] ?>"><?= ucfirst($c['level']) ?></span>
                            <?php if ($c['duration']): ?>
                            <span><i class="bi bi-clock"></i> <?= htmlspecialchars($c['duration']) ?></span>
                            <?php endif; ?>
                            <?php if ($c['total_lessons']): ?>
                            <span><i class="bi bi-play-circle"></i> <?= (int)$c['total_lessons'] ?> lessons</span>
                            <?php endif; ?>
                            <span><i class="bi bi-people-fill"></i> <?= (int)$c['enroll_count'] ?> enrolled</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="course-card-footer">
                        <div class="course-price <?= $c['is_free'] ? 'free' : '' ?>">
                            <?= $c['is_free'] ? 'Free' : '₹' . number_format($c['price'], 2) ?>
                        </div>

                        <?php if ($isEnrolled): ?>
                        <!-- Already enrolled -->
                        <a href="course-details.php?id=<?= $c['id'] ?>"
                           class="btn-brand" style="font-size:.8rem;padding:.42rem .9rem;">
                            <i class="bi bi-play-fill"></i> Continue
                        </a>
                        <?php else: ?>
                        <!-- Enroll button -->
                        <form method="POST" action="browse-courses.php" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="course_id"  value="<?= $c['id'] ?>">
                            <input type="hidden" name="enroll"     value="1">
                            <button type="submit" class="btn-brand" style="font-size:.8rem;padding:.42rem .9rem;">
                                <i class="bi bi-plus-lg"></i> Enroll
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
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
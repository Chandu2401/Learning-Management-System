<?php
/**
 * my-courses.php — Student's Enrolled Courses with Progress
 * Placement: lms-project/my-courses.php
 * Action   : REPLACE
 *
 * Changes from original:
 *  - Fetches lesson_progress data per course card
 *  - Shows Bootstrap progress bar on each card
 *  - Shows X/Y lessons completed text
 *  - Auto-checks lesson_progress table existence safely
 */

$currentPage = 'my_courses';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
$flashInfo    = $_SESSION['flash_info']    ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_info']);

// ── Safe table check ───────────────────────────────────────────
$hasProgress = false;
$tc = $conn->query("SHOW TABLES LIKE 'lesson_progress'");
if ($tc && $tc->num_rows > 0) $hasProgress = true;

// ── Handle Drop Course POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_course'])) {
    $dropCourseId = (int)($_POST['course_id'] ?? 0);
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
        header('Location: my-courses.php'); exit();
    }
    if ($dropCourseId > 0) {
        $drop = $conn->prepare(
            "UPDATE enrollments SET status='dropped'
             WHERE student_id=? AND course_id=? AND status='active'"
        );
        $drop->bind_param('ii', $authUserId, $dropCourseId);
        $drop->execute(); $drop->close();
        $_SESSION['flash_success'] = 'Course dropped successfully.';
    }
    header('Location: my-courses.php'); exit();
}

// ── Filter ─────────────────────────────────────────────────────
$filter = trim($_GET['filter'] ?? 'active');
if (!in_array($filter, ['active','completed','dropped'], true)) $filter = 'active';

// ── Fetch enrolled courses ─────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.id, c.title, c.image,
           c.category, c.level, c.price, c.is_free,
           c.duration, c.total_lessons,
           e.id AS enrollment_id, e.status AS enroll_status, e.enrolled_at
    FROM enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ? AND e.status = ?
    ORDER BY e.enrolled_at DESC
");
if ($stmt === false) {
    die('SQL Error (my-courses fetch): ' . $conn->error);
}
$stmt->bind_param('is', $authUserId, $filter);
$stmt->execute();
$enrolled = $stmt->get_result();
$stmt->close();

// ── Enrollment counts per status ───────────────────────────────
$counts = ['active'=>0,'completed'=>0,'dropped'=>0];
$cntStmt = $conn->prepare(
    "SELECT status, COUNT(*) AS cnt FROM enrollments WHERE student_id=? GROUP BY status"
);
$cntStmt->bind_param('i', $authUserId);
$cntStmt->execute();
$cntRes = $cntStmt->get_result();
while ($row = $cntRes->fetch_assoc()) $counts[$row['status']] = (int)$row['cnt'];
$cntStmt->close();

// ── Progress per course (batch fetch) ─────────────────────────
// Build map: course_id → completed lesson count
$progressMap = [];
if ($hasProgress) {
    $pStmt = $conn->prepare(
        "SELECT course_id, COUNT(*) AS done
         FROM lesson_progress
         WHERE student_id = ?
         GROUP BY course_id"
    );
    $pStmt->bind_param('i', $authUserId);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    while ($pRow = $pRes->fetch_assoc()) {
        $progressMap[(int)$pRow['course_id']] = (int)$pRow['done'];
    }
    $pStmt->close();
}

$totalEnrolled = array_sum($counts);

// ── Certificate map (course_id → certificate id) ───────────────
$certMap = [];
$certTblChk = $conn->query("SHOW TABLES LIKE 'certificates'");
if ($certTblChk && $certTblChk->num_rows > 0) {
    $certStmt = $conn->prepare(
        "SELECT id, course_id FROM certificates WHERE student_id = ?"
    );
    $certStmt->bind_param('i', $authUserId);
    $certStmt->execute();
    $certRes = $certStmt->get_result();
    while ($certRow = $certRes->fetch_assoc()) {
        $certMap[(int)$certRow['course_id']] = (int)$certRow['id'];
    }
    $certStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses — LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
    <style>
        /* Filter tabs */
        .filter-tabs { display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem; }
        .filter-tab {
            display:inline-flex;align-items:center;gap:.45rem;
            padding:.5rem 1.1rem;border-radius:20px;font-size:.83rem;
            font-weight:600;text-decoration:none;border:1.5px solid var(--border);
            color:var(--text-muted);background:var(--surface);transition:all .2s;
        }
        .filter-tab:hover { border-color:var(--brand);color:var(--brand); }
        .filter-tab.active-tab { background:var(--brand);border-color:var(--brand);color:#fff; }
        .filter-tab .tab-count { border-radius:20px;padding:.05em .5em;font-size:.72rem;background:rgba(255,255,255,.25); }
        .filter-tab:not(.active-tab) .tab-count { background:var(--bg);color:var(--text-muted); }

        /* Enrolled course cards */
        .enrolled-card { background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;display:flex;flex-direction:column;height:100%;transition:transform .2s,box-shadow .2s; }
        .enrolled-card:hover { transform:translateY(-2px);box-shadow:var(--shadow-md); }
        .enrolled-card-img { width:100%;height:160px;object-fit:cover;background:var(--brand-light);display:block; }
        .enrolled-card-img-placeholder { width:100%;height:160px;background:linear-gradient(135deg,#dbeafe,#eff6ff);display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:var(--brand); }
        .enrolled-card-body { padding:1rem 1.15rem;flex:1; }
        .enrolled-card-cat { font-size:.67rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand);margin-bottom:.3rem; }
        .enrolled-card-title { font-family:'Sora',sans-serif;font-weight:700;font-size:.9rem;color:var(--text);margin-bottom:.5rem;line-height:1.35; }
        .enrolled-card-meta { font-size:.72rem;color:var(--text-muted);display:flex;gap:.55rem;flex-wrap:wrap;margin-bottom:.6rem; }

        /* Progress bar */
        .course-progress { margin-top:.6rem; }
        .progress-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem; }
        .progress-text { font-size:.72rem;color:var(--text-muted); }
        .progress-pct  { font-size:.72rem;font-weight:700;color:var(--brand); }
        .progress-track { background:#e2e8f0;border-radius:20px;height:7px;overflow:hidden; }
        .progress-fill  { height:100%;border-radius:20px;transition:width .6s ease; }
        .pf-active    { background:linear-gradient(90deg,var(--brand),#60a5fa); }
        .pf-completed { background:linear-gradient(90deg,#16a34a,#4ade80); }
        .pf-dropped   { background:#94a3b8; }

        .enrolled-card-footer { padding:.85rem 1.15rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:.5rem;background:#fafafa; }
        .drop-btn { background:none;border:1px solid #fecaca;border-radius:7px;color:var(--danger);font-size:.78rem;padding:.35rem .7rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;transition:background .2s; }
        .drop-btn:hover { background:#fef2f2; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/student-sidebar.php'; ?>

<div class="st-main">
    <header class="st-topbar">
        <div class="st-topbar-left">
            <button class="st-sidebar-toggle" id="stToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="st-page-title">My Courses</div>
                <div class="st-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &rsaquo; My Courses
                </div>
            </div>
        </div>
        <div class="st-topbar-right">
            <a href="browse-courses.php" class="btn-brand" style="font-size:.82rem;">
                <i class="bi bi-compass-fill"></i> Browse More
            </a>
        </div>
    </header>

    <main class="st-body">

        <!-- Flash alerts -->
        <?php if ($flashSuccess): ?>
        <div class="st-alert st-alert-success"><i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="st-alert st-alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashInfo): ?>
        <div class="st-alert st-alert-info"><i class="bi bi-info-circle-fill"></i> <?= htmlspecialchars($flashInfo) ?></div>
        <?php endif; ?>

        <!-- Stat summary -->
        <div class="st-stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:1.5rem;">
            <div class="st-stat-card">
                <div class="st-stat-icon si-blue"><i class="bi bi-book-fill"></i></div>
                <div><div class="st-stat-label">Active</div><div class="st-stat-value"><?= $counts['active'] ?></div></div>
            </div>
            <div class="st-stat-card">
                <div class="st-stat-icon si-purple"><i class="bi bi-trophy-fill"></i></div>
                <div><div class="st-stat-label">Completed</div><div class="st-stat-value"><?= $counts['completed'] ?></div></div>
            </div>
            <div class="st-stat-card">
                <div class="st-stat-icon si-amber"><i class="bi bi-journals"></i></div>
                <div><div class="st-stat-label">Total</div><div class="st-stat-value"><?= $totalEnrolled ?></div></div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <?php
            $tabs = [
                'active'    => ['label'=>'Active',    'icon'=>'bi-play-circle-fill'],
                'completed' => ['label'=>'Completed', 'icon'=>'bi-trophy-fill'],
                'dropped'   => ['label'=>'Dropped',   'icon'=>'bi-x-circle-fill'],
            ];
            foreach ($tabs as $val => $tab): ?>
            <a href="my-courses.php?filter=<?= $val ?>"
               class="filter-tab <?= $filter===$val ? 'active-tab' : '' ?>">
                <i class="bi <?= $tab['icon'] ?>"></i>
                <?= $tab['label'] ?>
                <span class="tab-count"><?= $counts[$val] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Course grid -->
        <?php if ($enrolled->num_rows === 0): ?>
        <div class="st-empty">
            <div class="st-empty-icon">
                <i class="bi bi-<?= $filter==='active' ? 'book' : ($filter==='completed' ? 'trophy' : 'x-circle') ?>"></i>
            </div>
            <h5>
                <?php if($filter==='active') echo 'No active courses';
                elseif($filter==='completed') echo 'No completed courses yet';
                else echo 'No dropped courses'; ?>
            </h5>
            <p><?= $filter==='active' ? 'Browse and enroll in a course to start learning.' : 'Keep learning — your progress will show here!' ?></p>
            <?php if ($filter==='active'): ?>
            <a href="browse-courses.php" class="btn-brand"><i class="bi bi-compass-fill"></i> Browse Courses</a>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <div class="row g-3">
            <?php while ($c = $enrolled->fetch_assoc()):
                $courseId     = (int)$c['id'];
                $totalLessons = (int)$c['total_lessons'];
                $doneLessons  = $progressMap[$courseId] ?? 0;
                $pct          = ($totalLessons > 0) ? min(100, (int)round($doneLessons / $totalLessons * 100)) : 0;
                $pfClass      = ['active'=>'pf-active','completed'=>'pf-completed','dropped'=>'pf-dropped'][$c['enroll_status']] ?? 'pf-active';
                if ($c['enroll_status']==='completed') $pct = 100;
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="enrolled-card">

                    <!-- Image -->
                    <a href="course-details.php?id=<?= $courseId ?>" style="text-decoration:none;">
                        <?php if (!empty($c['image']) && file_exists($c['image'])): ?>
                        <img src="<?= htmlspecialchars($c['image']) ?>"
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             class="enrolled-card-img">
                        <?php else: ?>
                        <div class="enrolled-card-img-placeholder"><i class="bi bi-book-fill"></i></div>
                        <?php endif; ?>
                    </a>

                    <!-- Body -->
                    <div class="enrolled-card-body">
                        <div class="enrolled-card-cat"><?= htmlspecialchars($c['category'] ?? 'General') ?></div>
                        <a href="course-details.php?id=<?= $courseId ?>" style="text-decoration:none;">
                            <div class="enrolled-card-title"><?= htmlspecialchars($c['title']) ?></div>
                        </a>
                        <div class="enrolled-card-meta">
                            <span class="lms-badge badge-<?= $c['level'] ?>"><?= ucfirst($c['level']) ?></span>
                            <?php if ($c['duration']): ?>
                            <span><i class="bi bi-clock"></i> <?= htmlspecialchars($c['duration']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Status badge -->
                        <?php
                        $badgeMap = ['active'=>'badge-enrolled','completed'=>'badge-completed','dropped'=>'badge-dropped'];
                        $iconMap  = ['active'=>'bi-play-circle-fill','completed'=>'bi-trophy-fill','dropped'=>'bi-x-circle-fill'];
                        ?>
                        <span class="lms-badge <?= $badgeMap[$c['enroll_status']] ?? '' ?>" style="margin-bottom:.6rem;display:inline-flex;">
                            <i class="bi <?= $iconMap[$c['enroll_status']] ?? '' ?>"></i>
                            <?= ucfirst($c['enroll_status']) ?>
                        </span>

                        <!-- Progress bar -->
                        <div class="course-progress">
                            <div class="progress-header">
                                <span class="progress-text">
                                    <?php if ($totalLessons > 0): ?>
                                        <?= $doneLessons ?> / <?= $totalLessons ?> lessons
                                    <?php else: ?>
                                        No lessons yet
                                    <?php endif; ?>
                                </span>
                                <span class="progress-pct"><?= $pct ?>%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill <?= $pfClass ?>"
                                     style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>

                        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">
                            <i class="bi bi-calendar-check"></i>
                            Enrolled <?= date('d M Y', strtotime($c['enrolled_at'])) ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="enrolled-card-footer">
                        <a href="course-details.php?id=<?= $courseId ?>"
                           class="btn-brand" style="font-size:.8rem;padding:.42rem .85rem;">
                            <?= $c['enroll_status']==='completed'
                                ? '<i class="bi bi-eye-fill"></i> Review'
                                : '<i class="bi bi-play-fill"></i> Continue' ?>
                        </a>

                        <?php if ($c['enroll_status']==='completed' && isset($certMap[$courseId])): ?>
                        <div style="display:flex;gap:.4rem;">
                            <a href="view-certificate.php?id=<?= $certMap[$courseId] ?>"
                               class="btn-brand"
                               style="font-size:.78rem;padding:.38rem .7rem;background:linear-gradient(135deg,#16a34a,#15803d);"
                               title="View Certificate">
                                <i class="bi bi-award-fill"></i> View
                            </a>
                            <a href="download-certificate.php?id=<?= $certMap[$courseId] ?>"
                               class="btn-brand"
                               style="font-size:.78rem;padding:.38rem .7rem;background:linear-gradient(135deg,#0369a1,#0284c7);"
                               title="Download Certificate">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                        <?php elseif ($c['enroll_status']==='active'): ?>
                        <form method="POST" action="my-courses.php" style="margin:0;"
                              onsubmit="return confirm('Drop this course? You can re-enroll later.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="course_id"  value="<?= $courseId ?>">
                            <input type="hidden" name="drop_course" value="1">
                            <button type="submit" class="drop-btn">
                                <i class="bi bi-x-lg"></i> Drop
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
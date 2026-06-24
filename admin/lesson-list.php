<?php
/**
 * admin/lesson-list.php
 * Placement: lms-project/admin/lesson-list.php
 */

define('BASE_URL', '../');
$currentPage = 'lessons';

require_once '../config/db.php';
require_once 'includes/auth.php';

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// STEP 1 — Check if lessons table exists
// This prevents the fatal error if SQL has not been run yet
$tableCheck  = $conn->query("SHOW TABLES LIKE 'lessons'");
$tableExists = ($tableCheck && $tableCheck->num_rows > 0);

// Default values
$totalRows      = 0;
$totalPages     = 0;
$totalPublished = 0;
$totalDraft     = 0;
$allCourses     = [];
$lessons        = null;
$perPage        = 10;
$currentPg      = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($currentPg - 1) * $perPage;
$filterCourse   = (int)($_GET['course_id'] ?? 0);
$filterStatus   = trim($_GET['status']     ?? '');
$search         = trim($_GET['search']     ?? '');

// STEP 2 — Only run queries if table exists
if ($tableExists) {

    // Load courses for dropdown
    $cr = $conn->query("SELECT id, title FROM courses ORDER BY title ASC");
    if ($cr) {
        while ($row = $cr->fetch_assoc()) {
            $allCourses[] = $row;
        }
    }

    // Build WHERE clause
    $where  = [];
    $params = [];
    $types  = '';

    if ($filterCourse > 0) {
        $where[]  = 'l.course_id = ?';
        $params[] = $filterCourse;
        $types   .= 'i';
    }
    if ($filterStatus !== '' && in_array($filterStatus, ['published', 'draft'], true)) {
        $where[]  = 'l.status = ?';
        $params[] = $filterStatus;
        $types   .= 's';
    }
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = '(l.title LIKE ? OR l.description LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ss';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // COUNT query
    $countSQL  = "SELECT COUNT(*) AS cnt FROM lessons l $whereSQL";
    $countStmt = $conn->prepare($countSQL);

    // STEP 3 — Guard: only execute if prepare() succeeded
    if ($countStmt === false) {
        $flashError = 'SQL Error (count): ' . $conn->error;
    } else {
        if ($types !== '') {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $res        = $countStmt->get_result();
        $totalRows  = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
        $totalPages = (int)ceil($totalRows / $perPage);
        $countStmt->close();

        // DATA query
        $dataSQL = "
            SELECT l.id, l.title, l.description, l.video_url, l.pdf_notes,
                   l.sort_order, l.is_preview, l.status, l.created_at,
                   c.id AS course_id, c.title AS course_title
            FROM lessons l
            INNER JOIN courses c ON c.id = l.course_id
            $whereSQL
            ORDER BY c.title ASC, l.sort_order ASC, l.id ASC
            LIMIT ? OFFSET ?
        ";

        $dataParams = array_merge($params, [$perPage, $offset]);
        $dataTypes  = $types . 'ii';

        $dataStmt = $conn->prepare($dataSQL);

        if ($dataStmt === false) {
            $flashError = 'SQL Error (data): ' . $conn->error;
        } else {
            $dataStmt->bind_param($dataTypes, ...$dataParams);
            $dataStmt->execute();
            $lessons = $dataStmt->get_result();
            $dataStmt->close();
        }
    }

    // Stat counts
    $r = $conn->query("SELECT COUNT(*) AS c FROM lessons WHERE status = 'published'");
    if ($r) $totalPublished = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) AS c FROM lessons WHERE status = 'draft'");
    if ($r) $totalDraft = (int)$r->fetch_assoc()['c'];
}

function lessonPageUrl(int $p): string {
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
    <title>Lessons — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .indicator {
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .72rem; font-weight: 600; padding: .2em .55em;
            border-radius: 5px;
        }
        .ind-video { background: #fef2f2; color: #dc2626; }
        .ind-pdf   { background: #fff7ed; color: #f97316; }
        .ind-none  { background: #f1f5f9; color: #94a3b8; }
        .lesson-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--brand-light); color: var(--brand);
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .72rem;
        }
        .setup-banner {
            background: #fffbeb; border: 2px dashed #f59e0b;
            border-radius: 12px; padding: 2rem;
        }
        .setup-banner pre {
            background: #fff8e1; border: 1px solid #fde68a;
            border-radius: 8px; padding: 1rem; font-size: .8rem;
            overflow-x: auto; white-space: pre-wrap; color: #78350f;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <!-- Topbar -->
    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">All Lessons</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo; Lessons
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="add-lesson.php" class="btn-lms-primary">
                <i class="bi bi-plus-lg"></i> <span>Add Lesson</span>
            </a>
        </div>
    </header>

    <main class="lms-body">

        <!-- Flash alerts -->
        <?php if ($flashSuccess): ?>
        <div class="lms-alert lms-alert-success" data-autohide>
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
        <div class="lms-alert lms-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
        <?php endif; ?>

        <?php if (!$tableExists): ?>
        <!-- ── TABLE MISSING — Setup Guide ── -->
        <div class="setup-banner">
            <h4 style="font-family:'Sora',sans-serif;color:#92400e;margin-bottom:.5rem;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                lessons table aadhi create kara
            </h4>
            <p style="color:#78350f;margin-bottom:1rem;">
                <strong>phpMyAdmin</strong> madhe jaa →
                <strong>lms_db</strong> select kara →
                <strong>SQL</strong> tab click kara →
                khali SQL paste kara ani <strong>Go</strong> click kara:
            </p>
            <pre>CREATE TABLE IF NOT EXISTS lessons (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id     INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT DEFAULT NULL,
    video_url     VARCHAR(500) DEFAULT NULL,
    pdf_notes     VARCHAR(300) DEFAULT NULL,
    sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
    is_preview    TINYINT(1) NOT NULL DEFAULT 0,
    status        ENUM('published','draft') NOT NULL DEFAULT 'draft',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lesson_course
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course_id (course_id),
    INDEX idx_sort_order (course_id, sort_order),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>
            <p style="color:#78350f;margin-bottom:0;">
                SQL run zal yawar ha page refresh kara — error jaiel ani lessons disel.
            </p>
        </div>

        <?php else: ?>

        <!-- ── Stats ── -->
        <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:1.25rem;">
            <div class="stat-card">
                <div class="stat-icon-wrap si-blue"><i class="bi bi-play-btn-fill"></i></div>
                <div>
                    <div class="stat-label">Total</div>
                    <div class="stat-value"><?= $totalRows ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="bi bi-eye-fill"></i></div>
                <div>
                    <div class="stat-label">Published</div>
                    <div class="stat-value"><?= $totalPublished ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-amber"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <div class="stat-label">Drafts</div>
                    <div class="stat-value"><?= $totalDraft ?></div>
                </div>
            </div>
        </div>

        <!-- ── Main Table Card ── -->
        <div class="lms-card">

            <!-- Filter bar -->
            <div class="lms-card-header" style="flex-wrap:wrap;gap:.75rem;">
                <h5 class="lms-card-title">
                    <i class="bi bi-play-btn-fill"></i> Lessons
                    <span style="background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .6em;font-size:.75rem;font-weight:700;margin-left:.4rem;">
                        <?= $totalRows ?>
                    </span>
                </h5>
                <form method="GET" action="lesson-list.php" class="filter-bar ms-auto">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="search-input"
                               placeholder="Search lessons…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="course_id" class="lms-select" style="width:175px;">
                        <option value="0">All Courses</option>
                        <?php foreach ($allCourses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= $filterCourse === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="lms-select" style="width:130px;">
                        <option value="">All Status</option>
                        <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft"     <?= $filterStatus === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    </select>
                    <button type="submit" class="btn-lms-primary" style="padding:.52rem 1rem;">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if ($search || $filterCourse || $filterStatus): ?>
                    <a href="lesson-list.php" class="btn-lms-outline" style="padding:.52rem .9rem;" title="Clear filters">
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
                            <th width="40">#</th>
                            <th width="45">No.</th>
                            <th>Lesson Title</th>
                            <th>Course</th>
                            <th width="90">Media</th>
                            <th width="80">Preview</th>
                            <th width="90">Status</th>
                            <th width="100">Date</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$lessons || $lessons->num_rows === 0): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-play-btn"></i></div>
                                    <h5>
                                        <?= ($search || $filterCourse || $filterStatus)
                                            ? 'No lessons match your filters'
                                            : 'No lessons yet' ?>
                                    </h5>
                                    <p>
                                        <?= ($search || $filterCourse || $filterStatus)
                                            ? 'Try changing or clearing the filters.'
                                            : 'Add your first lesson to get started.' ?>
                                    </p>
                                    <?php if (!$search && !$filterCourse && !$filterStatus): ?>
                                    <a href="add-lesson.php" class="btn-lms-primary">
                                        <i class="bi bi-plus-lg"></i> Add Lesson
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = $offset + 1;
                              while ($l = $lessons->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;">
                                <?= $rowNum++ ?>
                            </td>
                            <td>
                                <span class="lesson-num"><?= (int)$l['sort_order'] ?></span>
                            </td>
                            <td>
                                <div style="font-weight:600;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($l['title']) ?>
                                </div>
                                <?php if (!empty($l['description'])): ?>
                                <div style="font-size:.72rem;color:var(--text-muted);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars(substr($l['description'], 0, 80)) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="lesson-list.php?course_id=<?= (int)$l['course_id'] ?>"
                                   style="font-size:.82rem;color:var(--brand);text-decoration:none;font-weight:500;">
                                    <?= htmlspecialchars($l['course_title']) ?>
                                </a>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                    <?php if (!empty($l['video_url'])): ?>
                                    <span class="indicator ind-video">
                                        <i class="bi bi-youtube"></i> YT
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($l['pdf_notes'])): ?>
                                    <a href="../<?= htmlspecialchars($l['pdf_notes']) ?>"
                                       target="_blank"
                                       class="indicator ind-pdf"
                                       style="text-decoration:none;">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                                    </a>
                                    <?php endif; ?>
                                    <?php if (empty($l['video_url']) && empty($l['pdf_notes'])): ?>
                                    <span class="indicator ind-none">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($l['is_preview']): ?>
                                <span class="lms-badge badge-free">
                                    <i class="bi bi-eye-fill"></i> Free
                                </span>
                                <?php else: ?>
                                <span class="lms-badge badge-inactive">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lms-badge <?= $l['status'] === 'published' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst($l['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= date('d M Y', strtotime($l['created_at'])) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;">
                                    <a href="edit-lesson.php?id=<?= (int)$l['id'] ?>"
                                       class="btn-icon edit" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="delete-lesson.php?id=<?= (int)$l['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                       class="btn-icon delete" title="Delete"
                                       data-confirm="Delete '<?= htmlspecialchars(addslashes($l['title'])) ?>'? Cannot be undone.">
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
                    Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" style="gap:.25rem;">
                        <li class="page-item <?= $currentPg <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= lessonPageUrl($currentPg - 1) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($p = max(1, $currentPg - 2); $p <= min($totalPages, $currentPg + 2); $p++): ?>
                        <li class="page-item <?= $p === $currentPg ? 'active' : '' ?>">
                            <a class="page-link" href="<?= lessonPageUrl($p) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPg >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= lessonPageUrl($currentPg + 1) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div><!-- /.lms-card -->

        <?php endif; // $tableExists ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
/**
 * course-details.php — Course Detail + Lesson List with Progress
 * Placement: lms-project/course-details.php
 * Action   : REPLACE
 *
 * Changes from original:
 *  - Fetches which lessons student has already completed
 *  - Shows "Mark Complete" button on each unlocked lesson
 *  - Completed lessons show a green tick — no re-mark button
 *  - Shows overall course progress bar at top of lesson list
 *  - Auto-complete badge if enrollment.status = 'completed'
 */

$currentPage = 'browse';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId <= 0) { header('Location: browse-courses.php'); exit(); }

// ── Handle Enroll POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
        header('Location: course-details.php?id=' . $courseId); exit();
    }

    // Check existing enrollment (any status) — re-enroll support,
    // matching the same logic as browse-courses.php
    $dup = $conn->prepare("SELECT id, status FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
    $dup->bind_param('ii', $authUserId, $courseId);
    $dup->execute();
    $existingEnrollment = $dup->get_result()->fetch_assoc();
    $dup->close();

    if ($existingEnrollment && in_array($existingEnrollment['status'], ['active', 'completed'], true)) {
        $_SESSION['flash_info'] = 'Already enrolled.';
        header('Location: my-courses.php'); exit();
    }

    if ($existingEnrollment && $existingEnrollment['status'] === 'dropped') {
        // Reactivate the existing (dropped) enrollment instead of inserting
        // a new row — preserves history, avoids the UNIQUE KEY violation.
        $reactivate = $conn->prepare(
            "UPDATE enrollments SET status = 'active', enrolled_at = NOW()
             WHERE id = ? AND student_id = ? AND course_id = ?"
        );
        $reactivate->bind_param('iii', $existingEnrollment['id'], $authUserId, $courseId);
        if ($reactivate->execute()) {
            $_SESSION['flash_success'] = 'Successfully re-enrolled!';
            header('Location: my-courses.php');
        } else {
            $_SESSION['flash_error'] = 'Enrollment failed.';
            header('Location: course-details.php?id=' . $courseId);
        }
        $reactivate->close(); exit();
    }

    $ins = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active')");
    $ins->bind_param('ii', $authUserId, $courseId);
    if ($ins->execute()) {
        $_SESSION['flash_success'] = 'Enrolled successfully!';
        header('Location: my-courses.php');
    } else {
        if ($conn->errno === 1062) { header('Location: my-courses.php'); }
        else { $_SESSION['flash_error']='Enrollment failed.'; header('Location: course-details.php?id='.$courseId); }
    }
    $ins->close(); exit();
}

// ── Fetch course ───────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.*, u.name AS instructor_name
    FROM courses c
    LEFT JOIN users u ON u.id = c.created_by
    WHERE c.id = ? AND c.status = 'active' LIMIT 1
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$course) { header('Location: browse-courses.php'); exit(); }

// ── Enrollment ─────────────────────────────────────────────────
$eStmt = $conn->prepare("SELECT id, status FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$eStmt->bind_param('ii', $authUserId, $courseId);
$eStmt->execute();
$enrollment = $eStmt->get_result()->fetch_assoc();
$eStmt->close();
$isEnrolled     = !empty($enrollment) && in_array($enrollment['status'], ['active', 'completed'], true);
$isCourseComplete = ($enrollment['status'] ?? '') === 'completed';

// ── Enrollment count ───────────────────────────────────────────
$cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM enrollments WHERE course_id=?");
$cntStmt->bind_param('i', $courseId); $cntStmt->execute();
$enrollCount = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
$cntStmt->close();

// ── Lessons ────────────────────────────────────────────────────
$lTableCheck  = $conn->query("SHOW TABLES LIKE 'lessons'");
$lTableExists = $lTableCheck && $lTableCheck->num_rows > 0;
$lessons = [];
if ($lTableExists) {
    $lStmt = $conn->prepare("
        SELECT id, title, description, video_url, pdf_notes, sort_order, is_preview, status
       FROM lessons WHERE course_id=? AND status = 'published'
        ORDER BY sort_order ASC, id ASC
    ");
    $lStmt->bind_param('i', $courseId); $lStmt->execute();
    $lRes = $lStmt->get_result();
    while ($row = $lRes->fetch_assoc()) $lessons[] = $row;
    $lStmt->close();
}
$totalLessons = count($lessons);

// ── Completed lessons for this student ────────────────────────
$completedLessonIds = [];
$lpCheck = $conn->query("SHOW TABLES LIKE 'lesson_progress'");
$hasLP   = $lpCheck && $lpCheck->num_rows > 0;

if ($hasLP && $isEnrolled && $totalLessons > 0) {
    $lpStmt = $conn->prepare(
        "SELECT lp.lesson_id
         FROM lesson_progress lp
         INNER JOIN lessons l ON l.id = lp.lesson_id
         WHERE lp.student_id=? AND lp.course_id=? AND l.status = 'published'"
    );
    $lpStmt->bind_param('ii', $authUserId, $courseId);
    $lpStmt->execute();
    $lpRes = $lpStmt->get_result();
    while ($lpRow = $lpRes->fetch_assoc()) {
        $completedLessonIds[] = (int)$lpRow['lesson_id'];
    }
    $lpStmt->close();
}

$doneLessons = count($completedLessonIds);
$progressPct = ($totalLessons > 0) ? min(100, (int)round($doneLessons / $totalLessons * 100)) : 0;
if ($isCourseComplete) $progressPct = 100;

// ── Certificate check for this course ──────────────────────────
$certificateId = null;
$certTblChk = $conn->query("SHOW TABLES LIKE 'certificates'");
if ($certTblChk && $certTblChk->num_rows > 0 && $isCourseComplete) {
    $certChkStmt = $conn->prepare(
        "SELECT id FROM certificates WHERE student_id=? AND course_id=? LIMIT 1"
    );
    $certChkStmt->bind_param('ii', $authUserId, $courseId);
    $certChkStmt->execute();
    $certRow = $certChkStmt->get_result()->fetch_assoc();
    if ($certRow) $certificateId = (int)$certRow['id'];
    $certChkStmt->close();
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── YouTube URL → embed ID helper ──────────────────────────────
function ytEmbedId(string $url): string {
    if (empty($url)) return '';
    // Handles: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w\-]{11})/i', $url, $m)) {
        return $m[1];
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> — LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
    <style>
        .course-hero { background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:2.5rem 2rem;color:#fff;border-radius:var(--radius);margin-bottom:1.75rem;position:relative;overflow:hidden; }
        .course-hero::before { content:'';position:absolute;top:-60px;right:-60px;width:250px;height:250px;background:rgba(37,99,235,.15);border-radius:50%; }
        .course-hero-cat { font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#60a5fa;margin-bottom:.6rem; }
        .course-hero-title { font-family:'Sora',sans-serif;font-size:1.65rem;font-weight:700;margin-bottom:.75rem;line-height:1.3; }
        .course-hero-meta { display:flex;gap:1.25rem;flex-wrap:wrap;font-size:.82rem;opacity:.75;margin-bottom:1.5rem; }
        .course-hero-meta span { display:flex;align-items:center;gap:.35rem; }

        /* Lesson rows */
        .lesson-row { display:flex;align-items:center;gap:.85rem;padding:.9rem 1.25rem;border-bottom:1px solid var(--border);transition:background .15s; }
        .lesson-row:last-child { border-bottom:none; }
        .lesson-row:hover { background:#f8fafc; }
        .lesson-num-circle { width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-weight:700;font-size:.72rem; }
        .lnc-done    { background:#dcfce7;color:#15803d; }
        .lnc-active  { background:var(--brand-light);color:var(--brand); }
        .lnc-locked  { background:#f1f5f9;color:#94a3b8; }
        .lesson-locked-row { opacity:.55; }

        /* Mark complete button */
        .btn-mark-done {
            display:inline-flex;align-items:center;gap:.35rem;
            background:none;border:1.5px solid var(--brand);
            border-radius:7px;color:var(--brand);font-size:.75rem;
            font-weight:600;padding:.3rem .7rem;cursor:pointer;
            white-space:nowrap;flex-shrink:0;
            transition:background .15s,color .15s;
        }
        .btn-mark-done:hover { background:var(--brand-light); }
        .done-check { display:inline-flex;align-items:center;gap:.35rem;color:#15803d;font-size:.78rem;font-weight:600;white-space:nowrap;flex-shrink:0; }

        /* Progress bar in card header */
        .course-progress-bar { margin-top:.75rem; }
        .cpb-labels { display:flex;justify-content:space-between;font-size:.75rem;color:rgba(255,255,255,.7);margin-bottom:.3rem; }
        .cpb-track { background:rgba(255,255,255,.2);border-radius:20px;height:8px;overflow:hidden; }
        .cpb-fill  { height:100%;border-radius:20px;background:linear-gradient(90deg,#4ade80,#22c55e);transition:width .6s ease; }

        /* Enroll box */
        .enroll-box { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;box-shadow:var(--shadow-md);position:sticky;top:90px; }
        .enroll-price { font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:700;color:var(--text);margin-bottom:1rem; }
        .enroll-price.free { color:var(--success); }
        .enroll-feature { display:flex;align-items:center;gap:.6rem;font-size:.83rem;color:var(--text-muted);padding:.35rem 0; }
        .enroll-feature i { color:var(--brand);width:16px;text-align:center; }

        /* Lesson accordion */
        .lesson-panel { border-bottom:1px solid var(--border); }
        .lesson-panel:last-child { border-bottom:none; }
        .lesson-trigger {
            display:flex;align-items:center;gap:.85rem;padding:.9rem 1.25rem;
            width:100%;background:none;border:none;cursor:pointer;text-align:left;
            transition:background .15s;
        }
        .lesson-trigger:hover { background:#f8fafc; }
        .lesson-trigger.open  { background:#f0f7ff; }
        .lesson-body {
            display:none;padding:0 1.25rem 1.1rem 1.25rem;
            border-top:1px dashed var(--border);background:#fafbfc;
        }
        .lesson-body.open { display:block; }

        /* Responsive YouTube embed */
        .yt-wrap {
            position:relative;padding-bottom:56.25%;height:0;overflow:hidden;
            border-radius:10px;background:#000;margin-bottom:.85rem;
        }
        .yt-wrap iframe {
            position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:10px;
        }

        /* Draft warning banner */
        .draft-warning {
            padding:1rem 1.25rem;text-align:center;color:#92400e;
            background:#fffbeb;font-size:.85rem;border-radius:0 0 var(--radius) var(--radius);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/student-sidebar.php'; ?>

<div class="st-main">
    <header class="st-topbar">
        <div class="st-topbar-left">
            <button class="st-sidebar-toggle" id="stToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="st-page-title" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($course['title']) ?>
                </div>
                <div class="st-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &rsaquo;
                    <a href="browse-courses.php">Courses</a> &rsaquo; Details
                </div>
            </div>
        </div>
        <div class="st-topbar-right">
            <a href="browse-courses.php" class="btn-outline-brand" style="font-size:.82rem;">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </header>

    <main class="st-body">

        <?php if ($flashSuccess): ?>
        <div class="st-alert st-alert-success"><i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="st-alert st-alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- LEFT: Course info + lessons -->
            <div class="col-lg-8">

                <!-- Hero with progress bar (enrolled only) -->
                <div class="course-hero">
                    <div class="course-hero-cat">
                        <?= htmlspecialchars($course['category'] ?? 'General') ?>
                        &nbsp;·&nbsp;
                        <span class="lms-badge badge-<?= $course['level'] ?>"><?= ucfirst($course['level']) ?></span>
                        <?php if ($isCourseComplete): ?>
                        &nbsp;·&nbsp;
                        <span style="background:rgba(74,222,128,.25);border:1px solid rgba(74,222,128,.4);color:#86efac;border-radius:20px;padding:.15em .7em;font-size:.72rem;font-weight:700;">
                            <i class="bi bi-trophy-fill me-1"></i>Completed
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="course-hero-title"><?= htmlspecialchars($course['title']) ?></div>
                    <div class="course-hero-meta">
                        <?php if ($course['instructor_name']): ?>
                        <span><i class="bi bi-person-fill"></i> <?= htmlspecialchars($course['instructor_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($course['duration']): ?>
                        <span><i class="bi bi-clock-fill"></i> <?= htmlspecialchars($course['duration']) ?></span>
                        <?php endif; ?>
                        <span><i class="bi bi-play-circle-fill"></i> <?= $totalLessons ?> lessons</span>
                        <span><i class="bi bi-people-fill"></i> <?= $enrollCount ?> students</span>
                    </div>

                    <!-- Progress bar for enrolled students -->
                    <?php if ($isEnrolled && $totalLessons > 0): ?>
                    <div class="course-progress-bar">
                        <div class="cpb-labels">
                            <span><?= $doneLessons ?> of <?= $totalLessons ?> lessons completed</span>
                            <span style="font-weight:700;color:#fff;"><?= $progressPct ?>%</span>
                        </div>
                        <div class="cpb-track">
                            <div class="cpb-fill" style="width:<?= $progressPct ?>%"></div>
                        </div>
                    </div>
                    <?php elseif ($isEnrolled): ?>
                    <div style="font-size:.82rem;opacity:.7;margin-top:.5rem;">
                        <i class="bi bi-check-circle-fill me-1"></i> You are enrolled in this course.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Course image -->
                <?php if (!empty($course['image']) && file_exists($course['image'])): ?>
                <div class="st-card">
                    <img src="<?= htmlspecialchars($course['image']) ?>"
                         alt="<?= htmlspecialchars($course['title']) ?>"
                         style="width:100%;max-height:320px;object-fit:cover;">
                </div>
                <?php endif; ?>

                <!-- Full description -->
                <div class="st-card">
                    <div class="st-card-header">
                        <h5 class="st-card-title"><i class="bi bi-info-circle-fill"></i> About this Course</h5>
                    </div>
                    <div class="st-card-body">
                        <p style="font-size:.9rem;color:var(--text-muted);line-height:1.75;margin:0;">
                            <?= nl2br(htmlspecialchars($course['description'])) ?>
                        </p>
                    </div>
                </div>

                <!-- Lesson list -->
                <div class="st-card">
                    <div class="st-card-header">
                        <h5 class="st-card-title">
                            <i class="bi bi-list-ol"></i> Course Content
                        </h5>
                        <span style="font-size:.8rem;color:var(--text-muted);">
                            <?= $totalLessons ?> lessons
                            <?php if ($isEnrolled && $totalLessons > 0): ?>
                            &nbsp;·&nbsp;
                            <span style="color:var(--brand);font-weight:600;"><?= $progressPct ?>% done</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (empty($lessons)): ?>
                    <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:.875rem;">
                        <i class="bi bi-hourglass-split" style="font-size:1.5rem;margin-bottom:.5rem;display:block;"></i>
                        Lessons will appear here once published.
                    </div>
                    <?php if ($isEnrolled): ?>
                    <div class="draft-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        No published lessons yet. If you added lessons in the admin panel, make sure their status is set to <strong>Published</strong>.
                    </div>
                    <?php endif; ?>
                    <?php else: ?>

                    <?php foreach ($lessons as $i => $lesson):
                        $isDone   = in_array((int)$lesson['id'], $completedLessonIds, true);
                        $canView  = $isEnrolled || (bool)$lesson['is_preview'];
                        $numClass = $isDone ? 'lnc-done' : ($canView ? 'lnc-active' : 'lnc-locked');
                        $ytId     = ytEmbedId($lesson['video_url'] ?? '');
                        $panelId  = 'lesson-body-' . (int)$lesson['id'];
                    ?>
                    <div class="lesson-panel">

                        <!-- ── Trigger row ── -->
                        <button type="button"
                                class="lesson-trigger"
                                onclick="toggleLesson('<?= $panelId ?>', this)"
                                <?= !$canView ? 'disabled style="cursor:not-allowed;opacity:.5;"' : '' ?>>

                            <!-- Step circle -->
                            <div class="lesson-num-circle <?= $numClass ?>" style="flex-shrink:0;">
                                <?php if ($isDone): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php else: ?>
                                    <?= $i + 1 ?>
                                <?php endif; ?>
                            </div>

                            <!-- Title + badges -->
                            <div style="flex:1;overflow:hidden;text-align:left;">
                                <div style="font-weight:600;font-size:.875rem;
                                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
                                    <?= $isDone ? 'color:var(--text-muted);text-decoration:line-through;' : '' ?>">
                                    <?= htmlspecialchars($lesson['title']) ?>
                                </div>
                                <div style="font-size:.72rem;color:var(--text-muted);display:flex;gap:.6rem;margin-top:.2rem;flex-wrap:wrap;">
                                    <?php if ($ytId): ?>
                                    <span><i class="bi bi-youtube" style="color:#dc2626;"></i> Video</span>
                                    <?php endif; ?>
                                    <?php if (!empty($lesson['pdf_notes'])): ?>
                                    <span><i class="bi bi-file-earmark-pdf-fill" style="color:#f97316;"></i> PDF Notes</span>
                                    <?php endif; ?>
                                    <?php if ($lesson['is_preview'] && !$isEnrolled): ?>
                                    <span style="color:#16a34a;font-weight:600;"><i class="bi bi-eye-fill"></i> Free Preview</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right side: Done badge / Lock / Chevron -->
                            <?php if ($isDone): ?>
                            <span class="done-check" style="flex-shrink:0;"><i class="bi bi-check-circle-fill"></i> Done</span>
                            <?php elseif (!$canView): ?>
                            <i class="bi bi-lock-fill" style="color:#cbd5e1;font-size:.9rem;flex-shrink:0;"></i>
                            <?php else: ?>
                            <i class="bi bi-chevron-down lesson-chevron" style="color:var(--text-muted);font-size:.8rem;flex-shrink:0;transition:transform .2s;"></i>
                            <?php endif; ?>
                        </button>

                        <!-- ── Expandable body ── -->
                        <?php if ($canView): ?>
                        <div class="lesson-body" id="<?= $panelId ?>">

                            <!-- YouTube embed -->
                            <?php if ($ytId): ?>
                            <div class="yt-wrap" style="margin-top:.85rem;">
                                <iframe
                                    src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>?rel=0&modestbranding=1"
                                    title="<?= htmlspecialchars($lesson['title']) ?>"
                                    allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            <?php endif; ?>

                            <!-- Description -->
                            <?php if (!empty($lesson['description'])): ?>
                            <p style="font-size:.875rem;color:var(--text-muted);line-height:1.7;margin:.75rem 0;">
                                <?= nl2br(htmlspecialchars($lesson['description'])) ?>
                            </p>
                            <?php endif; ?>

                            <!-- PDF download -->
                            <?php if (!empty($lesson['pdf_notes'])): ?>
                            <a href="<?= htmlspecialchars($lesson['pdf_notes']) ?>"
                               target="_blank"
                               style="display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:#f97316;font-weight:600;text-decoration:none;margin-bottom:.75rem;">
                                <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF Notes
                            </a>
                            <?php endif; ?>

                            <!-- Mark Complete button -->
                            <?php if (!$isDone && $isEnrolled && $hasLP): ?>
                            <div style="margin-top:.5rem;">
                                <form method="POST" action="mark-complete.php" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="lesson_id"  value="<?= (int)$lesson['id'] ?>">
                                    <input type="hidden" name="course_id"  value="<?= $courseId ?>">
                                    <button type="submit" class="btn-mark-done">
                                        <i class="bi bi-check2-circle"></i> Mark as Complete
                                    </button>
                                </form>
                            </div>
                            <?php elseif ($isDone): ?>
                            <div class="done-check" style="margin-top:.4rem;">
                                <i class="bi bi-check-circle-fill"></i> Lesson completed
                            </div>
                            <?php endif; ?>

                        </div>
                        <?php endif; // canView ?>

                    </div><!-- /.lesson-panel -->
                    <?php endforeach; ?>

                    <?php endif; // empty lessons ?>
                </div>

                <!-- ── Quiz Section ── -->
                <?php
                // Check if an active quiz exists for this course
                $quizBannerData = null;
                $qzTblChk = $conn->query("SHOW TABLES LIKE 'quizzes'");
                if ($qzTblChk && $qzTblChk->num_rows > 0 && $isEnrolled) {
                    $qzStmt = $conn->prepare("
                        SELECT q.id, q.title, q.pass_percent, q.time_limit,
                               qa.id AS attempt_id, qa.percentage, qa.result
                        FROM quizzes q
                        LEFT JOIN quiz_attempts qa
                               ON qa.quiz_id = q.id AND qa.student_id = ?
                        WHERE q.course_id = ? AND q.status = 'active'
                        LIMIT 1
                    ");
                    $qzStmt->bind_param('ii', $authUserId, $courseId);
                    $qzStmt->execute();
                    $quizBannerData = $qzStmt->get_result()->fetch_assoc();
                    $qzStmt->close();
                }

                // ── GATE: quiz only unlocks when ALL lessons are completed ──
                // $isCourseComplete = enrollment.status = 'completed'
                // $totalLessons = 0 means no lessons → quiz is freely accessible
                $quizUnlocked = $isCourseComplete || ($totalLessons === 0 && $isEnrolled);
                ?>
                <?php if ($quizBannerData): ?>
                <div class="st-card" style="margin-top:1rem;">
                    <div style="padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                        <div style="display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:<?= $quizUnlocked ? 'linear-gradient(135deg,#7c3aed,#a855f7)' : 'linear-gradient(135deg,#94a3b8,#cbd5e1)' ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;flex-shrink:0;">
                                <i class="bi bi-<?= $quizUnlocked ? 'patch-question-fill' : 'lock-fill' ?>"></i>
                            </div>
                            <div>
                                <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;color:var(--text);">
                                    <?= htmlspecialchars($quizBannerData['title']) ?>
                                </div>
                                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.15rem;">
                                    Pass: <?= $quizBannerData['pass_percent'] ?>%
                                    <?php if ($quizBannerData['time_limit'] > 0): ?>
                                    &nbsp;·&nbsp; <?= $quizBannerData['time_limit'] ?> min
                                    <?php else: ?>
                                    &nbsp;·&nbsp; No time limit
                                    <?php endif; ?>
                                    <?php if ($quizBannerData['attempt_id'] && $quizUnlocked): ?>
                                    &nbsp;·&nbsp;
                                    <?php if ($quizBannerData['result'] === 'pass'): ?>
                                    <span style="color:#16a34a;font-weight:700;">✓ Passed (<?= number_format((float)$quizBannerData['percentage'],1) ?>%)</span>
                                    <?php else: ?>
                                    <span style="color:#dc2626;font-weight:700;">✗ Failed (<?= number_format((float)$quizBannerData['percentage'],1) ?>%)</span>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($quizUnlocked): ?>
                        <!-- ✅ All lessons done — quiz is active -->
                        <a href="take-quiz.php?course_id=<?= $courseId ?>"
                           style="display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border-radius:8px;padding:.5rem 1.1rem;font-size:.85rem;font-weight:600;text-decoration:none;box-shadow:0 3px 10px rgba(124,58,237,.3);">
                            <i class="bi bi-play-fill"></i>
                            <?= $quizBannerData['attempt_id']
                                ? ($quizBannerData['result'] === 'pass' ? 'Retake Quiz' : 'Try Again')
                                : 'Take Quiz' ?>
                        </a>
                        <?php else: ?>
                        <!-- 🔒 Lessons not complete — quiz locked -->
                        <div style="text-align:right;">
                            <span style="display:inline-flex;align-items:center;gap:.4rem;background:#f1f5f9;color:#94a3b8;border-radius:8px;padding:.5rem 1.1rem;font-size:.85rem;font-weight:600;cursor:not-allowed;border:1.5px solid #e2e8f0;">
                                <i class="bi bi-lock-fill"></i> Locked
                            </span>
                            <div style="font-size:.72rem;color:#f59e0b;font-weight:600;margin-top:.4rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                Complete all lessons to unlock the quiz.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$quizUnlocked && $totalLessons > 0): ?>
                    <!-- Progress hint inside quiz card -->
                    <div style="padding:.75rem 1.5rem;border-top:1px solid var(--border);background:#fffbeb;border-radius:0 0 var(--radius) var(--radius);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem;">
                            <span style="font-size:.78rem;color:#92400e;font-weight:600;">
                                <i class="bi bi-bar-chart-fill"></i>
                                Lesson Progress: <?= $doneLessons ?> / <?= $totalLessons ?> completed
                            </span>
                            <span style="font-size:.78rem;font-weight:700;color:#92400e;"><?= $progressPct ?>%</span>
                        </div>
                        <div style="background:#fde68a;border-radius:20px;height:6px;overflow:hidden;">
                            <div style="width:<?= $progressPct ?>%;height:100%;border-radius:20px;background:linear-gradient(90deg,#f59e0b,#d97706);transition:width .6s;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT: Enroll / Progress box -->
            <div class="col-lg-4">
                <div class="enroll-box">

                    <div class="enroll-price <?= $course['is_free'] ? 'free' : '' ?>">
                        <?= $course['is_free'] ? 'Free' : '₹' . number_format($course['price'], 2) ?>
                    </div>

                    <?php if ($isEnrolled): ?>

                    <!-- Progress summary box -->
                    <?php if ($totalLessons > 0): ?>
                    <div style="background:var(--bg);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1rem;">
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem;">
                            <span>Your Progress</span>
                            <span style="font-weight:700;color:var(--brand);"><?= $progressPct ?>%</span>
                        </div>
                        <div style="background:#e2e8f0;border-radius:20px;height:8px;overflow:hidden;">
                            <div style="height:100%;border-radius:20px;background:linear-gradient(90deg,var(--brand),#60a5fa);width:<?= $progressPct ?>%;transition:width .6s ease;"></div>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem;text-align:center;">
                            <?= $doneLessons ?> of <?= $totalLessons ?> lessons completed
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="my-courses.php" class="btn-brand w-100"
                       style="justify-content:center;padding:.72rem;font-size:.9rem;margin-bottom:.75rem;">
                        <i class="bi bi-book-fill"></i> Go to My Courses
                    </a>

                    <?php if ($isCourseComplete): ?>
                    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:var(--radius-sm);padding:1rem;margin-bottom:.75rem;text-align:center;">
                        <div style="color:#16a34a;font-size:1.6rem;margin-bottom:.3rem;">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                        <div style="font-family:'Sora',sans-serif;font-weight:700;color:#15803d;font-size:.95rem;margin-bottom:.2rem;">
                            Course Completed!
                        </div>
                        <div style="font-size:.8rem;color:#16a34a;margin-bottom:<?= $certificateId ? '.75rem' : '0' ?>;">
                            Well done — you finished all lessons.
                        </div>
                        <?php if ($certificateId): ?>
                        <div style="display:flex;gap:.5rem;">
                            <a href="view-certificate.php?id=<?= $certificateId ?>"
                               class="btn-brand" style="flex:1;justify-content:center;font-size:.82rem;padding:.5rem;background:linear-gradient(135deg,#16a34a,#15803d);">
                                <i class="bi bi-award-fill"></i> View Certificate
                            </a>
                            <a href="download-certificate.php?id=<?= $certificateId ?>"
                               class="btn-brand" style="flex:1;justify-content:center;font-size:.82rem;padding:.5rem;background:linear-gradient(135deg,#0369a1,#0284c7);">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="st-alert st-alert-info" style="margin-bottom:0;">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Mark each lesson done to track your progress.</span>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>

                    <form method="POST" action="course-details.php?id=<?= $courseId ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="enroll" value="1">
                        <button type="submit" class="btn-brand w-100"
                                style="justify-content:center;padding:.72rem;font-size:.9rem;margin-bottom:.75rem;">
                            <i class="bi bi-plus-circle-fill"></i>
                            <?= $course['is_free'] ? 'Enroll for Free' : 'Enroll Now' ?>
                        </button>
                    </form>

                    <?php endif; ?>

                    <hr style="margin:1rem 0;border-color:var(--border);">

                    <div class="enroll-feature"><i class="bi bi-infinity"></i> Full lifetime access</div>
                    <?php if ($totalLessons): ?>
                    <div class="enroll-feature"><i class="bi bi-play-circle-fill"></i> <?= $totalLessons ?> lessons</div>
                    <?php endif; ?>
                    <?php if ($course['duration']): ?>
                    <div class="enroll-feature"><i class="bi bi-clock-fill"></i> <?= htmlspecialchars($course['duration']) ?></div>
                    <?php endif; ?>
                    <div class="enroll-feature"><i class="bi bi-file-earmark-pdf-fill"></i> PDF notes included</div>
                    <div class="enroll-feature"><i class="bi bi-phone-fill"></i> Access on any device</div>
                    <div class="enroll-feature"><i class="bi bi-trophy-fill"></i> Certificate on completion</div>

                    <hr style="margin:1rem 0;border-color:var(--border);">
                    <div style="display:flex;justify-content:space-around;text-align:center;">
                        <div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:1.1rem;"><?= $enrollCount ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted);">Students</div>
                        </div>
                        <div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:1.1rem;"><?= $totalLessons ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted);">Lessons</div>
                        </div>
                        <div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:1.1rem;"><?= ucfirst($course['level']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted);">Level</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Sidebar toggle ─────────────────────────────────────────────
const stSidebar = document.getElementById('stSidebar');
const stToggle  = document.getElementById('stToggle');
const stOverlay = document.getElementById('stOverlay');
stToggle?.addEventListener('click', () => { stSidebar.classList.toggle('open'); stOverlay.classList.toggle('show'); });
stOverlay?.addEventListener('click', () => { stSidebar.classList.remove('open'); stOverlay.classList.remove('show'); });

// ── Lesson accordion ───────────────────────────────────────────
function toggleLesson(panelId, triggerEl) {
    const body    = document.getElementById(panelId);
    const chevron = triggerEl.querySelector('.lesson-chevron');
    if (!body) return;

    const isOpen = body.classList.contains('open');

    // Close all other panels first
    document.querySelectorAll('.lesson-body.open').forEach(el => {
        el.classList.remove('open');
    });
    document.querySelectorAll('.lesson-trigger.open').forEach(el => {
        el.classList.remove('open');
        const ch = el.querySelector('.lesson-chevron');
        if (ch) ch.style.transform = 'rotate(0deg)';
    });

    // Toggle clicked panel
    if (!isOpen) {
        body.classList.add('open');
        triggerEl.classList.add('open');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
}
</script>
</body>
</html>
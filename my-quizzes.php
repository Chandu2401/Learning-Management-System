<?php
/**
 * my-quizzes.php — Student Quiz Dashboard
 * Placement: lms-project/my-quizzes.php
 * Action   : CREATE
 *
 * Shows all quizzes for courses the student is enrolled in.
 * Displays past attempt result if already taken.
 * Links to take-quiz.php for new/retake.
 */

$currentPage = 'my_quizzes';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Check tables ───────────────────────────────────────────────
$quizTblCheck  = $conn->query("SHOW TABLES LIKE 'quizzes'");
$quizTblExists = $quizTblCheck && $quizTblCheck->num_rows > 0;

$quizzes = [];
$stats   = ['total'=>0,'passed'=>0,'failed'=>0,'not_taken'=>0];

if ($quizTblExists) {
    // Get all quizzes for enrolled courses
    $stmt = $conn->prepare("
        SELECT q.id AS quiz_id, q.title AS quiz_title,
               q.description, q.pass_percent, q.time_limit, q.status,
               c.id AS course_id, c.title AS course_title,
               c.level, c.image,
               qa.id AS attempt_id, qa.score, qa.total_marks,
               qa.percentage, qa.result, qa.attempted_at
        FROM enrollments e
        INNER JOIN courses c  ON c.id  = e.course_id
        INNER JOIN quizzes q  ON q.course_id = c.id AND q.status = 'active'
        LEFT JOIN quiz_attempts qa
               ON qa.quiz_id = q.id AND qa.student_id = ?
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC, q.id ASC
    ");
    if ($stmt === false) {
        die('Query error: ' . $conn->error);
    }
    $stmt->bind_param('ii', $authUserId, $authUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $quizzes[] = $row;
        $stats['total']++;
        if ($row['attempt_id']) {
            if ($row['result'] === 'pass') $stats['passed']++;
            else $stats['failed']++;
        } else {
            $stats['not_taken']++;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes — LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
    <style>
        .quiz-card {
            background: var(--surface); border-radius: var(--radius);
            border: 1px solid var(--border); box-shadow: var(--shadow);
            overflow: hidden; height: 100%; display: flex; flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .quiz-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

        .quiz-card-header {
            padding: 1.1rem 1.25rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; gap: .75rem;
        }
        .quiz-icon {
            width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: #fff;
        }
        .quiz-course-name {
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: var(--brand); margin-bottom: .2rem;
        }
        .quiz-title {
            font-family: 'Sora', sans-serif; font-weight: 700;
            font-size: .9rem; color: var(--text); line-height: 1.3;
        }

        .quiz-card-body { padding: 1rem 1.25rem; flex: 1; }
        .quiz-meta {
            display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: .85rem;
            font-size: .72rem; color: var(--text-muted);
        }
        .quiz-meta span { display: flex; align-items: center; gap: .3rem; }

        /* Result section */
        .quiz-result-box {
            border-radius: var(--radius-sm); padding: .85rem 1rem;
            margin-bottom: .75rem;
        }
        .result-pass-box { background: #f0fdf4; border: 1px solid #86efac; }
        .result-fail-box { background: #fef2f2; border: 1px solid #fca5a5; }

        .result-score {
            font-family: 'Sora', sans-serif; font-size: 1.5rem; font-weight: 700;
        }
        .result-pass-box .result-score { color: #15803d; }
        .result-fail-box .result-score { color: #b91c1c; }

        .result-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .result-pass-box .result-label { color: #15803d; }
        .result-fail-box .result-label { color: #b91c1c; }

        .score-bar { background: #e2e8f0; border-radius: 20px; height: 7px; overflow: hidden; margin-top: .5rem; }
        .score-fill-pass { background: linear-gradient(90deg, #16a34a, #4ade80); height: 100%; border-radius: 20px; }
        .score-fill-fail { background: linear-gradient(90deg, #dc2626, #f87171); height: 100%; border-radius: 20px; }

        /* Not taken state */
        .not-taken-box {
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: var(--radius-sm); padding: .7rem 1rem;
            font-size: .82rem; color: #92400e; margin-bottom: .75rem;
            display: flex; align-items: center; gap: .5rem;
        }

        .quiz-card-footer {
            padding: .85rem 1.25rem; border-top: 1px solid var(--border);
            background: #fafafa; display: flex; gap: .5rem; align-items: center;
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
                <div class="st-page-title">My Quizzes</div>
                <div class="st-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &rsaquo; Quizzes
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
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="st-alert st-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($flashError) ?>
        </div>
        <?php endif; ?>

        <?php if (!$quizTblExists): ?>
        <!-- Tables not set up -->
        <div class="st-alert st-alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Quiz tables not found. Please run <strong>config/quiz_tables.sql</strong> in phpMyAdmin.
        </div>

        <?php elseif (empty($quizzes)): ?>
        <!-- No quizzes -->
        <div class="st-empty">
            <div class="st-empty-icon"><i class="bi bi-patch-question"></i></div>
            <h5>No quizzes available</h5>
            <p>Enroll in a course that has an active quiz to get started.</p>
            <a href="browse-courses.php" class="btn-brand">
                <i class="bi bi-compass-fill"></i> Browse Courses
            </a>
        </div>

        <?php else: ?>

        <!-- Stats -->
        <div class="st-stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(155px,1fr));margin-bottom:1.5rem;">
            <div class="st-stat-card">
                <div class="st-stat-icon si-blue"><i class="bi bi-patch-question-fill"></i></div>
                <div><div class="st-stat-label">Available</div><div class="st-stat-value"><?= $stats['total'] ?></div></div>
            </div>
            <div class="st-stat-card">
                <div class="st-stat-icon si-green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="st-stat-label">Passed</div><div class="st-stat-value"><?= $stats['passed'] ?></div></div>
            </div>
            <div class="st-stat-card">
                <div class="st-stat-icon si-rose"><i class="bi bi-x-circle-fill"></i></div>
                <div><div class="st-stat-label">Failed</div><div class="st-stat-value"><?= $stats['failed'] ?></div></div>
            </div>
            <div class="st-stat-card">
                <div class="st-stat-icon si-amber"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="st-stat-label">Not Taken</div><div class="st-stat-value"><?= $stats['not_taken'] ?></div></div>
            </div>
        </div>

        <!-- Quiz cards grid -->
        <div class="row g-3">
            <?php foreach ($quizzes as $qz):
                $attempted  = !empty($qz['attempt_id']);
                $passed     = $attempted && $qz['result'] === 'pass';
                $pct        = $attempted ? (float)$qz['percentage'] : 0;
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="quiz-card">

                    <!-- Header -->
                    <div class="quiz-card-header">
                        <div class="quiz-icon"><i class="bi bi-patch-question-fill"></i></div>
                        <div style="flex:1;overflow:hidden;">
                            <div class="quiz-course-name"><?= htmlspecialchars($qz['course_title']) ?></div>
                            <div class="quiz-title"><?= htmlspecialchars($qz['quiz_title']) ?></div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="quiz-card-body">
                        <div class="quiz-meta">
                            <span><i class="bi bi-trophy-fill"></i> Pass: <?= (int)$qz['pass_percent'] ?>%</span>
                            <?php if ($qz['time_limit'] > 0): ?>
                            <span><i class="bi bi-clock-fill"></i> <?= (int)$qz['time_limit'] ?> min</span>
                            <?php else: ?>
                            <span><i class="bi bi-infinity"></i> No time limit</span>
                            <?php endif; ?>
                            <span class="lms-badge badge-<?= $qz['level'] ?>"><?= ucfirst($qz['level']) ?></span>
                        </div>

                        <?php if ($qz['description']): ?>
                        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.85rem;line-height:1.5;">
                            <?= htmlspecialchars(substr($qz['description'], 0, 100)) ?>
                        </p>
                        <?php endif; ?>

                        <?php if ($attempted): ?>
                        <!-- Result box -->
                        <div class="quiz-result-box <?= $passed ? 'result-pass-box' : 'result-fail-box' ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.3rem;">
                                <div>
                                    <div class="result-label"><?= $passed ? '✓ PASSED' : '✗ FAILED' ?></div>
                                    <div class="result-score"><?= number_format($pct, 1) ?>%</div>
                                </div>
                                <div style="text-align:right;font-size:.78rem;">
                                    <div style="font-weight:700;"><?= $qz['score'] ?>/<?= $qz['total_marks'] ?></div>
                                    <div style="opacity:.7;">marks</div>
                                </div>
                            </div>
                            <div class="score-bar">
                                <div class="<?= $passed ? 'score-fill-pass' : 'score-fill-fail' ?>"
                                     style="width:<?= min(100, $pct) ?>%"></div>
                            </div>
                            <div style="font-size:.7rem;margin-top:.4rem;opacity:.75;">
                                Attempted: <?= date('d M Y', strtotime($qz['attempted_at'])) ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Not taken -->
                        <div class="not-taken-box">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            Not attempted yet
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="quiz-card-footer">
                        <?php if (!$attempted): ?>
                        <a href="take-quiz.php?course_id=<?= $qz['course_id'] ?>"
                           class="btn-brand" style="font-size:.82rem;padding:.45rem .95rem;">
                            <i class="bi bi-play-fill"></i> Start Quiz
                        </a>
                        <?php elseif ($passed): ?>
                        <a href="take-quiz.php?course_id=<?= $qz['course_id'] ?>"
                           class="btn-outline-brand" style="font-size:.82rem;padding:.43rem .9rem;">
                            <i class="bi bi-arrow-repeat"></i> Retake
                        </a>
                        <span style="font-size:.75rem;color:var(--success);display:flex;align-items:center;gap:.3rem;font-weight:600;">
                            <i class="bi bi-check-circle-fill"></i> Passed
                        </span>
                        <?php else: ?>
                        <a href="take-quiz.php?course_id=<?= $qz['course_id'] ?>"
                           class="btn-brand" style="font-size:.82rem;padding:.45rem .95rem;">
                            <i class="bi bi-arrow-repeat"></i> Try Again
                        </a>
                        <?php endif; ?>

                        <a href="course-details.php?id=<?= $qz['course_id'] ?>"
                           class="btn-outline-brand" style="font-size:.78rem;padding:.43rem .85rem;margin-left:auto;">
                            <i class="bi bi-book-fill"></i> Course
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
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
<?php
/**
 * admin/quiz-attempts.php — View Student Quiz Attempts
 * Placement: lms-project/admin/quiz-attempts.php
 * Action   : CREATE
 */

define('BASE_URL', '../');
$currentPage = 'quizzes';

require_once '../config/db.php';
require_once 'includes/auth.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Optional quiz_id filter from URL
$filterQuizId = (int)($_GET['quiz_id'] ?? 0);

// ── Load all quizzes for dropdown filter ───────────────────────
$quizListRes = $conn->query("
    SELECT q.id, q.title, c.title AS course_title
    FROM quizzes q
    INNER JOIN courses c ON c.id = q.course_id
    ORDER BY c.title ASC
");
$allQuizzes = [];
if ($quizListRes) {
    while ($row = $quizListRes->fetch_assoc()) $allQuizzes[] = $row;
}

// ── Fetch attempts ─────────────────────────────────────────────
$where  = '';
$params = [];
$types  = '';

if ($filterQuizId > 0) {
    $where    = 'WHERE qa.quiz_id = ?';
    $params[] = $filterQuizId;
    $types    = 'i';
}

$attSQL = "
    SELECT qa.id, qa.score, qa.total_marks, qa.percentage,
           qa.result, qa.attempted_at,
           qa.quiz_id,
           u.name  AS student_name,
           u.email AS student_email,
           q.title AS quiz_title,
           c.title AS course_title,
           q.pass_percent
    FROM quiz_attempts qa
    INNER JOIN users   u ON u.id = qa.student_id
    INNER JOIN quizzes q ON q.id = qa.quiz_id
    INNER JOIN courses c ON c.id = q.course_id
    $where
    ORDER BY qa.attempted_at DESC
    LIMIT 200
";

$attStmt = $conn->prepare($attSQL);
if ($attStmt === false) {
    die('Query error: ' . $conn->error);
}
if ($types) {
    $attStmt->bind_param($types, ...$params);
}
$attStmt->execute();
$attempts = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attStmt->close();

// ── Summary stats ──────────────────────────────────────────────
$totalAttempts = count($attempts);
$passCount     = count(array_filter($attempts, fn($a) => $a['result'] === 'pass'));
$failCount     = $totalAttempts - $passCount;
$avgPercent    = $totalAttempts > 0
    ? round(array_sum(array_column($attempts, 'percentage')) / $totalAttempts, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Attempts — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .result-pass { background:#dcfce7;color:#15803d;font-size:.72rem;font-weight:700;border-radius:20px;padding:.2em .7em; }
        .result-fail { background:#fee2e2;color:#b91c1c;font-size:.72rem;font-weight:700;border-radius:20px;padding:.2em .7em; }
        .pct-bar { background:#e2e8f0;border-radius:20px;height:6px;overflow:hidden;min-width:60px; }
        .pct-fill-pass { background:linear-gradient(90deg,#16a34a,#4ade80);height:100%;border-radius:20px; }
        .pct-fill-fail { background:linear-gradient(90deg,#dc2626,#f87171);height:100%;border-radius:20px; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">
    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Quiz Attempts</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="quiz-list.php">Quizzes</a> &rsaquo; Attempts
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="quiz-list.php" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </header>

    <main class="lms-body">

        <!-- Stat cards -->
        <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(165px,1fr));margin-bottom:1.5rem;">
            <div class="stat-card">
                <div class="stat-icon-wrap si-blue"><i class="bi bi-clipboard-data-fill"></i></div>
                <div><div class="stat-label">Total Attempts</div><div class="stat-value"><?= $totalAttempts ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="stat-label">Passed</div><div class="stat-value"><?= $passCount ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-rose"><i class="bi bi-x-circle-fill"></i></div>
                <div><div class="stat-label">Failed</div><div class="stat-value"><?= $failCount ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-amber"><i class="bi bi-bar-chart-fill"></i></div>
                <div><div class="stat-label">Avg Score</div><div class="stat-value"><?= $avgPercent ?>%</div></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="lms-card">
            <div class="lms-card-header" style="flex-wrap:wrap;gap:.75rem;">
                <h5 class="lms-card-title">
                    <i class="bi bi-clipboard-data-fill"></i> Attempt Records
                </h5>
                <form method="GET" action="quiz-attempts.php" class="filter-bar ms-auto">
                    <select name="quiz_id" class="lms-select" style="width:250px;">
                        <option value="0">All Quizzes</option>
                        <?php foreach ($allQuizzes as $q): ?>
                        <option value="<?= $q['id'] ?>"
                            <?= $filterQuizId === (int)$q['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($q['course_title'] . ' — ' . $q['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-lms-primary" style="padding:.52rem 1rem;">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <?php if ($filterQuizId): ?>
                    <a href="quiz-attempts.php" class="btn-lms-outline" style="padding:.52rem .9rem;">
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
                            <th>#</th>
                            <th>Student</th>
                            <th>Quiz</th>
                            <th>Course</th>
                            <th width="90">Score</th>
                            <th width="130">Progress</th>
                            <th width="80">Result</th>
                            <th width="100">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($attempts)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-clipboard-x"></i></div>
                                    <h5>No attempts yet</h5>
                                    <p>Students haven't taken any quizzes yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attempts as $i => $a): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i+1 ?></td>
                            <td>
                                <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($a['student_name']) ?></div>
                                <div style="font-size:.72rem;color:var(--text-muted);"><?= htmlspecialchars($a['student_email']) ?></div>
                            </td>
                            <td style="font-size:.82rem;"><?= htmlspecialchars($a['quiz_title']) ?></td>
                            <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($a['course_title']) ?></td>
                            <td style="font-weight:700;font-size:.875rem;">
                                <?= $a['score'] ?>/<?= $a['total_marks'] ?>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="pct-bar" style="flex:1;">
                                        <div class="<?= $a['result']==='pass' ? 'pct-fill-pass' : 'pct-fill-fail' ?>"
                                             style="width:<?= min(100, (float)$a['percentage']) ?>%"></div>
                                    </div>
                                    <span style="font-size:.75rem;font-weight:700;min-width:36px;text-align:right;">
                                        <?= number_format((float)$a['percentage'], 1) ?>%
                                    </span>
                                </div>
                                <div style="font-size:.68rem;color:var(--text-muted);margin-top:.15rem;">
                                    Pass: <?= $a['pass_percent'] ?>%
                                </div>
                            </td>
                            <td>
                                <span class="result-<?= $a['result'] ?>">
                                    <?= $a['result'] === 'pass' ? '✓ Pass' : '✗ Fail' ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= date('d M Y', strtotime($a['attempted_at'])) ?>
                                <div><?= date('h:i A', strtotime($a['attempted_at'])) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
/**
 * admin/quiz-list.php — All Quizzes
 * Placement: lms-project/admin/quiz-list.php
 * Action   : CREATE
 */

define('BASE_URL', '../');
$currentPage = 'quizzes';

require_once '../config/db.php';
require_once 'includes/auth.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Check tables exist ─────────────────────────────────────────
$tblCheck  = $conn->query("SHOW TABLES LIKE 'quizzes'");
$tblExists = $tblCheck && $tblCheck->num_rows > 0;

$quizzes = [];
$stats   = ['total' => 0, 'active' => 0, 'attempts' => 0];

if ($tblExists) {
    $res = $conn->query("
        SELECT q.id, q.title, q.pass_percent, q.time_limit, q.status,
               q.created_at,
               c.title AS course_title,
               (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS q_count,
               (SELECT COUNT(*) FROM quiz_attempts  qa WHERE qa.quiz_id = q.id) AS a_count,
               (SELECT COUNT(*) FROM quiz_attempts  qa WHERE qa.quiz_id = q.id AND qa.result = 'pass') AS pass_count
        FROM quizzes q
        INNER JOIN courses c ON c.id = q.course_id
        ORDER BY q.created_at DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) $quizzes[] = $row;
    }

    $stats['total']    = count($quizzes);
    $stats['active']   = count(array_filter($quizzes, fn($q) => $q['status'] === 'active'));

    $ar = $conn->query("SELECT COUNT(*) AS c FROM quiz_attempts");
    $stats['attempts'] = $ar ? (int)$ar->fetch_assoc()['c'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes — LMS Admin</title>
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
                <div class="page-title">Quizzes</div>
                <div class="page-breadcrumb"><a href="index.php">Dashboard</a> &rsaquo; Quizzes</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="add-quiz.php" class="btn-lms-primary">
                <i class="bi bi-plus-lg"></i> <span>Add Quiz</span>
            </a>
        </div>
    </header>

    <main class="lms-body">

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

        <?php if (!$tblExists): ?>
        <div class="lms-alert lms-alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Quiz tables not found. Run <strong>config/quiz_tables.sql</strong> in phpMyAdmin first.
        </div>
        <?php else: ?>

        <!-- Stats -->
        <div class="stat-grid" style="margin-bottom:1.25rem;">
            <div class="stat-card">
                <div class="stat-icon-wrap si-purple"><i class="bi bi-patch-question-fill"></i></div>
                <div>
                    <div class="stat-label">Total Quizzes</div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="bi bi-toggle-on"></i></div>
                <div>
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?= $stats['active'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-amber"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <div class="stat-label">Total Attempts</div>
                    <div class="stat-value"><?= $stats['attempts'] ?></div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="lms-card">
            <div class="lms-card-header">
                <h5 class="lms-card-title">
                    <i class="bi bi-patch-question-fill"></i> All Quizzes
                </h5>
                <a href="add-quiz.php" class="btn-lms-primary" style="font-size:.8rem;padding:.42rem .9rem;">
                    <i class="bi bi-plus-lg"></i> Add Quiz
                </a>
            </div>

            <div class="table-responsive">
                <table class="lms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Quiz Title</th>
                            <th>Course</th>
                            <th>Questions</th>
                            <th>Pass %</th>
                            <th>Time</th>
                            <th>Attempts</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($quizzes)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-patch-question"></i></div>
                                    <h5>No quizzes yet</h5>
                                    <p>Create your first quiz by clicking Add Quiz.</p>
                                    <a href="add-quiz.php" class="btn-lms-primary">
                                        <i class="bi bi-plus-lg"></i> Add Quiz
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quizzes as $i => $q): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= $i + 1 ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($q['title']) ?></div>
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted);">
                                <?= htmlspecialchars($q['course_title']) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="lms-badge badge-beginner"><?= (int)$q['q_count'] ?> Q</span>
                            </td>
                            <td style="font-weight:600;color:var(--brand);"><?= (int)$q['pass_percent'] ?>%</td>
                            <td style="font-size:.82rem;color:var(--text-muted);">
                                <?= $q['time_limit'] > 0 ? $q['time_limit'] . ' min' : 'Unlimited' ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;"><?= (int)$q['a_count'] ?></span>
                                <?php if ($q['a_count'] > 0): ?>
                                <span style="font-size:.72rem;color:var(--text-muted);">
                                    (<?= (int)$q['pass_count'] ?> passed)
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lms-badge <?= $q['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst($q['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;">
                                    <a href="edit-quiz.php?id=<?= $q['id'] ?>"
                                       class="btn-icon edit" title="Edit Quiz & Questions">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="quiz-attempts.php?quiz_id=<?= $q['id'] ?>"
                                       class="btn-icon view" title="View Attempts">
                                        <i class="bi bi-bar-chart-fill"></i>
                                    </a>
                                    <a href="delete-quiz.php?id=<?= $q['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                       class="btn-icon delete" title="Delete Quiz"
                                       data-confirm="Delete quiz '<?= htmlspecialchars(addslashes($q['title'])) ?>'? All questions and attempts will be deleted.">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
/**
 * admin/edit-quiz.php — Edit Quiz + Manage Questions
 * Placement: lms-project/admin/edit-quiz.php
 * Action   : CREATE
 */

define('BASE_URL', '../');
$currentPage = 'quizzes';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Validate quiz ID ───────────────────────────────────────────
$quizId = (int)($_GET['id'] ?? 0);
if ($quizId <= 0) {
    header('Location: quiz-list.php');
    exit();
}

// ── Fetch quiz ─────────────────────────────────────────────────
$qStmt = $conn->prepare("
    SELECT q.*, c.title AS course_title
    FROM quizzes q
    INNER JOIN courses c ON c.id = q.course_id
    WHERE q.id = ? LIMIT 1
");
$qStmt->bind_param('i', $quizId);
$qStmt->execute();
$quiz = $qStmt->get_result()->fetch_assoc();
$qStmt->close();

if (!$quiz) {
    $_SESSION['flash_error'] = 'Quiz not found.';
    header('Location: quiz-list.php');
    exit();
}

$errors  = [];
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Handle DELETE single question ──────────────────────────────
if (isset($_GET['delete_question']) && isset($_GET['csrf'])) {
    if (hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        $dqId = (int)$_GET['delete_question'];
        $dStmt = $conn->prepare("DELETE FROM quiz_questions WHERE id=? AND quiz_id=? LIMIT 1");
        $dStmt->bind_param('ii', $dqId, $quizId);
        $dStmt->execute();
        $dStmt->close();
        $_SESSION['flash_success'] = 'Question deleted.';
    }
    header('Location: edit-quiz.php?id=' . $quizId);
    exit();
}

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Action A: Update quiz settings ─────────────────────
        if ($action === 'update_quiz') {
            $title        = trim(htmlspecialchars($_POST['title']       ?? ''));
            $description  = trim(htmlspecialchars($_POST['description'] ?? ''));
            $pass_percent = max(1, min(100, (int)($_POST['pass_percent'] ?? 70)));
            $time_limit   = max(0, (int)($_POST['time_limit'] ?? 0));
            $status       = in_array($_POST['status'] ?? '', ['active','inactive'])
                            ? $_POST['status'] : 'active';

            if (empty($title)) {
                $errors[] = 'Quiz title is required.';
            } else {
                $upd = $conn->prepare("
                    UPDATE quizzes
                    SET title=?, description=?, pass_percent=?, time_limit=?, status=?
                    WHERE id=?
                ");
                $upd->bind_param('ssiisi', $title, $description, $pass_percent,
                                            $time_limit, $status, $quizId);
                if ($upd->execute()) {
                    // Refresh quiz data
                    $qStmt = $conn->prepare("SELECT q.*, c.title AS course_title FROM quizzes q INNER JOIN courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
                    $qStmt->bind_param('i', $quizId);
                    $qStmt->execute();
                    $quiz = $qStmt->get_result()->fetch_assoc();
                    $qStmt->close();
                    $success = 'Quiz settings updated successfully.';
                } else {
                    $errors[] = 'Update failed: ' . $conn->error;
                }
                $upd->close();
            }
        }

        // ── Action B: Add a new question ───────────────────────
        if ($action === 'add_question') {
            $qText   = trim(htmlspecialchars($_POST['question_text'] ?? ''));
            $optA    = trim(htmlspecialchars($_POST['option_a'] ?? ''));
            $optB    = trim(htmlspecialchars($_POST['option_b'] ?? ''));
            $optC    = trim(htmlspecialchars($_POST['option_c'] ?? ''));
            $optD    = trim(htmlspecialchars($_POST['option_d'] ?? ''));
            $correct = strtoupper(trim($_POST['correct_option'] ?? ''));
            $marks   = max(1, (int)($_POST['marks'] ?? 1));

            if (empty($qText))  $errors[] = 'Question text is required.';
            if (empty($optA))   $errors[] = 'Option A is required.';
            if (empty($optB))   $errors[] = 'Option B is required.';
            if (empty($optC))   $errors[] = 'Option C is required.';
            if (empty($optD))   $errors[] = 'Option D is required.';
            if (!in_array($correct, ['A','B','C','D'])) $errors[] = 'Select a correct answer.';

            if (empty($errors)) {
                // Next sort order
                $r = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS nxt FROM quiz_questions WHERE quiz_id=?");
                $r->bind_param('i', $quizId); $r->execute();
                $sortOrder = (int)$r->get_result()->fetch_assoc()['nxt'];
                $r->close();

                $ins = $conn->prepare("
                    INSERT INTO quiz_questions
                        (quiz_id, question_text, option_a, option_b, option_c, option_d,
                         correct_option, marks, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->bind_param('isssssisi',
                    $quizId, $qText, $optA, $optB, $optC, $optD,
                    $correct, $marks, $sortOrder
                );
                if ($ins->execute()) {
                    $success = 'Question added successfully.';
                } else {
                    $errors[] = 'Could not add question: ' . $conn->error;
                }
                $ins->close();
            }
        }
    }
}

// ── Fetch all questions for this quiz ──────────────────────────
$qqStmt = $conn->prepare("
    SELECT id, question_text, option_a, option_b, option_c, option_d,
           correct_option, marks, sort_order
    FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY sort_order ASC, id ASC
");
$qqStmt->bind_param('i', $quizId);
$qqStmt->execute();
$questions = $qqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qqStmt->close();

// ── Attempt count ──────────────────────────────────────────────
$attStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id=?");
$attStmt->bind_param('i', $quizId);
$attStmt->execute();
$attemptCount = (int)$attStmt->get_result()->fetch_assoc()['cnt'];
$attStmt->close();

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .q-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1.1rem 1.25rem;
            margin-bottom: .75rem; position: relative;
        }
        .q-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; background: var(--brand-light);
            color: var(--brand); border-radius: 50%;
            font-family: 'Sora', sans-serif; font-weight: 700;
            font-size: .72rem; flex-shrink: 0; margin-right: .6rem;
        }
        .q-text { font-weight: 600; font-size: .88rem; color: var(--text); margin-bottom: .6rem; }
        .q-opts { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem; }
        .q-opt {
            font-size: .78rem; color: var(--text-muted);
            padding: .3rem .6rem; border-radius: 5px;
            border: 1px solid var(--border);
            display: flex; align-items: center; gap: .4rem;
        }
        .q-opt.correct { background: #f0fdf4; border-color: #86efac; color: #15803d; font-weight: 600; }
        .q-opt-letter { font-weight: 700; width: 16px; flex-shrink: 0; }
        .q-delete {
            position: absolute; top: .75rem; right: .75rem;
        }
        .correct-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .68rem; font-weight: 700;
            background: #f0fdf4; border: 1px solid #86efac;
            color: #15803d; border-radius: 20px; padding: .15em .65em;
        }
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
                <div class="page-title">Edit Quiz</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="quiz-list.php">Quizzes</a> &rsaquo; Edit
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

        <!-- Alerts -->
        <?php if ($flashSuccess || $success): ?>
        <div class="lms-alert lms-alert-success" data-autohide>
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($flashSuccess ?: $success) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
        <div class="lms-alert lms-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>
                <?php if (count($errors) === 1): ?><?= $errors[0] ?>
                <?php else: ?>
                <ul class="mb-0 ps-3 mt-1">
                    <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quiz info banner -->
        <div style="background:linear-gradient(135deg,#1e40af,#2563eb);border-radius:var(--radius);padding:1.25rem 1.5rem;color:#fff;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
            <div>
                <div style="font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:.2rem;">
                    <?= htmlspecialchars($quiz['title']) ?>
                </div>
                <div style="font-size:.82rem;opacity:.8;">
                    Course: <?= htmlspecialchars($quiz['course_title']) ?>
                    &nbsp;·&nbsp; <?= count($questions) ?> questions
                    &nbsp;·&nbsp; <?= $attemptCount ?> attempts
                    &nbsp;·&nbsp; Pass: <?= $quiz['pass_percent'] ?>%
                </div>
            </div>
            <a href="quiz-attempts.php?quiz_id=<?= $quizId ?>"
               style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:8px;padding:.4rem .9rem;font-size:.82rem;text-decoration:none;font-weight:600;">
                <i class="bi bi-people-fill me-1"></i> View Attempts
            </a>
        </div>

        <div class="row g-3">

            <!-- LEFT: Quiz settings + questions list -->
            <div class="col-lg-7">

                <!-- Quiz Settings -->
                <div class="lms-card mb-3">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-gear-fill"></i> Quiz Settings</h5>
                    </div>
                    <div class="lms-card-body">
                        <form method="POST" action="edit-quiz.php?id=<?= $quizId ?>" data-loading>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action"     value="update_quiz">

                            <div class="mb-3">
                                <label class="lms-label">Quiz Title <span class="req">*</span></label>
                                <input type="text" name="title" class="lms-input"
                                       value="<?= htmlspecialchars($quiz['title']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="lms-label">Description</label>
                                <textarea name="description" class="lms-textarea" style="min-height:70px;"><?= htmlspecialchars($quiz['description'] ?? '') ?></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="lms-label">Pass % <span class="req">*</span></label>
                                    <input type="number" name="pass_percent" class="lms-input"
                                           min="1" max="100" value="<?= (int)$quiz['pass_percent'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="lms-label">Time Limit (min)</label>
                                    <input type="number" name="time_limit" class="lms-input"
                                           min="0" value="<?= (int)$quiz['time_limit'] ?>">
                                    <div class="field-hint">0 = No limit</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="lms-label">Status</label>
                                    <select name="status" class="lms-select">
                                        <option value="active"   <?= $quiz['status']==='active'   ? 'selected':'' ?>>Active</option>
                                        <option value="inactive" <?= $quiz['status']==='inactive' ? 'selected':'' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-lms-primary">
                                <i class="bi bi-check-circle-fill"></i> Update Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Questions List -->
                <div class="lms-card">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title">
                            <i class="bi bi-list-ol"></i> Questions
                            <span style="background:var(--brand-light);color:var(--brand);border-radius:20px;padding:.1em .6em;font-size:.72rem;margin-left:.3rem;">
                                <?= count($questions) ?>
                            </span>
                        </h5>
                    </div>
                    <div style="padding:1rem 1.25rem;">
                        <?php if (empty($questions)): ?>
                        <div style="text-align:center;padding:2rem;color:var(--text-muted);">
                            <i class="bi bi-patch-question" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#cbd5e1;"></i>
                            No questions yet. Add your first question →
                        </div>
                        <?php else: ?>
                        <?php foreach ($questions as $i => $q): ?>
                        <div class="q-card">
                            <div style="display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.6rem;">
                                <span class="q-num"><?= $i + 1 ?></span>
                                <div style="flex:1;">
                                    <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>
                                    <div class="q-opts">
                                        <?php foreach (['A','B','C','D'] as $letter):
                                            $optKey = 'option_' . strtolower($letter);
                                            $isCorrect = $q['correct_option'] === $letter;
                                        ?>
                                        <div class="q-opt <?= $isCorrect ? 'correct' : '' ?>">
                                            <span class="q-opt-letter"><?= $letter ?>.</span>
                                            <?= htmlspecialchars($q[$optKey]) ?>
                                            <?php if ($isCorrect): ?>
                                            <i class="bi bi-check-circle-fill ms-auto" style="font-size:.75rem;color:#16a34a;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="margin-top:.5rem;display:flex;align-items:center;gap:.5rem;">
                                        <span class="correct-badge">
                                            <i class="bi bi-check-circle-fill"></i>
                                            Answer: <?= $q['correct_option'] ?>
                                        </span>
                                        <span style="font-size:.7rem;color:var(--text-muted);">
                                            <?= (int)$q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="q-delete">
                                <a href="edit-quiz.php?id=<?= $quizId ?>&delete_question=<?= $q['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                   class="btn-icon delete" title="Delete question"
                                   data-confirm="Delete this question?">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Add question form -->
            <div class="col-lg-5">
                <div class="lms-card" style="position:sticky;top:90px;">
                    <div class="lms-card-header">
                        <h5 class="lms-card-title"><i class="bi bi-plus-circle-fill"></i> Add Question</h5>
                    </div>
                    <div class="lms-card-body">
                        <form method="POST" action="edit-quiz.php?id=<?= $quizId ?>" data-loading>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action"     value="add_question">

                            <div class="mb-3">
                                <label class="lms-label">Question <span class="req">*</span></label>
                                <textarea name="question_text" class="lms-textarea"
                                          style="min-height:80px;"
                                          placeholder="Enter the question..." required></textarea>
                            </div>

                            <?php foreach (['A','B','C','D'] as $letter): ?>
                            <div class="mb-2">
                                <label class="lms-label">Option <?= $letter ?> <span class="req">*</span></label>
                                <input type="text" name="option_<?= strtolower($letter) ?>"
                                       class="lms-input" placeholder="Option <?= $letter ?>" required>
                            </div>
                            <?php endforeach; ?>

                            <div class="row g-2 mb-3 mt-1">
                                <div class="col-7">
                                    <label class="lms-label">Correct Answer <span class="req">*</span></label>
                                    <select name="correct_option" class="lms-select" required>
                                        <option value="">— Select —</option>
                                        <?php foreach (['A','B','C','D'] as $l): ?>
                                        <option value="<?= $l ?>">Option <?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="lms-label">Marks</label>
                                    <input type="number" name="marks" class="lms-input" min="1" value="1">
                                </div>
                            </div>

                            <button type="submit" class="btn-lms-primary w-100" style="justify-content:center;">
                                <i class="bi bi-plus-lg"></i> Add Question
                            </button>
                        </form>
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
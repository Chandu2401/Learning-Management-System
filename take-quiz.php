<?php
/**
 * take-quiz.php — Student Takes Quiz
 * Placement: lms-project/take-quiz.php
 * Action   : CREATE
 *
 * GET  ?course_id=X  — Load quiz for that course
 * POST              — Submit answers, calculate score, save attempt
 */

$currentPage = 'my_quizzes';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
if ($courseId <= 0) {
    header('Location: browse-courses.php');
    exit();
}

// ── Verify student has COMPLETED all lessons before taking quiz ─
// enrollment.status = 'completed' is set by mark-complete.php
// only after ALL published lessons in the course are marked done.
// 'active' means still in progress — quiz must remain locked.
// 'dropped' — cannot access quiz.
// Exception: if the course has ZERO published lessons, allow access.
$enrollStmt = $conn->prepare(
    "SELECT id, status FROM enrollments
     WHERE student_id=? AND course_id=? AND status IN ('active','completed')
     LIMIT 1"
);
$enrollStmt->bind_param('ii', $authUserId, $courseId);
$enrollStmt->execute();
$enrollRow = $enrollStmt->get_result()->fetch_assoc();
$enrolled  = !empty($enrollRow);
$enrollStmt->close();

if (!$enrolled) {
    $_SESSION['flash_error'] = 'You must be enrolled to take this quiz.';
    header('Location: course-details.php?id=' . $courseId);
    exit();
}

// If enrollment is 'active' (lessons not all done), check if course has
// any lessons at all. If it does, the student must finish them first.
if (($enrollRow['status'] ?? '') === 'active') {
    $lessonCountStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM lessons WHERE course_id=? AND status='published'"
    );
    $lessonCountStmt->bind_param('i', $courseId);
    $lessonCountStmt->execute();
    $lessonCount = (int)$lessonCountStmt->get_result()->fetch_assoc()['cnt'];
    $lessonCountStmt->close();

    if ($lessonCount > 0) {
        $_SESSION['flash_error'] = 'Complete all lessons first to unlock the quiz.';
        header('Location: course-details.php?id=' . $courseId);
        exit();
    }
}

// ── Fetch quiz ─────────────────────────────────────────────────
$qStmt = $conn->prepare("
    SELECT q.id, q.title, q.description, q.pass_percent, q.time_limit,
           c.title AS course_title
    FROM quizzes q
    INNER JOIN courses c ON c.id = q.course_id
    WHERE q.course_id = ? AND q.status = 'active'
    LIMIT 1
");
$qStmt->bind_param('i', $courseId);
$qStmt->execute();
$quiz = $qStmt->get_result()->fetch_assoc();
$qStmt->close();

if (!$quiz) {
    $_SESSION['flash_error'] = 'No active quiz found for this course.';
    header('Location: course-details.php?id=' . $courseId);
    exit();
}

$quizId = (int) $quiz['id'];

// ── Fetch questions ────────────────────────────────────────────
$qqStmt = $conn->prepare("
    SELECT id, question_text, option_a, option_b, option_c, option_d, marks
    FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY sort_order ASC, id ASC
");
$qqStmt->bind_param('i', $quizId);
$qqStmt->execute();
$questionsRes = $qqStmt->get_result();
$questions = [];
while ($row = $questionsRes->fetch_assoc())
    $questions[] = $row;
$qqStmt->close();

if (empty($questions)) {
    $_SESSION['flash_error'] = 'This quiz has no questions yet.';
    header('Location: course-details.php?id=' . $courseId);
    exit();
}

// ── Handle POST: calculate and save score ──────────────────────
$result = null;  // null = not submitted yet

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid submission.';
        header('Location: take-quiz.php?course_id=' . $courseId);
        exit();
    }

    $answers = $_POST['answers'] ?? [];
    $totalMarks = 0;
    $earnedMarks = 0;
    $answerLog = [];

    foreach ($questions as $q) {
        $qId = (int) $q['id'];
        $marks = (int) $q['marks'];
        $selected = strtoupper(trim($answers[$qId] ?? ''));
        $totalMarks += $marks;

        // Fetch correct answer (don't trust client)
        $correctStmt = $conn->prepare(
            "SELECT correct_option FROM quiz_questions WHERE id = ? LIMIT 1"
        );
        $correctStmt->bind_param('i', $qId);
        $correctStmt->execute();
        $correct = $correctStmt->get_result()->fetch_assoc()['correct_option'] ?? '';
        $correctStmt->close();

        $isCorrect = ($selected === $correct);
        if ($isCorrect)
            $earnedMarks += $marks;

        $answerLog[$qId] = [
            'selected' => $selected,
            'correct' => $correct,
            'is_correct' => $isCorrect,
            'marks' => $marks,
        ];
    }

    $percentage = $totalMarks > 0 ? round(($earnedMarks / $totalMarks) * 100, 2) : 0;
    $passed = $percentage >= $quiz['pass_percent'];
    $resultStr = $passed ? 'pass' : 'fail';
    $answerJson = json_encode($answerLog);

    // Save attempt
    $saveStmt = $conn->prepare("
        INSERT INTO quiz_attempts
            (quiz_id, student_id, score, total_marks, percentage, result, answers)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $saveStmt->bind_param(
        'iiiiids',
        $quizId,
        $authUserId,
        $earnedMarks,
        $totalMarks,
        $percentage,
        $resultStr,
        $answerJson
    );
    $saveStmt->execute();
    $attemptId = $saveStmt->insert_id;
    $saveStmt->close();

    // Auto Generate Certificate
    // Rule: ONLY if (1) enrollments.status = 'completed' (all lessons done)
    //            AND (2) this quiz attempt result = 'pass'.
    // If either condition is missing, no certificate is created.
    $lessonsCompleted = false;
    $certificateIssued = false;

    if ($passed) {

        // Verify the student has actually completed all lessons for this course
        $enrollCheckStmt = $conn->prepare(
            "SELECT id FROM enrollments
             WHERE student_id = ? AND course_id = ? AND status = 'completed'
             LIMIT 1"
        );
        $enrollCheckStmt->bind_param('ii', $authUserId, $courseId);
        $enrollCheckStmt->execute();
        $lessonsCompleted = $enrollCheckStmt->get_result()->num_rows > 0;
        $enrollCheckStmt->close();

        if ($lessonsCompleted) {

            // Check certificate already exists (prevents duplicates,
            // in addition to the UNIQUE KEY on (student_id, course_id))
            $checkCert = $conn->prepare("
            SELECT id
            FROM certificates
            WHERE student_id = ? AND course_id = ?
            LIMIT 1
        ");

            $checkCert->bind_param('ii', $authUserId, $courseId);
            $checkCert->execute();
            $exists = $checkCert->get_result()->num_rows > 0;
            $checkCert->close();

            if (!$exists) {

                $certificateNo = 'CERT-' . time() . '-' . rand(1000, 9999);

                $certStmt = $conn->prepare("
                INSERT INTO certificates
                (student_id, course_id, certificate_no)
                VALUES (?, ?, ?)
            ");

                $certStmt->bind_param(
                    'iis',
                    $authUserId,
                    $courseId,
                    $certificateNo
                );

                $certStmt->execute();
                $certStmt->close();
            }

            // True whether the certificate already existed or was just
            // created — both cases mean the student has one available.
            $certificateIssued = true;
        }
        // else: quiz passed but lessons not yet completed — no certificate issued.
        //       $lessonsCompleted stays false so the result view can explain this.
    }

    $result = [
        'attempt_id' => $attemptId,
        'earned' => $earnedMarks,
        'total' => $totalMarks,
        'percentage' => $percentage,
        'passed' => $passed,
        'pass_percent' => $quiz['pass_percent'],
        'answer_log' => $answerLog,
        'lessons_completed' => $lessonsCompleted,
        'certificate_issued' => $certificateIssued,
    ];
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Refresh CSRF after use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> — LMS Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="assets/css/student.css" rel="stylesheet">
    <style>
        /* ── Quiz question card ── */
        .quiz-q-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.35rem 1.5rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow);
        }

        .quiz-q-num {
            font-size: .72rem;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: .5rem;
        }

        .quiz-q-text {
            font-family: 'Sora', sans-serif;
            font-size: .96rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            line-height: 1.45;
        }

        /* Custom radio option */
        .option-item {
            display: flex;
            align-items: center;
            padding: .6rem .9rem;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            margin-bottom: .45rem;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            gap: .75rem;
        }

        .option-item:hover {
            border-color: var(--brand);
            background: var(--brand-light);
        }

        .option-item input[type="radio"] {
            display: none;
        }

        .option-item.selected {
            border-color: var(--brand);
            background: var(--brand-light);
        }

        .option-item.correct {
            border-color: #16a34a;
            background: #f0fdf4;
        }

        .option-item.wrong {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .opt-letter {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: .78rem;
            border: 1.5px solid var(--border);
            transition: background .15s, border-color .15s;
        }

        .selected .opt-letter {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .correct .opt-letter {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }

        .wrong .opt-letter {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }

        .opt-text {
            font-size: .875rem;
            color: var(--text);
        }

        /* Result panel */
        .result-panel {
            border-radius: var(--radius);
            padding: 2rem 2rem 1.75rem;
            text-align: center;
            margin-bottom: 1.75rem;
            position: relative;
            overflow: hidden;
        }

        .result-panel.pass {
            background: linear-gradient(135deg, #14532d, #15803d, #16a34a);
            color: #fff;
        }

        .result-panel.fail {
            background: linear-gradient(135deg, #7f1d1d, #b91c1c, #dc2626);
            color: #fff;
        }

        .result-icon {
            font-size: 3rem;
            margin-bottom: .75rem;
        }

        .result-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: .4rem;
        }

        .result-score {
            font-size: 3.5rem;
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            line-height: 1;
            margin: .5rem 0;
        }

        .result-label {
            font-size: .9rem;
            opacity: .85;
            margin-bottom: 1.1rem;
        }

        .result-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: .75rem;
        }

        .result-meta-item {
            text-align: center;
        }

        .result-meta-val {
            font-family: 'Sora', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .result-meta-lbl {
            font-size: .75rem;
            opacity: .75;
        }

        /* Timer */
        .timer-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .6rem 1rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--text);
            position: sticky;
            top: 80px;
            z-index: 50;
            box-shadow: var(--shadow);
            margin-bottom: 1.25rem;
        }

        .timer-bar i {
            color: var(--brand);
        }

        .timer-bar.warning {
            border-color: #fca5a5;
            color: #dc2626;
        }

        .timer-bar.warning i {
            color: #dc2626;
        }

        /* Progress sticky bar */
        .quiz-progress-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .65rem 1.1rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow);
        }

        .qpb-track {
            background: #e2e8f0;
            border-radius: 20px;
            height: 6px;
            margin-top: .5rem;
            overflow: hidden;
        }

        .qpb-fill {
            background: var(--brand);
            height: 100%;
            border-radius: 20px;
            transition: width .3s ease;
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
                    <div class="st-page-title"><?= htmlspecialchars($quiz['title']) ?></div>
                    <div class="st-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> &rsaquo;
                        <a href="course-details.php?id=<?= $courseId ?>">Course</a> &rsaquo; Quiz
                    </div>
                </div>
            </div>
            <div class="st-topbar-right">
                <a href="course-details.php?id=<?= $courseId ?>" class="btn-outline-brand" style="font-size:.82rem;">
                    <i class="bi bi-arrow-left"></i> Back to Course
                </a>
            </div>
        </header>

        <main class="st-body">

            <?php if ($flashError): ?>
                <div class="st-alert st-alert-danger">
                    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($flashError) ?>
                </div>
            <?php endif; ?>

            <?php if ($result !== null): ?>
                <!-- ── RESULT VIEW ── -->

                <div class="result-panel <?= $result['passed'] ? 'pass' : 'fail' ?>">
                    <div class="result-icon"><?= $result['passed'] ? '🏆' : '😔' ?></div>
                    <div class="result-title">
                        <?= $result['passed'] ? 'Congratulations! You Passed!' : 'Better Luck Next Time' ?></div>
                    <div class="result-score"><?= number_format($result['percentage'], 1) ?>%</div>
                    <div class="result-label">
                        You scored <?= $result['earned'] ?> out of <?= $result['total'] ?> marks.
                        Pass mark: <?= $result['pass_percent'] ?>%
                    </div>

                    <?php if ($result['passed'] && $result['certificate_issued']): ?>
                        <div class="st-alert st-alert-success" style="margin-top:1rem;text-align:left;">
                            <i class="bi bi-award-fill"></i>
                            <span>Your certificate has been generated — check <a href="my-certificate.php">My Certificates</a>.</span>
                        </div>
                    <?php elseif ($result['passed'] && !$result['lessons_completed']): ?>
                        <div class="st-alert st-alert-info" style="margin-top:1rem;text-align:left;">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>You passed the quiz, but your certificate isn't ready yet — complete all remaining lessons in this course to unlock it.</span>
                        </div>
                    <?php endif; ?>

                    <div class="result-meta">
                        <div class="result-meta-item">
                            <div class="result-meta-val"><?= count($result['answer_log']) ?></div>
                            <div class="result-meta-lbl">Questions</div>
                        </div>
                        <div class="result-meta-item">
                            <div class="result-meta-val">
                                <?= count(array_filter($result['answer_log'], fn($a) => $a['is_correct'])) ?>
                            </div>
                            <div class="result-meta-lbl">Correct</div>
                        </div>
                        <div class="result-meta-item">
                            <div class="result-meta-val">
                                <?= count(array_filter($result['answer_log'], fn($a) => !$a['is_correct'])) ?>
                            </div>
                            <div class="result-meta-lbl">Incorrect</div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.75rem;">
                    <a href="my-quizzes.php" class="btn-brand">
                        <i class="bi bi-list-check"></i> My Quiz Results
                    </a>
                    <a href="take-quiz.php?course_id=<?= $courseId ?>" class="btn-outline-brand">
                        <i class="bi bi-arrow-repeat"></i> Retake Quiz
                    </a>
                    <a href="course-details.php?id=<?= $courseId ?>" class="btn-outline-brand">
                        <i class="bi bi-book-fill"></i> Back to Course
                    </a>
                </div>

                <!-- Answer review -->
                <div class="st-card">
                    <div class="st-card-header">
                        <h5 class="st-card-title"><i class="bi bi-journal-check"></i> Answer Review</h5>
                    </div>
                    <div class="st-card-body" style="padding:1rem 1.25rem;">
                        <?php foreach ($questions as $qi => $q):
                            $qId = (int) $q['id'];
                            $log = $result['answer_log'][$qId] ?? [];
                            $sel = $log['selected'] ?? '';
                            $cor = $log['correct'] ?? '';
                            $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                            ?>
                            <div class="quiz-q-card" style="margin-bottom:.85rem;">
                                <div class="quiz-q-num">Question <?= $qi + 1 ?></div>
                                <div class="quiz-q-text"><?= htmlspecialchars($q['question_text']) ?></div>
                                <?php foreach ($opts as $letter => $text):
                                    $cls = '';
                                    if ($letter === $cor)
                                        $cls = 'correct';
                                    elseif ($letter === $sel && $sel !== $cor)
                                        $cls = 'wrong';
                                    ?>
                                    <div class="option-item <?= $cls ?>" style="cursor:default;">
                                        <span class="opt-letter"><?= $letter ?></span>
                                        <span class="opt-text"><?= htmlspecialchars($text) ?></span>
                                        <?php if ($letter === $cor): ?>
                                            <i class="bi bi-check-circle-fill ms-auto" style="color:#16a34a;"></i>
                                        <?php elseif ($letter === $sel && $sel !== $cor): ?>
                                            <i class="bi bi-x-circle-fill ms-auto" style="color:#dc2626;"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($sel)): ?>
                                    <div style="font-size:.78rem;color:#dc2626;margin-top:.35rem;">
                                        <i class="bi bi-exclamation-circle"></i> Not answered
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- ── QUIZ FORM ── -->

                <?php if ($quiz['description']): ?>
                    <div class="st-alert st-alert-info" style="margin-bottom:1.25rem;">
                        <i class="bi bi-info-circle-fill"></i>
                        <span><?= htmlspecialchars($quiz['description']) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Timer -->
                <?php if ($quiz['time_limit'] > 0): ?>
                    <div class="timer-bar" id="timerBar">
                        <i class="bi bi-clock-fill"></i>
                        <span id="timerDisplay">
                            <?= sprintf('%02d:%02d', floor($quiz['time_limit']), 0) ?>
                        </span>
                        &nbsp; remaining
                    </div>
                <?php endif; ?>

                <!-- Progress bar -->
                <div class="quiz-progress-bar">
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--text-muted);">
                        <span><?= count($questions) ?> questions &nbsp;·&nbsp; Pass: <?= $quiz['pass_percent'] ?>%</span>
                        <span id="answeredCount">0 / <?= count($questions) ?> answered</span>
                    </div>
                    <div class="qpb-track">
                        <div class="qpb-fill" id="progressFill" style="width:0%"></div>
                    </div>
                </div>

                <form method="POST" action="take-quiz.php?course_id=<?= $courseId ?>" id="quizForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">

                    <?php foreach ($questions as $qi => $q):
                        $qId = (int) $q['id'];
                        $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                        ?>
                        <div class="quiz-q-card">
                            <div class="quiz-q-num">
                                Question <?= $qi + 1 ?> of <?= count($questions) ?>
                                &nbsp;·&nbsp; <?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?>
                            </div>
                            <div class="quiz-q-text"><?= htmlspecialchars($q['question_text']) ?></div>

                            <?php foreach ($opts as $letter => $text): ?>
                                <label class="option-item" id="opt_<?= $qId ?>_<?= $letter ?>">
                                    <input type="radio" name="answers[<?= $qId ?>]" value="<?= $letter ?>"
                                        onchange="selectOption(<?= $qId ?>, '<?= $letter ?>', this)">
                                    <span class="opt-letter"><?= $letter ?></span>
                                    <span class="opt-text"><?= htmlspecialchars($text) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Submit -->
                    <div style="text-align:center;margin-top:1.5rem;">
                        <button type="button" id="submitBtn" class="btn-brand" style="padding:.72rem 2.5rem;font-size:1rem;"
                            onclick="confirmSubmit()">
                            <i class="bi bi-send-fill"></i> Submit Quiz
                        </button>
                    </div>
                </form>

            <?php endif; ?>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Sidebar toggle ──────────────────────────────────────────────
        const stSidebar = document.getElementById('stSidebar');
        const stToggle = document.getElementById('stToggle');
        const stOverlay = document.getElementById('stOverlay');
        stToggle?.addEventListener('click', () => { stSidebar.classList.toggle('open'); stOverlay.classList.toggle('show'); });
        stOverlay?.addEventListener('click', () => { stSidebar.classList.remove('open'); stOverlay.classList.remove('show'); });

        <?php if ($result === null): ?>

            // ── Track answered questions for progress bar ───────────────────
            const totalQ = <?= count($questions) ?>;
            const answered = new Set();

            function selectOption(qId, letter, input) {
                // Remove selection highlight from all options of this question
                document.querySelectorAll('[id^="opt_' + qId + '_"]').forEach(el => {
                    el.classList.remove('selected');
                });
                // Highlight selected
                document.getElementById('opt_' + qId + '_' + letter)?.classList.add('selected');
                answered.add(qId);
                updateProgress();
            }

            function updateProgress() {
                const n = answered.size;
                document.getElementById('answeredCount').textContent = n + ' / ' + totalQ + ' answered';
                document.getElementById('progressFill').style.width = Math.round((n / totalQ) * 100) + '%';
            }

            function confirmSubmit() {
                const unanswered = totalQ - answered.size;
                let msg = 'Submit your quiz now?';
                if (unanswered > 0) {
                    msg = unanswered + ' question(s) unanswered. Submit anyway?';
                }
                if (confirm(msg)) {
                    document.getElementById('quizForm').submit();
                }
            }

            // ── Countdown timer ─────────────────────────────────────────────
            <?php if ($quiz['time_limit'] > 0): ?>
                let secondsLeft = <?= (int) $quiz['time_limit'] * 60 ?>;
                const timerDisplay = document.getElementById('timerDisplay');
                const timerBar = document.getElementById('timerBar');

                function updateTimer() {
                    const m = Math.floor(secondsLeft / 60);
                    const s = secondsLeft % 60;
                    timerDisplay.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');

                    if (secondsLeft <= 60) timerBar.classList.add('warning');

                    if (secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        alert('Time is up! Your quiz will be submitted now.');
                        document.getElementById('quizForm').submit();
                        return;
                    }
                    secondsLeft--;
                }

                updateTimer();
                const timerInterval = setInterval(updateTimer, 1000);
            <?php endif; ?>

        <?php endif; ?>
    </script>
</body>

</html>
<?php
/**
 * admin/add-quiz.php — Create Quiz + Add MCQ Questions
 * Placement: lms-project/admin/add-quiz.php
 * Action   : CREATE
 */

define('BASE_URL', '../');
$currentPage = 'add_quiz';

require_once '../config/db.php';
require_once 'includes/auth.php';

$errors  = [];
$success = '';
$old     = [];

// Load courses that don't already have a quiz
$courseRes = $conn->query("
    SELECT c.id, c.title
    FROM courses c
    WHERE c.status IN ('active','draft')
    AND c.id NOT IN (SELECT course_id FROM quizzes)
    ORDER BY c.title ASC
");
$availableCourses = [];
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) $availableCourses[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        // Quiz fields
        $course_id    = (int)($_POST['course_id']    ?? 0);
        $title        = trim(htmlspecialchars($_POST['title']       ?? ''));
        $description  = trim(htmlspecialchars($_POST['description'] ?? ''));
        $pass_percent = max(1, min(100, (int)($_POST['pass_percent'] ?? 70)));
        $time_limit   = max(0, (int)($_POST['time_limit'] ?? 0));
        $status       = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        $old = compact('course_id','title','description','pass_percent','time_limit','status');

        // Validate
        if ($course_id <= 0) $errors[] = 'Please select a course.';
        if (empty($title))   $errors[] = 'Quiz title is required.';

        // Validate questions
        $questions = $_POST['questions'] ?? [];
        if (count($questions) < 1) $errors[] = 'Add at least 1 question.';

        $cleanQs = [];
        foreach ($questions as $idx => $q) {
            $qText   = trim(htmlspecialchars($q['question_text'] ?? ''));
            $optA    = trim(htmlspecialchars($q['option_a'] ?? ''));
            $optB    = trim(htmlspecialchars($q['option_b'] ?? ''));
            $optC    = trim(htmlspecialchars($q['option_c'] ?? ''));
            $optD    = trim(htmlspecialchars($q['option_d'] ?? ''));
            $correct = strtoupper(trim($q['correct_option'] ?? ''));
            $marks   = max(1, (int)($q['marks'] ?? 1));

            if (empty($qText) || empty($optA) || empty($optB) || empty($optC) || empty($optD)) {
                $errors[] = 'Question ' . ($idx + 1) . ': All 4 options are required.';
                continue;
            }
            if (!in_array($correct, ['A','B','C','D'])) {
                $errors[] = 'Question ' . ($idx + 1) . ': Select a correct answer.';
                continue;
            }
            $cleanQs[] = compact('qText','optA','optB','optC','optD','correct','marks');
        }

        if (empty($errors)) {
            // Insert quiz
           $stmt = $conn->prepare("
    INSERT INTO quizzes (course_id, title, description, pass_percent, time_limit, status)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issiis",
    $course_id,
    $title,
    $description,
    $pass_percent,
    $time_limit,
    $status
);

            if ($stmt->execute()) {
                $quizId = $stmt->insert_id;
                $stmt->close();

                // Insert questions
                $qStmt = $conn->prepare("
                    INSERT INTO quiz_questions
                        (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($cleanQs as $sortIdx => $q) {
                    $sortOrder = $sortIdx + 1;
                    $qStmt->bind_param(
                        'isssssiii',
                        $quizId,
                        $q['qText'], $q['optA'], $q['optB'], $q['optC'], $q['optD'],
                        $q['correct'], $q['marks'], $sortOrder
                    );
                    // Correct types: quiz_id=i, texts=s×6, correct=s, marks=i, sort=i
                    $qStmt->bind_param(
                        'issssssii',
                        $quizId,
                        $q['qText'], $q['optA'], $q['optB'], $q['optC'], $q['optD'],
                        $q['correct'], $q['marks'], $sortOrder
                    );
                    $qStmt->execute();
                }
                $qStmt->close();

                $success = 'Quiz <strong>' . htmlspecialchars($title) . '</strong> created with ' . count($cleanQs) . ' question(s).';
                $old = [];

                // Refresh available courses
                $courseRes = $conn->query("
                    SELECT c.id, c.title FROM courses c
                    WHERE c.status IN ('active','draft')
                    AND c.id NOT IN (SELECT course_id FROM quizzes)
                    ORDER BY c.title ASC
                ");
                $availableCourses = [];
                if ($courseRes) while ($r = $courseRes->fetch_assoc()) $availableCourses[] = $r;

            } else {
                $errors[] = 'Database error: ' . $conn->error;
                $stmt->close();
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Quiz — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .question-block {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.4rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .question-block .q-number {
            font-family: 'Sora', sans-serif;
            font-size: .78rem; font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .85rem;
        }
        .option-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .65rem;
            margin-bottom: .65rem;
        }
        .option-label {
            display: flex; align-items: center; gap: .5rem;
            font-size: .8rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: .25rem;
        }
        .option-badge {
            width: 22px; height: 22px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700;
            flex-shrink: 0;
        }
        .ob-a { background: #dbeafe; color: #1d4ed8; }
        .ob-b { background: #dcfce7; color: #15803d; }
        .ob-c { background: #fef3c7; color: #92400e; }
        .ob-d { background: #fee2e2; color: #991b1b; }

        .remove-q-btn {
            position: absolute; top: .75rem; right: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 6px; color: var(--danger);
            padding: .25rem .5rem; font-size: .78rem;
            cursor: pointer;
        }
        .remove-q-btn:hover { background: #fee2e2; }
        #addQuestionBtn {
            display: flex; align-items: center; gap: .45rem;
            width: 100%; justify-content: center;
            padding: .75rem; border: 2px dashed var(--border);
            border-radius: var(--radius); background: none;
            color: var(--brand); font-weight: 600; font-size: .875rem;
            cursor: pointer; transition: border-color .2s, background .2s;
        }
        #addQuestionBtn:hover { border-color: var(--brand); background: var(--brand-light); }
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
                <div class="page-title">Add Quiz</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="quiz-list.php">Quizzes</a> &rsaquo; Add
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="quiz-list.php" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> <span>Back</span>
            </a>
        </div>
    </header>

    <main class="lms-body">

        <?php if (!empty($errors)): ?>
        <div class="lms-alert lms-alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>
                <?php if (count($errors) === 1): ?>
                    <?= $errors[0] ?>
                <?php else: ?>
                    <strong>Please fix:</strong>
                    <ul class="mb-0 mt-1 ps-3"><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="lms-alert lms-alert-success" data-autohide>
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <?= $success ?> —
                <a href="quiz-list.php" style="color:inherit;font-weight:700;">View Quizzes</a>
                &nbsp;|&nbsp;
                <a href="add-quiz.php" style="color:inherit;font-weight:700;">Add Another</a>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="add-quiz.php" id="quizForm" data-loading novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <!-- LEFT: Quiz details -->
                <div class="col-lg-8">

                    <!-- Quiz Info -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title">
                                <i class="bi bi-patch-question-fill"></i> Quiz Details
                            </h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="mb-3">
                                <label class="lms-label">Course <span class="req">*</span></label>
                                <?php if (empty($availableCourses)): ?>
                                <div class="lms-alert lms-alert-warning" style="margin:0;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    All courses already have a quiz, or no courses exist.
                                </div>
                                <?php else: ?>
                                <select name="course_id" class="lms-select" required>
                                    <option value="">— Select a course —</option>
                                    <?php foreach ($availableCourses as $c): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= ((int)($old['course_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['title']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-hint">Only one quiz allowed per course.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Quiz Title <span class="req">*</span></label>
                                <input type="text" name="title" class="lms-input"
                                       placeholder="e.g. Final Assessment"
                                       value="<?= htmlspecialchars($old['title'] ?? '') ?>"
                                       maxlength="200" required>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Description</label>
                                <textarea name="description" class="lms-textarea"
                                          placeholder="Instructions for students..."><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Questions builder -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title">
                                <i class="bi bi-list-ol"></i> Questions
                            </h5>
                            <span id="qCount" style="font-size:.78rem;color:var(--text-muted);">0 questions</span>
                        </div>
                        <div class="lms-card-body">

                            <div id="questionsContainer"></div>

                            <button type="button" id="addQuestionBtn">
                                <i class="bi bi-plus-lg"></i> Add Question
                            </button>

                        </div>
                    </div>

                </div>

                <!-- RIGHT: Settings -->
                <div class="col-lg-4">

                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-sliders"></i> Settings</h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="mb-3">
                                <label class="lms-label">Pass Percentage (%) <span class="req">*</span></label>
                                <input type="number" name="pass_percent" class="lms-input"
                                       min="1" max="100"
                                       value="<?= (int)($old['pass_percent'] ?? 70) ?>">
                                <div class="field-hint">Students need this % to pass.</div>
                            </div>
                            <div class="mb-3">
                                <label class="lms-label">Time Limit (minutes)</label>
                                <input type="number" name="time_limit" class="lms-input"
                                       min="0" placeholder="0 = unlimited"
                                       value="<?= (int)($old['time_limit'] ?? 0) ?>">
                            </div>
                            <div>
                                <label class="lms-label">Status</label>
                                <?php foreach (['active'=>'Active','inactive'=>'Inactive'] as $val=>$label): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio"
                                           name="status" value="<?= $val ?>"
                                           <?= (($old['status'] ?? 'active') === $val) ? 'checked' : '' ?>>
                                    <label class="form-check-label" style="font-size:.85rem;"><?= $label ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-lms-primary w-100"
                            style="justify-content:center;padding:.7rem;"
                            <?= empty($availableCourses) ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle-fill"></i> Save Quiz
                    </button>
                    <a href="quiz-list.php" class="btn-lms-outline w-100 mt-2"
                       style="justify-content:center;">Cancel</a>

                </div>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
let qIndex = 0;

function updateQCount() {
    const n = document.querySelectorAll('.question-block').length;
    document.getElementById('qCount').textContent = n + ' question' + (n !== 1 ? 's' : '');
}

function buildQuestion(idx) {
    return `
    <div class="question-block" id="qblock_${idx}">
        <button type="button" class="remove-q-btn" onclick="removeQuestion(${idx})">
            <i class="bi bi-x-lg"></i> Remove
        </button>
        <div class="q-number">Question ${document.querySelectorAll('.question-block').length + 1}</div>

        <div class="mb-3">
            <label class="lms-label">Question Text <span class="req">*</span></label>
            <textarea name="questions[${idx}][question_text]" class="lms-textarea"
                      rows="2" placeholder="Enter your question..." required></textarea>
        </div>

        <div class="option-row">
            <div>
                <div class="option-label"><span class="option-badge ob-a">A</span> Option A</div>
                <input type="text" name="questions[${idx}][option_a]"
                       class="lms-input" placeholder="Option A" required>
            </div>
            <div>
                <div class="option-label"><span class="option-badge ob-b">B</span> Option B</div>
                <input type="text" name="questions[${idx}][option_b]"
                       class="lms-input" placeholder="Option B" required>
            </div>
            <div>
                <div class="option-label"><span class="option-badge ob-c">C</span> Option C</div>
                <input type="text" name="questions[${idx}][option_c]"
                       class="lms-input" placeholder="Option C" required>
            </div>
            <div>
                <div class="option-label"><span class="option-badge ob-d">D</span> Option D</div>
                <input type="text" name="questions[${idx}][option_d]"
                       class="lms-input" placeholder="Option D" required>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-sm-6">
                <label class="lms-label">Correct Answer <span class="req">*</span></label>
                <select name="questions[${idx}][correct_option]" class="lms-select" required>
                    <option value="">— Select —</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>
            <div class="col-sm-6">
                <label class="lms-label">Marks</label>
                <input type="number" name="questions[${idx}][marks]"
                       class="lms-input" min="1" value="1">
            </div>
        </div>
    </div>`;
}

function addQuestion() {
    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', buildQuestion(qIndex++));
    renumberQuestions();
    updateQCount();
}

function removeQuestion(idx) {
    document.getElementById('qblock_' + idx)?.remove();
    renumberQuestions();
    updateQCount();
}

function renumberQuestions() {
    document.querySelectorAll('.question-block .q-number').forEach((el, i) => {
        el.textContent = 'Question ' + (i + 1);
    });
}

document.getElementById('addQuestionBtn').addEventListener('click', addQuestion);

// Add one question by default
addQuestion();
</script>
</body>
</html>
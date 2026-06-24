<?php
/**
 * mark-complete.php — Mark Lesson as Completed
 * ──────────────────────────────────────────────
 * Placement: lms-project/mark-complete.php
 *
 * Pure POST action handler — never renders HTML.
 * Always ends with redirect back to the course-details page.
 *
 * Flow:
 *  1. Auth guard
 *  2. Validate CSRF, lesson_id, course_id
 *  3. Confirm student is enrolled in the course
 *  4. Insert into lesson_progress (ignore duplicate — UNIQUE KEY)
 *  5. Check if ALL published lessons in course are now complete
 *     → If yes, update enrollments.status = 'completed'
 *  6. Redirect back to course-details.php?id=X with flash message
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse-courses.php');
    exit();
}

// ── CSRF ───────────────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: browse-courses.php');
    exit();
}

$lessonId = (int)($_POST['lesson_id']  ?? 0);
$courseId = (int)($_POST['course_id']  ?? 0);

if ($lessonId <= 0 || $courseId <= 0) {
    $_SESSION['flash_error'] = 'Invalid lesson or course.';
    header('Location: browse-courses.php');
    exit();
}

// ── Confirm student is enrolled and active ─────────────────────
$enrolStmt = $conn->prepare(
    "SELECT id FROM enrollments
     WHERE student_id = ? AND course_id = ? AND status = 'active'
     LIMIT 1"
);
$enrolStmt->bind_param('ii', $authUserId, $courseId);
$enrolStmt->execute();
$enrolled = $enrolStmt->get_result()->num_rows > 0;
$enrolStmt->close();

if (!$enrolled) {
    $_SESSION['flash_error'] = 'You are not enrolled in this course.';
    header('Location: course-details.php?id=' . $courseId);
    exit();
}

// ── Confirm lesson belongs to this course AND is published ─────
$lsnStmt = $conn->prepare(
    "SELECT id FROM lessons
     WHERE id = ? AND course_id = ? AND status = 'published'
     LIMIT 1"
);
$lsnStmt->bind_param('ii', $lessonId, $courseId);
$lsnStmt->execute();
$lessonValid = $lsnStmt->get_result()->num_rows > 0;
$lsnStmt->close();

if (!$lessonValid) {
    $_SESSION['flash_error'] = 'Lesson not found.';
    header('Location: course-details.php?id=' . $courseId);
    exit();
}

// ── Insert progress (ignore duplicate via INSERT IGNORE) ───────
$insStmt = $conn->prepare(
    "INSERT IGNORE INTO lesson_progress
        (student_id, lesson_id, course_id, status)
     VALUES (?, ?, ?, 'completed')"
);
$insStmt->bind_param('iii', $authUserId, $lessonId, $courseId);
$insStmt->execute();
$insStmt->close();

// ── Check if entire course is now complete ─────────────────────
// Total published lessons in this course
$totalStmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM lessons
     WHERE course_id = ? AND status = 'published'"
);
$totalStmt->bind_param('i', $courseId);
$totalStmt->execute();
$totalLessons = (int)$totalStmt->get_result()->fetch_assoc()['cnt'];
$totalStmt->close();

// How many of the currently-published lessons this student has completed
$doneStmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM lesson_progress lp
     INNER JOIN lessons l ON l.id = lp.lesson_id
     WHERE lp.student_id = ? AND lp.course_id = ? AND l.status = 'published'"
);
$doneStmt->bind_param('ii', $authUserId, $courseId);
$doneStmt->execute();
$doneLessons = (int)$doneStmt->get_result()->fetch_assoc()['cnt'];
$doneStmt->close();

// If all lessons done → mark enrollment as completed
if ($totalLessons > 0 && $doneLessons >= $totalLessons) {
    $complStmt = $conn->prepare(
        "UPDATE enrollments SET status = 'completed'
         WHERE student_id = ? AND course_id = ? AND status = 'active'"
    );
    $complStmt->bind_param('ii', $authUserId, $courseId);
    $complStmt->execute();
    $complStmt->close();

    $_SESSION['flash_success'] = '🎉 Congratulations! You have completed this course!';
} else {
    $_SESSION['flash_success'] = 'Lesson marked as completed.';
}

header('Location: course-details.php?id=' . $courseId);
exit();
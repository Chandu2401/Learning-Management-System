<?php
/**
 * admin/delete-quiz.php — Delete Quiz Action Handler
 * Placement: lms-project/admin/delete-quiz.php
 * Action   : CREATE
 */

define('BASE_URL', '../');

require_once '../config/db.php';
require_once 'includes/auth.php';

function redirectWithFlash(string $type, string $msg): void {
    $_SESSION['flash_' . $type] = $msg;
    header('Location: quiz-list.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithFlash('error', 'Invalid quiz ID.');
}

$tokenUrl = $_GET['csrf'] ?? '';
$tokenSes = $_SESSION['csrf_token'] ?? '';
if (empty($tokenUrl) || empty($tokenSes) || !hash_equals($tokenSes, $tokenUrl)) {
    redirectWithFlash('error', 'Security check failed.');
}

// Fetch quiz title before deleting
$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    redirectWithFlash('error', 'Quiz not found.');
}

// Delete — FK CASCADE handles questions and attempts
$del = $conn->prepare("DELETE FROM quizzes WHERE id = ? LIMIT 1");
$del->bind_param('i', $id);
if ($del->execute() && $del->affected_rows > 0) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    redirectWithFlash('success', 'Quiz "' . htmlspecialchars($quiz['title']) . '" deleted.');
} else {
    redirectWithFlash('error', 'Could not delete quiz. Please try again.');
}
$del->close();
<?php
/**
 * admin/delete-lesson.php — Delete a Lesson
 * ────────────────────────────────────────────
 * Placement: lms-project/admin/delete-lesson.php
 *
 * Pure action handler — never renders HTML.
 * Always ends with a redirect to lesson-list.php.
 *
 * Flow:
 *  1. Auth guard (admin only).
 *  2. Validate lesson ID.
 *  3. CSRF token validation.
 *  4. Fetch lesson record (need title + pdf_notes before deletion).
 *  5. Delete DB row.
 *  6. Delete PDF file from disk if it exists.
 *  7. Rotate CSRF token.
 *  8. Redirect with flash message.
 */

define('BASE_URL', '../');

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Helper ─────────────────────────────────────────────────────
function redirectWithFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
    header('Location: lesson-list.php');
    exit();
}

// ── 1. Validate ID ─────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithFlash('error', 'Invalid request: missing or malformed lesson ID.');
}

// ── 2. CSRF validation ─────────────────────────────────────────
$tokenFromUrl     = $_GET['csrf'] ?? '';
$tokenFromSession = $_SESSION['csrf_token'] ?? '';

if (empty($tokenFromUrl) || empty($tokenFromSession)) {
    redirectWithFlash('error', 'Security check failed: missing CSRF token. Please try again.');
}
if (!hash_equals($tokenFromSession, $tokenFromUrl)) {
    redirectWithFlash('error', 'Security check failed: invalid CSRF token. Please try again.');
}

// ── 3. Fetch lesson record ─────────────────────────────────────
$fetchStmt = $conn->prepare(
    "SELECT l.id, l.title, l.pdf_notes, c.title AS course_title
     FROM lessons l
     LEFT JOIN courses c ON c.id = l.course_id
     WHERE l.id = ? LIMIT 1"
);
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$lesson = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$lesson) {
    redirectWithFlash('error', 'Lesson not found. It may have already been deleted.');
}

$lessonTitle = $lesson['title'];
$pdfPath     = $lesson['pdf_notes'];   // e.g. "uploads/lessons/lesson_xxx.pdf" or NULL

// ── 4. Delete from database ────────────────────────────────────
$deleteStmt = $conn->prepare("DELETE FROM lessons WHERE id = ? LIMIT 1");
$deleteStmt->bind_param('i', $id);
$executed   = $deleteStmt->execute();
$affected   = $deleteStmt->affected_rows;
$deleteStmt->close();

if (!$executed || $affected === 0) {
    redirectWithFlash(
        'error',
        'Could not delete lesson "' . htmlspecialchars($lessonTitle) . '". Please try again.'
    );
}

// ── 5. Delete PDF file from disk ───────────────────────────────
// DB row is already gone; file cleanup is best-effort.
if (!empty($pdfPath)) {
    // Path relative to this file's directory (admin/) → go one level up
    $relativePath = dirname(__DIR__) . '/' . ltrim($pdfPath, '/');

    // Fallback: DOCUMENT_ROOT based path
    $absolutePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' .
                    ltrim(str_replace('..', '', $pdfPath), '/');

    if (file_exists($relativePath)) {
        @unlink($relativePath);
    } elseif (file_exists($absolutePath)) {
        @unlink($absolutePath);
    }
    // If neither path resolves, the file was already gone — not an error.
}

// ── 6. Rotate CSRF token ───────────────────────────────────────
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── 7. Redirect with success ───────────────────────────────────
redirectWithFlash(
    'success',
    'Lesson "' . htmlspecialchars($lessonTitle) . '" has been permanently deleted.'
);
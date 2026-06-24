<?php
/**
 * admin/delete-course.php — Delete a Course
 * ────────────────────────────────────────────────────────────────
 * Placement : lms-project/admin/delete-course.php
 *
 * Flow:
 *  1. Auth guard — admin only.
 *  2. Validate course ID from GET param.
 *  3. CSRF token validation (GET param passed by course-list / edit).
 *  4. Fetch course record (need image path before deleting row).
 *  5. Delete DB row with prepared statement.
 *  6. If DB delete succeeded → delete uploaded image file from disk.
 *  7. Set flash message in session → redirect to course-list.php.
 *
 * This file is a pure action handler — it never renders HTML.
 * It always ends with a redirect.
 */

define('BASE_URL', '../');

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Helpers ────────────────────────────────────────────────────

/**
 * Redirect to course list with a flash message and stop execution.
 */
function redirectWithFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
    header('Location: course-list.php');
    exit();
}

// ── 1. Validate course ID ──────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirectWithFlash('error', 'Invalid request: missing course ID.');
}

// ── 2. CSRF validation ─────────────────────────────────────────
//
// course-list.php  → delete-course.php?id=X&csrf=TOKEN   (GET link)
// edit-course.php  → delete-course.php?id=X&csrf=TOKEN   (GET link)
// admin/index.php  → delete-course.php?id=X              (no CSRF — handled below)
//
// If the CSRF param is present, it MUST match the session token.
// If it is absent (legacy link from index.php), we still require
// the session token to exist, but we cannot verify the value →
// reject to be safe (GET-based deletes without CSRF are dangerous).

$tokenFromUrl     = $_GET['csrf'] ?? '';
$tokenFromSession = $_SESSION['csrf_token'] ?? '';

if (empty($tokenFromUrl) || empty($tokenFromSession)) {
    redirectWithFlash('error', 'Security check failed: missing CSRF token. Please try again from the course list.');
}

if (!hash_equals($tokenFromSession, $tokenFromUrl)) {
    redirectWithFlash('error', 'Security check failed: invalid CSRF token. Please try again.');
}

// ── 3. Fetch course record (need title & image before deleting) ─
$fetchStmt = $conn->prepare(
    "SELECT id, title, image FROM courses WHERE id = ? LIMIT 1"
);
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$course = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$course) {
    redirectWithFlash('error', 'Course not found. It may have already been deleted.');
}

$courseTitle = $course['title'];
$imagePath   = $course['image'];   // e.g. "uploads/courses/course_1234_abc.jpg" or NULL

// ── 4. Delete the database row ─────────────────────────────────
$deleteStmt = $conn->prepare("DELETE FROM courses WHERE id = ? LIMIT 1");
$deleteStmt->bind_param('i', $id);
$executed   = $deleteStmt->execute();
$affected   = $deleteStmt->affected_rows;
$deleteStmt->close();

if (!$executed || $affected === 0) {
    redirectWithFlash(
        'error',
        'Could not delete "' . htmlspecialchars($courseTitle) . '". Please try again.'
    );
}

// ── 5. Delete uploaded image from disk ─────────────────────────
// Only attempt if a path was stored and the file physically exists.
// Use @ to suppress warnings — a missing file is non-critical at
// this point because the DB row is already gone.
if (!empty($imagePath)) {
    $absolutePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' .
                    ltrim(str_replace('..', '', $imagePath), '/');

    // Fallback: path relative to this file's directory (../uploads/...)
    $relativePath = dirname(__DIR__) . '/' . ltrim($imagePath, '/');

    if (file_exists($relativePath)) {
        @unlink($relativePath);
    } elseif (file_exists($absolutePath)) {
        @unlink($absolutePath);
    }
    // If neither exists the file was already gone — that's fine.
}

// ── 6. Regenerate CSRF token after destructive action ──────────
// Prevents the same token from being reused to delete another course.
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── 7. Redirect with success flash ────────────────────────────
redirectWithFlash(
    'success',
    'Course "' . htmlspecialchars($courseTitle) . '" has been permanently deleted.'
);
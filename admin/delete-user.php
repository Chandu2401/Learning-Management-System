<?php
/**
 * admin/delete-user.php — Delete a User (Action Handler)
 * ────────────────────────────────────────────────────────
 * Placement: lms-project/admin/delete-user.php
 *
 * Flow:
 *  1. Auth guard — admin only.
 *  2. Validate user ID from GET.
 *  3. CSRF token check.
 *  4. Block self-deletion.
 *  5. Fetch user record.
 *  6. Delete from DB.
 *  7. Flash message → redirect to users.php.
 *
 * Pure action handler — renders no HTML.
 * Always ends with a redirect.
 */

define('BASE_URL', '../');

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Helper ─────────────────────────────────────────────────────
function redirectWithFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
    header('Location: users.php');
    exit();
}

// ── 1. Validate ID ─────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithFlash('error', 'Invalid request: missing user ID.');
}

// ── 2. CSRF check ──────────────────────────────────────────────
$tokenFromUrl     = $_GET['csrf']              ?? '';
$tokenFromSession = $_SESSION['csrf_token']    ?? '';

if (empty($tokenFromUrl) || empty($tokenFromSession)) {
    redirectWithFlash('error', 'Security check failed: missing CSRF token. Please try again.');
}
if (!hash_equals($tokenFromSession, $tokenFromUrl)) {
    redirectWithFlash('error', 'Security check failed: invalid CSRF token. Please try again.');
}

// ── 3. Block self-deletion ─────────────────────────────────────
if ($id === (int)$_SESSION['user_id']) {
    redirectWithFlash('error', 'You cannot delete your own admin account.');
}

// ── 4. Fetch user (get name before deleting) ──────────────────
$fetchStmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$user = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$user) {
    redirectWithFlash('error', 'User not found. They may have already been deleted.');
}

// ── 5. Delete from DB ──────────────────────────────────────────
$deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
$deleteStmt->bind_param('i', $id);
$executed = $deleteStmt->execute();
$affected = $deleteStmt->affected_rows;
$deleteStmt->close();

if (!$executed || $affected === 0) {
    redirectWithFlash('error', 'Could not delete "' . htmlspecialchars($user['name']) . '". Please try again.');
}

// ── 6. Regenerate CSRF token ───────────────────────────────────
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── 7. Success ─────────────────────────────────────────────────
redirectWithFlash('success', 'User "' . htmlspecialchars($user['name']) . '" has been permanently deleted.');

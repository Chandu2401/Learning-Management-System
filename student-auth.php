<?php
/**
 * includes/student-auth.php
 * ─────────────────────────────────────────────
 * Placement: lms-project/includes/student-auth.php
 *
 * Include at top of every student-facing page.
 * Starts session, checks login, blocks admin-only pages.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?unauthorized=1');
    exit();
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handy globals available in every student page after this include
$authUserId   = (int)$_SESSION['user_id'];
$authUserName = htmlspecialchars($_SESSION['user_name'] ?? '');
$authUserRole = $_SESSION['user_role'] ?? 'student';
<?php
/**
 * admin/includes/auth.php
 * ─────────────────────────────────────────────────────────────
 * Include this at the very top of every admin page.
 * Starts the session, verifies login, and enforces admin role.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in → send to login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . BASE_URL . "login.php?unauthorized=1");
    exit();
}

// Logged in but not admin → send to student dashboard
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}
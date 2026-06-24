<?php
/**
 * config/db.php — Database Connection
 * Uses MySQLi with error reporting.
 * Include this file in every page that needs DB access.
 */

// ── Database credentials (move to .env in production) ─────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');       // Change to your DB username
define('DB_PASS',    '');           // Change to your DB password
define('DB_NAME',    'lms_db');
define('DB_CHARSET', 'utf8mb4');

// ── Create connection ──────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ── Check connection ───────────────────────────────────────────
if ($conn->connect_error) {
    // In production, log this error instead of displaying it
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(['error' => 'Service temporarily unavailable. Please try again later.']));
}

// ── Set charset ────────────────────────────────────────────────
if (!$conn->set_charset(DB_CHARSET)) {
    error_log("Error setting charset: " . $conn->error);
} 
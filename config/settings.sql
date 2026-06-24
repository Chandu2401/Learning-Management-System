-- ================================================================
-- LMS Database: settings table
-- File   : config/settings.sql
-- Run in : phpMyAdmin → lms_db → SQL tab → paste → Go
-- Depends: none — standalone key/value table
-- ================================================================

USE lms_db;

CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value VARCHAR(500) NOT NULL DEFAULT '',
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default values ───────────────────────────────────────────────
-- admin/settings.php reads these on load and will auto-insert any
-- missing key with its default the first time the page runs, so
-- running these INSERTs by hand is optional — they're here for
-- reference and for anyone setting up the database manually.

INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name',               'LearnHub'),
    ('site_tagline',            'Learn anything, anytime.'),
    ('admin_email',             'admin@example.com'),
    ('allow_registration',      '1'),
    ('default_user_role',       'student'),
    ('certificate_auto_generate', '1'),
    ('session_timeout',         '30'),
    ('password_min_length',     '6')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
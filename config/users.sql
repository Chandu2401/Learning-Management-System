-- =================================================================
-- users.sql — Users Table (Students & Admins)
-- =================================================================
-- Placement: lms-project/config/users.sql
--
-- NOTE: This file did not previously exist in the project, even
-- though every other table (courses, enrollments, lesson_progress,
-- quiz_attempts, certificates) has a foreign key pointing at
-- users.id. This script reconstructs the table definition strictly
-- from how the `users` table is actually used across the codebase,
-- so it matches the live database rather than introducing anything
-- new:
--
--   Column      Inferred from
--   ----------  --------------------------------------------------
--   id          FK target in enrollments.sql, certificates.sql,
--               lesson_progress.sql, quiz_tables.sql; courses.created_by
--   name        register.php INSERT, login.php SELECT, used as
--               u.name in admin/*.php and certificate pages
--   email       register.php duplicate-check + INSERT (UNIQUE — the
--               app-level duplicate check in register.php assumes
--               this), login.php SELECT ... WHERE email = ?
--   password    register.php password_hash() INSERT (bcrypt/argon2
--               output via PASSWORD_DEFAULT, max 255 chars),
--               login.php password_verify()
--   role        login.php SELECT ... role; only two values are ever
--               checked anywhere in the project: 'student' and
--               'admin' (see dashboard.php, admin/index.php, login.php)
--   created_at  used in dashboard.php for "recently joined users"
--               lists, ORDER BY created_at
--
-- Run this BEFORE courses.sql, enrollments.sql, lesson_progress.sql,
-- quiz_tables.sql, and certificates.sql, since they all declare a
-- foreign key referencing users(id).
-- =================================================================

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)      NOT NULL,
    email         VARCHAR(190)      NOT NULL,
    password      VARCHAR(255)      NOT NULL,
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Optional: seed one admin account if your existing database does
-- not already have one. Skip this if an admin user already exists.
--
-- Replace the password hash below by generating your own with:
--   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT);"
-- -----------------------------------------------------------------
-- INSERT INTO users (name, email, password, role)
-- VALUES ('Admin', 'admin@example.com', '$2y$10$REPLACE_WITH_REAL_HASH', 'admin');
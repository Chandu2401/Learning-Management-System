-- ================================================================
-- LMS Database — Courses Table
-- File: config/courses.sql
-- Run this in your MySQL client or phpMyAdmin
-- ================================================================

-- Make sure you're using the correct database
USE lms_db;

-- ── courses ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS courses (
    id            INT           UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Core details
    title         VARCHAR(200)  NOT NULL,
    slug          VARCHAR(220)  NOT NULL UNIQUE,          -- URL-friendly title
    description   TEXT          NOT NULL,
    short_desc    VARCHAR(500)  DEFAULT NULL,             -- Card/list excerpt

    -- Media
    image         VARCHAR(300)  DEFAULT NULL,             -- Relative path: uploads/courses/filename.jpg

    -- Classification
    category      VARCHAR(100)  DEFAULT NULL,
    level         ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    language      VARCHAR(60)   DEFAULT 'English',

    -- Pricing
    price         DECIMAL(8,2)  NOT NULL DEFAULT 0.00,    -- 0.00 = free
    is_free       TINYINT(1)    NOT NULL DEFAULT 1,

    -- Content meta
    duration      VARCHAR(60)   DEFAULT NULL,             -- e.g. "12 hours", "6 weeks"
    total_lessons INT           UNSIGNED DEFAULT 0,

    -- Status
    status        ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',

    -- Ownership
    created_by    INT           UNSIGNED NOT NULL,        -- FK → users.id

    -- Timestamps
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_status     (status),
    INDEX idx_level      (level),
    INDEX idx_created_by (created_by),
    INDEX idx_category   (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Sample seed data (optional — remove in production) ──────────
INSERT INTO courses (title, slug, description, short_desc, category, level, price, is_free, duration, total_lessons, status, created_by) VALUES
('PHP for Beginners', 'php-for-beginners', 'A comprehensive introduction to PHP programming from scratch.', 'Learn PHP basics and build your first dynamic web pages.', 'Web Development', 'beginner', 0.00, 1, '8 hours', 24, 'active', 1),
('Advanced MySQL', 'advanced-mysql', 'Deep dive into MySQL optimization, indexing, and stored procedures.', 'Take your MySQL skills to a professional level.', 'Database', 'advanced', 999.00, 0, '10 hours', 30, 'active', 1),
('Bootstrap 5 Masterclass', 'bootstrap-5-masterclass', 'Build responsive, professional websites using Bootstrap 5.', 'Master Bootstrap 5 grid, components, and utilities.', 'Web Design', 'intermediate', 499.00, 0, '6 hours', 18, 'draft', 1);
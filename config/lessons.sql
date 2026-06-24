-- ================================================================
-- LMS Database — Lessons Table
-- File: config/lessons.sql
-- Run this in your MySQL client or phpMyAdmin
-- after courses table already exists.
-- ================================================================

USE lms_db;

-- ── lessons ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lessons (
    id            INT           UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationship
    course_id     INT           UNSIGNED NOT NULL,               -- FK → courses.id

    -- Core fields
    title         VARCHAR(200)  NOT NULL,
    description   TEXT          DEFAULT NULL,

    -- Media
    video_url     VARCHAR(500)  DEFAULT NULL,                    -- YouTube embed URL
    pdf_notes     VARCHAR(300)  DEFAULT NULL,                    -- Relative path: uploads/lessons/filename.pdf

    -- Ordering & status
    sort_order    INT           UNSIGNED NOT NULL DEFAULT 0,     -- lesson position within course
    is_preview    TINYINT(1)    NOT NULL DEFAULT 0,              -- 1 = free preview for non-enrolled
    status        ENUM('published','draft') NOT NULL DEFAULT 'draft',

    -- Timestamps
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    CONSTRAINT fk_lesson_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,                                       -- deleting a course removes its lessons

    -- Indexes
    INDEX idx_course_id  (course_id),
    INDEX idx_sort_order (course_id, sort_order),
    INDEX idx_status     (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Sample seed data (optional — remove in production) ───────────
-- Assumes course IDs 1, 2, 3 from courses.sql seed data exist.
INSERT INTO lessons
    (course_id, title, description, video_url, sort_order, is_preview, status)
VALUES
    (1, 'Introduction to PHP',         'What is PHP and how the web works.',            'https://www.youtube.com/watch?v=OK_JCtrrv-c', 1, 1, 'published'),
    (1, 'Variables & Data Types',      'Scalars, arrays, and type juggling in PHP.',    'https://www.youtube.com/watch?v=OK_JCtrrv-c', 2, 0, 'published'),
    (1, 'Control Flow',                'If/else, loops, and switch statements.',        NULL,                                           3, 0, 'draft'),
    (2, 'Indexes & Query Optimization','How MySQL uses indexes to speed up queries.',   'https://www.youtube.com/watch?v=HubezKbFL7E', 1, 1, 'published'),
    (2, 'Stored Procedures',           'Writing and calling stored procedures.',        NULL,                                           2, 0, 'draft');
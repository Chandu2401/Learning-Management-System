-- ================================================================
-- LMS Database — Enrollments Table
-- File: config/enrollments.sql
-- Run in phpMyAdmin → lms_db → SQL tab
-- Requires: users table and courses table to exist first
-- ================================================================

USE lms_db;

CREATE TABLE IF NOT EXISTS enrollments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    student_id    INT UNSIGNED NOT NULL,          -- FK → users.id
    course_id     INT UNSIGNED NOT NULL,          -- FK → courses.id

    -- Status tracking
    status        ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',

    -- Timestamps
    enrolled_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One enrollment per student per course (prevents duplicates)
    UNIQUE KEY uq_student_course (student_id, course_id),

    -- Foreign keys
    CONSTRAINT fk_enroll_student
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,

    CONSTRAINT fk_enroll_course
        FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_student_id (student_id),
    INDEX idx_course_id  (course_id),
    INDEX idx_status     (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
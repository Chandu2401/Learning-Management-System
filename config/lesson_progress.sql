-- ============================================================
-- LMS Database: lesson_progress table
-- File   : config/lesson_progress.sql
-- Run in : phpMyAdmin → select lms_db → SQL tab → paste → Go
-- Depends: users, courses, lessons tables must exist first
-- ============================================================

USE lms_db;

CREATE TABLE IF NOT EXISTS lesson_progress (

    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    student_id   INT UNSIGNED      NOT NULL,
    lesson_id    INT UNSIGNED      NOT NULL,
    course_id    INT UNSIGNED      NOT NULL,
    status       ENUM('completed') NOT NULL DEFAULT 'completed',
    completed_at TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_student_lesson (student_id, lesson_id),

    INDEX idx_lp_student_course (student_id, course_id),
    INDEX idx_lp_lesson         (lesson_id),
    INDEX idx_lp_completed_at   (completed_at),

    CONSTRAINT fk_lp_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_lp_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_lp_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
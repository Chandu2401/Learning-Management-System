-- ================================================================
-- LMS Database: Quiz & Assessment Tables
-- File   : config/quiz_tables.sql
-- Run in : phpMyAdmin → lms_db → SQL tab → paste → Go
-- Depends: users, courses tables must exist first
-- ================================================================

USE lms_db;

-- ── Table 1: quizzes ──────────────────────────────────────────
-- One quiz per course (enforced by UNIQUE KEY on course_id)
CREATE TABLE IF NOT EXISTS quizzes (
    id            INT(11)      NOT NULL AUTO_INCREMENT,
    course_id     INT(10) UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         DEFAULT NULL,
    pass_percent  INT(3)       NOT NULL DEFAULT 70,
    time_limit    INT(5)       NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_quiz_course (course_id),
    INDEX idx_quiz_status (status),

    CONSTRAINT fk_quiz_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Table 2: quiz_questions ───────────────────────────────────
-- MCQ questions — each has 4 options, one correct answer
CREATE TABLE IF NOT EXISTS quiz_questions (
    id             INT(11)      NOT NULL AUTO_INCREMENT,
    quiz_id        INT(11)      NOT NULL,
    question_text  TEXT         NOT NULL,
    option_a       VARCHAR(500) NOT NULL,
    option_b       VARCHAR(500) NOT NULL,
    option_c       VARCHAR(500) NOT NULL,
    option_d       VARCHAR(500) NOT NULL,
    correct_option ENUM('A','B','C','D') NOT NULL,
    marks          INT(3)       NOT NULL DEFAULT 1,
    sort_order     INT(5)       NOT NULL DEFAULT 0,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_qq_quiz_id    (quiz_id),
    INDEX idx_qq_sort_order (quiz_id, sort_order),

    CONSTRAINT fk_qq_quiz
        FOREIGN KEY (quiz_id)
        REFERENCES quizzes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Table 3: quiz_attempts ────────────────────────────────────
-- Each attempt stores score + all individual answers as JSON
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id             INT(11)               NOT NULL AUTO_INCREMENT,
    quiz_id        INT(11)               NOT NULL,
    student_id     INT(11)               NOT NULL,
    score          INT(5)                NOT NULL DEFAULT 0,
    total_marks    INT(5)                NOT NULL DEFAULT 0,
    percentage     DECIMAL(5,2)          NOT NULL DEFAULT 0.00,
    result         ENUM('pass','fail')   NOT NULL DEFAULT 'fail',
    answers        TEXT                  DEFAULT NULL,
    attempted_at   TIMESTAMP             NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_qa_quiz_student (quiz_id, student_id),
    INDEX idx_qa_student      (student_id),
    INDEX idx_qa_result       (result),

    CONSTRAINT fk_qa_quiz
        FOREIGN KEY (quiz_id)
        REFERENCES quizzes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_qa_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ================================================================
-- LMS Database: certificates table
-- File   : config/certificates.sql
-- Run in : phpMyAdmin → lms_db → SQL tab → paste → Go
-- Depends: users, courses, enrollments tables must exist first
-- ================================================================

USE lms_db;

CREATE TABLE IF NOT EXISTS certificates (
    id              INT(11)         NOT NULL AUTO_INCREMENT,
    student_id      INT(11)         NOT NULL,
    course_id       INT(10) UNSIGNED NOT NULL,
    certificate_no  VARCHAR(30)     NOT NULL,
    issued_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_cert_number      (certificate_no),
    UNIQUE KEY uq_student_course   (student_id, course_id),

    INDEX idx_cert_student  (student_id),
    INDEX idx_cert_course   (course_id),
    INDEX idx_cert_issued   (issued_at),

    CONSTRAINT fk_cert_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_cert_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- BCNSHS Attendance System
-- Base schema for moving the project database to another PC.
-- Import this file in phpMyAdmin or with the mysql CLI.

CREATE DATABASE IF NOT EXISTS `Aliviado_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `Aliviado_db`;

CREATE TABLE IF NOT EXISTS `students` (
  `student_id` VARCHAR(64) NOT NULL,
  `fullname` VARCHAR(150) NOT NULL,
  `grade_section` VARCHAR(100) NOT NULL,
  `parent_email` VARCHAR(150) NOT NULL,
  `face_descriptor` LONGTEXT NOT NULL,
  `face_descriptor_norm` DOUBLE NULL,
  `qr_code` VARCHAR(255) NOT NULL,
  `is_disabled` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_students_qr_code` (`qr_code`),
  KEY `idx_students_is_disabled` (`is_disabled`),
  KEY `idx_students_face_descriptor_norm` (`face_descriptor_norm`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(64) NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `photo_path` VARCHAR(255) NOT NULL,
  `method` VARCHAR(100) NOT NULL,
  `status` ENUM('Present', 'Late', 'Absent') NOT NULL DEFAULT 'Present',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_student_date` (`student_id`, `date`),
  KEY `idx_attendance_date_status` (`date`, `status`),
  KEY `idx_attendance_student_id` (`student_id`),
  CONSTRAINT `fk_attendance_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

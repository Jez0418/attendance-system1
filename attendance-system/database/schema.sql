-- ============================================================
-- QR CODE LABORATORY ATTENDANCE MANAGEMENT SYSTEM
-- Database Schema
-- Import this file in phpMyAdmin, or run:
--   mysql -u root -p < schema.sql
-- ============================================================

DROP DATABASE IF EXISTS qr_attendance_system;
CREATE DATABASE qr_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qr_attendance_system;

-- ------------------------------------------------------------
-- TABLE: users  (central auth table for Admin / Teacher / Student)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- bcrypt hashed (PHP password_hash)
    role ENUM('admin','teacher','student') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: programs (academic programs, e.g. BSCS, BSIT)
-- ------------------------------------------------------------
CREATE TABLE programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    program_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: students (profile linked to users)
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_number VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    program_id INT NULL,
    year_level TINYINT NOT NULL DEFAULT 1,
    contact_number VARCHAR(20),
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    INDEX idx_student_number (student_number)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: teachers (profile linked to users)
-- ------------------------------------------------------------
CREATE TABLE teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_number VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    department VARCHAR(100),
    contact_number VARCHAR(20),
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: laboratories (7 laboratories)
-- ------------------------------------------------------------
CREATE TABLE laboratories (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    lab_name VARCHAR(100) NOT NULL,
    lab_code VARCHAR(20) NOT NULL UNIQUE,
    location VARCHAR(150),
    capacity INT DEFAULT 40,
    status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: subjects
-- ------------------------------------------------------------
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(150) NOT NULL,
    units DECIMAL(3,1) DEFAULT 3.0,
    status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: teacher_subjects
-- A teacher handling a subject in a lab with a schedule (a "class")
-- ------------------------------------------------------------
CREATE TABLE teacher_subjects (
    teacher_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    lab_id INT NOT NULL,
    section VARCHAR(50) NOT NULL,
    schedule_day VARCHAR(30) NOT NULL,       -- e.g. 'Monday' or 'Mon/Wed'
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    school_year VARCHAR(20) DEFAULT '2025-2026',
    semester ENUM('1st','2nd','Summer') DEFAULT '1st',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: enrollments
-- Students enrolled into a specific teacher_subject (class)
-- ------------------------------------------------------------
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_subject_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled','dropped') DEFAULT 'enrolled',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_subject_id) REFERENCES teacher_subjects(teacher_subject_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_enrollment (student_id, teacher_subject_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: attendance_sessions
-- Created when a teacher "activates" attendance for a class meeting
-- ------------------------------------------------------------
CREATE TABLE attendance_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_subject_id INT NOT NULL,
    session_date DATE NOT NULL,
    qr_token VARCHAR(100) NOT NULL UNIQUE,   -- random token encoded in the QR
    scheduled_start DATETIME NOT NULL,       -- used to compute Late status
    late_threshold_minutes INT NOT NULL DEFAULT 15,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    activated_by INT NOT NULL,               -- teacher_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deactivated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (teacher_subject_id) REFERENCES teacher_subjects(teacher_subject_id) ON DELETE CASCADE,
    INDEX idx_active (is_active),
    INDEX idx_token (qr_token)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: attendance_records
-- ------------------------------------------------------------
CREATE TABLE attendance_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    time_in DATETIME NOT NULL,
    status ENUM('Present','Late','Absent') NOT NULL DEFAULT 'Present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_scan (session_id, student_id), -- prevents duplicate scans
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: activity_logs (simple audit trail)
-- ------------------------------------------------------------
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

INSERT INTO programs (program_code, program_name) VALUES
('BSCS', 'Bachelor of Science in Computer Science'),
('BSIT', 'Bachelor of Science in Information Technology'),
('BSCpE', 'Bachelor of Science in Computer Engineering');

-- 7 Laboratories
INSERT INTO laboratories (lab_name, lab_code, location, capacity) VALUES
('Computer Laboratory 1', 'LAB-1', 'Building A, 2nd Floor', 40),
('Computer Laboratory 2', 'LAB-2', 'Building A, 2nd Floor', 40),
('Computer Laboratory 3', 'LAB-3', 'Building A, 3rd Floor', 35),
('Networking Laboratory', 'LAB-4', 'Building B, 1st Floor', 30),
('Multimedia Laboratory', 'LAB-5', 'Building B, 2nd Floor', 30),
('Hardware Laboratory', 'LAB-6', 'Building B, 2nd Floor', 25),
('Research & Innovation Laboratory', 'LAB-7', 'Building C, 1st Floor', 20);

-- Subjects
INSERT INTO subjects (subject_code, subject_name, units) VALUES
('IT101', 'Introduction to Computing', 3.0),
('IT201', 'Data Structures and Algorithms', 3.0),
('IT301', 'Web Systems and Technologies', 3.0),
('IT302', 'Database Management Systems', 3.0),
('IT401', 'System Integration and Architecture', 3.0);

-- ------------------------------------------------------------
-- Demo accounts. These are seeded with a placeholder password hash.
-- IMPORTANT: after importing, open database/reset_passwords_to_id.php
-- in your browser once — it sets every account's real password to its
-- own ID number (Admin ID / Employee No. / Student No.), which is the
-- convention this app's login screen expects. See README.md.
-- ------------------------------------------------------------
INSERT INTO users (username, password, role, email, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@school.edu', 'active');

INSERT INTO users (username, password, role, email, status) VALUES
('tcruz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'tcruz@school.edu', 'active'),
('jsantos', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'jsantos@school.edu', 'active');

INSERT INTO teachers (user_id, employee_number, full_name, department, contact_number) VALUES
(2, 'EMP-001', 'Teresa Cruz', 'College of Computing', '09171234567'),
(3, 'EMP-002', 'Juan Santos', 'College of Computing', '09179876543');

INSERT INTO users (username, password, role, email, status) VALUES
('s2023001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 's2023001@school.edu', 'active'),
('s2023002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 's2023002@school.edu', 'active'),
('s2023003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 's2023003@school.edu', 'active');

INSERT INTO students (user_id, student_number, full_name, program_id, year_level, contact_number) VALUES
(4, '2023-0001', 'Maria Dela Cruz', 1, 3, '09051112222'),
(5, '2023-0002', 'Jose Rizal Jr.', 1, 3, '09053334444'),
(6, '2023-0003', 'Ana Lopez', 2, 2, '09055556666');

-- Teacher-Subject assignments (classes)
INSERT INTO teacher_subjects (teacher_id, subject_id, lab_id, section, schedule_day, start_time, end_time) VALUES
(1, 3, 1, 'BSCS-3A', 'Monday', '08:00:00', '11:00:00'),
(1, 4, 2, 'BSCS-3A', 'Wednesday', '13:00:00', '16:00:00'),
(2, 1, 3, 'BSIT-1A', 'Tuesday', '09:00:00', '12:00:00');

-- Enrollments
INSERT INTO enrollments (student_id, teacher_subject_id) VALUES
(1, 1), (1, 2), (2, 1), (2, 2), (3, 3);

-- Student Clearance Management System Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10+ (XAMPP and InfinityFree)


-- Drop existing tables if re-importing
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `clearance_subject_status`;
DROP TABLE IF EXISTS `clearance_signatory_status`;
DROP TABLE IF EXISTS `clearance_requests`;
DROP TABLE IF EXISTS `student_subjects`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `clearance_periods`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments / Offices (Administrative Signatories)
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `requires_proof` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table (Admin, Signatories, Instructors, Students)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'signatory', 'instructor', 'student') NOT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `email` VARCHAR(100) NULL,
  `student_id` VARCHAR(50) NULL UNIQUE,
  `department_id` INT NULL,
  `course` VARCHAR(80) NULL,
  `year_level` VARCHAR(20) NULL,
  `section` VARCHAR(20) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Clearance Periods (e.g., 1st Sem 2026-2027)
CREATE TABLE `clearance_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL,
  `semester` VARCHAR(30) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Subjects / Courses (Taught by Instructors)
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `clearance_requirements` TEXT NULL,
  `instructor_id` INT NOT NULL,
  `period_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`period_id`) REFERENCES `clearance_periods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Student Enrolled Subjects
CREATE TABLE `student_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `period_id` INT NOT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`period_id`) REFERENCES `clearance_periods`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_subject_period` (`student_id`, `subject_id`, `period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Clearance Requests (Overall clearance per student per period)
CREATE TABLE `clearance_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `period_id` INT NOT NULL,
  `clearance_code` VARCHAR(50) NOT NULL UNIQUE,
  `overall_status` ENUM('pending', 'in_progress', 'cleared', 'rejected') DEFAULT 'in_progress',
  `submission_note` TEXT NULL,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`period_id`) REFERENCES `clearance_periods`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_period_clearance` (`student_id`, `period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Clearance Signatory Status (Administrative Offices)
CREATE TABLE `clearance_signatory_status` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `signatory_id` INT NULL,
  `status` ENUM('pending', 'cleared', 'deficient') DEFAULT 'pending',
  `remarks` TEXT NULL,
  `proof_file` VARCHAR(255) NULL,
  `student_note` TEXT NULL,
  `submitted_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  FOREIGN KEY (`request_id`) REFERENCES `clearance_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`signatory_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_request_department` (`request_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Clearance Subject Status (Academic Instructors)
CREATE TABLE `clearance_subject_status` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `instructor_id` INT NOT NULL,
  `status` ENUM('pending', 'cleared', 'deficient') DEFAULT 'pending',
  `remarks` TEXT NULL,
  `proof_file` VARCHAR(255) NULL,
  `student_note` TEXT NULL,
  `submitted_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  FOREIGN KEY (`request_id`) REFERENCES `clearance_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_request_subject` (`request_id`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Login Attempts (Rate Limiting & Brute Force Prevention)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  INDEX `idx_ip_time` (`ip_address`, `attempted_at`),
  INDEX `idx_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- SEED DATA FOR TESTING
-- Default Passwords:
-- Admin: admin123
-- Signatory: signatory123
-- Instructor: instructor123
-- Student: student123
-- ==========================================================

-- Default Departments
INSERT INTO `departments` (`id`, `code`, `name`, `description`, `requires_proof`, `is_active`) VALUES
(1, 'LIB', 'University Library', 'Check unreturned books and library dues', 0, 1),
(2, 'CASH', 'Cashier / Accounting Office', 'Verify tuition balances and clearance fees', 1, 1),
(3, 'REG', 'Registrar Office', 'Check complete school credentials & documents', 0, 1),
(4, 'GUID', 'Guidance Office', 'Exit interview & student counseling evaluation', 0, 1),
(5, 'DHEAD', 'Department Head / Dean', 'Academic compliance and curriculum verification', 0, 1),
(6, 'OSAS', 'Student Affairs (OSAS / SSC)', 'Uniform, ID compliance, and student organization dues', 0, 1);

-- Default Users (Bcrypt Hashes PASSWORD_BCRYPT)
-- 'admin123'      => $2y$10$zlzrmAp24cTE9SpfjJ/HXezYSRjPsv/QX/PjZQA9zFx.46c0Maeiq
-- 'signatory123'  => $2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK
-- 'instructor123' => $2y$10$z4wNI5CT/RA5bsD6uJnWh.7157G1J7uB33P2PxfsGKPkXvwJaVug2
-- 'student123'    => $2y$10$zqxvvRYqhw8IDDm0Jr1gcumJ2p/ABshIRsZW2b6h5dnhql3Lvafuy

INSERT INTO `users` (`id`, `username`, `password`, `role`, `first_name`, `last_name`, `email`, `student_id`, `department_id`, `course`, `year_level`, `section`, `status`) VALUES
-- Admin
(1, 'admin', '$2y$10$zlzrmAp24cTE9SpfjJ/HXezYSRjPsv/QX/PjZQA9zFx.46c0Maeiq', 'admin', 'System', 'Administrator', 'admin@school.edu.ph', NULL, NULL, NULL, NULL, NULL, 'active'),

-- Department Signatories
(2, 'lib_officer', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Maria', 'Santos', 'library@school.edu.ph', NULL, 1, NULL, NULL, NULL, 'active'),
(3, 'cashier', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Robert', 'Cruz', 'cashier@school.edu.ph', NULL, 2, NULL, NULL, NULL, 'active'),
(4, 'registrar', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Elena', 'Reyes', 'registrar@school.edu.ph', NULL, 3, NULL, NULL, NULL, 'active'),
(5, 'guidance', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Grace', 'Mendoza', 'guidance@school.edu.ph', NULL, 4, NULL, NULL, NULL, 'active'),
(6, 'dept_head', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Dr. Arthur', 'Gonzales', 'depthead@school.edu.ph', NULL, 5, NULL, NULL, NULL, 'active'),
(7, 'osas_officer', '$2y$10$97rTrkgbRLfep4cSJVLr8u72dxpByjbw.5x2vL8QucELw0GSKUSvK', 'signatory', 'Liza', 'Dizon', 'osas@school.edu.ph', NULL, 6, NULL, NULL, NULL, 'active'),

-- Subject Instructors
(8, 'prof_ramirez', '$2y$10$z4wNI5CT/RA5bsD6uJnWh.7157G1J7uB33P2PxfsGKPkXvwJaVug2', 'instructor', 'Juan', 'Ramirez', 'j.ramirez@school.edu.ph', NULL, NULL, NULL, NULL, NULL, 'active'),
(9, 'prof_flores', '$2y$10$z4wNI5CT/RA5bsD6uJnWh.7157G1J7uB33P2PxfsGKPkXvwJaVug2', 'instructor', 'Anna', 'Flores', 'a.flores@school.edu.ph', NULL, NULL, NULL, NULL, NULL, 'active'),
(10, 'prof_torres', '$2y$10$z4wNI5CT/RA5bsD6uJnWh.7157G1J7uB33P2PxfsGKPkXvwJaVug2', 'instructor', 'Michael', 'Torres', 'm.torres@school.edu.ph', NULL, NULL, NULL, NULL, NULL, 'active'),

-- Sample Students
(11, 'student1', '$2y$10$zqxvvRYqhw8IDDm0Jr1gcumJ2p/ABshIRsZW2b6h5dnhql3Lvafuy', 'student', 'Excel', 'Avenido', 'avenido.excel@student.edu.ph', '2026-0001', NULL, 'BSIT', '3rd Year', '3-A', 'active'),
(12, 'student2', '$2y$10$zqxvvRYqhw8IDDm0Jr1gcumJ2p/ABshIRsZW2b6h5dnhql3Lvafuy', 'student', 'Bea', 'Aquino', 'bea.aquino@student.edu.ph', '2026-0002', NULL, 'BSIS', '3rd Year', '3-B', 'active');

-- Clearance Period
INSERT INTO `clearance_periods` (`id`, `name`, `academic_year`, `semester`, `start_date`, `end_date`, `is_active`) VALUES
(1, '1st Semester Clearance 2026-2027', '2026-2027', '1st Semester', '2026-10-01', '2026-11-15', 1);

-- Subjects for this Period
INSERT INTO `subjects` (`id`, `code`, `name`, `clearance_requirements`, `instructor_id`, `period_id`) VALUES
(1, 'IT311', 'Web Systems & Technologies 2', '1. Complete all laboratory machine problems.\n2. Submit final Capstone / Web project source code repository.\n3. Return all borrowed development kits & equipment.', 8, 1),
(2, 'IT312', 'Information Assurance & Security', '1. Submit Security Audit & Vulnerability Assessment Report.\n2. Pass Final Lab Defense.', 9, 1),
(3, 'IT313', 'Database Administration', '1. Final Database Backup & Recovery Practical Exam.\n2. Submit normalized database project documentation and SQL dump.', 10, 1);

-- Enrolled Subjects for student1
INSERT INTO `student_subjects` (`student_id`, `subject_id`, `period_id`) VALUES
(11, 1, 1),
(11, 2, 1),
(11, 3, 1),
(12, 1, 1),
(12, 2, 1);

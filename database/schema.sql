-- ============================================================
-- AI Interview Preparation Platform
-- COMPLETE UNIFIED DATABASE SCHEMA & SEED DATA
-- ============================================================
-- Single unified database file for the entire application.
-- Includes:
--   1. All Core Platform Tables (Users, Admins, Categories, Sessions, Questions, Answers)
--   2. Admin Question Bank (Module 9)
--   3. Secure OTP & 2FA Verifications Table (Registration, Login 2FA, Password Reset)
--   4. Live AI Spoken Interview Tables (Sessions, Spoken Questions, Spoken Answers)
--   5. Notifications Table
--   6. Default Seed Categories (HR, Technical, Aptitude, Coding, Company Specific, Resume AI)
--   7. Default Super Admin Seed Account (admin@projectai.com / Admin@123)
--
-- Deployment Instructions:
--   - Local XAMPP: Import directly or run in phpMyAdmin / MySQL CLI.
--   - Cloud / InfinityFree / cPanel: Select your assigned database in phpMyAdmin,
--     then import this file.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ai_interview_prep`;
USE `ai_interview_prep`;

-- ------------------------------------------------------------
-- 1. USERS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `profile_picture` VARCHAR(255) DEFAULT 'images/default-avatar.png',
    `skills` TEXT,
    `education` VARCHAR(255),
    `resume_path` VARCHAR(255),
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_token_expiry` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. ADMINS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. INTERVIEW CATEGORIES TABLE & SEED DATA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255),
    `icon` VARCHAR(100) DEFAULT 'bi-briefcase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`name`, `description`, `icon`) VALUES
('HR Interview', 'Behavioral and HR round questions', 'bi-people'),
('Technical Interview', 'Core subject and concept questions', 'bi-cpu'),
('Aptitude', 'Logical and quantitative reasoning', 'bi-calculator'),
('Coding Interview', 'DSA and problem solving', 'bi-code-slash'),
('Company Specific', 'Questions tailored to a target company', 'bi-building'),
('Resume AI', 'Resume-based personalized interview preparation', 'bi-file-earmark-person')
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`), `icon`=VALUES(`icon`);

-- ------------------------------------------------------------
-- 4. INTERVIEW SESSIONS TABLE (Traditional / Text-Based)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interview_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `role_target` VARCHAR(150),
    `difficulty_level` ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
    `company_name` VARCHAR(150) DEFAULT NULL,
    `total_questions` INT DEFAULT 5,
    `total_score` DECIMAL(4,2) DEFAULT 0,
    `confidence_score` DECIMAL(4,2) DEFAULT 0,
    `status` ENUM('in_progress','completed','abandoned') DEFAULT 'in_progress',
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    INDEX `idx_sessions_user_status` (`user_id`, `status`),
    INDEX `idx_sessions_category` (`category_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. QUESTIONS TABLE (Traditional Mock Questions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_order` INT NOT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `interview_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. ANSWERS & AI EVALUATIONS TABLE (Traditional Mock Answers)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `answer_text` TEXT,
    `score` DECIMAL(4,2),
    `strengths` TEXT,
    `improvements` TEXT,
    `grammar_feedback` TEXT,
    `confidence_score` DECIMAL(4,2),
    `ai_summary` TEXT,
    `improved_answer` TEXT,
    `answered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `interview_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. ADMIN QUESTION BANK TABLE (Module 9)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_bank` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `difficulty` VARCHAR(50) NOT NULL DEFAULT 'Intermediate',
    `question_text` TEXT NOT NULL,
    `created_by_admin` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_admin`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. OTP VERIFICATIONS TABLE (Register OTP, Login 2FA, Reset)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `otp` VARCHAR(6) NOT NULL,
    `purpose` ENUM('register','login','reset_password') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_purpose` (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 9. LIVE AI INTERVIEW SESSIONS TABLE (Voice + Camera Avatar)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `live_interview_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role_name` VARCHAR(255),
    `category` ENUM('technical','hr','viva') DEFAULT 'technical',
    `difficulty` VARCHAR(50) DEFAULT 'adaptive',
    `status` ENUM('active','completed','abandoned') DEFAULT 'active',
    `evaluation_json` LONGTEXT NULL,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 10. LIVE AI INTERVIEW QUESTIONS TABLE (Spoken by AI Avatar)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `live_interview_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `interview_id` INT NOT NULL,
    `question_number` INT DEFAULT 1,
    `question_text` TEXT NOT NULL,
    `question_type` VARCHAR(50) DEFAULT 'spoken',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`interview_id`) REFERENCES `live_interview_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 11. LIVE AI INTERVIEW ANSWERS TABLE (Spoken by Candidate)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `live_interview_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `interview_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `answer_text` TEXT,
    `response_time_seconds` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`interview_id`) REFERENCES `live_interview_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `live_interview_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 12. NOTIFICATIONS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 13. DEFAULT SUPER ADMIN SEED
-- Email: admin@projectai.com | Password: Admin@123
-- ------------------------------------------------------------
INSERT INTO `admins` (`name`, `email`, `password_hash`)
VALUES (
    'Super Admin',
    'admin@projectai.com',
    '$2y$10$oPFB/Zosul0kaTs1hD3fNexDoXQOHV4.HuDhvnHAj5hNg14Qi5yz6'
)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

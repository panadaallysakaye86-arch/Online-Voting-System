-- ========================================================
-- Database Schema & Sample Seed Data
-- Database Name: online_voting_db
-- ========================================================

CREATE DATABASE IF NOT EXISTS `online_voting_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `online_voting_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'voter') NOT NULL DEFAULT 'voter',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Positions Table
CREATE TABLE IF NOT EXISTS `positions` (
  `position_id` INT AUTO_INCREMENT PRIMARY KEY,
  `position_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Candidates Table
CREATE TABLE IF NOT EXISTS `candidates` (
  `candidate_id` INT AUTO_INCREMENT PRIMARY KEY,
  `position_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `course_year` VARCHAR(100) NOT NULL,
  `platform` TEXT NULL,
  `photo` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`position_id`) REFERENCES `positions`(`position_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Votes Table (with UNIQUE constraint blocking double voting per user per position)
CREATE TABLE IF NOT EXISTS `votes` (
  `vote_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `position_id` INT NOT NULL,
  `candidate_id` INT NOT NULL,
  `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_position_vote` (`user_id`, `position_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`position_id`) REFERENCES `positions`(`position_id`) ON DELETE CASCADE,
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`candidate_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Settings Table (Election Schedule & Title)
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('election_title', 'General Online Voting'),
('election_start', '2026-01-01 08:00:00'),
('election_end', '2026-12-31 17:00:00')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

-- Default Test Accounts:
-- Admin: ADMIN-001 / admin123
-- Voters: 2026-0001, 2026-0002, 2026-0003 / voter123
INSERT INTO `users` (`student_id`, `full_name`, `email`, `password`, `role`) VALUES
('ADMIN-001', 'System Administrator', 'admin@example.com', '$2y$12$Sl01iWt.XII.BbAeolUz2u5rfXUU9OMjWxsFb7U/rV/VTL5EsVneq', 'admin'),
('2026-0001', 'Juan Dela Cruz', 'juan@example.com', '$2y$12$Hn8Nxmf1ToXnaVvkwou6j.tGLLHdKCX8ERF5zDFyDrlD4ZGFJ8vEK', 'voter'),
('2026-0002', 'Maria Clara', 'maria@example.com', '$2y$12$Hn8Nxmf1ToXnaVvkwou6j.tGLLHdKCX8ERF5zDFyDrlD4ZGFJ8vEK', 'voter'),
('2026-0003', 'Crisostomo Ibarra', 'ibarra@example.com', '$2y$12$Hn8Nxmf1ToXnaVvkwou6j.tGLLHdKCX8ERF5zDFyDrlD4ZGFJ8vEK', 'voter')
ON DUPLICATE KEY UPDATE `student_id` = VALUES(`student_id`);

-- Sample Election Positions
INSERT INTO `positions` (`position_id`, `position_name`, `description`) VALUES
(1, 'President / Chairperson', 'Chief executive officer representing the organization and presiding over board sessions.'),
(2, 'Vice President', 'Assists the president and oversees internal committee operations.'),
(3, 'Secretary General', 'Maintains official records, resolutions, and organizational communications.')
ON DUPLICATE KEY UPDATE `position_name` = VALUES(`position_name`);

-- Sample Candidates
INSERT INTO `candidates` (`position_id`, `full_name`, `course_year`, `platform`, `photo`) VALUES
(1, 'Andrea Torres', 'Operations Division', 'Fostering digital transformation, member inclusion, and organizational transparency.', NULL),
(1, 'Gabriel Mendoza', 'Strategy & Planning', 'Advocating for 24/7 service access, community welfare support programs, and development initiatives.', NULL),
(2, 'Sophia Reyes', 'Member Relations', 'Revitalizing community initiatives and expanding partner networks.', NULL),
(2, 'Marco Santos', 'Infrastructure & IT', 'Facility upgrades, technology modernizations, and transparent feedback systems.', NULL),
(3, 'Alyssa Cruz', 'Communications & Audit', 'Streamlined online inquiries filing and timely organizational audits.', NULL)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);


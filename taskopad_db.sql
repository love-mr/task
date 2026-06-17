-- vyala_taskpad.sql
-- Database Dump for Vyala Task Pad Clone (Vyala Task Pad)
-- Generated on: 2026-06-15

CREATE DATABASE IF NOT EXISTS `vyala_taskpad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vyala_taskpad`;

-- Table structure for table `clients`
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `projects`
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `client_id` INT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
  `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium',
  `created_by` INT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`client_id`),
  INDEX (`created_by`),
  INDEX (`assigned_to`)
) ENGINE=InnoDB;

-- Table structure for table `employees`
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `role` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `emp_code` VARCHAR(50) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `tasks`
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `project_id` INT NOT NULL,
  `assigned_to` INT DEFAULT NULL,
  `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Todo',
  `due_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`project_id`),
  INDEX (`assigned_to`)
) ENGINE=InnoDB;

-- Table structure for table `timesheets`
DROP TABLE IF EXISTS `timesheets`;
CREATE TABLE `timesheets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `hours` DECIMAL(5, 2) NOT NULL,
  `date` DATE NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`task_id`),
  INDEX (`employee_id`)
) ENGINE=InnoDB;

-- Table structure for table `discussions`
DROP TABLE IF EXISTS `discussions`;
CREATE TABLE `discussions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL DEFAULT 'General',
  `title` VARCHAR(255) NOT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_type` VARCHAR(50) DEFAULT NULL,
  `date_logged` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `activities`
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(255) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `project_name` VARCHAR(255) DEFAULT NULL,
  `logged_date` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `pin_notes`
DROP TABLE IF EXISTS `pin_notes`;
CREATE TABLE `pin_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'info',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- DUMPING DATA

-- Dumping data for table `employees`
INSERT INTO `employees` (`id`, `name`, `role`, `email`, `password`, `emp_code`, `avatar`) VALUES
(4, 'SELVAKUMAR J', 'Project Lead', 'selvakumar@vyalasoftware.com', '$2y$10$gTljku/.7lAnHTtSq.mLeePG/Mu6OIfoWwSadAcISmYmkkis8uvAG', 'T-130555', 'SJ');

-- vyala_taskpad.sql
-- Database Dump for Vyala Task Pad Clone (Vyala Task Pad)
-- Generated on: 2026-06-17

CREATE DATABASE IF NOT EXISTS `vyala_taskpad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vyala_taskpad`;

-- Table structure for table `organizations`
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('Pending','Active','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB;

-- Table structure for table `clients`
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
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
  `pipeline_stage` VARCHAR(100) NOT NULL DEFAULT 'Lead Received',
  `service_type` VARCHAR(100) NOT NULL DEFAULT 'Layout',
  `due_date` DATE DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
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
  `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `project_members`
DROP TABLE IF EXISTS `project_members`;
CREATE TABLE `project_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  UNIQUE KEY `uniq_proj_emp` (`project_id`, `employee_id`),
  INDEX `idx_pm_project` (`project_id`),
  INDEX `idx_pm_employee` (`employee_id`)
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
  `estimated_duration` INT DEFAULT 0,
  `sequence_order` INT DEFAULT 0,
  `depends_on` INT DEFAULT NULL,
  `actual_start_date` DATE DEFAULT NULL,
  `actual_completion_date` DATE DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
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
  `org_id` INT NOT NULL DEFAULT 1,
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
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `discussion_members`
DROP TABLE IF EXISTS `discussion_members`;
CREATE TABLE `discussion_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `discussion_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `disc_emp_unique` (`discussion_id`, `employee_id`),
  INDEX (`discussion_id`),
  INDEX (`employee_id`)
) ENGINE=InnoDB;

-- Table structure for table `discussion_messages`
DROP TABLE IF EXISTS `discussion_messages`;
CREATE TABLE `discussion_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `discussion_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_type` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`discussion_id`),
  INDEX (`sender_id`)
) ENGINE=InnoDB;

-- Table structure for table `documents`
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(500) NOT NULL,
  `owner_id` INT DEFAULT NULL,
  `project_id` INT DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `size` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_doc_owner` (`owner_id`),
  INDEX `idx_doc_org` (`org_id`)
) ENGINE=InnoDB;

-- Table structure for table `attendance`
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `check_in` TIME DEFAULT NULL,
  `check_out` TIME DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Present',
  `org_id` INT NOT NULL DEFAULT 1,
  UNIQUE KEY `emp_date_unique` (`employee_id`, `date`),
  INDEX (`employee_id`)
) ENGINE=InnoDB;

-- Table structure for table `activities`
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(255) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `project_name` VARCHAR(255) DEFAULT NULL,
  `logged_date` VARCHAR(100) DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `pin_notes`
DROP TABLE IF EXISTS `pin_notes`;
CREATE TABLE `pin_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'Work',
  `tags` VARCHAR(255) DEFAULT NULL,
  `owner_id` INT NOT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`owner_id`)
) ENGINE=InnoDB;

-- Table structure for table `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'info',
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `departments`
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'git-branch',
  `color` VARCHAR(50) DEFAULT '#2563eb',
  `bg` VARCHAR(50) DEFAULT '#eff6ff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `buildings`
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `total_floors` INT DEFAULT 0,
  `total_units` INT DEFAULT 0,
  `total_area` DECIMAL(10,2) DEFAULT 0,
  `owner_name` VARCHAR(255) DEFAULT NULL,
  `contact_number` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Available','Sold','Rented') DEFAULT 'Available',
  `document_path` VARCHAR(500) DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `single_plots`
DROP TABLE IF EXISTS `single_plots`;
CREATE TABLE `single_plots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plot_number` VARCHAR(100) NOT NULL,
  `layout_name` VARCHAR(255) DEFAULT NULL,
  `survey_number` VARCHAR(100) DEFAULT NULL,
  `area` DECIMAL(10,2) DEFAULT 0,
  `location` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(15,2) DEFAULT 0,
  `facing_direction` VARCHAR(100) DEFAULT NULL,
  `status` VARCHAR(100) DEFAULT 'Available',
  `owner_name` VARCHAR(255) DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `ual_records`
DROP TABLE IF EXISTS `ual_records`;
CREATE TABLE `ual_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_number` VARCHAR(100) NOT NULL,
  `owner_name` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `total_land_area` DECIMAL(10,2) DEFAULT 0,
  `gov_ceiling_limit` DECIMAL(10,2) DEFAULT 0,
  `excess_land_area` DECIMAL(10,2) DEFAULT 0,
  `approval_status` VARCHAR(100) DEFAULT 'Pending',
  `gov_order_number` VARCHAR(100) DEFAULT NULL,
  `document_path` VARCHAR(500) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table structure for table `land_surveys`
DROP TABLE IF EXISTS `land_surveys`;
CREATE TABLE `land_surveys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `survey_number` VARCHAR(100) NOT NULL,
  `village_name` VARCHAR(255) DEFAULT NULL,
  `taluk` VARCHAR(255) DEFAULT NULL,
  `district` VARCHAR(255) DEFAULT NULL,
  `land_type` VARCHAR(100) DEFAULT NULL,
  `owner_name` VARCHAR(255) DEFAULT NULL,
  `total_area` DECIMAL(10,2) DEFAULT 0,
  `latitude` VARCHAR(50) DEFAULT NULL,
  `longitude` VARCHAR(50) DEFAULT NULL,
  `document_path` VARCHAR(500) DEFAULT NULL,
  `org_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- DUMPING DATA
-- Dumping data for table `organizations`
INSERT INTO `organizations` (`id`, `name`, `slug`, `status`) VALUES
(1, 'Default Organization', 'default', 'Active');

-- Dumping data for table `employees`
INSERT INTO `employees` (`id`, `name`, `role`, `email`, `password`, `emp_code`, `avatar`, `status`, `org_id`) VALUES
(4, 'SELVAKUMAR J', 'Project Lead', 'selvakumar@vyalasoftware.com', '$2y$10$gTljku/.7lAnHTtSq.mLeePG/Mu6OIfoWwSadAcISmYmkkis8uvAG', 'T-130555', 'SJ', 'Active', 1);

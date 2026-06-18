<?php
// db.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';
date_default_timezone_set('Asia/Kolkata');

// Custom Query Rewriter for Super Admin Global View
class MyPDOQueryRewriter {
    public static function rewrite($query) {
        if (!preg_match('/^\s*\(?\s*select\b/i', $query)) {
            return $query;
        }
        
        $isGlobalSuperAdmin = false;
        $jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
        if (!empty($jwtToken)) {
            $jwtPayload = verify_jwt($jwtToken);
            if ($jwtPayload && isset($jwtPayload['role']) && $jwtPayload['role'] === 'Super Admin' && isset($jwtPayload['org_id']) && (int)$jwtPayload['org_id'] === 0) {
                $isGlobalSuperAdmin = true;
            }
        }
        
        if ($isGlobalSuperAdmin) {
            $query = preg_replace_callback('/(?:\b|`)(?:`?\w+`?\.)?`?org_id`?\s*=\s*(\?|0)(?!\w)/', function($matches) {
                $val = $matches[1];
                if ($val === '?') {
                    return '(? IS NOT NULL OR 1=1)';
                } else {
                    return '(1=1)';
                }
            }, $query);
        }
        
        return $query;
    }
}

class MyPDO extends PDO {
    public function prepare($query, $options = []) {
        $query = MyPDOQueryRewriter::rewrite($query);
        return parent::prepare($query, $options);
    }
    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        $query = MyPDOQueryRewriter::rewrite($query);
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
    public function exec($statement) {
        $statement = MyPDOQueryRewriter::rewrite($statement);
        return parent::exec($statement);
    }
}

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server without database first
    $pdo = new MyPDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `vyala_taskpad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Connect to the specific database
    $pdo->exec("USE `vyala_taskpad`");
    $pdo->exec("SET time_zone = '+05:30';");

    // Create clients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `clients` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create projects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `client_id` INT DEFAULT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
        `pipeline_stage` VARCHAR(100) DEFAULT NULL,
        `service_type` VARCHAR(100) DEFAULT NULL,
        `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium',
        `created_by` INT DEFAULT NULL,
        `assigned_to` INT DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `fee_amount` DECIMAL(15,2) DEFAULT 0.00,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`client_id`),
        INDEX (`created_by`),
        INDEX (`assigned_to`)
    ) ENGINE=InnoDB;");



    // Create employees (team members) table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employees` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `role` VARCHAR(150) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `emp_code` VARCHAR(50) DEFAULT NULL UNIQUE,
        `avatar` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
        `public_key` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create tasks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `project_id` INT NOT NULL,
        `assigned_to` INT DEFAULT NULL,
        `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium', -- High, Medium, Low
        `status` VARCHAR(50) NOT NULL DEFAULT 'Todo', -- Todo, In Progress, In Review, Completed
        `due_date` DATE DEFAULT NULL,
        `estimated_duration` INT DEFAULT 0,
        `sequence_order` INT DEFAULT 0,
        `depends_on` INT DEFAULT NULL,
        `actual_start_date` DATE DEFAULT NULL,
        `actual_completion_date` DATE DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`project_id`),
        INDEX (`assigned_to`)
    ) ENGINE=InnoDB;");


    // Create timesheets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timesheets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `task_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `hours` DECIMAL(5, 2) NOT NULL,
        `date` DATE NOT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`task_id`),
        INDEX (`employee_id`)
    ) ENGINE=InnoDB;");

    // Create discussions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `discussions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `type` VARCHAR(50) NOT NULL DEFAULT 'General', -- General, Task
        `title` VARCHAR(255) NOT NULL,
        `attachment_name` VARCHAR(255) DEFAULT NULL,
        `attachment_type` VARCHAR(50) DEFAULT NULL, -- pdf, dwg, doc, image, etc.
        `date_logged` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create discussion_messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `discussion_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `discussion_id` INT NOT NULL,
        `sender_id` INT NOT NULL,
        `message` TEXT NOT NULL,
        `attachment_name` VARCHAR(255) DEFAULT NULL,
        `attachment_type` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`discussion_id`),
        INDEX (`sender_id`)
    ) ENGINE=InnoDB;");

    // Create documents table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `filepath` VARCHAR(255) NOT NULL,
        `owner_id` INT NOT NULL,
        `project_id` INT DEFAULT NULL,
        `size` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`owner_id`),
        INDEX (`project_id`)
    ) ENGINE=InnoDB;");

    // Create attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `employee_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `check_in` TIME DEFAULT NULL,
        `check_out` TIME DEFAULT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Present', -- Present, Absent, Late
        UNIQUE KEY `emp_date_unique` (`employee_id`, `date`),
        INDEX (`employee_id`)
    ) ENGINE=InnoDB;");

    // Create activities table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activities` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `action` VARCHAR(255) NOT NULL,
        `details` VARCHAR(255) DEFAULT NULL,
        `project_name` VARCHAR(255) DEFAULT NULL,
        `logged_date` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create pin_notes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pin_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'Work', -- Work, Personal, Archive
        `tags` VARCHAR(255) DEFAULT NULL,
        `owner_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`owner_id`)
    ) ENGINE=InnoDB;");

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `message` TEXT NOT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'info', -- info, success, warning, danger
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL UNIQUE,
        `icon` VARCHAR(50) DEFAULT 'git-branch',
        `color` VARCHAR(50) DEFAULT '#2563eb',
        `bg` VARCHAR(50) DEFAULT '#eff6ff',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create discussion_members table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `discussion_members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `discussion_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `disc_emp_unique` (`discussion_id`, `employee_id`),
        INDEX (`discussion_id`),
        INDEX (`employee_id`)
    ) ENGINE=InnoDB;");

    // Create goal_tracker table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `goal_tracker` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `org_id` INT NOT NULL DEFAULT 1,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `start_date` DATE NOT NULL,
        `target_date` DATE NOT NULL,
        `actual_completion_date` DATE DEFAULT NULL,
        `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `status` ENUM('Not Started','In Progress','Completed','Overdue') NOT NULL DEFAULT 'Not Started',
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`org_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB;");

    // Create document_logs table for audit trail
    $pdo->exec("CREATE TABLE IF NOT EXISTS `document_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_id` INT NOT NULL,
        `employee_id` INT DEFAULT NULL,
        `action` ENUM('upload','download','delete','edit','view') NOT NULL DEFAULT 'upload',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `org_id` INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`doc_id`),
        INDEX (`employee_id`),
        INDEX (`org_id`)
    ) ENGINE=InnoDB;");

    // Create survey_management table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `survey_management` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `survey_number` VARCHAR(100) NOT NULL,
        `sub_division_number` VARCHAR(100) DEFAULT NULL,
        `owner_name` VARCHAR(255) DEFAULT NULL,
        `village_name` VARCHAR(255) DEFAULT NULL,
        `taluk` VARCHAR(255) DEFAULT NULL,
        `district` VARCHAR(255) DEFAULT NULL,
        `land_type` VARCHAR(100) DEFAULT NULL,
        `total_area` DECIMAL(10,2) DEFAULT 0.00,
        `patta_number` VARCHAR(100) DEFAULT NULL,
        `fmb_number` VARCHAR(100) DEFAULT NULL,
        `latitude` VARCHAR(50) DEFAULT NULL,
        `longitude` VARCHAR(50) DEFAULT NULL,
        `survey_date` DATE DEFAULT NULL,
        `status` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        `remarks` TEXT DEFAULT NULL,
        `document_path` VARCHAR(500) DEFAULT NULL,
        `org_id` INT NOT NULL DEFAULT 1,
        `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`org_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB;");

    // Create survey_history table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `survey_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `survey_id` INT NOT NULL,
        `action` VARCHAR(255) NOT NULL,
        `performed_by` VARCHAR(255) DEFAULT NULL,
        `details` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`survey_id`)
    ) ENGINE=InnoDB;");

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// One-time database initialization/reset to a clean starting state
$lockFile = __DIR__ . '/database_init.lock';
if (!file_exists($lockFile)) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("TRUNCATE TABLE `timesheets`;");
        $pdo->exec("TRUNCATE TABLE `tasks`;");
        $pdo->exec("TRUNCATE TABLE `projects`;");
        $pdo->exec("TRUNCATE TABLE `clients`;");
        $pdo->exec("TRUNCATE TABLE `discussions`;");
        $pdo->exec("TRUNCATE TABLE `discussion_messages`;");
        $pdo->exec("TRUNCATE TABLE `discussion_members`;");
        $pdo->exec("TRUNCATE TABLE `documents`;");
        $pdo->exec("TRUNCATE TABLE `attendance`;");
        $pdo->exec("TRUNCATE TABLE `activities`;");
        $pdo->exec("TRUNCATE TABLE `pin_notes`;");
        $pdo->exec("TRUNCATE TABLE `notifications`;");
        $pdo->exec("TRUNCATE TABLE `departments`;");
        $pdo->exec("TRUNCATE TABLE `employees`;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Seed default admin account
        $adminPass = password_hash('admin@123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`, `status`) VALUES ('Admin', 'Admin', 'admin', ?, 'T-130000', 'AD', 'Approved')")->execute([$adminPass]);

        file_put_contents($lockFile, 'initialized');
    } catch (PDOException $e) {
        // Silently handle if schema is not ready
    }
}

// Always ensure the default admin user exists
try {
    $checkAdmin = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = 'admin'");
    $checkAdmin->execute();
    if ($checkAdmin->fetchColumn() == 0) {
        $adminPass = password_hash('admin@123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`, `status`) VALUES ('Admin', 'Admin', 'admin', ?, 'T-130000', 'AD', 'Approved')")->execute([$adminPass]);
    }
} catch (PDOException $e) {
    // Silently handle if db setup is not complete yet
}

// Relative time helper
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}



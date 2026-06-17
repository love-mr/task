<?php
// reset_clean.php - Resets the database to a clean state with only selvakumar@vyalasoftware.com user

require_once 'db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS `timesheets`, `tasks`, `projects`, `clients`, `employees`, `discussions`, `discussion_messages`, `discussion_members`, `documents`, `attendance`, `activities`, `pin_notes`, `notifications`, `departments` ");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // Recreate tables by calling db.php
    require 'db.php';
    
    echo "All tables dropped and recreated successfully.\n";
} catch (PDOException $e) {
    die("Error resetting database: " . $e->getMessage() . "\n");
}

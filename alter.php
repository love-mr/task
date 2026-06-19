<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN pipeline_stage VARCHAR(100) NOT NULL DEFAULT 'Lead Received'");
} catch(Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN service_type VARCHAR(100) NOT NULL DEFAULT 'Layout'");
} catch(Exception $e) {}
echo "Done.";

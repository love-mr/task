<?php
require_once 'db.php';
$tables = ['employees', 'projects', 'tasks', 'clients', 'pin_notes', 'attendance', 'discussions', 'discussion_messages', 'documents', 'activities', 'notifications'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "$t: $count rows\n";
        if ($count > 0) {
            $rows = $pdo->query("SELECT * FROM `$t` LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            print_r($rows);
        }
    } catch (Exception $e) {
        echo "$t error: " . $e->getMessage() . "\n";
    }
}

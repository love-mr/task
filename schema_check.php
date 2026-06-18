<?php
require_once 'db.php';
$cols = $pdo->query('DESCRIBE projects')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['Field'].'|'.$c['Type'].PHP_EOL;

echo "\n-- documents table:\n";
try {
    $dcols = $pdo->query('DESCRIBE documents')->fetchAll(PDO::FETCH_ASSOC);
    foreach($dcols as $c) echo $c['Field'].'|'.$c['Type'].PHP_EOL;
} catch(Exception $e) { echo "No documents table\n"; }

echo "\n-- project_files table:\n";
try {
    $pfcols = $pdo->query('DESCRIBE project_files')->fetchAll(PDO::FETCH_ASSOC);
    foreach($pfcols as $c) echo $c['Field'].'|'.$c['Type'].PHP_EOL;
} catch(Exception $e) { echo "No project_files table\n"; }

echo "\n-- clients table:\n";
$clcols = $pdo->query('DESCRIBE clients')->fetchAll(PDO::FETCH_ASSOC);
foreach($clcols as $c) echo $c['Field'].'|'.$c['Type'].PHP_EOL;

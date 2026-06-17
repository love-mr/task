<?php
try {
    echo "Connecting...\n";
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    echo "Connected!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

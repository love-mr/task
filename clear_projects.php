<?php
require 'db.php';
$pdo->exec("DELETE FROM projects");
echo "Projects deleted.\n";

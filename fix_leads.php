<?php
require 'c:/Users/acer/Desktop/dummy/db.php';
$pdo->exec("UPDATE employees e JOIN organizations o ON e.org_id = o.id SET e.status = 'Approved' WHERE o.status = 'Active' AND e.role = 'Project Lead' AND e.status = 'Pending'");
echo "Fixed existing users!";

<?php
$f = 'db.php';
$c = file_get_contents($f);
$s = "`status` VARCHAR(50) NOT NULL DEFAULT 'Active',";
$r = "`status` VARCHAR(50) NOT NULL DEFAULT 'Active',\n        `pipeline_stage` VARCHAR(100) DEFAULT NULL,\n        `service_type` VARCHAR(100) DEFAULT NULL,";
if (strpos($c, 'pipeline_stage') === false) {
    $c = str_replace($s, $r, $c);
    file_put_contents($f, $c);
    echo "db.php updated successfully.\n";
} else {
    echo "db.php already contains pipeline_stage.\n";
}

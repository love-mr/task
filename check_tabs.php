<?php
preg_match_all('/id="view-([^"]+)"/', file_get_contents('dashboard.php'), $m);
print_r($m[1]);

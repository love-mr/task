<?php
$c = file_get_contents('dashboard.php');
preg_match('/<div id="view-building".*?<div class="rsk-module-header">.*?<\/div>\s*<\/div>\s*<\/div>/s', $c, $m);
if ($m) {
    echo substr($m[0], 0, 2000);
} else {
    echo "No match for building view";
}

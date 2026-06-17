<?php
$c = file_get_contents('dashboard.php');
if(strpos($c, 'modal-surveymanagement') !== false) {
    echo "Found modal-surveymanagement\n";
    preg_match_all('/id="(modal-[^"]+)"/', $c, $m);
    print_r($m[1]);
} else {
    echo "Not found";
}

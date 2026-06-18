<?php
$c = file_get_contents('api.php');
$s = strpos($c, "action === 'create_survey_record'");
if ($s !== false) {
    echo substr($c, $s - 20, 1500);
} else {
    echo "Not found";
}

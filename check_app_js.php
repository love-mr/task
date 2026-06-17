<?php
$c = file_get_contents('app.js');
$s = strpos($c, 'form-surveymanagement');
if ($s !== false) {
    echo substr($c, max(0, $s - 200), 1000);
}

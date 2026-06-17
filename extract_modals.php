<?php
$lines = file('inject_surveymanagement.php');
$output = '';
$started = false;
foreach($lines as $line) {
    if (strpos($line, '<!-- Modal: Survey Management -->') !== false) {
        $started = true;
    }
    if ($started) {
        $output .= $line;
    }
    if (strpos($line, 'HTML;') !== false && $started) {
        break;
    }
}
file_put_contents('modals.txt', $output);
echo "Extracted.";

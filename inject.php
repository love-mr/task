<?php
$dashboardContent = file_get_contents('c:/Users/acer/Desktop/dummy/dashboard.php');
$snippet = file_get_contents('c:/Users/acer/Desktop/dummy/view_dashboard_snippet.php');

$startStr = '<div id="view-dashboard" class="tab-view active">';
$endStr = '<!-- ==========================================================================';

$startPos = strpos($dashboardContent, $startStr);
// Find the next TAB comment which is TAB 2: PROJECTS
$endPos = strpos($dashboardContent, $endStr, $startPos + 10);

if ($startPos !== false && $endPos !== false) {
    // The snippet already contains the start string, but let's just replace the whole chunk
    // Actually snippet contains startStr and closes its own div.
    $newContent = substr_replace($dashboardContent, $snippet . "\n\n                ", $startPos, $endPos - $startPos);
    file_put_contents('c:/Users/acer/Desktop/dummy/dashboard.php', $newContent);
    echo "Replaced successfully!";
} else {
    echo "Could not find tags.";
}

<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

// Remove the inline style="display: none;" from the view-surveymanagement div
$content = str_replace('<div id="view-surveymanagement" class="tab-view" style="display: none;">', '<div id="view-surveymanagement" class="tab-view">', $content);

file_put_contents($dashboardPath, $content);
echo "Display style removed.";

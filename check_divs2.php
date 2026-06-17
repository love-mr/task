<?php
$html = file_get_contents('c:/Users/acer/Desktop/dummy/dashboard.php');
$startLayout = strpos($html, '<div id="view-layout"');
$startTasks = strpos($html, '<div id="view-tasks"');
echo "Layout starts at: $startLayout\n";
echo "Tasks starts at: $startTasks\n";

$layoutBlock = substr($html, $startLayout, $startTasks - $startLayout);
$divOpen = substr_count($layoutBlock, '<div');
$divClose = substr_count($layoutBlock, '</div');
echo "In view-layout: opened $divOpen, closed $divClose\n";

if ($divOpen != $divClose) {
    echo "MISMATCH! Missing " . ($divOpen - $divClose) . " closing divs before view-tasks.\n";
}

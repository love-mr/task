<?php
$html = file_get_contents('c:/Users/acer/Desktop/dummy/dashboard.php');
$startDashboard = strpos($html, '<div id="view-dashboard"');
$startLayout = strpos($html, '<div id="view-layout"');
echo "Dashboard starts at: $startDashboard\n";
echo "Layout starts at: $startLayout\n";

$dashboardBlock = substr($html, $startDashboard, $startLayout - $startDashboard);
$divOpen = substr_count($dashboardBlock, '<div');
$divClose = substr_count($dashboardBlock, '</div');
echo "In view-dashboard: opened $divOpen, closed $divClose\n";

if ($divOpen != $divClose) {
    echo "MISMATCH! Missing " . ($divOpen - $divClose) . " closing divs before view-layout.\n";
}

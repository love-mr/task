<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

// The block to extract starts with <!-- TAB: SURVEY MANAGEMENT --> and ends with the closing div of view-surveymanagement
$startMarker = "<!-- TAB: SURVEY MANAGEMENT -->";
$startPos = strpos($content, $startMarker);

// Find the end of view-surveymanagement. It ends right before <!-- Footer -->
$footerPos = strpos($content, "<!-- Footer -->");

if ($startPos !== false && $footerPos !== false && $startPos < $footerPos) {
    // Extract the block
    $extractedBlock = substr($content, $startPos, $footerPos - $startPos);
    
    // Remove the block from its current position
    $content = substr_replace($content, "", $startPos, $footerPos - $startPos);
    
    // Now we need to insert it inside the content-area.
    // The user's fix_layout.php looked for the </div> preceding the <!-- Footer --> (after the extraction).
    // Let's do exactly that.
    $insertMarker = "<!-- Footer -->";
    $insertPos = strpos($content, $insertMarker);
    
    if ($insertPos !== false) {
        $precedingDiv = strrpos(substr($content, 0, $insertPos), "</div>");
        
        if ($precedingDiv !== false) {
            $content = substr_replace($content, $extractedBlock . "\n    ", $precedingDiv, 0);
            file_put_contents($dashboardPath, $content);
            echo "Successfully relocated Survey Management to the content-area.";
        } else {
            echo "Could not find closing div before Footer.";
        }
    } else {
        echo "Could not find Footer marker.";
    }
} else {
    echo "Could not find the extracted block markers.";
}

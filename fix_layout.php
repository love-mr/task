<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

$startMarker = "<!-- ==========================================================================\n         TAB: BUILDING MODULE";
$endMarker = "<!-- Export DB variables for JS rendering -->";

$startPos = strpos($content, $startMarker);
$endPos = strpos($content, $endMarker);

if ($startPos !== false && $endPos !== false && $startPos < $endPos) {
    // Extract the block
    $extractedBlock = substr($content, $startPos, $endPos - $startPos);
    
    // Remove the block from its current position
    $content = substr_replace($content, "", $startPos, $endPos - $startPos);
    
    // Find where to insert it. We want it inside content-area, before the footer.
    $insertMarker = "<!-- Footer -->";
    $insertPos = strpos($content, $insertMarker);
    
    if ($insertPos !== false) {
        // Look for the </div> that closes content-area which is right above <!-- Footer -->
        // Actually, just inserting it right before <!-- Footer --> is fine if we also put it inside the </div>.
        // Let's just insert it before the </div> that comes before <!-- Footer -->
        $precedingDiv = strrpos(substr($content, 0, $insertPos), "</div>");
        
        if ($precedingDiv !== false) {
            $content = substr_replace($content, $extractedBlock . "\n    ", $precedingDiv, 0);
            file_put_contents($dashboardPath, $content);
            echo "Successfully relocated the views to the content-area.";
        } else {
            echo "Could not find closing div before Footer.";
        }
    } else {
        echo "Could not find Footer marker.";
    }
} else {
    echo "Could not find the extracted block markers.";
}
?>

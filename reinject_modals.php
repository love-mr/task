<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);
$modals = file_get_contents('modals.txt');

// Check if already injected to avoid duplicates
if (strpos($content, 'id="modal-surveymanagement"') === false) {
    // Inject right before </body>
    // But since the original duplicate cleaner erased up to <!-- Modal: Document Preview -->
    // Let's just put it before </body> to be safe
    $content = str_replace('</body>', "\n    " . trim($modals) . "\n</body>", $content);
    file_put_contents($dashboardPath, $content);
    echo "Modals re-injected successfully.";
} else {
    echo "Modals already exist.";
}

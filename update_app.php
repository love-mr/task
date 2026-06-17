<?php
$file = 'app.js';
$content = file_get_contents($file);
$content = str_replace("alert('Error: ' + data.error);", "alert('Error: ' + (data.message || data.error));", $content);
file_put_contents($file, $content);
echo "app.js updated.\n";

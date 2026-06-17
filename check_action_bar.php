<?php
$c = file_get_contents('dashboard.php');
if (preg_match('/<div class="([^"]*action[^"]*)"/i', $c, $m)) {
    echo $m[0];
} else {
    echo "Not found";
}

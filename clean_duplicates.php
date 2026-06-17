<?php
$c = file_get_contents('dashboard.php');
$c = preg_replace('/<!-- Modal: Survey Management -->.*?<!-- Modal: Document Preview -->/s', '<!-- Modal: Document Preview -->', $c, 1);
file_put_contents('dashboard.php', $c);
echo "Cleaned duplicate modal block.";

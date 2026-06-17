<?php
$f = 'C:/xampp/htdocs/dummy/dashboard.php';
$c = file_get_contents($f);
$logger = '<div id="js-error-logger" style="position:fixed; top:0; left:0; width:100%; background:red; color:white; z-index:999999; padding:10px; display:none; font-family:monospace; font-weight: bold;"></div><script>window.onerror = function(m, s, l, c, e) { var el = document.getElementById("js-error-logger"); el.style.display = "block"; el.innerHTML += m + " at " + s + ":" + l + "<br>"; };</script>';
$c = str_replace('<body>', '<body>'.$logger, $c);
file_put_contents($f, $c);

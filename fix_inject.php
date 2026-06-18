<?php
$f = 'inject_survey_api.php';
$c = file_get_contents($f);
$c = str_replace('} catch (Exception $e) {', '} catch (Throwable $e) {', $c);
file_put_contents($f, $c);
echo "Fixed inject script\n";

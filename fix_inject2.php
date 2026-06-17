<?php
$f = 'inject_survey_api.php';
$c = file_get_contents($f);
$c = str_replace("preg_replace('/(\} catch \(Exception \\\$e\) \{)/i'", "preg_replace('/(\} catch \(Throwable \\\$e\) \{)/i'", $c);
file_put_contents($f, $c);
echo "Fixed regex.\n";

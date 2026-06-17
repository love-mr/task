<?php
$f = 'api.php';
$c = file_get_contents($f);

// Find the start of survey module
$surveyStart = "        // SURVEY MANAGEMENT MODULE";
$fixCode = "        \$meOrgId = (int)(\$jwtPayload['org_id'] ?? 1);\n        \$meId = (int)(\$jwtPayload['user_id'] ?? 1);\n\n";

$c = str_replace($surveyStart, $surveyStart . "\n" . $fixCode, $c);

file_put_contents($f, $c);
echo "Fixed missing meOrgId/meId variables.\n";

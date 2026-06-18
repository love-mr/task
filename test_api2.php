<?php
$ch = curl_init('http://localhost/task/api.php');
$postData = [
    'action' => 'create_survey_record',
    'survey_number' => '123-TEST',
    'total_area' => '100'
];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
curl_close($ch);
echo "RESPONSE:\n" . $response . "\n";

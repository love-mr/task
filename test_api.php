<?php
$_REQUEST['action'] = 'create_survey_record';
$_POST['survey_number'] = 'TEST-123';
$_POST['sub_division_number'] = 'SUB-1';
$_POST['owner_name'] = 'Test Owner';
$_POST['total_area'] = '100.5';
$_POST['survey_date'] = '2026-06-17';

// Mock Cookie to bypass JWT
$_COOKIE['vyala_taskpad_jwt_token'] = 'test';
require_once 'jwt.php';
// Override verify_jwt somehow? Actually, I'll just temporarily override $jwtPayload in api.php?
// No, let me just make a cURL request!

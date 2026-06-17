<?php
// logout.php
setcookie('vyala_taskpad_jwt_token', '', time() - 3600, '/', '', false, true);
header("Location: index.php");
exit;
?>


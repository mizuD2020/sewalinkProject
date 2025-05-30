<?php
session_start();
session_destroy();
header('Location: ../logins/worker_login.php');
exit();
?>
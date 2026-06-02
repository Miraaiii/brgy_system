<?php
session_start();
include 'config/connection.php';

/* Clear session */
$_SESSION = [];

/* Destroy session */
session_destroy();

/* Redirect to login page */
header("Location: ../login.php");
exit();
?>
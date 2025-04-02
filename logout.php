<?php
require_once 'includes/session.php';

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
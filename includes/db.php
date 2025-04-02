<?php
$db_host = 'localhost';
$db_port = '1521';
$db_service = 'XE';
$db_user = 'system';
$db_pass = 'oracle';

try {
    $conn = new PDO("oci:dbname=//$db_host:$db_port/$db_service", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
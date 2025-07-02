<?php
$DB_HOST = 'localhost';
$DB_NAME = 'hopt';
$DB_USER = 'hopt_user';
$DB_PASS = 'password';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES 'utf8'");
} catch (PDOException $e) {
    exit('Database error: ' . $e->getMessage());
}
?>

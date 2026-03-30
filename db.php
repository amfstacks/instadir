<?php
$db_host = 'localhost';
$db_name = 'instadirdev';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Fail silently or log to a local file so the user can still use the tool
    error_log("DB Connection failed: " . $e->getMessage());
    $pdo = null; 
}
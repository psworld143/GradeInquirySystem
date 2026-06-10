<?php
$host = 'localhost';
$db   = 'grade_inquiry_system';
$user = 'root'; // or your DB user
$pass = '';     // or your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<?php
// Database configuration template
// Rename this file to db.php and update the values with your hosting credentials

$host = 'localhost'; // Your hosting database host
$db   = 'your_database_name'; // Your hosting database name
$user = 'your_database_user'; // Your hosting database username
$pass = 'your_database_password'; // Your hosting database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?> 
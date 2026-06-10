<?php
require_once 'config/db.php';

try {
    // Read the SQL file
    $sql = file_get_contents('migrations/create_teacher_ratings_table.sql');
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Teacher ratings table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
} 
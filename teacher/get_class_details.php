<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get class_id from URL parameter
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

try {
    // Fetch class details including semester and school year
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.class_name,
            c.semester_id,
            s.semester_name,
            sy.id as school_year_id,
            sy.school_year
        FROM classes c
        JOIN semesters s ON c.semester_id = s.id
        JOIN school_years sy ON s.school_year_id = sy.id
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$class_id, $_SESSION['user_id']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Class not found or unauthorized']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'class' => $class
    ]);
} catch (PDOException $e) {
    error_log("Error fetching class details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} 
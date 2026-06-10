<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['grade_id']) || !isset($data['grade']) || !isset($data['remarks'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$grade_id = (int)$data['grade_id'];
$grade = trim($data['grade']);
$remarks = trim($data['remarks']);
$is_visible = isset($data['is_visible']) ? (int)$data['is_visible'] : 0;

try {
    // First verify that the grade belongs to a class taught by this teacher
    $stmt = $pdo->prepare("
        SELECT g.id 
        FROM grades g
        JOIN classes c ON g.class_id = c.id
        WHERE g.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$grade_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Grade not found or unauthorized']);
        exit();
    }

    // Update the grade
    $stmt = $pdo->prepare("
        UPDATE grades 
        SET final_grade = ?, remarks = ?, is_visible = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$grade, $remarks, $is_visible, $grade_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update grade']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 
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

if (!isset($data['class_id']) || !isset($data['new_name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$class_id = $data['class_id'];
$new_name = trim($data['new_name']);
$teacher_id = $_SESSION['user_id'];

// Validate input
if (empty($new_name)) {
    echo json_encode(['success' => false, 'message' => 'Class name cannot be empty']);
    exit();
}

try {
    // First verify that the class belongs to the teacher
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found or unauthorized']);
        exit();
    }

    // Update the class name
    $stmt = $pdo->prepare("UPDATE classes SET class_name = ? WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$new_name, $class_id, $teacher_id]);

    echo json_encode(['success' => true, 'message' => 'Class name updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 
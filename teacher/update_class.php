<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $class_name = trim($_POST['class_name']);
    $semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;

    if (!$class_id || empty($class_name) || $semester_id === null) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Verify that the class belongs to the teacher
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized to update this class');
        }

        // Verify that the semester exists and belongs to the active school year
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM semesters s 
            JOIN school_years sy ON s.school_year_id = sy.id 
            WHERE s.id = ? AND sy.is_active = 1
        ");
        $stmt->execute([$semester_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Invalid semester selected");
        }

        // Update the class
        $stmt = $pdo->prepare("
            UPDATE classes 
            SET class_name = ?, semester_id = ? 
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([$class_name, $semester_id, $class_id, $_SESSION['user_id']]);

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Class updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error updating class: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 
<?php
session_start();
require_once '../config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request and has the required parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student' && isset($_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First check if student exists
        $check_stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
        $check_stmt->execute([$student_id]);
        if (!$check_stmt->fetch()) {
            throw new Exception("Student not found");
        }

        // Delete related records first
        try {
            $stmt = $pdo->prepare("DELETE FROM complaints WHERE student_id = ?");
            $stmt->execute([$student_id]);
        } catch (PDOException $e) {
            error_log("Error deleting complaints: " . $e->getMessage());
            // Continue with deletion even if complaints deletion fails
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM grades WHERE student_id = ?");
            $stmt->execute([$student_id]);
        } catch (PDOException $e) {
            error_log("Error deleting grades: " . $e->getMessage());
            // Continue with deletion even if grades deletion fails
        }
        
        // Delete the student
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No student was deleted");
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log the error
        error_log("Error in delete_student.php: " . $e->getMessage());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'error' => 'Error deleting student',
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Return error for invalid request
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid request',
        'details' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'action' => $_POST['action'] ?? 'not set',
            'student_id' => $_POST['student_id'] ?? 'not set'
        ]
    ]);
} 
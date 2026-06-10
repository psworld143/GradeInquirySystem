<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['grade_ids']) || !is_array($data['grade_ids']) || empty($data['grade_ids'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No grades selected for deletion']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, verify that all grades belong to classes owned by this teacher
    $placeholders = str_repeat('?,', count($data['grade_ids']) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT g.id 
        FROM grades g
        JOIN classes c ON g.class_id = c.id
        WHERE g.id IN ($placeholders)
        AND c.teacher_id = ?
    ");
    
    $params = array_merge($data['grade_ids'], [$_SESSION['user_id']]);
    $stmt->execute($params);
    $validGradeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($validGradeIds) !== count($data['grade_ids'])) {
        throw new Exception('Some grades do not belong to your classes');
    }

    // Delete the grades
    $stmt = $pdo->prepare("DELETE FROM grades WHERE id IN ($placeholders)");
    $stmt->execute($data['grade_ids']);

    // Commit transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Grades deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    error_log("Error in delete_grades.php: " . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete grades: ' . $e->getMessage()
    ]);
} 
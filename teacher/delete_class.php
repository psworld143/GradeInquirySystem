<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$class_id = $data['class_id'] ?? null;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if class belongs to the teacher
    $stmt = $pdo->prepare("SELECT teacher_id FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch();

    if (!$class || $class['teacher_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized to delete this class');
    }

    // Count related data
    $counts = [
        'grades' => 0,
        'ratings' => 0
    ];

    // Count grades
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $counts['grades'] = $stmt->fetchColumn();

    // Count ratings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_ratings WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $counts['ratings'] = $stmt->fetchColumn();

    // Delete related data
    if ($counts['grades'] > 0) {
        $stmt = $pdo->prepare("DELETE FROM grades WHERE class_id = ?");
        $stmt->execute([$class_id]);
    }

    if ($counts['ratings'] > 0) {
        $stmt = $pdo->prepare("DELETE FROM teacher_ratings WHERE class_id = ?");
        $stmt->execute([$class_id]);
    }

    // Delete the class
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Class deleted successfully',
        'deleted_data' => $counts
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$class_id = $_GET['class_id'] ?? null;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

try {
    // Check if class belongs to the teacher
    $stmt = $pdo->prepare("SELECT teacher_id FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch();

    if (!$class || $class['teacher_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized to access this class');
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

    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$class_id = isset($data['class_id']) ? (int)$data['class_id'] : 0;
$is_visible = isset($data['is_visible']) ? (int)$data['is_visible'] : 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid class ID.']);
    exit();
}

try {
    // Verify the class belongs to the teacher
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found or unauthorized.']);
        exit();
    }

    // Update all grades for this class
    $stmt = $pdo->prepare("UPDATE grades SET is_visible = ? WHERE class_id = ?");
    $stmt->execute([$is_visible, $class_id]);

    echo json_encode([
        'success' => true,
        'message' => $is_visible ? 'All grades are now visible to students.' : 'All grades are now hidden from students.'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 
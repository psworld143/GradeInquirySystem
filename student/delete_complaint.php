<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['complaint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Complaint ID is required']);
    exit();
}

$complaint_id = $data['complaint_id'];
$student_id = $_SESSION['user_id'];

try {
    // First verify that the complaint belongs to the student
    $stmt = $pdo->prepare("SELECT id FROM complaints WHERE id = ? AND student_id = ?");
    $stmt->execute([$complaint_id, $student_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found or unauthorized']);
        exit();
    }
    
    // Delete the complaint
    $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ? AND student_id = ?");
    $stmt->execute([$complaint_id, $student_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Complaint deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete complaint']);
    }
} catch (PDOException $e) {
    error_log("Error deleting complaint: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the complaint']);
}
?> 
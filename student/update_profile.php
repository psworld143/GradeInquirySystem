<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['first_name']) || !isset($data['last_name']) || 
    empty(trim($data['first_name'])) || empty(trim($data['last_name']))) {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
    exit();
}

// Sanitize input
$first_name = trim($data['first_name']);
$last_name = trim($data['last_name']);
$user_id = $_SESSION['user_id'];

try {
    // Update student information
    $stmt = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE id = ?");
    $result = $stmt->execute([$first_name, $last_name, $user_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch (PDOException $e) {
    error_log("Error updating profile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating your profile']);
}
?> 
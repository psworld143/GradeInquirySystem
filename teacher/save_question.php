<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['question']) || !isset($data['correct_answer']) || 
    empty(trim($data['question'])) || empty(trim($data['correct_answer']))) {
    echo json_encode(['success' => false, 'message' => 'Question and correct answer are required']);
    exit();
}

try {
    // Prepare and execute the insert query
    $stmt = $pdo->prepare("INSERT INTO question_bank (question, correct_answer) VALUES (?, ?)");
    $result = $stmt->execute([
        trim($data['question']),
        trim($data['correct_answer'])
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Question added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add question']);
    }
} catch (PDOException $e) {
    error_log("Error in save_question.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 
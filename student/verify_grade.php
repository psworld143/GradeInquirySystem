<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get a random question
    $stmt = $pdo->query("SELECT id, question FROM question_bank ORDER BY RAND() LIMIT 1");
    $question = $stmt->fetch();
    
    if ($question) {
        $_SESSION['current_question_id'] = $question['id'];
        echo json_encode(['success' => true, 'question' => $question['question']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No questions available']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $answer = $data['answer'] ?? '';
    $question_id = $_SESSION['current_question_id'] ?? 0;
    
    if (!$question_id) {
        echo json_encode(['success' => false, 'message' => 'No question selected']);
        exit();
    }
    
    // Verify the answer
    $stmt = $pdo->prepare("SELECT correct_answer FROM question_bank WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();
    
    if (strtolower(trim($answer)) === strtolower(trim($correct_answer))) {
        $_SESSION['grade_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'Answer correct!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect answer. Please try again.']);
    }
}
?> 
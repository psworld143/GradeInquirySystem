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
$password = $data['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get student's current password and name
    $stmt = $pdo->prepare("SELECT password, first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Verify password
    if (!password_verify($password, $student['password'])) {
        throw new Exception('Incorrect password');
    }

    // Delete student's ratings
    $stmt = $pdo->prepare("DELETE FROM teacher_ratings WHERE student_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Delete student's grades using first_name and last_name
    $stmt = $pdo->prepare("DELETE FROM grades WHERE firstname = ? AND lastname = ?");
    $stmt->execute([$student['first_name'], $student['last_name']]);

    // Delete student's complaints
    $stmt = $pdo->prepare("DELETE FROM complaints WHERE student_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Finally, delete the student account
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Commit transaction
    $pdo->commit();

    // Clear session
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 
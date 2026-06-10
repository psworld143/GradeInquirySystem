<?php
session_start();
require_once '../config/db.php';

// Disable error reporting for production
error_reporting(0);

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get and validate input
$complaint_type = $_POST['complaint_type'] ?? '';
$title = $_POST['complaint_title'] ?? '';
$description = $_POST['complaint_description'] ?? '';
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

// Validate required fields
if (empty($complaint_type) || empty($title) || empty($description)) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]);
    exit();
}

// Validate complaint type
$valid_types = ['grade', 'teacher', 'class', 'other'];
if (!in_array($complaint_type, $valid_types)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid complaint type'
    ]);
    exit();
}

try {
    // First, check if complaints table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'complaints'");
    if ($stmt->rowCount() === 0) {
        // Table doesn't exist, try to create it
        $sql = file_get_contents('../migrations/create_complaints_table.sql');
        $pdo->exec($sql);
    }

    // Insert complaint into database
    $stmt = $pdo->prepare("
        INSERT INTO complaints (
            student_id,
            complaint_type,
            title,
            description,
            status,
            is_anonymous,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $complaint_type,
        $title,
        $description,
        $is_anonymous
    ]);

    // Log successful submission
    error_log("Complaint submitted successfully - Student ID: $_SESSION[user_id], Type: $complaint_type");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Complaint submitted successfully'
    ]);

} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error: " . $e->getMessage());
    
    // Check for specific error types
    if (strpos($e->getMessage(), 'Table \'complaints\' doesn\'t exist') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Complaints system is not properly set up. Please contact the administrator.'
        ]);
    } else if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid student information. Please try logging out and logging back in.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while submitting your complaint'
        ]);
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 
<?php
// Disable error reporting to prevent HTML errors from being output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config/db.php';

// Set JSON header for all responses
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    sendJsonResponse(false, 'Unauthorized access');
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Validate and sanitize input
$class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
$is_anonymous = filter_input(INPUT_POST, 'is_anonymous', FILTER_VALIDATE_INT);

// Log received data
error_log("Received rating submission data: " . json_encode([
    'class_id' => $class_id,
    'rating' => $rating,
    'comment' => $comment,
    'is_anonymous' => $is_anonymous,
    'raw_is_anonymous' => $_POST['is_anonymous'] ?? 'not set'
]));

// Validate required fields
if (!$class_id || !$rating || $rating < 1 || $rating > 5) {
    error_log("Invalid input validation failed: " . json_encode([
        'class_id' => $class_id,
        'rating' => $rating,
        'is_anonymous' => $is_anonymous
    ]));
    sendJsonResponse(false, 'Invalid input: Please provide a valid class ID and rating (1-5)');
}

// Ensure is_anonymous is either 0 or 1
$is_anonymous = ($is_anonymous === 1) ? 1 : 0;
error_log("Processed is_anonymous value: " . $is_anonymous);

try {
    // Get student's name from students table
    $stmt = $pdo->prepare("
        SELECT first_name, last_name 
        FROM students 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();

    if (!$student) {
        sendJsonResponse(false, 'Student information not found');
    }

    // Check if student is enrolled in this class using firstname and lastname
    $stmt = $pdo->prepare("
        SELECT 1 FROM grades 
        WHERE class_id = ? 
        AND firstname = ? 
        AND lastname = ?
    ");
    $stmt->execute([$class_id, $student['first_name'], $student['last_name']]);
    
    if (!$stmt->fetch()) {
        sendJsonResponse(false, 'You are not enrolled in this class');
    }

    // Insert or update rating in teacher_ratings table
    $stmt = $pdo->prepare("
        INSERT INTO teacher_ratings (class_id, student_id, rating, comment, is_anonymous)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        rating = VALUES(rating),
        comment = VALUES(comment),
        is_anonymous = VALUES(is_anonymous)
    ");
    
    $params = [$class_id, $_SESSION['user_id'], $rating, $comment, $is_anonymous];
    error_log("Executing query with parameters: " . json_encode($params));
    
    $result = $stmt->execute($params);
    
    if ($result) {
        error_log("Rating submitted successfully for class_id: " . $class_id);
        sendJsonResponse(true, 'Rating submitted successfully');
    } else {
        error_log("Failed to save rating for class_id: " . $class_id);
        sendJsonResponse(false, 'Failed to save rating');
    }

} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in submit_rating.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Info: " . json_encode($e->errorInfo));
    
    // Check for specific database errors
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, "Table 'teacher_ratings' doesn't exist") !== false) {
        sendJsonResponse(false, 'Please run setup_ratings_table.php first to create the required table.');
    } elseif (strpos($errorMessage, "foreign key constraint fails") !== false) {
        sendJsonResponse(false, 'Invalid class or student ID. Please try again.');
    } elseif (strpos($errorMessage, "Duplicate entry") !== false) {
        sendJsonResponse(false, 'You have already rated this class.');
    } else {
        sendJsonResponse(false, 'Database error: ' . $errorMessage);
    }
} catch (Exception $e) {
    // Log any other errors
    error_log("Unexpected error in submit_rating.php: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    sendJsonResponse(false, 'An unexpected error occurred. Please try again.');
} 
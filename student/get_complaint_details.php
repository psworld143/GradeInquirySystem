<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if complaint ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid complaint ID'
    ]);
    exit();
}

$complaint_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

try {
    // Fetch complaint details
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.complaint_type,
            c.title,
            c.description,
            c.status,
            c.created_at,
            c.updated_at,
            c.is_anonymous,
            c.admin_response
        FROM complaints c
        WHERE c.id = ? AND c.student_id = ?
    ");
    
    $stmt->execute([$complaint_id, $student_id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        echo json_encode([
            'success' => false,
            'message' => 'Complaint not found or access denied'
        ]);
        exit();
    }

    // Format dates
    $complaint['created_at'] = date('Y-m-d H:i:s', strtotime($complaint['created_at']));
    $complaint['updated_at'] = date('Y-m-d H:i:s', strtotime($complaint['updated_at']));

    // Return success response
    echo json_encode([
        'success' => true,
        'complaint' => $complaint
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching complaint details'
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 
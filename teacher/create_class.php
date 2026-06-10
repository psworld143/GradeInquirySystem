<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name']);
    $teacher_id = $_SESSION['user_id'];
    $semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;

    if (!empty($class_name) && $semester_id !== null) {
        try {
            // Verify that the semester exists and belongs to the active school year
            $stmt = $pdo->prepare("
                SELECT s.id 
                FROM semesters s 
                JOIN school_years sy ON s.school_year_id = sy.id 
                WHERE s.id = ? AND sy.is_active = 1
            ");
            $stmt->execute([$semester_id]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Invalid semester selected");
            }

            // Insert the class with semester_id
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, semester_id) VALUES (?, ?, ?)");
            $stmt->execute([$teacher_id, $class_name, $semester_id]);
        } catch (Exception $e) {
            // Log the error
            error_log("Error creating class: " . $e->getMessage());
        }
    }
}

header("Location: dashboard.php");
exit();

<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';

// Function to send teacher status notification email
function sendTeacherStatusEmail($teacher_email, $teacher_name, $status) {
    require_once __DIR__ . '/../vendor/autoload.php';  // Updated path to vendor autoload

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Debug mode
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($teacher_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Teacher Account Status Update - SEAIT Grade Inquiry System';
        
        // Email body
        $status_text = $status === 'approved' ? 'Approved' : 'Rejected';
        $status_color = $status === 'approved' ? '#28a745' : '#dc3545';
        $status_message = $status === 'approved' 
            ? 'Your teacher account has been approved. You can now log in to the teacher dashboard.'
            : 'Your teacher account has been rejected. If you believe this is an error, please contact the administrator.';
        
        // Set the specific login URL
        $login_url = "http://mygrades.seait-edu.ph/";

        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4e73df; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8f9fc; }
                    .status { 
                        display: inline-block;
                        padding: 10px 20px;
                        background: {$status_color};
                        color: white;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .button { 
                        display: inline-block;
                        padding: 10px 20px;
                        background: #4e73df;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Teacher Account Status Update</h2>
                    </div>
                    <div class='content'>
                        <p>Hello {$teacher_name},</p>
                        <p>Your teacher account status has been updated:</p>
                        <p style='text-align: center;'>
                            <span class='status'>{$status_text}</span>
                        </p>
                        <p>{$status_message}</p>
                        " . ($status === 'approved' ? "
                        <p style='text-align: center; color: white;'>
                            <a href='{$login_url}' class='button' style='color: white;'>Login to Teacher Dashboard</a>
                        </p>
                        <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                        <p style='word-break: break-all;'>{$login_url}</p>
                        " : "") . "
                    </div>
                    <div class='footer'>
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " SEAIT Grade Inquiry System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
            Teacher Account Status Update
            
            Hello {$teacher_name},
            
            Your teacher account status has been updated: {$status_text}
            
            {$status_message}
            " . ($status === 'approved' ? "
            
            To access your teacher dashboard, visit this link:
            {$login_url}
            " : "") . "
            
            This is an automated message, please do not reply to this email.
        ";

        error_log("Attempting to send email to: " . $teacher_email);
        $result = $mail->send();
        error_log("Email sent successfully to: " . $teacher_email);
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed for {$teacher_email}: " . $mail->ErrorInfo);
        error_log("Exception details: " . $e->getMessage());
        return false;
    }
}

// Test database connection and table structure
try {
    // Test students table
    $test_stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $student_count = $test_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Total students in database: " . $student_count);
    
    // Test users table
    $test_stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $test_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Total users in database: " . $user_count);
    
    // Test the join between students and users
    $test_stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM students s 
        JOIN users u ON s.id = u.id
    ");
    $joined_count = $test_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Total joined records: " . $joined_count);
} catch (PDOException $e) {
    error_log("Database test error: " . $e->getMessage());
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle school year and semester management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_school_year':
                $school_year = trim($_POST['school_year']);
                $is_active = (int)$_POST['is_active'];

                // Validate school year format (e.g., 2023-2024)
                if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                    throw new Exception("Invalid school year format. Please use format: YYYY-YYYY");
                }

                // Check if school year already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM school_years WHERE school_year = ?");
                $stmt->execute([$school_year]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("School year already exists");
                }

                // If setting as active, deactivate other school years
                if ($is_active) {
                    $stmt = $pdo->prepare("UPDATE school_years SET is_active = 0");
                    $stmt->execute();
                }

                // Insert new school year
                $stmt = $pdo->prepare("INSERT INTO school_years (school_year, is_active) VALUES (?, ?)");
                $stmt->execute([$school_year, $is_active]);
                break;

            case 'edit_school_year':
                $id = (int)$_POST['id'];
                $school_year = trim($_POST['school_year']);
                $is_active = (int)$_POST['is_active'];

                // Validate school year format
                if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                    throw new Exception("Invalid school year format. Please use format: YYYY-YYYY");
                }

                // Check if school year already exists (excluding current record)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM school_years WHERE school_year = ? AND id != ?");
                $stmt->execute([$school_year, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("School year already exists");
                }

                // If setting as active, deactivate other school years
                if ($is_active) {
                    $stmt = $pdo->prepare("UPDATE school_years SET is_active = 0 WHERE id != ?");
                    $stmt->execute([$id]);
                }

                // Update school year
                $stmt = $pdo->prepare("UPDATE school_years SET school_year = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$school_year, $is_active, $id]);
                break;

            case 'delete_school_year':
                $id = (int)$_POST['id'];

                // Check if school year exists
                $stmt = $pdo->prepare("SELECT school_year FROM school_years WHERE id = ?");
                $stmt->execute([$id]);
                $school_year = $stmt->fetch();
                
                if (!$school_year) {
                    throw new Exception("School year not found");
                }

                // Check if school year has associated semesters
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE school_year_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete school year with associated semesters");
                }

                // Delete school year
                $stmt = $pdo->prepare("DELETE FROM school_years WHERE id = ?");
                $stmt->execute([$id]);

                // Return success response
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'School year deleted successfully']);
                exit;
                break;

            case 'add_semester':
                $semester_name = trim($_POST['semester_name']);
                $school_year_id = (int)$_POST['school_year_id'];
                $is_active = (int)$_POST['is_active'];

                // Validate semester name
                if (empty($semester_name)) {
                    throw new Exception("Semester name is required");
                }

                // Check if semester already exists for this school year
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE semester_name = ? AND school_year_id = ?");
                $stmt->execute([$semester_name, $school_year_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Semester already exists for this school year");
                }

                // If setting as active, deactivate other semesters
                if ($is_active) {
                    $stmt = $pdo->prepare("UPDATE semesters SET is_active = 0");
                    $stmt->execute();
                }

                // Insert new semester
                $stmt = $pdo->prepare("INSERT INTO semesters (semester_name, school_year_id, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$semester_name, $school_year_id, $is_active]);
                break;

            case 'edit_semester':
                $id = (int)$_POST['id'];
                $semester_name = trim($_POST['semester_name']);
                $school_year_id = (int)$_POST['school_year_id'];
                $is_active = (int)$_POST['is_active'];

                // Validate semester name
                if (empty($semester_name)) {
                    throw new Exception("Semester name is required");
                }

                // Check if semester already exists for this school year (excluding current record)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE semester_name = ? AND school_year_id = ? AND id != ?");
                $stmt->execute([$semester_name, $school_year_id, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Semester already exists for this school year");
                }

                // If setting as active, deactivate other semesters
                if ($is_active) {
                    $stmt = $pdo->prepare("UPDATE semesters SET is_active = 0 WHERE id != ?");
                    $stmt->execute([$id]);
                }

                // Update semester
                $stmt = $pdo->prepare("UPDATE semesters SET semester_name = ?, school_year_id = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$semester_name, $school_year_id, $is_active, $id]);
                break;

            case 'delete_semester':
                $id = (int)$_POST['id'];

                // Check if semester exists
                $stmt = $pdo->prepare("SELECT semester_name FROM semesters WHERE id = ?");
                $stmt->execute([$id]);
                $semester = $stmt->fetch();
                
                if (!$semester) {
                    throw new Exception("Semester not found");
                }

                // Check if semester has associated grades
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM grades g 
                    JOIN classes c ON g.class_id = c.id 
                    WHERE c.semester_id = ?
                ");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete semester with associated grades");
                }

                // Delete semester
                $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = ?");
                $stmt->execute([$id]);

                // Return success response
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Semester deleted successfully']);
                exit;
                break;
        }
    } catch (Exception $e) {
        // Return error response
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle teacher verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['teacher_id'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $action = $_POST['action'];

    try {
        // Get teacher information before updating status
        $stmt = $pdo->prepare("SELECT email, full_name FROM teachers WHERE id = ?");
        $stmt->execute([$teacher_id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Processing teacher verification for ID: " . $teacher_id);
        error_log("Teacher data: " . print_r($teacher, true));

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE teachers SET status = 'approved' WHERE id = ?");
            $stmt->execute([$teacher_id]);
            
            // Send approval email
            if ($teacher) {
                error_log("Attempting to send approval email to: " . $teacher['email']);
                $email_sent = sendTeacherStatusEmail($teacher['email'], $teacher['full_name'], 'approved');
                error_log("Approval email send result: " . ($email_sent ? "Success" : "Failed"));
            } else {
                error_log("Teacher data not found for ID: " . $teacher_id);
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE teachers SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$teacher_id]);
            
            // Send rejection email
            if ($teacher) {
                error_log("Attempting to send rejection email to: " . $teacher['email']);
                $email_sent = sendTeacherStatusEmail($teacher['email'], $teacher['full_name'], 'rejected');
                error_log("Rejection email send result: " . ($email_sent ? "Success" : "Failed"));
            } else {
                error_log("Teacher data not found for ID: " . $teacher_id);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in teacher verification: " . $e->getMessage());
        $error = "Error processing request: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("General error in teacher verification: " . $e->getMessage());
        $error = "Error processing request: " . $e->getMessage();
    }
}

// Fetch pending teachers
try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, created_at 
        FROM teachers 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pending_teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching teachers: " . $e->getMessage();
    $pending_teachers = [];
}

// Fetch approved teachers with their classes
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.full_name, t.email, t.created_at,
               COUNT(c.id) as class_count,
               GROUP_CONCAT(c.class_name SEPARATOR ', ') as classes
        FROM teachers t
        LEFT JOIN classes c ON t.id = c.teacher_id
        WHERE t.status = 'approved'
        GROUP BY t.id
        ORDER BY t.full_name ASC
    ");
    $stmt->execute();
    $approved_teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching teachers: " . $e->getMessage();
    $approved_teachers = [];
}

// Fetch rejected teachers
try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, created_at 
        FROM teachers 
        WHERE status = 'rejected'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $rejected_teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching teachers: " . $e->getMessage();
    $rejected_teachers = [];
}

// Fetch complaints
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.complaint_type,
            c.title,
            c.description,
            c.status,
            c.created_at,
            c.is_anonymous,
            CASE 
                WHEN c.is_anonymous = 1 THEN 'Anonymous'
                ELSE CONCAT(s.first_name, ' ', s.last_name)
            END as student_name,
            u.email as student_email
        FROM complaints c
        LEFT JOIN students s ON c.student_id = s.id
        LEFT JOIN users u ON s.id = u.id
        ORDER BY 
            CASE 
                WHEN c.status = 'pending' THEN 1
                WHEN c.status = 'in_progress' THEN 2
                ELSE 3
            END,
            c.created_at DESC
    ");
    $stmt->execute();
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching complaints: " . $e->getMessage();
    $complaints = [];
}

// Handle complaint status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $action = $_POST['action'];
    $admin_response = $_POST['admin_response'] ?? '';

    try {
        if (in_array($action, ['pending', 'in_progress', 'resolved', 'rejected'])) {
            $stmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_response = ? WHERE id = ?");
            $stmt->execute([$action, $admin_response, $complaint_id]);
            
            // Set a success message in session
            $_SESSION['update_success'] = true;
            
            // Redirect to refresh the page
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error updating complaint status: " . $e->getMessage();
    }
}

// Display success message if set
if (isset($_SESSION['update_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            Complaint status updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['update_success']);
}

// Handle student deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student' && isset($_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];
    
    try {
        // First delete related records
        $stmt = $pdo->prepare("DELETE FROM complaints WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        $stmt = $pdo->prepare("DELETE FROM grades WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        // Then delete the student
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        
        $success = "Student successfully deleted.";
    } catch (PDOException $e) {
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Handle student search
$search_results = [];
if (isset($_GET['search_student'])) {
    $search_term = '%' . $_GET['search_student'] . '%';
    try {
        // Debug: Print the search term
        error_log("Search term: " . $search_term);
        
        $stmt = $pdo->prepare("
            SELECT * 
            FROM students 
            WHERE first_name LIKE ? OR last_name LIKE ?
            ORDER BY last_name, first_name
        ");
        $stmt->execute([$search_term, $search_term]);
        $search_results = $stmt->fetchAll();
        
        // Debug: Print the number of results
        error_log("Number of results found: " . count($search_results));
        
        // Debug: Print the actual SQL query
        error_log("SQL Query: " . $stmt->queryString);
        
        // Debug: Print the first result if any
        if (!empty($search_results)) {
            error_log("First result: " . print_r($search_results[0], true));
        }
    } catch (PDOException $e) {
        $error = "Error searching students: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// Fetch existing co-admins
try {
    // First, check if last_login column exists
    $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    $column_exists = $check_column->rowCount() > 0;

    if (!$column_exists) {
        // Add last_login column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.created_at, u.last_login
        FROM users u
        WHERE u.user_type = 'admin' AND u.id != ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $co_admins = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching co-admins: " . $e->getMessage();
    $co_admins = [];
}

// Handle co-admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_co_admin') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email already exists");
        }

        // Validate password
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }

        // Create co-admin account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, user_type, created_at)
            VALUES (?, ?, 'admin', NOW())
        ");
        $stmt->execute([$email, $hashed_password]);

        $success = "Co-admin account created successfully";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch school years
$stmt = $pdo->query("SELECT * FROM school_years ORDER BY school_year DESC");
$school_years = $stmt->fetchAll();

// Fetch semesters with their school years
$stmt = $pdo->query("
    SELECT s.*, sy.school_year 
    FROM semesters s 
    JOIN school_years sy ON s.school_year_id = sy.id 
    ORDER BY sy.school_year DESC, s.semester_name
");
$semesters = $stmt->fetchAll();

// Get active school year and semester
$stmt = $pdo->query("SELECT * FROM school_years WHERE is_active = 1 LIMIT 1");
$active_school_year = $stmt->fetch();

$stmt = $pdo->query("SELECT * FROM semesters WHERE is_active = 1 LIMIT 1");
$active_semester = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            padding: 2rem 1rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.2s ease-in-out;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: var(--secondary-color);
            border-top: none;
        }

        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .alert {
            border-radius: 1rem;
            border: none;
        }

        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .dashboard-header {
                padding: 1.5rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        /* Add styles for the View Details button container */
        .view-details-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 38px; /* Match the height of the button */
        }

        .view-details-btn {
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .status-option {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin: 0.5rem 0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .status-option:hover {
            background-color: #f8f9fa;
        }

        .status-option.selected {
            background-color: #e9ecef;
        }

        .status-option .status-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .status-option .status-text {
            font-weight: 500;
        }

        .status-option .status-description {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .complaint-description {
            text-align: left;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .complaint-description .description-content {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            line-height: 1.6;
            white-space: pre-wrap;
            width: 100%;
            max-width: 100%;
            text-align: justify;
            text-align-last: left;
        }

        .complaint-description-modal .swal2-popup {
            width: 50vw !important;
            max-width: 600px !important;
        }
        .complaint-description-modal .swal2-html-container {
            text-align: justify !important;
            text-align-last: left !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        /* Add loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex; /* Changed from 'none' to 'flex' to show by default */
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add fade-out animation */
        .loading-overlay.fade-out {
            opacity: 0;
            transition: opacity 0.5s ease-out;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-primary text-white me-3" style="width: 45px; height: 45px; font-size: 1.2rem;">
                        A
                    </div>
                    <div>
                        <h2 class="mb-0">Admin Dashboard</h2>
                        <p class="mb-0 mt-2">Welcome back, Administrator</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                        <i class="bi bi-key-fill"></i> Change Password
                    </button>
                    <a href="../logout.php" class="btn btn-light">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?= count($pending_teachers) ?></div>
                    <div class="stats-label">Pending Teachers</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?= count($approved_teachers) ?></div>
                    <div class="stats-label">Approved Teachers</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?= count($rejected_teachers) ?></div>
                    <div class="stats-label">Rejected Teachers</div>
                </div>
            </div>
        </div>

        <!-- Pending Teachers -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-clock-history me-2"></i>
                Pending Teacher Verifications
            </div>
            <div class="card-body">
                <?php if (empty($pending_teachers)): ?>
                    <p class="text-muted mb-0 text-center py-4">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        No pending teacher verifications.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_teachers as $teacher): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-primary text-white me-2">
                                                    <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($teacher['full_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($teacher['email']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($teacher['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="button" class="btn btn-success btn-sm approve-teacher-btn" 
                                                            data-teacher-id="<?= $teacher['id'] ?>"
                                                            data-teacher-name="<?= htmlspecialchars($teacher['full_name']) ?>">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline ms-2">
                                                    <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="button" class="btn btn-danger btn-sm reject-teacher-btn"
                                                            data-teacher-id="<?= $teacher['id'] ?>"
                                                            data-teacher-name="<?= htmlspecialchars($teacher['full_name']) ?>">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approved Teachers -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="bi bi-check-circle-fill me-2"></i>
                Approved Teachers and Their Classes
            </div>
            <div class="card-body">
                <?php if (empty($approved_teachers)): ?>
                    <p class="text-muted mb-0 text-center py-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        No approved teachers yet.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Teacher Name</th>
                                    <th>Email</th>
                                    <th>Number of Classes</th>
                                    <th>Classes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_teachers as $teacher): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-success text-white me-2">
                                                    <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($teacher['full_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($teacher['email']) ?></td>
                                        <td>
                                            <span class="badge bg-primary rounded-pill">
                                                <?= $teacher['class_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php if ($teacher['classes']): 
                                                        $classes = explode(', ', $teacher['classes']);
                                                        $firstClass = $classes[0] ?? '';
                                                        $remainingClasses = array_slice($classes, 1);
                                                        $remainingCount = $teacher['class_count'] - 1;
                                                    ?>
                                                        <span class="text-muted">
                                                            <?= htmlspecialchars($firstClass ?: '') ?>
                                                            <?php if ($remainingCount > 0): ?>
                                                                <span class="text-primary" 
                                                                      data-bs-toggle="tooltip" 
                                                                      data-bs-placement="top" 
                                                                      title="<?= htmlspecialchars(implode(', ', $remainingClasses) ?: '') ?>">
                                                                    +<?= $remainingCount ?> more
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">No classes created yet</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="view-details-container">
                                                    <a href="view_teacher_details.php?teacher_id=<?= $teacher['id'] ?>" 
                                                       class="btn btn-primary btn-sm view-details-btn">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejected Teachers -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-x-circle-fill me-2"></i>
                Rejected Teachers
            </div>
            <div class="card-body">
                <?php if (empty($rejected_teachers)): ?>
                    <p class="text-muted mb-0 text-center py-4">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        No rejected teachers.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Rejection Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_teachers as $teacher): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-danger text-white me-2">
                                                    <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($teacher['full_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($teacher['email']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($teacher['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Complaints Management -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                Complaints Management
            </div>
            <div class="card-body">
                <?php if (empty($complaints)): ?>
                    <p class="text-muted mb-0 text-center py-4">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        No complaints to manage.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $complaint['complaint_type'] === 'grade' ? 'primary' : 
                                                ($complaint['complaint_type'] === 'teacher' ? 'warning' : 
                                                ($complaint['complaint_type'] === 'class' ? 'info' : 'secondary')) ?>">
                                                <?= ucfirst($complaint['complaint_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <button type="button" 
                                                        class="btn btn-link text-dark p-0 text-decoration-none view-complaint-btn" 
                                                        data-complaint='<?= json_encode([
                                                            'id' => $complaint['id'],
                                                            'type' => $complaint['complaint_type'],
                                                            'title' => $complaint['title'],
                                                            'description' => $complaint['description'],
                                                            'student_name' => $complaint['student_name'],
                                                            'student_email' => $complaint['student_email'],
                                                            'status' => $complaint['status'],
                                                            'created_at' => $complaint['created_at'],
                                                            'is_anonymous' => $complaint['is_anonymous']
                                                        ]) ?>'>
                                                    <?= htmlspecialchars($complaint['title']) ?>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-info text-white me-2">
                                                    <?= strtoupper(substr($complaint['student_name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($complaint['student_name']) ?>
                                                <?php if (!$complaint['is_anonymous'] && isset($complaint['student_email'])): ?>
                                                    <small class="text-muted ms-2">(<?= htmlspecialchars($complaint['student_email']) ?>)</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $complaint['status'] === 'pending' ? 'warning' : 
                                                ($complaint['status'] === 'in_progress' ? 'info' : 
                                                ($complaint['status'] === 'resolved' ? 'success' : 'danger')) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $complaint['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-info text-white me-2 view-description-btn"
                                                        data-description="<?= htmlspecialchars($complaint['description']) ?>"
                                                        data-title="<?= htmlspecialchars($complaint['title']) ?>">
                                                    <i class="bi bi-eye me-1"></i> View Description
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary update-status-btn"
                                                        data-complaint-id="<?= $complaint['id'] ?>"
                                                        data-current-status="<?= $complaint['status'] ?>"
                                                        data-title="<?= htmlspecialchars($complaint['title']) ?>">
                                                    <i class="bi bi-arrow-repeat me-1"></i> Update Status
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Student Management -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-people-fill me-2"></i>
                Student Management
            </div>
            <div class="card-body">
                <!-- Search Form -->
                <form method="GET" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       name="search_student" 
                                       placeholder="Search by first name or last name..."
                                       value="<?= isset($_GET['search_student']) ? htmlspecialchars($_GET['search_student']) : '' ?>"
                                       required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if (isset($_GET['search_student'])): ?>
                    <?php if (empty($search_results)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No students found matching your search criteria "<?= htmlspecialchars($_GET['search_student']) ?>".
                            <?php if (isset($error)): ?>
                                <br><small class="text-danger"><?= htmlspecialchars($error) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Found <?= count($search_results) ?> student(s) matching "<?= htmlspecialchars($_GET['search_student']) ?>"
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>ID Number</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary text-white me-2">
                                                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                                    </div>
                                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($student['id_number']) ?></td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm delete-student-btn"
                                                        data-student-id="<?= $student['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Co-Admin Management -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-shield-lock-fill me-2"></i>
                Co-Admin Management
            </div>
            <div class="card-body">
                <!-- Add Co-Admin Form -->
                <form method="POST" class="mb-4" id="addCoAdminForm">
                    <input type="hidden" name="action" value="add_co_admin">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill me-2"></i>Add Co-Admin
                        </button>
                    </div>
                </form>

                <!-- Co-Admins List -->
                <h5 class="mb-3">Existing Co-Admins</h5>
                <?php if (empty($co_admins)): ?>
                    <p class="text-muted mb-0 text-center py-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        No co-admin accounts have been created yet.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Created Date</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($co_admins as $admin): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-primary text-white me-2">
                                                    <?= strtoupper(substr($admin['email'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($admin['email']) ?>
                                            </div>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <?= $admin['last_login'] ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm delete-co-admin-btn"
                                                    data-admin-id="<?= $admin['id'] ?>"
                                                    data-admin-email="<?= htmlspecialchars($admin['email']) ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- School Year and Semester Management -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">School Year and Semester Management</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Add School Year Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Add School Year</h6>
                            </div>
                            <div class="card-body">
                                <form id="addSchoolYearForm" method="POST">
                                    <input type="hidden" name="action" value="add_school_year">
                                    <div class="mb-3">
                                        <label for="school_year" class="form-label">School Year</label>
                                        <input type="text" class="form-control" id="school_year" name="school_year" 
                                               placeholder="e.g., 2023-2024" required pattern="\d{4}-\d{4}">
                                        <div class="form-text">Format: YYYY-YYYY</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="school_year_active" name="is_active" value="1">
                                            <label class="form-check-label" for="school_year_active">Set as Active</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Add School Year</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Add Semester Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Add Semester</h6>
                            </div>
                            <div class="card-body">
                                <form id="addSemesterForm" method="POST">
                                    <input type="hidden" name="action" value="add_semester">
                                    <div class="mb-3">
                                        <label for="semester_name" class="form-label">Semester Name</label>
                                        <input type="text" class="form-control" id="semester_name" name="semester_name" 
                                               placeholder="e.g., First Semester" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="school_year_id" class="form-label">School Year</label>
                                        <select class="form-select" id="school_year_id" name="school_year_id" required>
                                            <option value="">Select School Year</option>
                                            <?php foreach ($school_years as $year): ?>
                                                <option value="<?php echo $year['id']; ?>">
                                                    <?php echo htmlspecialchars($year['school_year']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="semester_active" name="is_active" value="1">
                                            <label class="form-check-label" for="semester_active">Set as Active</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Add Semester</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- School Years List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">School Years</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>School Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($school_years as $year): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($year['school_year']); ?></td>
                                            <td>
                                                <?php if ($year['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-school-year" 
                                                        data-id="<?php echo $year['id']; ?>"
                                                        data-school-year="<?php echo htmlspecialchars($year['school_year']); ?>"
                                                        data-is-active="<?php echo $year['is_active']; ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-school-year"
                                                        data-id="<?php echo $year['id']; ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Semesters List -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Semesters</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Semester</th>
                                        <th>School Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semesters as $semester): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($semester['semester_name']); ?></td>
                                            <td><?php echo htmlspecialchars($semester['school_year']); ?></td>
                                            <td>
                                                <?php if ($semester['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-semester"
                                                        data-id="<?php echo $semester['id']; ?>"
                                                        data-semester-name="<?php echo htmlspecialchars($semester['semester_name']); ?>"
                                                        data-school-year-id="<?php echo $semester['school_year_id']; ?>"
                                                        data-is-active="<?php echo $semester['is_active']; ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-semester"
                                                        data-id="<?php echo $semester['id']; ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit School Year Modal -->
        <div class="modal fade" id="editSchoolYearModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit School Year</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editSchoolYearForm" method="POST">
                            <input type="hidden" name="action" value="edit_school_year">
                            <input type="hidden" name="id" id="edit_school_year_id">
                            <div class="mb-3">
                                <label for="edit_school_year" class="form-label">School Year</label>
                                <input type="text" class="form-control" id="edit_school_year" name="school_year" 
                                       required pattern="\d{4}-\d{4}">
                                <div class="form-text">Format: YYYY-YYYY</div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_school_year_active" name="is_active" value="1">
                                    <label class="form-check-label" for="edit_school_year_active">Set as Active</label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="editSchoolYearForm" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Semester Modal -->
        <div class="modal fade" id="editSemesterModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Semester</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editSemesterForm" method="POST">
                            <input type="hidden" name="action" value="edit_semester">
                            <input type="hidden" name="id" id="edit_semester_id">
                            <div class="mb-3">
                                <label for="edit_semester_name" class="form-label">Semester Name</label>
                                <input type="text" class="form-control" id="edit_semester_name" name="semester_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_school_year_id" class="form-label">School Year</label>
                                <select class="form-select" id="edit_school_year_id" name="school_year_id" required>
                                    <?php foreach ($school_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>">
                                            <?php echo htmlspecialchars($year['school_year']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_semester_active" name="is_active" value="1">
                                    <label class="form-check-label" for="edit_semester_active">Set as Active</label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="editSemesterForm" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm" method="POST" action="change_password.php">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>
                        <div class="alert alert-danger d-none" id="passwordError"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitPasswordChange">Change Password</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add loading overlay HTML -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Add this at the beginning of your script section
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading overlay when page is fully loaded
            const loadingOverlay = document.querySelector('.loading-overlay');
            loadingOverlay.classList.add('fade-out');
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 500); // Match this with the CSS transition duration
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true
                });
            });

            // Handle complaint view button clicks
            document.querySelectorAll('.view-complaint-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const complaint = JSON.parse(this.dataset.complaint);
                    
                    // Get status badge color
                    const statusColor = complaint.status === 'pending' ? 'warning' : 
                                      (complaint.status === 'in_progress' ? 'info' : 
                                      (complaint.status === 'resolved' ? 'success' : 'danger'));
                    
                    // Get type badge color
                    const typeColor = complaint.type === 'grade' ? 'primary' : 
                                    (complaint.type === 'teacher' ? 'warning' : 
                                    (complaint.type === 'class' ? 'info' : 'secondary'));

                    Swal.fire({
                        title: 'Complaint Details',
                        html: `
                            <div class="complaint-details">
                                <div>
                                    <div class="label">Type</div>
                                    <div class="value">
                                        <span class="badge bg-${typeColor}">${complaint.type.charAt(0).toUpperCase() + complaint.type.slice(1)}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="label">Title</div>
                                    <div class="value">${complaint.title}</div>
                                </div>
                                <div>
                                    <div class="label">Description</div>
                                    <div class="value description">${complaint.description}</div>
                                </div>
                                <div>
                                    <div class="label">Student</div>
                                    <div class="value">
                                        ${complaint.student_name}
                                        ${!complaint.is_anonymous ? `<small class="text-muted">(${complaint.student_email})</small>` : ''}
                                    </div>
                                </div>
                                <div>
                                    <div class="label">Status</div>
                                    <div class="value">
                                        <span class="badge bg-${statusColor}">
                                            ${complaint.status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="label">Date Submitted</div>
                                    <div class="value">${new Date(complaint.created_at).toLocaleString()}</div>
                                </div>
                            </div>
                        `,
                        width: '50vw',
                        showCloseButton: true,
                        showConfirmButton: false,
                        customClass: {
                            container: 'complaint-modal'
                        }
                    });
                });
            });

            // Handle view description button clicks
            document.querySelectorAll('.view-description-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const description = this.dataset.description;
                    const title = this.dataset.title;

                    Swal.fire({
                        title: title,
                        html: `
                            <div class="complaint-description">
                                <div class="description-content">
                                    ${description}
                                </div>
                            </div>
                        `,
                        customClass: {
                            popup: 'complaint-description-modal'
                        },
                        width: '50vw',
                        showCloseButton: true,
                        showConfirmButton: false
                    });
                });
            });

            // Handle update status button clicks
            document.querySelectorAll('.update-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const complaintId = this.dataset.complaintId;
                    const currentStatus = this.dataset.currentStatus;
                    const title = this.dataset.title;

                    const statusOptions = [
                        {
                            value: 'pending',
                            label: 'Pending',
                            description: 'Complaint is waiting for review',
                            icon: 'bi-clock',
                            color: '#ffc107'
                        },
                        {
                            value: 'in_progress',
                            label: 'In Progress',
                            description: 'Complaint is being addressed',
                            icon: 'bi-arrow-repeat',
                            color: '#17a2b8'
                        },
                        {
                            value: 'resolved',
                            label: 'Resolved',
                            description: 'Complaint has been resolved',
                            icon: 'bi-check-circle',
                            color: '#28a745'
                        },
                        {
                            value: 'rejected',
                            label: 'Rejected',
                            description: 'Complaint has been rejected',
                            icon: 'bi-x-circle',
                            color: '#dc3545'
                        }
                    ];

                    Swal.fire({
                        title: 'Update Complaint Status',
                        html: `
                            <div class="text-start mb-3">
                                <strong>Complaint:</strong> ${title}
                            </div>
                            <div class="status-options mb-3">
                                ${statusOptions.map(option => `
                                    <div class="status-option ${option.value === currentStatus ? 'selected' : ''}" 
                                         data-status="${option.value}">
                                        <div class="status-icon" style="background-color: ${option.color}">
                                            <i class="bi ${option.icon}"></i>
                                        </div>
                                        <div>
                                            <div class="status-text">${option.label}</div>
                                            <div class="status-description">${option.description}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="form-group text-start">
                                <label for="adminResponse" class="form-label">Admin Response</label>
                                <textarea id="adminResponse" class="form-control" rows="4" 
                                          placeholder="Enter your response to the complaint..."></textarea>
                            </div>
                        `,
                        showCancelButton: true,
                        showConfirmButton: true,
                        confirmButtonText: 'Update Status',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            container: 'status-update-modal',
                            confirmButton: 'btn btn-primary',
                            cancelButton: 'btn btn-secondary'
                        },
                        didOpen: () => {
                            // Add click handlers for status options
                            document.querySelectorAll('.status-option').forEach(option => {
                                option.addEventListener('click', function() {
                                    document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('selected'));
                                    this.classList.add('selected');
                                });
                            });
                        },
                        preConfirm: () => {
                            const selectedStatus = document.querySelector('.status-option.selected')?.dataset.status;
                            const adminResponse = document.getElementById('adminResponse').value;
                            
                            if (!selectedStatus) {
                                Swal.showValidationMessage('Please select a status');
                                return false;
                            }
                            
                            return {
                                status: selectedStatus,
                                adminResponse: adminResponse
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const newStatus = result.value.status;
                            const adminResponse = result.value.adminResponse;
                            
                            // Show loading overlay
                            document.querySelector('.loading-overlay').style.display = 'flex';
                            
                            // Create and submit the form
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = window.location.href; // Ensure form submits to current page
                            form.innerHTML = `
                                <input type="hidden" name="complaint_id" value="${complaintId}">
                                <input type="hidden" name="action" value="${newStatus}">
                                <input type="hidden" name="admin_response" value="${adminResponse}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Handle student deletion
            document.querySelectorAll('.delete-student-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const studentName = this.dataset.studentName;

                    Swal.fire({
                        title: 'Delete Student',
                        html: `Are you sure you want to delete <strong>${studentName}</strong>?<br><br>
                               <span class="text-danger">This action cannot be undone!</span>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete student',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch('delete_student.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=delete_student&student_id=${studentId}`
                            })
                            .then(response => {
                                if (!response.ok) {
                                    return response.json().then(err => {
                                        throw new Error(err.message || 'Failed to delete student')
                                    })
                                }
                                return response.json()
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                )
                            })
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (result.value.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: result.value.message || 'Student has been successfully deleted.',
                                    icon: 'success',
                                    confirmButtonColor: '#28a745'
                                }).then(() => {
                                    // Reload the page to show updated list
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: result.value.message || 'Failed to delete student.',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        }
                    });
                });
            });

            // Handle password change
            document.getElementById('submitPasswordChange').addEventListener('click', function() {
                const form = document.getElementById('changePasswordForm');
                const errorDiv = document.getElementById('passwordError');
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;

                // Reset error message
                errorDiv.classList.add('d-none');
                errorDiv.textContent = '';

                // Validate passwords match
                if (newPassword !== confirmPassword) {
                    errorDiv.textContent = 'New passwords do not match!';
                    errorDiv.classList.remove('d-none');
                    return;
                }

                // Validate password strength
                if (newPassword.length < 8) {
                    errorDiv.textContent = 'Password must be at least 8 characters long!';
                    errorDiv.classList.remove('d-none');
                    return;
                }

                // Submit the form
                const formData = new FormData(form);
                fetch('change_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Password has been changed successfully.',
                            icon: 'success',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Close the modal and reset the form
                            const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                            modal.hide();
                            form.reset();
                        });
                    } else {
                        errorDiv.textContent = data.message || 'Failed to change password. Please try again.';
                        errorDiv.classList.remove('d-none');
                    }
                })
                .catch(error => {
                    errorDiv.textContent = 'An error occurred. Please try again.';
                    errorDiv.classList.remove('d-none');
                });
            });

            // Handle teacher approval
            document.querySelectorAll('.approve-teacher-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const teacherId = this.dataset.teacherId;
                    const teacherName = this.dataset.teacherName;

                    Swal.fire({
                        title: 'Approve Teacher',
                        html: `Are you sure you want to approve <strong>${teacherName}</strong>?<br><br>
                               This will grant them access to the teacher dashboard.`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, approve teacher',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=approve&teacher_id=${teacherId}`
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to approve teacher')
                                }
                                return response.text()
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                )
                            })
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Approved!',
                                text: 'Teacher has been successfully approved.',
                                icon: 'success',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                // Reload the page to show updated list
                                window.location.reload();
                            });
                        }
                    });
                });
            });

            // Handle teacher rejection
            document.querySelectorAll('.reject-teacher-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const teacherId = this.dataset.teacherId;
                    const teacherName = this.dataset.teacherName;

                    Swal.fire({
                        title: 'Reject Teacher',
                        html: `Are you sure you want to reject <strong>${teacherName}</strong>?<br><br>
                               This will deny them access to the teacher dashboard.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, reject teacher',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=reject&teacher_id=${teacherId}`
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to reject teacher')
                                }
                                return response.text()
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                )
                            })
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Rejected!',
                                text: 'Teacher has been successfully rejected.',
                                icon: 'success',
                                confirmButtonColor: '#dc3545'
                            }).then(() => {
                                // Reload the page to show updated list
                                window.location.reload();
                            });
                        }
                    });
                });
            });

            // Handle co-admin form submission
            document.getElementById('addCoAdminForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                // Validate password
                if (password.length < 8) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Password must be at least 8 characters long',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }

                if (password !== confirmPassword) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Passwords do not match',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }

                // Submit the form
                const formData = new FormData(this);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Co-admin account created successfully',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to create co-admin account',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });

            // Handle co-admin deletion
            document.querySelectorAll('.delete-co-admin-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const adminId = this.dataset.adminId;
                    const adminEmail = this.dataset.adminEmail;

                    Swal.fire({
                        title: 'Delete Co-Admin',
                        html: `Are you sure you want to delete <strong>${adminEmail}</strong>?<br><br>
                               <span class="text-danger">This action cannot be undone!</span>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete co-admin',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=delete_co_admin&admin_id=${adminId}`
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to delete co-admin')
                                }
                                return response.text()
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                )
                            })
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Co-admin has been successfully deleted.',
                                icon: 'success',
                                confirmButtonColor: '#dc3545'
                            }).then(() => {
                                window.location.reload();
                            });
                        }
                    });
                });
            });

            // Handle form submissions
            document.getElementById('addSchoolYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'School year added successfully',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to add school year',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });

            document.getElementById('addSemesterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Semester added successfully',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to add semester',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });

            document.getElementById('editSchoolYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'School year updated successfully',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update school year',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });

            document.getElementById('editSemesterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Semester updated successfully',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update semester',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });

            // Handle edit school year button clicks
            document.querySelectorAll('.edit-school-year').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const schoolYear = this.dataset.schoolYear;
                    const isActive = this.dataset.isActive;

                    document.getElementById('edit_school_year_id').value = id;
                    document.getElementById('edit_school_year').value = schoolYear;
                    document.getElementById('edit_school_year_active').checked = isActive === '1';

                    const modal = new bootstrap.Modal(document.getElementById('editSchoolYearModal'));
                    modal.show();
                });
            });

            // Handle edit semester button clicks
            document.querySelectorAll('.edit-semester').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const semesterName = this.dataset.semesterName;
                    const schoolYearId = this.dataset.schoolYearId;
                    const isActive = this.dataset.isActive;

                    document.getElementById('edit_semester_id').value = id;
                    document.getElementById('edit_semester_name').value = semesterName;
                    document.getElementById('edit_school_year_id').value = schoolYearId;
                    document.getElementById('edit_semester_active').checked = isActive === '1';

                    const modal = new bootstrap.Modal(document.getElementById('editSemesterModal'));
                    modal.show();
                });
            });

            // Handle delete school year button clicks
            document.querySelectorAll('.delete-school-year').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const schoolYear = this.dataset.schoolYear;
                    
                    Swal.fire({
                        title: 'Delete School Year',
                        html: `Are you sure you want to delete <strong>${schoolYear}</strong>?<br><br>
                               <span class="text-danger">This action cannot be undone!</span>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete school year',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            const formData = new FormData();
                            formData.append('action', 'delete_school_year');
                            formData.append('id', id);
                            
                            return fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error(data.message);
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                );
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: result.value.message,
                                icon: 'success',
                                confirmButtonColor: '#dc3545'
                            }).then(() => {
                                window.location.reload();
                            });
                        }
                    });
                });
            });

            // Handle delete semester button clicks
            document.querySelectorAll('.delete-semester').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const semesterName = this.dataset.semesterName;
                    
                    Swal.fire({
                        title: 'Delete Semester',
                        html: `Are you sure you want to delete <strong>${semesterName}</strong>?<br><br>
                               <span class="text-danger">This action cannot be undone!</span>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete semester',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            const formData = new FormData();
                            formData.append('action', 'delete_semester');
                            formData.append('id', id);
                            
                            return fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error(data.message);
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(
                                    `Request failed: ${error.message}`
                                );
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: result.value.message,
                                icon: 'success',
                                confirmButtonColor: '#dc3545'
                            }).then(() => {
                                window.location.reload();
                            });
                        }
                    });
                });
            });
        });

        // Show loading overlay when page is about to unload
        window.addEventListener('beforeunload', function() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        });
    </script>
</body>
</html> 
<?php
require_once 'config/db.php';

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_type = $_POST['user_type'];

    if ($user_type === 'teacher') {
        $name = trim($_POST['teacher_name']);
        $email = trim($_POST['teacher_email']);
        $password = password_hash($_POST['teacher_password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO teachers (full_name, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$name, $email, $password])) {
            $success = "Teacher registered successfully.";
        } else {
            $errors[] = "Error registering teacher.";
        }

    } elseif ($user_type === 'student') {
        // Get and sanitize input
        $id_number = trim($_POST['student_id']);
        $first_name = trim($_POST['student_fname']);
        $last_name = trim($_POST['student_lname']);
        $email = trim($_POST['student_email']);
        $password = $_POST['student_password'];

        // Validate ID Number
        if (empty($id_number)) {
            $errors[] = "ID Number is required.";
        } elseif (!preg_match('/^[A-Za-z0-9-]+$/', $id_number)) {
            $errors[] = "ID Number can only contain letters, numbers, and hyphens.";
        } else {
            // Check if ID number already exists
            $check_id = $pdo->prepare("SELECT COUNT(*) FROM students WHERE id_number = ?");
            $check_id->execute([$id_number]);
            if ($check_id->fetchColumn() > 0) {
                $errors[] = "ID Number already registered.";
            }
        }

        // Validate First Name
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        } elseif (!preg_match('/^[A-Za-zñÑ\s-]+$/', $first_name)) {
            $errors[] = "First name can only contain letters, ñ, hyphens, and spaces.";
        } elseif (strlen($first_name) < 2 || strlen($first_name) > 50) {
            $errors[] = "First name must be between 2 and 50 characters.";
        }

        // Validate Last Name
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        } elseif (!preg_match('/^[A-Za-zñÑ\s-]+$/', $last_name)) {
            $errors[] = "Last name can only contain letters, ñ, hyphens, and spaces.";
        } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
            $errors[] = "Last name must be between 2 and 50 characters.";
        }

        // Validate Email
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } elseif (strlen($email) > 255) {
            $errors[] = "Email is too long.";
        } else {
            // Check if email already exists
            $check_email = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
            $check_email->execute([$email]);
            if ($check_email->fetchColumn() > 0) {
                $errors[] = "Email already registered.";
            }
        }

        // Validate Password
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }

        // If no errors, proceed with registration
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO students (id_number, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$id_number, $first_name, $last_name, $email, $hashed_password])) {
                $success = "Student registered successfully. You can now login.";
            } else {
                $errors[] = "Error registering student. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Inquiry System - Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --dark-color: #5a5c69;
        }

        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e8eaf6 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }

        .form-section { 
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e3e6f0;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #224abe;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .input-group-text {
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
        }

        .login-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .login-link:hover {
            color: #224abe;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .card {
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header text-center">
                    <h4><i class="fas fa-user-plus me-2"></i>Register - Grade Inquiry System</h4>
                </div>
                <div class="card-body p-4">

                    <form method="post" action="" id="registrationForm">
                        <div class="mb-4">
                            <label for="userType" class="form-label">Register as:</label>
                            <select name="user_type" id="userType" class="form-select" onchange="showForm(this.value)" required>
                                <option value="">-- Select User Type --</option>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>
                        </div>

                        <!-- Teacher Form -->
                        <div id="teacherForm" class="form-section">
                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name">
                            </div>
                            <div class="mb-3">
                                <label for="teacher_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="teacher_email" name="teacher_email">
                            </div>
                            <div class="mb-3">
                                <label for="teacher_password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="teacher_password" name="teacher_password">
                                    <span class="input-group-text" style="cursor: pointer;" onclick="toggleTeacherPassword()">
                                        <i class="fas fa-eye" id="toggleTeacherPassword"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Student Form -->
                        <div id="studentForm" class="form-section">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="student_id" name="student_id">
                            </div>
                            <div class="mb-3">
                                <label for="student_fname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="student_fname" name="student_fname">
                            </div>
                            <div class="mb-3">
                                <label for="student_lname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="student_lname" name="student_lname">
                            </div>
                            <div class="mb-3">
                                <label for="student_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="student_email" name="student_email">
                            </div>
                            <div class="mb-3">
                                <label for="student_password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="student_password" name="student_password">
                                    <span class="input-group-text" style="cursor: pointer;" onclick="toggleStudentPassword()">
                                        <i class="fas fa-eye" id="toggleStudentPassword"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </div>
                    </form>

                    <p class="mt-4 text-center text-muted">Already have an account? <a href="index.php" class="login-link">Login here</a></p>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showForm(userType) {
        const teacherForm = document.getElementById('teacherForm');
        const studentForm = document.getElementById('studentForm');
        
        if (userType === 'teacher') {
            teacherForm.style.display = 'block';
            studentForm.style.display = 'none';
            
            // Set required attributes for teacher form
            document.getElementById('teacher_name').required = true;
            document.getElementById('teacher_email').required = true;
            document.getElementById('teacher_password').required = true;
            
            // Remove required attributes from student form
            document.getElementById('student_id').required = false;
            document.getElementById('student_fname').required = false;
            document.getElementById('student_lname').required = false;
            document.getElementById('student_email').required = false;
            document.getElementById('student_password').required = false;
        } else if (userType === 'student') {
            teacherForm.style.display = 'none';
            studentForm.style.display = 'block';
            
            // Remove required attributes from teacher form
            document.getElementById('teacher_name').required = false;
            document.getElementById('teacher_email').required = false;
            document.getElementById('teacher_password').required = false;
            
            // Set required attributes for student form
            document.getElementById('student_id').required = true;
            document.getElementById('student_fname').required = true;
            document.getElementById('student_lname').required = true;
            document.getElementById('student_email').required = true;
            document.getElementById('student_password').required = true;
        } else {
            teacherForm.style.display = 'none';
            studentForm.style.display = 'none';
            
            // Remove required attributes from both forms
            document.getElementById('teacher_name').required = false;
            document.getElementById('teacher_email').required = false;
            document.getElementById('teacher_password').required = false;
            document.getElementById('student_id').required = false;
            document.getElementById('student_fname').required = false;
            document.getElementById('student_lname').required = false;
            document.getElementById('student_email').required = false;
            document.getElementById('student_password').required = false;
        }
    }

    function toggleTeacherPassword() {
        const passwordInput = document.getElementById('teacher_password');
        const toggleIcon = document.getElementById('toggleTeacherPassword');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    function toggleStudentPassword() {
        const passwordInput = document.getElementById('student_password');
        const toggleIcon = document.getElementById('toggleStudentPassword');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Function to convert input to uppercase
    function convertToUpperCase(input) {
        input.value = input.value.toUpperCase();
    }

    // Add event listeners for first name and last name inputs
    document.getElementById('student_fname').addEventListener('input', function() {
        convertToUpperCase(this);
    });

    document.getElementById('student_lname').addEventListener('input', function() {
        convertToUpperCase(this);
    });

    // Add form validation and submission handling
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        try {
            e.preventDefault();
            console.log('Form submission intercepted');
            
            const userType = document.getElementById('userType').value;
            console.log('Selected user type:', userType);
            
            let isValid = true;
            let errorMessage = '';

            if (!userType) {
                isValid = false;
                errorMessage = 'Please select a user type.';
                console.log('Validation failed: No user type selected');
            } else if (userType === 'teacher') {
                const name = document.getElementById('teacher_name').value.trim();
                const email = document.getElementById('teacher_email').value.trim();
                const password = document.getElementById('teacher_password').value;

                console.log('Teacher registration attempt:', {
                    name: name,
                    email: email,
                    passwordLength: password ? password.length : 0
                });

                if (!name) {
                    isValid = false;
                    errorMessage = 'Please enter your full name.';
                    console.log('Validation failed: Missing teacher name');
                } else if (!email) {
                    isValid = false;
                    errorMessage = 'Please enter your email.';
                    console.log('Validation failed: Missing teacher email');
                } else if (!isValidEmail(email)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address.';
                    console.log('Validation failed: Invalid teacher email format');
                } else if (!password) {
                    isValid = false;
                    errorMessage = 'Please enter a password.';
                    console.log('Validation failed: Missing teacher password');
                } else if (password.length < 8) {
                    isValid = false;
                    errorMessage = 'Password must be at least 8 characters long.';
                    console.log('Validation failed: Teacher password too short');
                }
            } else if (userType === 'student') {
                const idNumber = document.getElementById('student_id').value.trim();
                const firstName = document.getElementById('student_fname').value.trim();
                const lastName = document.getElementById('student_lname').value.trim();
                const email = document.getElementById('student_email').value.trim();
                const password = document.getElementById('student_password').value;

                console.log('Student registration attempt:', {
                    idNumber: idNumber,
                    firstName: firstName,
                    lastName: lastName,
                    email: email,
                    passwordLength: password ? password.length : 0
                });

                if (!idNumber) {
                    isValid = false;
                    errorMessage = 'Please enter your ID number.';
                    console.log('Validation failed: Missing student ID number');
                } else if (!firstName) {
                    isValid = false;
                    errorMessage = 'Please enter your first name.';
                    console.log('Validation failed: Missing student first name');
                } else if (!lastName) {
                    isValid = false;
                    errorMessage = 'Please enter your last name.';
                    console.log('Validation failed: Missing student last name');
                } else if (!email) {
                    isValid = false;
                    errorMessage = 'Please enter your email.';
                    console.log('Validation failed: Missing student email');
                } else if (!isValidEmail(email)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address.';
                    console.log('Validation failed: Invalid student email format');
                } else if (!password) {
                    isValid = false;
                    errorMessage = 'Please enter a password.';
                    console.log('Validation failed: Missing student password');
                } else if (password.length < 8) {
                    isValid = false;
                    errorMessage = 'Password must be at least 8 characters long.';
                    console.log('Validation failed: Student password too short');
                }
            }

            if (!isValid) {
                console.log('Form validation failed:', errorMessage);
                Swal.fire({
                    title: 'Validation Error',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#4e73df'
                });
                return;
            }

            console.log('Form validation passed, submitting form...');
            
            // Add a loading state to the submit button
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

            // Submit the form
            this.submit();
        } catch (error) {
            console.error('Error during form submission:', error);
            Swal.fire({
                title: 'System Error',
                text: 'An unexpected error occurred. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#4e73df'
            });
        }
    });

    function isValidEmail(email) {
        try {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = emailRegex.test(email);
            console.log('Email validation:', { email, isValid });
            return isValid;
        } catch (error) {
            console.error('Error during email validation:', error);
            return false;
        }
    }

    // Add error handling for PHP responses
    <?php if (!empty($success)): ?>
    console.log('Server response: Success - <?= $success ?>');
    Swal.fire({
        title: 'Success!',
        text: '<?= $success ?>',
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#4e73df'
    }).then((result) => {
        if (result.isConfirmed) {
            console.log('Redirecting to login page...');
            window.location.href = 'index.php';
        }
    });
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    console.log('Server response: Errors - <?= implode(", ", $errors) ?>');
    Swal.fire({
        title: 'Error!',
        html: '<?= implode("<br>", $errors) ?>',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#4e73df'
    });
    <?php endif; ?>

    // Add error handling for network issues
    window.addEventListener('error', function(e) {
        if (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK') {
            console.error('Resource loading error:', e.target.src || e.target.href);
        }
    }, true);
</script>

</body>
</html>

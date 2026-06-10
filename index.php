<?php
session_start();
require_once 'config/db.php';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    // First check if user exists in teachers table
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
    $stmt->execute([$identifier]);
    $teacher = $stmt->fetch();

    if ($teacher && password_verify($password, $teacher['password'])) {
        // Check if teacher's account is approved
        if ($teacher['status'] === 'pending') {
            $errors[] = "Your account is pending approval. Please wait for admin verification.";
        } elseif ($teacher['status'] === 'rejected') {
            $errors[] = "Your account has been rejected. Please contact the administrator.";
        } elseif ($teacher['status'] === 'approved') {
            // Set session for teacher
            $_SESSION['user_type'] = 'teacher';
            $_SESSION['user_id'] = $teacher['id'];
            header("Location: teacher/dashboard.php");
            exit();
        }
    } else {
        // If not found in teachers, check students table
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id_number = ?");
        $stmt->execute([$identifier]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password'])) {
            // Set session for student
            $_SESSION['user_type'] = 'student';
            $_SESSION['user_id'] = $student['id'];
            header("Location: student/dashboard.php");
            exit();
        } else {
            // Only show invalid credentials if no match found in either table
            $errors[] = "Invalid credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Grade Inquiry System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #2e59d9;
            --background-color: #f8f9fc;
        }

        body {
            background: linear-gradient(135deg, var(--background-color) 0%, #e3e6f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }

        .login-container {
            max-width: 600px;
            width: 95%;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e3e6f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .form-label {
            font-weight: 500;
            color: #5a5c69;
            margin-bottom: 0.5rem;
        }

        .input-group-text {
            background-color: transparent;
            border: 1px solid #e3e6f0;
            border-right: none;
            cursor: pointer;
            padding: 0.75rem 1rem;
        }

        .input-group-text:last-child {
            border-left: none;
            border-right: 1px solid #e3e6f0;
        }

        .form-control {
            border-left: none;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .register-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .register-link:hover {
            color: var(--secondary-color);
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--secondary-color);
        }

        @media (max-width: 576px) {
            .login-container {
                margin: 1rem auto;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="card">
        <div class="card-header text-center">
            <h4><i class="fas fa-graduation-cap me-2"></i>Grade Inquiry System</h4>
        </div>
        <div class="card-body">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                </div>
            <?php endforeach; ?>

            <form method="post" action="">
                <div class="mb-4">
                    <label class="form-label">Email / ID Number</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="identifier" class="form-control" placeholder="Enter your email or ID number" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                            <i class="fas fa-eye" id="togglePassword"></i>
                        </span>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>

            <p class="mt-4 text-center text-muted">
                <a href="forgot_password.php" class="forgot-link me-3">
                    <i class="fas fa-key me-1"></i>Forgot Password?
                </a>
                <span class="text-muted">|</span>
                <a href="signup.php" class="register-link ms-3">
                    <i class="fas fa-user-plus me-1"></i>Register here
                </a>
            </p>
        </div>
    </div>
</div>

<script>
    const userTypeSelect = document.getElementById('userType');
    const label = document.getElementById('identifierLabel');
    userTypeSelect.addEventListener('change', function() {
        label.innerText = this.value === 'teacher' ? "Email" : "ID Number";
    });

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePassword');
        
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
</script>

</body>
</html>

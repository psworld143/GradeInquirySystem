<?php
session_start();
require_once 'config/db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // First check if it's a teacher
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
        $stmt->execute([$email]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher && password_verify($password, $teacher['password'])) {
            // Check teacher status
            if ($teacher['status'] === 'approved') {
                $_SESSION['user_id'] = $teacher['id'];
                $_SESSION['user_type'] = 'teacher';
                header("Location: teacher/dashboard.php");
                exit();
            } else {
                switch ($teacher['status']) {
                    case 'pending':
                        $error = "Your account is pending approval. Please wait for admin verification.";
                        break;
                    case 'rejected':
                        $error = "Your account has been rejected. Please contact the administrator for more information.";
                        break;
                    default:
                        $error = "Invalid account status. Please contact the administrator.";
                }
            }
        } else {
            // If not a teacher, check if it's a student
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['user_id'] = $student['id'];
                $_SESSION['user_type'] = 'student';
                header("Location: student/dashboard.php");
                exit();
            } else {
                // If not a student, check if it's an admin
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'admin'");
                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['user_id'] = $admin['id'];
                    $_SESSION['user_type'] = 'admin';
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Login failed: " . $e->getMessage();
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: #4a90e2;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            text-align: center;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .card-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
            border-color: #4a90e2;
        }
        .btn-primary {
            background: #4a90e2;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #357abd;
            transform: translateY(-1px);
        }
        .btn-outline-secondary {
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
        }
        .input-group-text {
            background: transparent;
            border: 1px solid #e0e0e0;
            border-right: none;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-user-lock me-2"></i>Login</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                            <label for="email"><i class="fas fa-envelope me-2"></i>Email address</label>
                        </div>

                        <div class="form-floating mb-4">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= htmlspecialchars($error) ?>',
            confirmButtonColor: '#4a90e2'
        });
    </script>
    <?php endif; ?>
</body>
</html> 
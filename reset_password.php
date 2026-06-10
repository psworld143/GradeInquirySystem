<?php
session_start();
require_once 'config/db.php';

$errors = [];
$success = false;
$valid_token = false;
$user_type = '';
$token = '';

if (isset($_GET['token']) && isset($_GET['type'])) {
    $token = trim($_GET['token']);
    $user_type = trim($_GET['type']);

    // Debug information
    $debug_info = [
        'token' => $token,
        'type' => $user_type,
        'current_time' => date('Y-m-d H:i:s')
    ];

    // Verify token
    if ($user_type === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE reset_token = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE reset_token = ?");
    }

    try {
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Add user data to debug info
            $debug_info['user_found'] = true;
            $debug_info['user_id'] = $user['id'];
            $debug_info['reset_expires'] = $user['reset_expires'];
            
            // Check if token is expired
            if (strtotime($user['reset_expires']) > time()) {
                $valid_token = true;
                $debug_info['token_valid'] = true;
            } else {
                $debug_info['token_valid'] = false;
                $debug_info['reason'] = 'Token expired';
                $errors[] = "Reset token has expired. Please request a new password reset.";
            }
        } else {
            $debug_info['user_found'] = false;
            $debug_info['reason'] = 'Token not found';
            $errors[] = "Invalid reset token.";
        }

        // Log debug information
        error_log("Reset Password Debug: " . json_encode($debug_info));
        
    } catch (PDOException $e) {
        error_log("Database error during token verification: " . $e->getMessage());
        $errors[] = "An error occurred. Please try again later.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            if ($user_type === 'teacher') {
                $stmt = $pdo->prepare("UPDATE teachers SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE students SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
            }

            if ($stmt->execute([$hashed_password, $token])) {
                $success = true;
                error_log("Password successfully reset for token: " . $token);
            } else {
                $errors[] = "Failed to update password. Please try again.";
                error_log("Failed to update password for token: " . $token);
            }
        } catch (PDOException $e) {
            error_log("Database error during password update: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Grade Inquiry System</title>
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

        .reset-container {
            max-width: 450px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
        }

        .back-to-login {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-to-login:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>

<div class="reset-container">
    <div class="card">
        <div class="card-header text-center">
            <h4><i class="fas fa-key me-2"></i>Reset Password</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Your password has been successfully reset. You can now <a href="index.php" class="alert-link">login</a> with your new password.
                </div>
            <?php elseif ($valid_token): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="" id="resetPasswordForm">
                    <div class="mb-4">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo isset($errors[0]) ? $errors[0] : "Invalid reset link."; ?>
                </div>
            <?php endif; ?>

            <p class="mt-4 text-center">
                <a href="index.php" class="back-to-login">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
            </p>
        </div>
    </div>
</div>

<script>
    // Log debug information to console
    console.log('Reset Password Debug:', <?= json_encode($debug_info ?? []) ?>);
    
    // Log any PHP errors to console
    <?php if (!empty($errors)): ?>
        console.error('Password Reset Errors:', <?= json_encode($errors) ?>);
    <?php endif; ?>

    // Log token validation status
    console.log('Token Validation:', {
        valid: <?= $valid_token ? 'true' : 'false' ?>,
        token: '<?= $token ?>',
        userType: '<?= $user_type ?>'
    });

    // Form submission logging
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(e) {
            const formData = new FormData(this);
            console.log('Submitting new password:', {
                newPassword: formData.get('new_password'),
                confirmPassword: formData.get('confirm_password')
            });
        });
    }
</script>

</body>
</html> 
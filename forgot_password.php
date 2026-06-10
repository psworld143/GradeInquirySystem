<?php
session_start();
require_once 'config/db.php';
require_once 'config/mail.php';

$errors = [];
$success = false;
$debug_info = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_type = $_POST['user_type'];
    $identifier = trim($_POST['identifier']);

    $debug_info['request'] = [
        'user_type' => $user_type,
        'identifier' => $identifier,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Check if the user exists
    if ($user_type === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
    } elseif ($user_type === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
    }

    try {
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $debug_info['token_generation'] = [
                'token' => $token,
                'expires' => $expires,
                'user_id' => $user['id']
            ];

            // Store the token in the database
            if ($user_type === 'teacher') {
                $stmt = $pdo->prepare("UPDATE teachers SET reset_token = ?, reset_expires = ? WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE students SET reset_token = ?, reset_expires = ? WHERE id = ?");
            }
            
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Verify token was stored
            if ($user_type === 'teacher') {
                $verify = $pdo->prepare("SELECT reset_token, reset_expires FROM teachers WHERE id = ?");
            } else {
                $verify = $pdo->prepare("SELECT reset_token, reset_expires FROM students WHERE id = ?");
            }
            $verify->execute([$user['id']]);
            $stored = $verify->fetch();
            
            $debug_info['token_storage'] = [
                'stored_token' => $stored['reset_token'],
                'stored_expires' => $stored['reset_expires'],
                'verification' => $stored['reset_token'] === $token ? 'success' : 'mismatch'
            ];

            // Generate reset link
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . 
                          dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token . "&type=" . $user_type;

            // Send reset email
            $to_email = $user['email'];
            
            if (sendResetEmail($to_email, $reset_link)) {
                $success = true;
                $debug_info['email_sent'] = true;
            } else {
                $errors[] = "Failed to send reset email. Please try again later.";
                $debug_info['email_sent'] = false;
            }
        } else {
            $errors[] = "No account found with the provided information.";
            $debug_info['user_found'] = false;
        }
    } catch (PDOException $e) {
        error_log("Database error during password reset: " . $e->getMessage());
        $errors[] = "An error occurred. Please try again later.";
        $debug_info['error'] = $e->getMessage();
    }
}

// Log debug information
error_log("Forgot Password Debug: " . json_encode($debug_info));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Grade Inquiry System</title>
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

        .forgot-container {
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

<div class="forgot-container">
    <div class="card">
        <div class="card-header text-center">
            <h4><i class="fas fa-key me-2"></i>Forgot Password</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Password reset instructions have been sent. Please check your email.
                </div>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="" id="resetForm">
                    <div class="mb-4">
                        <label for="userType" class="form-label">Account Type:</label>
                        <select name="user_type" id="userType" class="form-select" required>
                            <option value="">-- Select User Type --</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" id="identifierLabel">Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" name="identifier" class="form-control" placeholder="Enter your email address" required>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                        </button>
                    </div>
                </form>
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
    const userTypeSelect = document.getElementById('userType');
    const label = document.getElementById('identifierLabel');
    const resetForm = document.getElementById('resetForm');

    userTypeSelect.addEventListener('change', function() {
        label.innerText = "Email";
    });

    // Log debug information to console
    console.log('Forgot Password Debug:', <?= json_encode($debug_info) ?>);
    
    // Log any PHP errors to console
    <?php if (!empty($errors)): ?>
        console.error('Password Reset Errors:', <?= json_encode($errors) ?>);
    <?php endif; ?>

    // Form submission logging
    resetForm.addEventListener('submit', function(e) {
        const formData = new FormData(this);
        console.log('Submitting reset request:', {
            userType: formData.get('user_type'),
            identifier: formData.get('identifier')
        });
    });
</script>

</body>
</html> 
<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    // Validate input
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($user_type)) $errors[] = "User type is required";

    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered";
            } else {
                // Start transaction
                $pdo->beginTransaction();

                // Insert into users table
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password, user_type) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $user_type]);
                $user_id = $pdo->lastInsertId();

                // Insert into specific user type table
                if ($user_type === 'teacher') {
                    $stmt = $pdo->prepare("
                        INSERT INTO teachers (id, full_name, email, status) 
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$user_id, $full_name, $email]);
                } elseif ($user_type === 'student') {
                    $stmt = $pdo->prepare("
                        INSERT INTO students (id, first_name, last_name, email) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $full_name, '', $email]);
                }

                $pdo->commit();
                $success = true;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Grade Inquiry System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0">Register</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                Registration successful! You can now <a href="login.php">login</a>.
                                <?php if ($user_type === 'teacher'): ?>
                                    <br>Note: Your account will need to be approved by an administrator before you can log in.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="user_type" class="form-label">Register as</label>
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="">Select user type</option>
                                        <option value="teacher">Teacher</option>
                                        <option value="student">Student</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Register</button>
                                    <a href="login.php" class="btn btn-outline-secondary">Already have an account? Login</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
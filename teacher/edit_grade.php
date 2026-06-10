<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$grade_id = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : null;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$grade_id || !$class_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify that the grade belongs to a class owned by the current teacher
try {
    $stmt = $pdo->prepare("
        SELECT g.*, c.class_name 
        FROM grades g
        JOIN classes c ON g.class_id = c.id
        WHERE g.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$grade_id, $_SESSION['user_id']]);
    $grade = $stmt->fetch();
    
    if (!$grade) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error verifying grade: " . $e->getMessage());
}

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $final_grade = trim($_POST['final_grade']);
    $remarks = trim($_POST['remarks']);
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;

    // Validate grade format
    $final_grade = strtoupper($final_grade);
    $validGrade = false;
    
    if (is_numeric($final_grade) && $final_grade >= 0 && $final_grade <= 100) {
        $validGrade = true;
    } elseif (in_array($final_grade, ['A', 'B', 'C', 'D', 'F', 'P', 'INC', 'DRP'])) {
        $validGrade = true;
    }

    if (!$validGrade) {
        $error = "Invalid grade format. Please enter a number between 0-100 or a valid letter grade (A, B, C, D, F, P, INC, DRP).";
    } else {
        // Validate remarks
        $remarks = ucfirst(strtolower($remarks));
        if (!in_array($remarks, ['Passed', 'Failed'])) {
            $error = "Remarks must be either 'Passed' or 'Failed'.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE grades 
                    SET final_grade = ?, remarks = ?, is_visible = ?
                    WHERE id = ? AND class_id = ?
                ");
                $stmt->execute([$final_grade, $remarks, $is_visible, $grade_id, $class_id]);
                $success = true;
            } catch (PDOException $e) {
                $error = "Error updating grade: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Grade - <?= htmlspecialchars($grade['class_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0">Edit Grade - <?= htmlspecialchars($grade['class_name']) ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                Grade updated successfully!
                                <a href="view_grades.php?class_id=<?= $class_id ?>" class="alert-link">Return to grades list</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Student Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($grade['lastname'] . ', ' . $grade['firstname']) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="final_grade" class="form-label">Grade</label>
                                <input type="text" class="form-control" id="final_grade" name="final_grade" 
                                       value="<?= htmlspecialchars($grade['final_grade']) ?>" required>
                                <div class="form-text">
                                    Enter a number between 0-100 or a letter grade (A, B, C, D, F, P, INC, DRP)
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <select class="form-select" id="remarks" name="remarks" required>
                                    <option value="Passed" <?= $grade['remarks'] === 'Passed' ? 'selected' : '' ?>>Passed</option>
                                    <option value="Failed" <?= $grade['remarks'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_visible" name="is_visible" 
                                       <?= $grade['is_visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_visible">Make grade visible to student</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Grade</button>
                                <a href="view_grades.php?class_id=<?= $class_id ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html> 
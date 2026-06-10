<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get class_id from URL parameter
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

// Verify that the class belongs to the current teacher
if ($class_id) {
    try {
        // First check if the class exists and belongs to the current teacher
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM classes c 
            WHERE c.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$class_id, $_SESSION['user_id']]);
        $class = $stmt->fetch();
        
        if (!$class) {
            // Log the unauthorized access attempt
            error_log("Unauthorized access attempt to class_id: $class_id by teacher_id: {$_SESSION['user_id']}");
            // Instead of redirecting, we'll show the warning and logout
            $show_unauthorized_warning = true;
        }
    } catch (PDOException $e) {
        // Log the detailed error for debugging
        error_log("Database Error in view_grades.php: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Attempted Query Parameters - class_id: $class_id, teacher_id: {$_SESSION['user_id']}");
        
        // Show a more user-friendly error message
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Error</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: "System Error",
                    text: "There was an error accessing the class information. Please try again later.",
                    icon: "error",
                    confirmButtonText: "OK",
                    willClose: () => {
                        window.location.href = "dashboard.php";
                    }
                });
            </script>
        </body>
        </html>';
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

// If unauthorized access was detected, show warning and logout
if (isset($show_unauthorized_warning)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unauthorized Access</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: "Unauthorized Access!",
                text: "Illegal access attempt detected. You will be logged out for security reasons.",
                icon: "warning",
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                willClose: () => {
                    window.location.href = "../logout.php";
                }
            });
        </script>
    </body>
    </html>';
    exit();
}

// Fetch all grades for this class
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            lastname,
            firstname,
            final_grade,
            remarks,
            is_visible,
            created_at
        FROM grades 
        WHERE class_id = ?
        ORDER BY lastname, firstname
    ");
    $stmt->execute([$class_id]);
    $grades = $stmt->fetchAll();

    // Calculate statistics
    $total_students = count($grades);
    $passed_students = count(array_filter($grades, function($grade) { 
        return $grade['remarks'] === 'Passed'; 
    }));
    $failed_students = $total_students - $passed_students;
    $visible_grades = count(array_filter($grades, function($grade) { 
        return $grade['is_visible'] == 1; 
    }));

} catch (PDOException $e) {
    die("Error fetching grades: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Grades - <?= htmlspecialchars($class['class_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .page-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .grades-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .grades-card .card-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 1.5rem;
        }
        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn i {
            margin-right: 0.5rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }
        .back-btn {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 1000;
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            border: none;
        }
        .back-btn:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .back-btn i {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
            }
            .stats-card {
                margin-bottom: 1rem;
            }
            .btn {
                padding: 0.4rem 1rem;
            }
            .back-btn {
                width: 50px;
                height: 50px;
                bottom: 1.5rem;
                left: 1.5rem;
            }
            .back-btn i {
                font-size: 1.3rem;
            }
            .table-responsive {
                border-radius: 0.5rem;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Class Grades</h2>
                <h3 class="mb-0"><?= htmlspecialchars($class['class_name']) ?></h3>
            </div>
            <div class="d-none d-md-block">
                <i class="bi bi-journal-text" style="font-size: 3rem;"></i>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-primary">
                    <i class="bi bi-people"></i>
                </div>
                <h3><?= $total_students ?></h3>
                <p class="text-muted mb-0">Total Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3><?= $passed_students ?></h3>
                <p class="text-muted mb-0">Passed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
                <h3><?= $failed_students ?></h3>
                <p class="text-muted mb-0">Failed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-info">
                    <i class="bi bi-eye"></i>
                </div>
                <h3><?= $visible_grades ?></h3>
                <p class="text-muted mb-0">Visible Grades</p>
            </div>
        </div>
    </div>

    <!-- Grades Table -->
    <div class="grades-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table me-2"></i>Student Grades
            </h5>
            <div>
                <button type="button" class="btn btn-danger me-2" id="deleteSelectedBtn" style="display: none;">
                    <i class="bi bi-trash"></i>Delete Selected
                </button>
                <button type="button" class="btn btn-light me-2 toggle-visibility-btn" data-visible="<?= $visible_grades > 0 ? '1' : '0' ?>">
                    <i class="bi <?= $visible_grades > 0 ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                    <?= $visible_grades > 0 ? 'Set All Hidden' : 'Set All Visible' ?>
                </button>
                <a href="download_format.php" class="btn btn-light me-2">
                    <i class="bi bi-download"></i>Download Import Format
                </a>
                <a href="import_grades.php?class_id=<?= $class_id ?>" class="btn btn-light">
                    <i class="bi bi-upload"></i>Import Grades
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($grades)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No grades have been imported for this class yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th><i class=" me-2"></i>#</th>
                                <th><i class="bi bi-person me-2"></i>Last Name</th>
                                <th><i class="bi bi-person me-2"></i>First Name</th>
                                <th><i class="bi bi-star me-2"></i>Grade</th>
                                <th><i class="bi bi-check-circle me-2"></i>Remarks</th>
                                <th><i class="bi bi-eye me-2"></i>Status</th>
                                <th><i class="bi bi-calendar me-2"></i>Date Added</th>
                                <th><i class="bi bi-gear me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades as $index => $grade): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input grade-checkbox" data-grade-id="<?= $grade['id'] ?>">
                                    </td>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($grade['lastname']) ?></td>
                                    <td><?= htmlspecialchars($grade['firstname']) ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= htmlspecialchars($grade['final_grade']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $grade['remarks'] === 'Passed' ? 'success' : 'danger' ?>">
                                            <?= htmlspecialchars($grade['remarks']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $grade['is_visible'] ? 'success' : 'warning' ?>">
                                            <?= $grade['is_visible'] ? 'Visible' : 'Hidden' ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y h:i A', strtotime($grade['created_at'])) ?></td>
                                    <td>
                                        <button type="button" 
                                           class="btn btn-sm btn-outline-warning edit-grade-btn"
                                           data-grade-id="<?= $grade['id'] ?>"
                                           data-grade="<?= htmlspecialchars($grade['final_grade']) ?>"
                                           data-remarks="<?= htmlspecialchars($grade['remarks']) ?>"
                                           data-visible="<?= $grade['is_visible'] ? '1' : '0' ?>">
                                            <i class="bi bi-pencil"></i>Edit
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

    <a href="dashboard.php" class="btn back-btn" title="Back to Dashboard">
        <i class="bi bi-arrow-left"></i>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Handle edit grade button clicks
    document.querySelectorAll('.edit-grade-btn').forEach(button => {
        button.addEventListener('click', function() {
            const gradeId = this.dataset.gradeId;
            const currentGrade = this.dataset.grade;
            const currentRemarks = this.dataset.remarks;
            const isVisible = this.dataset.visible === '1';

            Swal.fire({
                title: 'Edit Grade',
                html: `
                    <form id="editGradeForm" class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Grade</label>
                            <input type="text" id="grade" class="form-control" value="${currentGrade}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <select id="remarks" class="form-select" required>
                                <option value="Passed" ${currentRemarks === 'Passed' ? 'selected' : ''}>Passed</option>
                                <option value="Failed" ${currentRemarks === 'Failed' ? 'selected' : ''}>Failed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" id="isVisible" class="form-check-input" ${isVisible ? 'checked' : ''}>
                                <label class="form-check-label" for="isVisible">Make grade visible to students</label>
                            </div>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Save Changes',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                preConfirm: () => {
                    const grade = document.getElementById('grade').value;
                    const remarks = document.getElementById('remarks').value;
                    const isVisible = document.getElementById('isVisible').checked;

                    if (!grade) {
                        Swal.showValidationMessage('Please enter a grade');
                        return false;
                    }

                    return { grade, remarks, isVisible };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Updating Grade',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send update request
                    fetch('update_grade.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            grade_id: gradeId,
                            grade: result.value.grade,
                            remarks: result.value.remarks,
                            is_visible: result.value.isVisible ? 1 : 0
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Grade Updated!',
                                text: 'The grade has been updated successfully.',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to update grade',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while updating the grade',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        });
    });

    // Toggle Visibility Button
    document.querySelector('.toggle-visibility-btn').addEventListener('click', function() {
        const currentVisible = this.dataset.visible === '1';
        const makeVisible = !currentVisible;
        
        Swal.fire({
            title: makeVisible ? 'Set All Grades Visible?' : 'Set All Grades Hidden?',
            text: makeVisible ? 'All grades for this class will be visible to students.' : 'All grades for this class will be hidden from students.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: makeVisible ? 'Yes, set all visible' : 'Yes, set all hidden',
            cancelButtonText: 'Cancel',
            confirmButtonColor: makeVisible ? '#28a745' : '#ffc107',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Updating...',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                fetch('set_all_grades_visibility.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        class_id: <?= $class_id ?>,
                        is_visible: makeVisible ? 1 : 0
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message || 'All grades updated.',
                            confirmButtonColor: '#28a745'
                        }).then(() => { location.reload(); });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to update grades.',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while updating grades.',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    });

    // Multiple Delete Functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const gradeCheckboxes = document.querySelectorAll('.grade-checkbox');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

    // Handle select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        gradeCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateDeleteButtonVisibility();
    });

    // Handle individual checkboxes
    gradeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateDeleteButtonVisibility();
            // Update select all checkbox state
            selectAllCheckbox.checked = Array.from(gradeCheckboxes).every(cb => cb.checked);
        });
    });

    // Update delete button visibility based on selected checkboxes
    function updateDeleteButtonVisibility() {
        const hasSelected = Array.from(gradeCheckboxes).some(cb => cb.checked);
        deleteSelectedBtn.style.display = hasSelected ? 'inline-block' : 'none';
    }

    // Handle delete selected button click
    deleteSelectedBtn.addEventListener('click', function() {
        const selectedGrades = Array.from(gradeCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.dataset.gradeId);

        if (selectedGrades.length === 0) return;

        Swal.fire({
            title: 'Delete Selected Grades',
            text: `Are you sure you want to delete ${selectedGrades.length} grade(s)? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Deleting Grades',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send delete request
                fetch('delete_grades.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        grade_ids: selectedGrades
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Grades Deleted!',
                            text: 'The selected grades have been deleted successfully.',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to delete grades',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while deleting the grades',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    });
</script>
</body>
</html> 
<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Enable error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Function to log errors
function logError($message) {
    error_log($message);
    echo "<script>console.log('" . addslashes($message) . "');</script>";
}

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
        error_log("Database Error in import_grades.php: " . $e->getMessage());
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

$errors = [];
$success = false;
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $show_grades = isset($_POST['show_grades']) ? 1 : 0;

    // Debug: Log file information
    logError("File upload started: " . $file['name']);
    logError("File type: " . $file['type']);
    logError("Class ID: " . $class_id);

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file. Please try again.";
    } else {
        $allowedTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Please upload an Excel file (.xls, .xlsx) or CSV file.";
        } else {
            try {
                // Load the Excel file
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Debug: Log number of rows
                logError("Total rows in file: " . count($rows));

                // Remove header row
                array_shift($rows);

                // Start transaction
                $pdo->beginTransaction();

                $imported = 0;
                $skipped = 0;
                foreach ($rows as $index => $row) {
                    if (count($row) >= 3) { // Ensure we have all required columns
                        $lastname = trim($row[0]);
                        $firstname = trim($row[1]);
                        $grade = trim($row[2]);
                        $remarks = isset($row[3]) ? trim($row[3]) : '';

                        // Debug: Log row data
                        logError("Processing row " . ($index + 1) . ": $lastname, $firstname, $grade, $remarks");

                        // Validate data
                        if (empty($lastname) || empty($firstname) || empty($grade)) {
                            logError("Skipping row " . ($index + 1) . ": Missing required data");
                            $skipped++;
                            continue;
                        }

                        // Validate grade format
                        $grade = strtoupper($grade);
                        $validGrade = false;
                        
                        if (is_numeric($grade) && $grade >= 0 && $grade <= 100) {
                            $validGrade = true;
                        } elseif (in_array($grade, ['A', 'B', 'C', 'D', 'F', 'P', 'INC', 'DRP'])) {
                            $validGrade = true;
                        }

                        if (!$validGrade) {
                            logError("Skipping row " . ($index + 1) . ": Invalid grade format - '$grade'");
                            $skipped++;
                            continue;
                        }

                        // Set default remarks if not provided
                        if (empty($remarks)) {
                            if (is_numeric($grade)) {
                                $remarks = ($grade <= 3.0) ? 'Passed' : 'Failed';
                            } else {
                                $remarks = 'Failed';
                            }
                        } else {
                            // Validate remarks
                            $remarks = ucfirst(strtolower($remarks));
                            if (!in_array($remarks, ['Passed', 'Failed'])) {
                                logError("Skipping row " . ($index + 1) . ": Invalid remarks - '$remarks'");
                                $skipped++;
                                continue;
                            }
                        }

                        try {
                            // Insert grade directly
                            $stmt = $pdo->prepare("
                                INSERT INTO grades (class_id, lastname, firstname, final_grade, remarks, is_visible, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE
                                    final_grade = VALUES(final_grade),
                                    remarks = VALUES(remarks),
                                    is_visible = VALUES(is_visible)
                            ");

                            $stmt->execute([
                                $class_id,
                                $lastname,
                                $firstname,
                                $grade,
                                $remarks,
                                $show_grades
                            ]);
                            $imported++;
                            logError("Successfully imported grade for: $lastname, $firstname");
                        } catch (PDOException $e) {
                            logError("Error importing grade for $lastname, $firstname: " . $e->getMessage());
                            $skipped++;
                        }
                    }
                }

                $pdo->commit();
                $success = true;
                $message = "Successfully imported $imported grades. Skipped $skipped rows.";

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                    logError("Transaction rolled back due to error");
                }
                $errors[] = "Error processing file: " . $e->getMessage();
                logError("Exception: " . $e->getMessage());
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
    <title>Import Grades - <?= htmlspecialchars($class['class_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .import-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        .import-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.5rem;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #4b6cb7;
            box-shadow: 0 0 0 0.2rem rgba(75, 108, 183, 0.25);
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .form-check-input:checked {
            background-color: #4b6cb7;
            border-color: #4b6cb7;
        }
        .btn {
            padding: 0.75rem 1.5rem;
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
        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 1rem 1.5rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert-info {
            background-color: #e2f3f5;
            color: #0c5460;
        }
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 2.5rem;
            min-height: 120px;
        }
        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .file-upload-text {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .file-upload-text:hover {
            border-color: #4b6cb7;
            background: #f1f3f9;
        }
        .file-upload-text i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 1rem;
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
            .card-header {
                padding: 1rem;
            }
            .btn {
                padding: 0.6rem 1.2rem;
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
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Import Grades</h2>
                    <h3 class="mb-0"><?= htmlspecialchars($class['class_name']) ?></h3>
                </div>
                <div class="d-none d-md-block">
                    <i class="bi bi-file-earmark-spreadsheet" style="font-size: 3rem;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="import-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-upload me-2"></i>Upload Excel File
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i><?= $message ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($debug_info)): ?>
                            <div class="alert alert-info">
                                <h5><i class="bi bi-info-circle-fill me-2"></i>Debug Information:</h5>
                                <pre class="mb-0"><?= htmlspecialchars(implode("\n", $debug_info)) ?></pre>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="file-upload-wrapper mb-4">
                                <div class="file-upload-text" id="fileUploadText">
                                    <div>
                                        <i class="bi bi-cloud-upload"></i>
                                        <p class="mb-0">Drag and drop your Excel file here<br>or click to browse</p>
                                    </div>
                                </div>
                                <input type="file" name="excel_file" id="excel_file" class="file-upload-input" required aria-label="Excel File">
                            </div>

                            <div class="form-text mb-4" style="margin-top: -1.5rem;">
                                <div class="alert alert-light mb-0">
                                    <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>File Requirements:</h6>
                                    <ul class="mb-0">
                                        <li>Columns must be in order: Lastname, Firstname, Grade, Remarks (optional)</li>
                                        <li>Grade can be numeric (0-100) or letter grade (A, B, C, D, F, P, INC, DRP)</li>
                                        <li>If Remarks is not provided, it will be automatically set based on the grade</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="show_grades" id="showGrades">
                                <label class="form-check-label" for="showGrades">
                                    <i class="bi bi-eye me-2"></i>Make grades visible to students
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i>Import Grades
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a href="dashboard.php" class="btn back-btn" title="Back to Grades">
        <i class="bi bi-arrow-left"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>
    <script>
        // File upload UI enhancement
        const fileInput = document.getElementById('excel_file');
        const fileUploadText = document.getElementById('fileUploadText');

        // Make drag-and-drop area clickable
        fileUploadText.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileUploadText.innerHTML = `
                    <div>
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <p class="mb-0">${this.files[0].name}</p>
                    </div>
                `;
            }
        });

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadText.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadText.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadText.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            fileUploadText.classList.add('border-primary');
        }

        function unhighlight(e) {
            fileUploadText.classList.remove('border-primary');
        }

        fileUploadText.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            if (files && files[0]) {
                fileUploadText.innerHTML = `
                    <div>
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <p class="mb-0">${files[0].name}</p>
                    </div>
                `;
            }
        }
    </script>
    <?php if ($success && isset($message)): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Import Successful',
            html: '<?= addslashes($message) ?>',
            confirmButtonColor: '#4b6cb7'
        });
    </script>
    <?php elseif (!empty($errors)): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Import Failed',
            html: `<?= addslashes(implode('<br>', $errors)) ?>`,
            confirmButtonColor: '#d33'
        });
    </script>
    <?php endif; ?>
</body>
</html> 
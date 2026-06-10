<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Fetch teacher information
$stmt = $pdo->prepare("SELECT full_name FROM teachers WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Fetch active school year and semesters
$stmt = $pdo->query("SELECT * FROM school_years WHERE is_active = 1 LIMIT 1");
$active_school_year = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT s.* 
    FROM semesters s 
    JOIN school_years sy ON s.school_year_id = sy.id 
    WHERE sy.id = ? 
    ORDER BY s.semester_name
");
$stmt->execute([$active_school_year['id']]);
$semesters = $stmt->fetchAll();

// Fetch teacher classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Count total classes
$total_classes = count($classes);

// Count passed and failed students
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN g.remarks = 'Passed' THEN 1 END) as passed_count,
            COUNT(CASE WHEN g.remarks = 'Failed' THEN 1 END) as failed_count,
            COUNT(DISTINCT g.id) as total_students
        FROM grades g
        JOIN classes c ON g.class_id = c.id
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = [
        'passed_count' => 0,
        'failed_count' => 0,
        'total_students' => 0
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Loading Spinner Styles */
        #loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 1.5s ease-out;
        }

        .spinner-container {
            text-align: center;
            color: white;
            animation: fadeIn 1s ease-in;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #3498db;
            border-radius: 50%;
            animation: spin 2s linear infinite;
            margin: 0 auto 20px;
        }

        .loading-text {
            font-size: 1.2rem;
            font-weight: 500;
            margin-top: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            animation: pulse 2s infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }

        .dashboard-header {
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
            border: none;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #4b6cb7, #182848);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .class-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border: none;
            position: relative;
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .class-cover {
            height: 160px;
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
        }

        .class-cover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.7));
            z-index: 1;
        }

        .class-cover-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            color: white;
            z-index: 2;
        }

        .class-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .class-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.25rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .class-content {
            padding: 1.5rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn i {
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(75, 108, 183, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid #4b6cb7;
            color: #4b6cb7;
        }

        .btn-outline-primary:hover {
            background: #4b6cb7;
            color: white;
            transform: translateY(-2px);
        }

        .create-class-form {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .create-class-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #4b6cb7, #182848);
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4b6cb7;
            box-shadow: 0 0 0 0.2rem rgba(75, 108, 183, 0.15);
        }

        .header-logout-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.8rem 1.5rem;
            border-radius: 0.8rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
            font-size: 1rem;
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .header-logout-btn i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .header-logout-btn:hover i {
            transform: translateX(3px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
            }
            .stats-card {
                margin-bottom: 1rem;
            }
            .class-cover {
                height: 140px;
            }
            .class-title {
                font-size: 1.3rem;
            }
            .btn {
                padding: 0.5rem 1rem;
            }
        }

        .delete-class-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            border: none;
            backdrop-filter: blur(4px);
        }

        .delete-class-btn:hover {
            background: rgba(220, 53, 69, 0.9);
            transform: scale(1.1);
        }

        .delete-class-btn i {
            font-size: 1.2rem;
        }

        .edit-class-btn {
            position: absolute;
            top: 1rem;
            right: 4rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            border: none;
            backdrop-filter: blur(4px);
        }

        .edit-class-btn:hover {
            background: rgba(13, 110, 253, 0.9);
            transform: scale(1.1);
        }

        .edit-class-btn i {
            font-size: 1.2rem;
        }

        /* Gradient Presets */
        .gradient-1 { background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%); }
        .gradient-2 { background: linear-gradient(135deg, #A8E6CF 0%, #DCEDC1 100%); }
        .gradient-3 { background: linear-gradient(135deg, #FFD3B6 0%, #FFAAA5 100%); }
        .gradient-4 { background: linear-gradient(135deg, #AA96DA 0%, #FCBAD3 100%); }
        .gradient-5 { background: linear-gradient(135deg, #84FAB0 0%, #8FD3F4 100%); }
        .gradient-6 { background: linear-gradient(135deg, #A1C4FD 0%, #C2E9FB 100%); }
        .gradient-7 { background: linear-gradient(135deg, #FF9A9E 0%, #FAD0C4 100%); }
        .gradient-8 { background: linear-gradient(135deg, #FEE140 0%, #FA709A 100%); }
        .gradient-9 { background: linear-gradient(135deg, #43E97B 0%, #38F9D7 100%); }
        .gradient-10 { background: linear-gradient(135deg, #FA709A 0%, #FEE140 100%); }
        .gradient-11 { background: linear-gradient(135deg, #6A11CB 0%, #2575FC 100%); }
        .gradient-12 { background: linear-gradient(135deg, #F093FB 0%, #F5576C 100%); }
        .gradient-13 { background: linear-gradient(135deg, #4FACFE 0%, #00F2FE 100%); }
        .gradient-14 { background: linear-gradient(135deg, #43E97B 0%, #38F9D7 100%); }
        .gradient-15 { background: linear-gradient(135deg, #FA709A 0%, #FEE140 100%); }
        .gradient-16 { background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%); }
        .gradient-17 { background: linear-gradient(135deg, #FF9A9E 0%, #FAD0C4 100%); }
        .gradient-18 { background: linear-gradient(135deg, #A18CD1 0%, #FBC2EB 100%); }
        .gradient-19 { background: linear-gradient(135deg, #F6D365 0%, #FDA085 100%); }
        .gradient-20 { background: linear-gradient(135deg, #5EE7DF 0%, #B490CA 100%); }

        /* Floating Action Button Styles */
        .fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 1000;
            border: none;
        }

        .fab:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .fab i {
            font-size: 1.5rem;
        }

        @media (max-width: 768px) {
            .fab {
                bottom: 1.5rem;
                right: 1.5rem;
                width: 50px;
                height: 50px;
            }
        }

        /* Add these styles to ensure equal height */
        .form-control, .form-select {
            height: 50px;
        }
        
        .input-group {
            height: 50px;
        }
        
        .input-group .form-control {
            height: 100%;
        }
    </style>
</head>
<body>

<!-- Loading Spinner -->
<div id="loading-spinner">
    <div class="spinner-container">
        <div class="spinner"></div>
        <div class="loading-text">Loading Dashboard...</div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" onclick="openQuestionBankModal()">
    <i class="bi bi-plus-lg"></i>
</button>

<div class="dashboard-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Welcome back,</h2>
                <h3 class="mb-0"><?= htmlspecialchars($teacher['full_name']) ?></h3>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block">
                    <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                </div>
                <a href="../logout.php" class="header-logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon">
                    <i class="bi bi-book"></i>
                </div>
                <h3><?= $total_classes ?></h3>
                <p class="text-muted mb-0">Total Classes</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3><?= $stats['passed_count'] ?></h3>
                <p class="text-muted mb-0">Passed Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
                <h3><?= $stats['failed_count'] ?></h3>
                <p class="text-muted mb-0">Failed Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card text-center">
                <div class="stats-icon text-primary">
                    <i class="bi bi-people"></i>
                </div>
                <h3><?= $stats['total_students'] ?></h3>
                <p class="text-muted mb-0">Total Students</p>
            </div>
        </div>
    </div>

    <!-- Create New Class Form -->
    <div class="create-class-form">
        <h4 class="mb-4">
            <i class="bi bi-plus-circle me-2"></i>Create New Class
        </h4>
        <form action="create_class.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="class_name" class="form-label">Class Name (Course Code, Block)</label>
                    <input type="text" name="class_name" id="class_name" class="form-control" 
                           placeholder="Enter class name" required>
                </div>
                <div class="col-md-3">
                    <label for="school_year" class="form-label">School Year</label>
                    <div class="input-group">
                        <input type="text" id="school_year" class="form-control" 
                               value="<?= htmlspecialchars($active_school_year['school_year']) ?>" readonly>
                        <input type="hidden" name="school_year_id" value="<?= $active_school_year['id'] ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="semester_id" class="form-label">Semester</label>
                    <select name="semester_id" id="semester_id" class="form-select" required>
                        <option value="">Select Semester</option>
                        <?php foreach ($semesters as $semester): ?>
                            <option value="<?= $semester['id'] ?>" <?= $semester['is_active'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($semester['semester_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i>Create Class
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Classes List -->
    <div class="row g-4">
        <?php if (count($classes) > 0): ?>
            <?php foreach ($classes as $class): 
                // Fetch semester and school year information for this class
                $stmt = $pdo->prepare("
                    SELECT s.semester_name, sy.school_year 
                    FROM classes c 
                    JOIN semesters s ON c.semester_id = s.id 
                    JOIN school_years sy ON s.school_year_id = sy.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$class['id']]);
                $class_info = $stmt->fetch();
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="class-card">
                        <div class="class-cover gradient-<?= ($class['id'] % 20) + 1 ?>">
                            <button class="edit-class-btn" onclick="editClassName(<?= $class['id'] ?>, '<?= htmlspecialchars($class['class_name']) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="delete-class-btn" onclick="confirmDeleteClass(<?= $class['id'] ?>, '<?= htmlspecialchars($class['class_name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <div class="class-cover-overlay">
                                <h5 class="class-title"><?= htmlspecialchars($class['class_name']) ?></h5>
                                <p class="class-subtitle mb-0">
                                    <?= htmlspecialchars($class_info['school_year']) ?> - <?= htmlspecialchars($class_info['semester_name']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="class-content">
                            <div class="d-grid gap-2">
                                <a href="view_grades.php?class_id=<?= $class['id'] ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-eye"></i>View Grades
                                </a>
                                <a href="import_grades.php?class_id=<?= $class['id'] ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-upload"></i>Upload Grades
                                </a>
                                <a href="view_ratings.php?class_id=<?= $class['id'] ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-star"></i>View Ratings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No classes yet. Create your first class above!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Class Modal -->
    <div class="modal fade" id="editClassModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editClassForm" method="POST">
                        <input type="hidden" name="action" value="edit_class">
                        <input type="hidden" name="class_id" id="edit_class_id">
                        <div class="mb-3">
                            <label for="edit_class_name" class="form-label">Class Name</label>
                            <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_school_year" class="form-label">School Year</label>
                            <input type="text" id="edit_school_year" class="form-control" readonly>
                            <input type="hidden" name="school_year_id" id="edit_school_year_id">
                        </div>
                        <div class="mb-3">
                            <label for="edit_semester_id" class="form-label">Semester</label>
                            <select name="semester_id" id="edit_semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?= $semester['id'] ?>">
                                        <?= htmlspecialchars($semester['semester_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editClassForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading spinner when page is fully loaded
    window.addEventListener('load', function() {
        const spinner = document.getElementById('loading-spinner');
        spinner.style.opacity = '0';
        setTimeout(() => {
            spinner.style.display = 'none';
        }, 1500);
    });
});

function confirmDeleteClass(classId, className) {
    console.log('Starting delete process for class:', { classId, className });
    
    // First, get the data counts
    fetch('get_class_data.php?class_id=' + classId)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Class data response:', data);
            
            if (data.success) {
                const counts = data.counts;
                const totalData = counts.grades + counts.ratings + counts.complaints;
                console.log('Data counts:', counts);
                
                let warningMessage = `Are you sure you want to delete <strong>${className}</strong>?`;
                
                if (totalData > 0) {
                    warningMessage += '<br><br><strong>Warning:</strong> This will also delete:';
                    if (counts.grades > 0) {
                        warningMessage += `<br>• ${counts.grades} grade record${counts.grades > 1 ? 's' : ''}`;
                    }
                    if (counts.ratings > 0) {
                        warningMessage += `<br>• ${counts.ratings} rating${counts.ratings > 1 ? 's' : ''}`;
                    }
                    if (counts.complaints > 0) {
                        warningMessage += `<br>• ${counts.complaints} complaint${counts.complaints > 1 ? 's' : ''}`;
                    }
                    warningMessage += '<br><br>This action cannot be undone.';
                }

                Swal.fire({
                    title: 'Delete Class',
                    html: warningMessage,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        console.log('Sending delete request for class:', classId);
                        return fetch('delete_class.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ class_id: classId })
                        })
                        .then(response => {
                            console.log('Delete response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Delete response data:', data);
                            if (!data.success) {
                                throw new Error(data.message || 'Failed to delete the class');
                            }
                            return data;
                        })
                        .catch(error => {
                            console.error('Delete error:', error);
                            Swal.showValidationMessage(`Request failed: ${error.message}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    console.log('Delete confirmation result:', result);
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Deleted!',
                            html: `The class has been deleted successfully.<br><br>
                                  <strong>Deleted Data:</strong><br>
                                  • ${result.value.deleted_data.grades} grade record${result.value.deleted_data.grades !== 1 ? 's' : ''}<br>
                                  • ${result.value.deleted_data.ratings} rating${result.value.deleted_data.ratings !== 1 ? 's' : ''}<br>
                                  • ${result.value.deleted_data.complaints} complaint${result.value.deleted_data.complaints !== 1 ? 's' : ''}`,
                            icon: 'success',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            console.log('Reloading page after successful deletion');
                            location.reload();
                        });
                    }
                });
            } else {
                console.error('Error getting class data:', data.message);
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to get class data',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error in class data fetch:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while checking class data',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        });
}

function editClassName(classId, currentName) {
    // Fetch class details including semester
    fetch('get_class_details.php?class_id=' + classId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const classDetails = data.class;
                
                // Set form values
                document.getElementById('edit_class_id').value = classId;
                document.getElementById('edit_class_name').value = currentName;
                document.getElementById('edit_school_year').value = classDetails.school_year;
                document.getElementById('edit_school_year_id').value = classDetails.school_year_id;
                document.getElementById('edit_semester_id').value = classDetails.semester_id;

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
                modal.show();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to fetch class details',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while fetching class details',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        });
}

// Handle edit class form submission
document.getElementById('editClassForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('update_class.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Class updated successfully',
                icon: 'success',
                confirmButtonColor: '#28a745'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message || 'Failed to update class',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while updating the class',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
    });
});

function openQuestionBankModal() {
    Swal.fire({
        title: 'Contribute to Question Bank',
        html: `
            <form id="questionForm" class="text-start">
                <div class="mb-3">
                    <label for="question" class="form-label">Question</label>
                    <textarea class="form-control" id="question" rows="3" required 
                        placeholder="Enter your question here..."></textarea>
                </div>
                <div class="mb-3">
                    <label for="correct_answer" class="form-label">Correct Answer</label>
                    <textarea class="form-control" id="correct_answer" rows="3" required 
                        placeholder="Enter the correct answer..."></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit Question',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#4b6cb7',
        cancelButtonColor: '#6c757d',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const question = document.getElementById('question').value;
            const correct_answer = document.getElementById('correct_answer').value;

            if (!question || !correct_answer) {
                Swal.showValidationMessage('Please fill in all fields');
                return false;
            }

            return fetch('save_question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    question,
                    correct_answer
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to save question');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Success!',
                text: 'Your question has been added to the question bank.',
                icon: 'success',
                confirmButtonColor: '#28a745'
            });
        }
    });
}
</script>
</body>
</html>

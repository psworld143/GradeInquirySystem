<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student information
$stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: ../login.php");
    exit();
}

// Fetch grades with ratings
$stmt = $pdo->prepare("
    SELECT 
        g.final_grade, 
        g.remarks, 
        g.is_visible, 
        c.id as class_id,
        c.class_name, 
        t.full_name AS teacher_name,
        COALESCE(r.rating, 0) as rating,
        r.comment as rating_comment,
        COALESCE(r.is_anonymous, FALSE) as is_anonymous,
        s.semester_name,
        sy.school_year
    FROM grades g
    JOIN classes c ON g.class_id = c.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN semesters s ON c.semester_id = s.id
    JOIN school_years sy ON s.school_year_id = sy.id
    LEFT JOIN teacher_ratings r ON c.id = r.class_id AND r.student_id = ?
    WHERE g.firstname = ? AND g.lastname = ?
    ORDER BY c.class_name ASC
");
$stmt->execute([$_SESSION['user_id'], $student['first_name'], $student['last_name']]);
$grades = $stmt->fetchAll();

// Calculate statistics
$total_classes = count($grades);
$passed_classes = count(array_filter($grades, function($grade) { return $grade['remarks'] === 'Passed'; }));
$failed_classes = $total_classes - $passed_classes;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .grades-table {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }
        .grade-hidden {
            color: #6c757d;
            font-style: italic;
        }
        .logout-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .logout-btn:hover {
            transform: scale(1.1);
        }
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            .table-responsive {
                border-radius: 1rem;
            }
        }
        
        /* Rating Modal Styles */
        .rating-stars .star {
            transition: all 0.2s ease;
            color: #ffc107;
        }
        
        .rating-stars .star:hover {
            transform: scale(1.2);
        }
        
        .rating-stars .bi-star-fill {
            color: #ffc107;
        }
        
        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            border-radius: 1rem 1rem 0 0;
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #182848 0%, #4b6cb7 100%);
            transform: translateY(-1px);
        }
        
        .btn-light {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .btn-light:hover {
            background: #e9ecef;
        }
        
        #anonymousInfo {
            border-radius: 0.5rem;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        @media (max-width: 576px) {
            .modal-body {
                padding: 1.5rem;
            }
            
            .rating-stars .star {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 0.4rem 1rem;
            }
        }

        /* Updated Floating Action Buttons */
        .floating-buttons {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            z-index: 1000;
        }

        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .floating-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: scale(0);
            transition: transform 0.3s ease;
            border-radius: 50%;
        }

        .floating-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .floating-btn:hover::before {
            transform: scale(1);
        }

        .floating-btn i {
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }

        .floating-btn:hover i {
            transform: scale(1.1);
        }

        .complaint-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #000;
        }

        .complaint-btn:hover {
            background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
            color: #000;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #fff;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
            color: #fff;
        }

        /* Tooltip styles */
        .floating-btn::after {
            content: attr(title);
            position: absolute;
            right: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(246, 162, 7, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .floating-btn:hover::after {
            opacity: 1;
            visibility: visible;
            right: 75px;
        }

        @media (max-width: 576px) {
            .floating-buttons {
                bottom: 1.5rem;
                right: 1.5rem;
                gap: 0.75rem;
            }

            .floating-btn {
                width: 50px;
                height: 50px;
            }

            .floating-btn i {
                font-size: 1.2rem;
            }

            .floating-btn::after {
                display: none;
            }
        }

        /* Updated Header Logout Button */
        .logout-btn-header {
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
        }

        .logout-btn-header:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
        }

        .logout-btn-header i {
            transition: transform 0.3s ease;
        }

        .logout-btn-header:hover i {
            transform: translateX(3px);
        }

        @media (max-width: 576px) {
            .logout-btn-header {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }
            
            .logout-btn-header i {
                margin-right: 0.5rem !important;
            }
        }

        /* Update floating buttons container for single button */
        .floating-buttons {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        /* Emoji Animation Styles */
        .emoji-rain {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        .falling-emoji {
            position: absolute;
            font-size: 2rem;
            animation: fall linear forwards;
        }

        @keyframes fall {
            to {
                transform: translateY(100vh) rotate(360deg);
            }
        }

        .clap-animation {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            animation: clap 0.5s ease-in-out infinite;
            z-index: 1000;
        }

        @keyframes clap {
            0%, 100% { transform: translateX(-50%) scale(1); }
            50% { transform: translateX(-50%) scale(1.2); }
        }

        /* Add shake animation style */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .shake-animation {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        /* Grade Card Styles */
        .grade-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .grade-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Dynamic Gradient Classes */
        .gradient-1 { background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); }
        .gradient-2 { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .gradient-3 { background: linear-gradient(135deg, #f46b45 0%, #eea849 100%); }
        .gradient-4 { background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); }
        .gradient-5 { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .gradient-6 { background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%); }
        .gradient-7 { background: linear-gradient(135deg, #396afc 0%, #2948ff 100%); }
        .gradient-8 { background: linear-gradient(135deg, #1D976C 0%, #93F9B9 100%); }
        .gradient-9 { background: linear-gradient(135deg, #834d9b 0%, #d04ed6 100%); }
        .gradient-10 { background: linear-gradient(135deg, #009245 0%, #FCEE21 100%); }
        .gradient-11 { background: linear-gradient(135deg, #654ea3 0%, #eaafc8 100%); }
        .gradient-12 { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }
        .gradient-13 { background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); }
        .gradient-14 { background: linear-gradient(135deg, #373B44 0%, #4286f4 100%); }
        .gradient-15 { background: linear-gradient(135deg, #0F2027 0%, #203A43 100%); }

        .grade-card-header {
            padding: 1.5rem;
            color: white;
        }

        .grade-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .grade-card-subtitle {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .grade-card-subtitle .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white !important;
            font-weight: 500;
        }

        .grade-card-body {
            padding: 1.5rem;
        }

        .teacher-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .grade-info, .remarks-info, .rating-info {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .grade-label, .remarks-label, .rating-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .rating-stars {
            display: flex;
            gap: 0.25rem;
        }

        .rating-stars .star {
            color: #ffc107;
            font-size: 1rem;
        }

        .grade-card-actions {
            margin-top: 1rem;
        }

        .grade-hidden {
            color: #6c757d;
            font-style: italic;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .grade-card {
                margin-bottom: 1rem;
            }
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

<div class="dashboard-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Welcome back,</h2>
                <h3 class="mb-0"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block">
                    <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                </div>
                <button class="btn btn-outline-light me-2" id="editProfileBtn" title="Edit Profile">
                    <i class="bi bi-pencil-square me-1"></i>Edit Profile
                </button>
                <a href="../logout.php" class="btn btn-outline-light logout-btn-header" title="Logout">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card text-center">
                <div class="stats-icon text-primary">
                    <i class="bi bi-book"></i>
                </div>
                <h3><?= $total_classes ?></h3>
                <p class="text-muted mb-0">Total Classes</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card text-center">
                <div class="stats-icon text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3><?= $passed_classes ?></h3>
                <p class="text-muted mb-0">Passed Classes</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card text-center">
                <div class="stats-icon text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
                <h3><?= $failed_classes ?></h3>
                <p class="text-muted mb-0">Failed Classes</p>
            </div>
        </div>
    </div>

    <!-- Grades Table -->
    <div class="card grades-table mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-card-list me-2"></i>
                Your Grades
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($grades) > 0): ?>
                <div class="row g-4">
                    <?php foreach ($grades as $grade): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="grade-card h-100" data-class-id="<?= $grade['class_id'] ?>">
                                <div class="grade-card-header gradient-<?= ($grade['class_id'] % 15) + 1 ?>">
                                    <h5 class="grade-card-title mb-0">
                                        <?= htmlspecialchars($grade['class_name']) ?>
                                    </h5>
                                    <div class="grade-card-subtitle">
                                        <span class="badge">
                                            <i class="bi bi-calendar me-1"></i><?= htmlspecialchars($grade['school_year']) ?>
                                        </span>
                                        <span class="badge">
                                            <i class="bi bi-calendar-week me-1"></i><?= htmlspecialchars($grade['semester_name']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="grade-card-body">
                                    <div class="teacher-info mb-3">
                                        <i class="bi bi-person me-2"></i>
                                        <span><?= htmlspecialchars($grade['teacher_name']) ?></span>
                                    </div>
                                    
                                    <div class="grade-info mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="grade-label">Final Grade:</span>
                                    <?php if ($grade['is_visible'] == 1): ?>
                                        <?php if (isset($_SESSION['grade_verified']) && $_SESSION['grade_verified']): ?>
                                            <span class="badge rounded-pill" style="
                                                background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
                                                color: white;
                                                font-size: 0.9rem;
                                                padding: 0.5rem 1rem;
                                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                                display: inline-flex;
                                                align-items: center;
                                                gap: 0.5rem;
                                            ">
                                                <i class="bi bi-star-fill"></i>
                                                <?= htmlspecialchars($grade['final_grade']) ?>
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-primary verify-grade-btn" 
                                                    data-class-id="<?= $grade['class_id'] ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#verifyGradeModal">
                                                <i class="bi bi-shield-lock me-1"></i>Verify to View Grade
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="grade-hidden">
                                            <i class="bi bi-eye-slash me-1"></i>Grade Hidden
                                        </span>
                                    <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="remarks-info mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="remarks-label">Remarks:</span>
                                    <span class="badge <?= $grade['remarks'] == 'Passed' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= htmlspecialchars($grade['remarks']) ?>
                                    </span>
                                        </div>
                                    </div>

                                    <div class="rating-info mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="rating-label">Rating:</span>
                                    <?php if ($grade['rating'] > 0): ?>
                                        <div class="rating-stars" data-class-id="<?= $grade['class_id'] ?>" data-rating="<?= $grade['rating'] ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $grade['rating'] >= $i ? '-fill' : '' ?> star" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not rated yet</span>
                                    <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="grade-card-actions">
                                        <button class="btn btn-sm btn-outline-primary rate-btn w-100 mb-2" 
                                            data-class-id="<?= $grade['class_id'] ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#ratingModal">
                                        <i class="bi bi-star me-1"></i>
                                        <?= $grade['rating'] > 0 ? 'Update Rating' : 'Rate Teacher' ?>
                                    </button>
                                    <?php if ($grade['rating'] > 0 && !empty($grade['rating_comment'])): ?>
                                            <button class="btn btn-sm btn-outline-info view-comment-btn w-100" 
                                                data-comment="<?= htmlspecialchars($grade['rating_comment']) ?>"
                                                data-rating="<?= $grade['rating'] ?>"
                                                data-anonymous="<?= $grade['is_anonymous'] ? '1' : '0' ?>">
                                            <i class="bi bi-chat-dots me-1"></i>View Comment
                                        </button>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No grades available yet. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Complaints Status Section -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-exclamation-circle me-2"></i>
                Your Complaints Status
            </h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#complaintModal">
                <i class="bi bi-plus-circle me-1"></i>New Complaint
            </button>
        </div>
        <div class="card-body">
            <?php
            // Fetch student's complaints
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.complaint_type,
                    c.title,
                    c.description,
                    c.status,
                    c.created_at,
                    c.updated_at,
                    c.is_anonymous,
                    CASE 
                        WHEN c.status = 'pending' THEN 'warning'
                        WHEN c.status = 'in_progress' THEN 'info'
                        WHEN c.status = 'resolved' THEN 'success'
                        WHEN c.status = 'rejected' THEN 'danger'
                        ELSE 'secondary'
                    END as status_color
                FROM complaints c
                WHERE c.student_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $complaints = $stmt->fetchAll();
            ?>

            <?php if (count($complaints) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= ucfirst(htmlspecialchars($complaint['complaint_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($complaint['is_anonymous']): ?>
                                                <i class="bi bi-incognito text-muted me-2" title="Anonymous Complaint"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($complaint['title']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $complaint['status_color'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $complaint['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($complaint['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($complaint['updated_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-complaint-btn" 
                                                data-complaint-id="<?= $complaint['id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewComplaintModal">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-complaint-btn" 
                                                data-complaint-id="<?= $complaint['id'] ?>"
                                                title="Delete Complaint">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No complaints submitted yet.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complaintModal">
                        <i class="bi bi-plus-circle me-1"></i>Submit Your First Complaint
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal fade" id="viewComplaintModal" tabindex="-1" aria-labelledby="viewComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);">
                    <h5 class="modal-title" id="viewComplaintModalLabel">
                        <i class="bi bi-exclamation-circle me-2"></i>Complaint Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="complaintDetails">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);">
                    <h5 class="modal-title" id="ratingModalLabel">
                        <i class="bi bi-star-fill me-2"></i>Rate Your Teacher
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ratingForm">
                        <input type="hidden" id="classId" name="class_id">
                        <input type="hidden" id="ratingValue" name="rating">
                        
                        <div class="rating-section mb-4">
                            <label class="form-label fw-bold">How would you rate your teacher?</label>
                            <div class="rating-stars d-flex justify-content-center gap-2" id="modalRatingStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star star" data-rating="<?= $i ?>" style="font-size: 2rem; cursor: pointer;"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted rating-text">Click on a star to rate</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="comment" class="form-label fw-bold">
                                <i class="bi bi-chat-dots me-1"></i>Share your experience
                            </label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" 
                                    placeholder="What did you like about the class? What could be improved?"></textarea>
                            <small class="text-muted">Your feedback helps improve teaching quality</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isAnonymous" name="is_anonymous">
                                <label class="form-check-label" for="isAnonymous">
                                    <i class="bi bi-incognito"></i> Submit Anonymously
                                </label>
                            </div>
                            <div class="mt-2 p-3 bg-light rounded" id="anonymousInfo" style="display: none;">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    <strong>Your identity will be protected</strong>
                                </div>
                                <ul class="mb-0 ps-3">
                                    <li>Your name will not be visible to the teacher</li>
                                    <li>Your rating and comment will be shown as anonymous</li>
                                    <li>You can still update or delete your rating later</li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitRating" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); border: none;">
                        <i class="bi bi-star me-1"></i>Submit Rating
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Replace the existing floating buttons with this new arrangement -->
    <div class="floating-buttons">
        <button class="btn btn-primary floating-btn complaint-btn" title="Submit Complaint" data-bs-toggle="modal" data-bs-target="#complaintModal" style="background: linear-gradient(135deg,rgb(236, 158, 4) 0%,rgb(236, 62, 4) 100%); border: none;">
            <i class="bi bi-exclamation-triangle-fill text-white"></i>
        </button>
        <button class="btn btn-danger floating-btn delete-account-btn" title="Delete Account" data-bs-toggle="modal" data-bs-target="#deleteAccountModal" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none;">
            <i class="bi bi-person-x-fill text-white"></i>
        </button>
    </div>

    <!-- Complaint Modal -->
    <div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);">
                    <h5 class="modal-title" id="complaintModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Submit a Complaint
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="complaintForm">
                        <div class="mb-4">
                            <label for="complaintType" class="form-label fw-bold">
                                <i class="bi bi-tag me-1"></i>Type of Complaint
                            </label>
                            <select class="form-select" id="complaintType" name="complaint_type" required>
                                <option value="">Select complaint type...</option>
                                <option value="grade">Grade Related</option>
                                <option value="teacher">Teacher Related</option>
                                <option value="class">Class Related</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="complaintTitle" class="form-label fw-bold">
                                <i class="bi bi-pencil me-1"></i>Title
                            </label>
                            <input type="text" class="form-control" id="complaintTitle" name="complaint_title" 
                                   placeholder="Brief description of your complaint" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="complaintDescription" class="form-label fw-bold">
                                <i class="bi bi-chat-text me-1"></i>Description
                            </label>
                            <textarea class="form-control" id="complaintDescription" name="complaint_description" 
                                    rows="4" placeholder="Please provide detailed information about your complaint..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="complaintAnonymous" name="is_anonymous">
                                <label class="form-check-label" for="complaintAnonymous">
                                    <i class="bi bi-incognito"></i> Submit Anonymously
                                </label>
                            </div>
                            <div class="mt-2 p-3 bg-light rounded" id="complaintAnonymousInfo" style="display: none;">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    <strong>Your identity will be protected</strong>
                                </div>
                                <ul class="mb-0 ps-3">
                                    <li>Your name will not be visible to the administration</li>
                                    <li>Your complaint will be handled confidentially</li>
                                    <li>You can still track the status of your complaint</li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitComplaint" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); border: none;">
                        <i class="bi bi-send me-1"></i>Submit Complaint
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Verification Modal -->
    <div class="modal fade" id="verifyGradeModal" tabindex="-1" aria-labelledby="verifyGradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);">
                    <h5 class="modal-title" id="verifyGradeModalLabel">
                        <i class="bi bi-shield-lock me-2"></i>Answer to reveal your grade
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="verificationQuestion" class="mb-4">
                        <div class="verification-icon text-center mb-4">
                            <i class="bi bi-shield-lock" style="font-size: 3rem; color: #4b6cb7;"></i>
                        </div>
                        <p class="fw-bold text-center mb-3">Please answer the following question to view your grade:</p>
                        <div id="questionText" class="p-4 bg-light rounded mb-4 text-center" style="border-left: 4px solid #4b6cb7;"></div>
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-pencil-square"></i>
                                </span>
                                <input type="text" class="form-control" id="answerInput" placeholder="Enter your answer">
                            </div>
                        </div>
                    </div>
                    <div id="verificationResult" class="alert" style="display: none;">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi" style="font-size: 1.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0" id="resultMessage"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitAnswer" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); border: none;">
                        <i class="bi bi-check-circle me-1"></i>Submit Answer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <h5 class="modal-title" id="deleteAccountModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
                    </div>
                    <form id="deleteAccountForm">
                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold">
                                <i class="bi bi-shield-lock me-1"></i>Enter your password to confirm
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-key-fill"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <small class="text-muted">Please enter your password to confirm account deletion</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteAccount" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none;">
                        <i class="bi bi-trash me-1"></i>Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
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

    const ratingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
    let currentClassId = null;
    let currentRating = 0;

    // Toggle anonymous info box
    document.getElementById('isAnonymous').addEventListener('change', function() {
        document.getElementById('anonymousInfo').style.display = this.checked ? 'block' : 'none';
    });

    // Handle rate button clicks
    document.querySelectorAll('.rate-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            currentClassId = this.dataset.classId;
            document.getElementById('classId').value = currentClassId;
            
            // Reset modal
            document.getElementById('ratingValue').value = '';
            document.getElementById('comment').value = '';
            document.getElementById('isAnonymous').checked = false;
            document.getElementById('anonymousInfo').style.display = 'none';
            document.querySelectorAll('#modalRatingStars .star').forEach(star => {
                star.className = 'bi bi-star star';
            });
            
            // Show modal
            ratingModal.show();
        });
    });

    // Update rating text based on selected rating
    function updateRatingText(rating) {
        const ratingTexts = {
            1: 'Poor - Needs significant improvement',
            2: 'Fair - Room for improvement',
            3: 'Good - Meets expectations',
            4: 'Very Good - Exceeds expectations',
            5: 'Excellent - Outstanding performance'
        };
        
        const ratingText = document.querySelector('.rating-text');
        ratingText.textContent = ratingTexts[rating] || 'Click on a star to rate';
        ratingText.className = 'text-muted rating-text ' + 
            (rating ? 'fw-bold text-primary' : '');
    }

    // Handle star clicks in the modal
    document.querySelectorAll('#modalRatingStars .star').forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            currentRating = rating;
            document.getElementById('ratingValue').value = rating;
            
            // Update all stars
            document.querySelectorAll('#modalRatingStars .star').forEach((s, index) => {
                s.className = `bi bi-star${index < rating ? '-fill' : ''} star`;
            });
            
            // Update rating text
            updateRatingText(rating);
        });
        
        // Add hover effect
        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.dataset.rating);
            document.querySelectorAll('#modalRatingStars .star').forEach((s, index) => {
                s.className = `bi bi-star${index < rating ? '-fill' : ''} star`;
            });
        });
        
        star.addEventListener('mouseout', function() {
            document.querySelectorAll('#modalRatingStars .star').forEach((s, index) => {
                s.className = `bi bi-star${index < currentRating ? '-fill' : ''} star`;
            });
        });
    });

    // Function to update rating display after submission
    function updateRatingDisplay(classId, rating, comment, isAnonymous) {
        // Find the card for this class
        const card = document.querySelector(`.grade-card[data-class-id="${classId}"]`);
        if (!card) return;

        // Update the rating stars
        const ratingInfo = card.querySelector('.rating-info');
        if (ratingInfo) {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += `<i class="bi bi-star${i <= rating ? '-fill' : ''} star" data-rating="${i}"></i>`;
        }
            ratingInfo.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span class="rating-label">Rating:</span>
            <div class="rating-stars" data-class-id="${classId}" data-rating="${rating}">
                ${starsHtml}
            </div>
                </div>
        `;
        }

        // Update the action buttons
        const actionsDiv = card.querySelector('.grade-card-actions');
        if (actionsDiv) {
            actionsDiv.innerHTML = `
                <button class="btn btn-sm btn-outline-primary rate-btn w-100 mb-2" 
                        data-class-id="${classId}"
                        data-bs-toggle="modal" 
                        data-bs-target="#ratingModal">
                    <i class="bi bi-star me-1"></i>Update Rating
                </button>
                ${comment ? `
                    <button class="btn btn-sm btn-outline-info view-comment-btn w-100" 
                            data-comment="${comment}"
                            data-rating="${rating}"
                            data-anonymous="${isAnonymous ? '1' : '0'}">
                        <i class="bi bi-chat-dots me-1"></i>View Comment
                    </button>
                ` : ''}
            `;

            // Reattach event listeners to the new buttons
            const rateBtn = actionsDiv.querySelector('.rate-btn');
            if (rateBtn) {
                rateBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentClassId = this.dataset.classId;
                    document.getElementById('classId').value = currentClassId;
                    
                    // Reset modal
                    document.getElementById('ratingValue').value = '';
                    document.getElementById('comment').value = '';
                    document.getElementById('isAnonymous').checked = false;
                    document.getElementById('anonymousInfo').style.display = 'none';
                    document.querySelectorAll('#modalRatingStars .star').forEach(star => {
                        star.className = 'bi bi-star star';
                    });
                    
                    // Show modal
                    ratingModal.show();
                });
            }

            const viewCommentBtn = actionsDiv.querySelector('.view-comment-btn');
            if (viewCommentBtn) {
                viewCommentBtn.addEventListener('click', function() {
                    const comment = this.dataset.comment;
                    const rating = this.dataset.rating;
                    const isAnonymous = this.dataset.anonymous === '1';
                    
                    // Create stars HTML
                    let starsHtml = '';
                    for (let i = 1; i <= 5; i++) {
                        starsHtml += `<i class="bi bi-star${i <= rating ? '-fill' : ''} text-warning"></i>`;
                    }
                    
                    Swal.fire({
                        title: 'Student Comment',
                        html: `
                            <div class="text-center mb-3">
                                <div class="mb-2">${starsHtml}</div>
                                <div class="text-muted small">
                                    ${isAnonymous ? '<i class="bi bi-incognito"></i> Anonymous Rating' : 'Student Rating'}
                                </div>
                            </div>
                            <div class="text-start p-3 bg-light rounded">
                                ${comment}
                            </div>
                        `,
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#3085d6',
                        customClass: {
                            container: 'comment-dialog'
                        }
                    });
                });
            }
        }
    }

    // Handle rating submission
    document.getElementById('submitRating').addEventListener('click', function() {
        if (!currentRating) {
            Swal.fire({
                icon: 'warning',
                title: 'Rating Required',
                text: 'Please select a rating before submitting.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const formData = new FormData(document.getElementById('ratingForm'));
        // Ensure is_anonymous is set as an integer (0 or 1)
        formData.set('is_anonymous', document.getElementById('isAnonymous').checked ? '1' : '0');
        
        // Show loading state
        Swal.fire({
            title: 'Submitting Rating',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('submit_rating.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                throw new Error('Server returned invalid JSON response');
            }
        })
        .then(data => {
            if (data.success) {
                // Update the rating display
                updateRatingDisplay(
                    currentClassId,
                    currentRating,
                    formData.get('comment'),
                    formData.get('is_anonymous') === '1'
                );
                
                // Hide the modal
                ratingModal.hide();
                
                // Show success message and reload page
                Swal.fire({
                    icon: 'success',
                    title: 'Rating Submitted!',
                    text: 'Your rating has been saved successfully.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    // Reload the page
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error submitting rating',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while submitting your rating. Please try again.',
                confirmButtonColor: '#dc3545'
            });
        });
    });

    // Handle view comment button clicks
    document.querySelectorAll('.view-comment-btn').forEach(button => {
        button.addEventListener('click', function() {
            const comment = this.dataset.comment;
            const rating = this.dataset.rating;
            const isAnonymous = this.dataset.anonymous === '1';
            
            // Create stars HTML
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                starsHtml += `<i class="bi bi-star${i <= rating ? '-fill' : ''} text-warning"></i>`;
            }
            
            Swal.fire({
                title: 'Student Comment',
                html: `
                    <div class="text-center mb-3">
                        <div class="mb-2">${starsHtml}</div>
                        <div class="text-muted small">
                            ${isAnonymous ? '<i class="bi bi-incognito"></i> Anonymous Rating' : 'Student Rating'}
                        </div>
                    </div>
                    <div class="text-start p-3 bg-light rounded">
                        ${comment}
                    </div>
                `,
                confirmButtonText: 'Close',
                confirmButtonColor: '#3085d6',
                customClass: {
                    container: 'comment-dialog'
                }
            });
        });
    });

    // Handle complaint anonymous checkbox
    document.getElementById('complaintAnonymous').addEventListener('change', function() {
        document.getElementById('complaintAnonymousInfo').style.display = this.checked ? 'block' : 'none';
    });

    // Handle complaint submission
    document.getElementById('submitComplaint').addEventListener('click', function() {
        const form = document.getElementById('complaintForm');
        if (!form.checkValidity()) {
            console.log('Form validation failed:', {
                complaintType: form.complaint_type.value,
                title: form.complaint_title.value,
                description: form.complaint_description.value,
                isAnonymous: form.is_anonymous.checked
            });
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        formData.append('is_anonymous', document.getElementById('complaintAnonymous').checked ? '1' : '0');

        // Log the form data being sent
        console.log('Submitting complaint with data:', {
            complaintType: formData.get('complaint_type'),
            title: formData.get('complaint_title'),
            description: formData.get('complaint_description'),
            isAnonymous: formData.get('is_anonymous')
        });

        // Show loading state
        Swal.fire({
            title: 'Submitting Complaint',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Send complaint to server
        fetch('submit_complaint.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            console.log('Server response status:', response.status);
            const text = await response.text();
            console.log('Raw server response:', text);
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON response:', e);
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            console.log('Parsed response data:', data);
            
            if (data.success) {
                console.log('Complaint submitted successfully');
                Swal.fire({
                    icon: 'success',
                    title: 'Complaint Submitted!',
                    text: 'Your complaint has been received and will be reviewed by the administration.',
                    confirmButtonColor: '#ffc107'
                }).then(() => {
                // Close the modal and reset the form
                const modal = bootstrap.Modal.getInstance(document.getElementById('complaintModal'));
                modal.hide();
                form.reset();
                document.getElementById('complaintAnonymousInfo').style.display = 'none';
                    
                    // Reload the page to show the new complaint
                    window.location.reload();
                });
            } else {
                console.error('Server returned error:', data.message);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'An error occurred while submitting your complaint.',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                timestamp: new Date().toISOString()
            });
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An unexpected error occurred. Please try again later.',
                confirmButtonColor: '#dc3545'
            });
        });
    });

    // Grade Verification System
    const verifyGradeModal = new bootstrap.Modal(document.getElementById('verifyGradeModal'));

    // Handle verify grade button clicks
    document.querySelectorAll('.verify-grade-btn').forEach(button => {
        button.addEventListener('click', function() {
            currentClassId = this.dataset.classId;
            loadVerificationQuestion();
        });
    });

    function loadVerificationQuestion() {
        fetch('verify_grade.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('questionText').textContent = data.question;
                    document.getElementById('answerInput').value = '';
                    document.getElementById('verificationResult').style.display = 'none';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load verification question',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while loading the question',
                    confirmButtonColor: '#dc3545'
                });
            });
    }

    // Handle answer submission
    document.getElementById('submitAnswer').addEventListener('click', function() {
        const answer = document.getElementById('answerInput').value.trim();
        if (!answer) {
            Swal.fire({
                icon: 'warning',
                title: 'Answer Required',
                text: 'Please enter your answer',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        fetch('verify_grade.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ answer: answer })
        })
        .then(response => response.json())
        .then(data => {
            const resultDiv = document.getElementById('verificationResult');
            const resultMessage = document.getElementById('resultMessage');
            resultDiv.style.display = 'block';
            
            if (data.success) {
                resultDiv.className = 'alert alert-success';
                resultDiv.querySelector('.bi').className = 'bi bi-check-circle-fill text-success';
                resultMessage.textContent = data.message;
                
                // Add success animation
                resultDiv.classList.add('animate__animated', 'animate__fadeIn');
                
                // Trigger confetti
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });

                // Add clapping animation
                const clapDiv = document.createElement('div');
                clapDiv.className = 'clap-animation';
                clapDiv.innerHTML = '👏';
                document.body.appendChild(clapDiv);

                // Remove clap animation after 3 seconds
                setTimeout(() => {
                    clapDiv.remove();
                }, 3000);

                // Close modal and show grades with appropriate message
                setTimeout(() => {
                    verifyGradeModal.hide();
                    
                    // Get all grades and their remarks
                    const grades = document.querySelectorAll('tr[data-class-id]');
                    let passedCount = 0;
                    let failedCount = 0;
                    let failedClasses = [];
                    
                    grades.forEach(grade => {
                        const remarks = grade.querySelector('td:nth-child(5) .badge').textContent.trim();
                        const className = grade.querySelector('td:nth-child(2)').textContent.trim();
                        if (remarks === 'Passed') {
                            passedCount++;
                        } else {
                            failedCount++;
                            failedClasses.push(className);
                        }
                    });

                    // Motivational quotes for failed grades
                    const motivationalQuotes = [
                        "Success is not final, failure is not fatal: it is the courage to continue that counts. - Winston Churchill",
                        "The only way to do great work is to love what you do. - Steve Jobs",
                        "Don't watch the clock; do what it does. Keep going. - Sam Levenson",
                        "Success is walking from failure to failure with no loss of enthusiasm. - Winston Churchill",
                        "The future belongs to those who believe in the beauty of their dreams. - Eleanor Roosevelt",
                        "Believe you can and you're halfway there. - Theodore Roosevelt",
                        "It does not matter how slowly you go as long as you do not stop. - Confucius",
                        "The only limit to our realization of tomorrow is our doubts of today. - Franklin D. Roosevelt",
                        "Everything you've ever wanted is on the other side of fear. - George Addair",
                        "The harder you work for something, the greater you'll feel when you achieve it. - Unknown"
                    ];

                    // Create and show the appropriate alert
                    const showAlert = () => {
                        if (failedCount > 0) {
                            // Show motivational message for failed grades
                            const randomQuote = motivationalQuotes[Math.floor(Math.random() * motivationalQuotes.length)];
                            return Swal.fire({
                                title: 'Keep Going! 💪',
                                html: `
                                    <div class="text-center">
                                        <div class="mb-4">
                                            <span style="font-size: 4rem;">🎓</span>
                                        </div>
                                        <p class="mb-3">You've passed ${passedCount} out of ${grades.length} classes!</p>
                                        <p class="mb-3">Classes that need attention:</p>
                                        <ul class="text-start mb-4">
                                            ${failedClasses.map(c => `<li>${c}</li>`).join('')}
                                        </ul>
                                        <div class="p-3 bg-light rounded">
                                            <i class="bi bi-quote me-2"></i>
                                            ${randomQuote}
                                        </div>
                                    </div>
                                `,
                                confirmButtonText: 'I Will Succeed! 💫',
                                confirmButtonColor: '#3085d6',
                                customClass: {
                                    container: 'grade-result-dialog'
                                }
                            });
                        } else {
                            // Show congratulatory message for all passed grades
                            return Swal.fire({
                                title: 'Congratulations! 🎉',
                                html: `
                                    <div class="text-center">
                                        <div class="mb-4">
                                            <span style="font-size: 4rem;">🎓</span>
                                            <span style="font-size: 4rem;">🌟</span>
                                            <span style="font-size: 4rem;">🏆</span>
                                        </div>
                                        <p class="mb-3">You've passed all ${passedCount} classes!</p>
                                        <p class="mb-3">Your hard work has paid off! Keep up the excellent work!</p>
                                    </div>
                                `,
                                confirmButtonText: 'Thank You! 🎓',
                                confirmButtonColor: '#28a745',
                                customClass: {
                                    container: 'grade-result-dialog'
                                }
                            });
                        }
                    };

                    // Show the alert and then reload the page
                    showAlert().then(() => {
                        location.reload();
                    });
                }, 1500);
            } else {
                resultDiv.className = 'alert alert-danger animate__animated animate__shakeX';
                resultDiv.querySelector('.bi').className = 'bi bi-x-circle-fill text-danger';
                resultMessage.textContent = data.message;
                document.getElementById('answerInput').value = '';

                // Show angry/disappointed emojis for incorrect answer
                const angryEmojis = ['😠', '😡', '😤', '🤬', '😒', '😞', '😣', '😫'];
                const randomEmoji = angryEmojis[Math.floor(Math.random() * angryEmojis.length)];
                createEmojiRain(randomEmoji, 15);

                // Add shake animation to the modal
                const modalContent = document.querySelector('#verifyGradeModal .modal-content');
                modalContent.classList.add('shake-animation');
                setTimeout(() => {
                    modalContent.classList.remove('shake-animation');
                }, 500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while verifying your answer',
                confirmButtonColor: '#dc3545'
            });
        });
    });

    // Reset verification state when modal is closed
    document.getElementById('verifyGradeModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('answerInput').value = '';
        document.getElementById('verificationResult').style.display = 'none';
    });

    // Function to create emoji rain with random positions and speeds
    function createEmojiRain(emoji, count = 30) {
        const container = document.createElement('div');
        container.className = 'emoji-rain';
        document.body.appendChild(container);

        for (let i = 0; i < count; i++) {
            const emojiElement = document.createElement('div');
            emojiElement.className = 'falling-emoji';
            emojiElement.innerHTML = emoji;
            emojiElement.style.left = Math.random() * 100 + 'vw';
            emojiElement.style.animationDuration = (Math.random() * 3 + 2) + 's';
            emojiElement.style.opacity = Math.random() * 0.5 + 0.5;
            emojiElement.style.transform = `rotate(${Math.random() * 360}deg)`;
            emojiElement.style.fontSize = (Math.random() * 1 + 1.5) + 'rem';
            container.appendChild(emojiElement);
        }

        // Remove the container after animation
        setTimeout(() => {
            container.remove();
        }, 5000);
    }

    // Function to check grade and show appropriate emojis
    function checkGradeAndShowEmojis() {
        const gradeElements = document.querySelectorAll('td:nth-child(3)');
        gradeElements.forEach(gradeElement => {
            const gradeText = gradeElement.textContent.trim();
            if (gradeText && gradeText !== 'Grade Hidden' && !gradeText.includes('Verify')) {
                const remarksElement = gradeElement.nextElementSibling;
                if (remarksElement && remarksElement.textContent.includes('Failed')) {
                    createEmojiRain('😢', 20);
                }
            }
        });
    }

    // Handle account deletion
    document.getElementById('confirmDeleteAccount').addEventListener('click', function() {
        const password = document.getElementById('password').value.trim();
        
        if (!password) {
            Swal.fire({
                icon: 'warning',
                title: 'Password Required',
                text: 'Please enter your password to confirm account deletion.',
                confirmButtonColor: '#dc3545'
            });
            return;
        }

        // Close the password modal first
        const deleteAccountModal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
        deleteAccountModal.hide();

        // Wait for modal to be fully hidden
        setTimeout(() => {
            // Show confirmation dialog
            Swal.fire({
                title: 'Are you absolutely sure?',
                text: 'This action cannot be undone. This will permanently delete your account and remove all your data from our servers.',
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Type DELETE to confirm',
                inputAttributes: {
                    autocapitalize: 'on',
                    autocorrect: 'off',
                    required: 'required'
                },
                showCancelButton: true,
                confirmButtonText: 'Yes, delete my account',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to type something!';
                    }
                    if (value.toUpperCase() !== 'DELETE') {
                        return 'Please type DELETE to confirm';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting Account',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send delete request
                    fetch('delete_account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ password: password })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Account Deleted',
                                text: 'Your account has been successfully deleted.',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                // Redirect to login page
                                window.location.href = '../login.php';
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to delete account. Please try again.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred. Please try again later.',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        }, 300); // Wait for 300ms to ensure modal is fully hidden
    });

    // Call the function after page load
    window.addEventListener('load', checkGradeAndShowEmojis);

    // Handle profile update
    document.getElementById('editProfileBtn').addEventListener('click', function() {
        Swal.fire({
            title: 'Update Profile',
            html: `
                <form id="updateProfileForm" class="text-start">
                    <div class="mb-3">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" value="<?= htmlspecialchars($student['first_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" value="<?= htmlspecialchars($student['last_name']) ?>" required>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Update Profile',
            confirmButtonColor: '#3085d6',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: () => {
                const firstName = document.getElementById('firstName').value.trim();
                const lastName = document.getElementById('lastName').value.trim();
                
                if (!firstName || !lastName) {
                    Swal.showValidationMessage('Please fill in all fields');
                    return false;
                }
                
                return { firstName, lastName };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Updating Profile',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send update request
                fetch('update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        first_name: result.value.firstName,
                        last_name: result.value.lastName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Profile Updated!',
                            text: 'Your profile has been successfully updated.',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Reload the page to show updated name
                            location.reload();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to update profile. Please try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again later.',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    });

    // Handle view complaint button clicks
    document.querySelectorAll('.view-complaint-btn').forEach(button => {
        button.addEventListener('click', function() {
            const complaintId = this.dataset.complaintId;
            
            // Show loading state
            document.getElementById('complaintDetails').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading complaint details...</p>
                </div>
            `;
            
            // Fetch complaint details
            fetch(`get_complaint_details.php?id=${complaintId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const complaint = data.complaint;
                        const statusColors = {
                            'pending': 'warning',
                            'in_progress': 'info',
                            'resolved': 'success',
                            'rejected': 'danger'
                        };
                        
                        document.getElementById('complaintDetails').innerHTML = `
                            <div class="complaint-details">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-tag-fill me-2"></i>Complaint Type
                                    </h6>
                                    <span class="badge bg-light text-dark p-2">
                                        <i class="bi bi-${getComplaintTypeIcon(complaint.complaint_type)} me-1"></i>
                                        ${complaint.complaint_type}
                                    </span>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-pencil-square me-2"></i>Title
                                    </h6>
                                    <p class="mb-0 p-2 bg-light rounded">
                                        ${complaint.title}
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-chat-text me-2"></i>Description
                                    </h6>
                                    <div class="p-3 bg-light rounded">
                                        ${complaint.description}
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-flag-fill me-2"></i>Status
                                    </h6>
                                    <span class="badge bg-${statusColors[complaint.status]} p-2">
                                        <i class="bi bi-${getStatusIcon(complaint.status)} me-1"></i>
                                        ${complaint.status.replace('_', ' ')}
                                    </span>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-calendar-plus me-2"></i>Submitted
                                    </h6>
                                    <p class="mb-0 p-2 bg-light rounded">
                                        <i class="bi bi-clock me-1"></i>
                                        ${new Date(complaint.created_at).toLocaleString()}
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-calendar-check me-2"></i>Last Updated
                                    </h6>
                                    <p class="mb-0 p-2 bg-light rounded">
                                        <i class="bi bi-clock-history me-1"></i>
                                        ${new Date(complaint.updated_at).toLocaleString()}
                                    </p>
                                </div>
                                
                                ${complaint.admin_response ? `
                                    <div class="mb-4">
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-person-badge me-2"></i>Admin Response
                                        </h6>
                                        <div class="p-3 bg-light rounded">
                                            <i class="bi bi-chat-quote me-2"></i>
                                            ${complaint.admin_response}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                ${complaint.is_anonymous ? `
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="bi bi-incognito me-2" style="font-size: 1.2rem;"></i>
                                        <div>
                                            <strong>Anonymous Complaint</strong>
                                            <p class="mb-0 small">Your identity is protected</p>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    } else {
                        document.getElementById('complaintDetails').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Failed to load complaint details. Please try again.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('complaintDetails').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            An error occurred while loading the complaint details.
                        </div>
                    `;
                });
        });
    });

    // Handle complaint deletion
    document.querySelectorAll('.delete-complaint-btn').forEach(button => {
        button.addEventListener('click', function() {
            const complaintId = this.dataset.complaintId;
            
            Swal.fire({
                title: 'Delete Complaint',
                text: 'Are you sure you want to delete this complaint? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting Complaint',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send delete request
                    fetch('delete_complaint.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ complaint_id: complaintId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'Your complaint has been deleted successfully.',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                // Reload the page to update the complaints list
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to delete complaint. Please try again.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred. Please try again later.',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        });
    });
});

// Add these helper functions before the closing script tag
function getComplaintTypeIcon(type) {
    const icons = {
        'grade': 'journal-text',
        'teacher': 'person-badge',
        'class': 'book',
        'other': 'question-circle'
    };
    return icons[type] || 'question-circle';
}

function getStatusIcon(status) {
    const icons = {
        'pending': 'hourglass-split',
        'in_progress': 'arrow-repeat',
        'resolved': 'check-circle',
        'rejected': 'x-circle'
    };
    return icons[status] || 'question-circle';
}
</script>
</body>
</html>

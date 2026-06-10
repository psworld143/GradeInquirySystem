<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if teacher_id is provided
if (!isset($_GET['teacher_id'])) {
    header("Location: dashboard.php");
    exit();
}

$teacher_id = (int)$_GET['teacher_id'];

// Fetch teacher information
try {
    $stmt = $pdo->prepare("
        SELECT full_name, email, created_at 
        FROM teachers 
        WHERE id = ? AND status = 'approved'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error fetching teacher information: " . $e->getMessage();
}

// Fetch teacher ratings
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as class_id,
            c.class_name,
            COALESCE(AVG(tr.rating), 0) as average_rating,
            COUNT(tr.id) as total_ratings,
            COALESCE(SUM(CASE WHEN tr.rating = 5 THEN 1 ELSE 0 END), 0) as five_star,
            COALESCE(SUM(CASE WHEN tr.rating = 4 THEN 1 ELSE 0 END), 0) as four_star,
            COALESCE(SUM(CASE WHEN tr.rating = 3 THEN 1 ELSE 0 END), 0) as three_star,
            COALESCE(SUM(CASE WHEN tr.rating = 2 THEN 1 ELSE 0 END), 0) as two_star,
            COALESCE(SUM(CASE WHEN tr.rating = 1 THEN 1 ELSE 0 END), 0) as one_star
        FROM classes c
        LEFT JOIN teacher_ratings tr ON c.id = tr.class_id
        WHERE c.teacher_id = ?
        GROUP BY c.id, c.class_name
    ");
    $stmt->execute([$teacher_id]);
    $class_ratings = $stmt->fetchAll();

    // Fetch recent ratings with comments
    $stmt = $pdo->prepare("
        SELECT 
            tr.rating,
            tr.comment,
            tr.created_at,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            c.class_name
        FROM teacher_ratings tr
        JOIN students s ON tr.student_id = s.id
        JOIN classes c ON tr.class_id = c.id
        WHERE c.teacher_id = ?
        ORDER BY tr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$teacher_id]);
    $recent_ratings = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching ratings: " . $e->getMessage();
    $class_ratings = [];
    $recent_ratings = [];
}

// Fetch classes and their students with statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as class_id,
            c.class_name,
            c.created_at as class_created_at,
            COUNT(DISTINCT g.id) as student_count,
            SUM(CASE WHEN g.remarks = 'Passed' THEN 1 ELSE 0 END) as passed_count,
            SUM(CASE WHEN g.remarks = 'Failed' THEN 1 ELSE 0 END) as failed_count,
            GROUP_CONCAT(
                DISTINCT CONCAT(g.firstname, ' ', g.lastname, ' (', g.final_grade, ' - ', g.remarks, ')')
                ORDER BY g.lastname, g.firstname
                SEPARATOR '|'
            ) as students
        FROM classes c
        LEFT JOIN grades g ON c.id = g.class_id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.class_name ASC
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching classes: " . $e->getMessage();
    $classes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Details - Grade Inquiry System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Poppins', sans-serif;
        }

        .container {
            padding: 2rem 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.25rem;
        }

        .teacher-info-card {
            background: linear-gradient(135deg, var(--secondary-color), #13855c);
        }

        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }

        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table thead th {
            background-color: var(--light-bg);
            border-bottom: 2px solid #e3e6f0;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .stats-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }
            
            .container {
                padding: 1rem;
            }
        }

        .rating-stars {
            color: #ffc107;
        }
        
        .rating-progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .rating-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .rating-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .rating-comment {
            font-style: italic;
            color: #495057;
        }

        .teacher-logo {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center">
                            <div class="teacher-logo me-3">
                                <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h2 class="mb-2">Teacher Details</h2>
                                <h4 class="mb-0 text-white-50"><?= htmlspecialchars($teacher['full_name']) ?></h4>
                            </div>
                        </div>
                        <div class="mt-3 mt-md-0">
                            <a href="dashboard.php" class="btn btn-light me-2">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                            <a href="../logout.php" class="btn btn-outline-light">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger rounded-3 shadow-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Teacher Information -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header teacher-info-card text-white">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Teacher Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <p class="mb-2"><i class="bi bi-envelope me-2 text-primary"></i><strong>Email:</strong></p>
                                <p class="text-muted"><?= htmlspecialchars($teacher['email']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><i class="bi bi-calendar-check me-2 text-primary"></i><strong>Registration Date:</strong></p>
                                <p class="text-muted"><?= date('M d, Y h:i A', strtotime($teacher['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ratings Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-star-fill me-2 text-warning"></i>Class Ratings</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($class_ratings)): ?>
                            <?php 
                            $total_classes = count($class_ratings);
                            $current_class = 0;
                            foreach ($class_ratings as $class): 
                                $current_class++;
                            ?>
                                <div class="mb-4">
                                    <h6 class="mb-3"><?= htmlspecialchars($class['class_name']) ?></h6>
                                    <div class="row">
                                        <div class="col-md-4 text-center mb-4 mb-md-0">
                                            <h2 class="display-4 mb-0"><?= number_format($class['average_rating'], 1) ?></h2>
                                            <div class="rating-stars mb-2">
                                                <?php
                                                $fullStars = floor($class['average_rating']);
                                                $halfStar = $class['average_rating'] - $fullStars >= 0.5;
                                                
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $fullStars) {
                                                        echo '<i class="bi bi-star-fill"></i>';
                                                    } elseif ($i == $fullStars + 1 && $halfStar) {
                                                        echo '<i class="bi bi-star-half"></i>';
                                                    } else {
                                                        echo '<i class="bi bi-star"></i>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <p class="text-muted"><?= $class['total_ratings'] ?> total ratings</p>
                                        </div>
                                        <div class="col-md-8">
                                            <?php
                                            $stars = [
                                                5 => ['count' => $class['five_star'], 'label' => '5 stars'],
                                                4 => ['count' => $class['four_star'], 'label' => '4 stars'],
                                                3 => ['count' => $class['three_star'], 'label' => '3 stars'],
                                                2 => ['count' => $class['two_star'], 'label' => '2 stars'],
                                                1 => ['count' => $class['one_star'], 'label' => '1 star']
                                            ];
                                            
                                            foreach ($stars as $rating => $data):
                                                $percentage = $class['total_ratings'] > 0 
                                                    ? ($data['count'] / $class['total_ratings']) * 100 
                                                    : 0;
                                            ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="me-2" style="width: 60px;"><?= $data['label'] ?></div>
                                                    <div class="flex-grow-1 me-2">
                                                        <div class="progress rating-progress">
                                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                                 style="width: <?= $percentage ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <div style="width: 40px;"><?= $data['count'] ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($current_class < $total_classes): ?>
                                    <hr class="my-4">
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (!empty($recent_ratings)): ?>
                                <hr class="my-4">
                                <h6 class="mb-3">Recent Reviews</h6>
                                <?php foreach ($recent_ratings as $rating): ?>
                                    <div class="card rating-card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?= $i <= $rating['rating'] ? '-fill' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="rating-date">
                                                    <?= date('M d, Y', strtotime($rating['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-1">
                                                <strong><?= htmlspecialchars($rating['student_name']) ?></strong>
                                                <span class="text-muted"> - <?= htmlspecialchars($rating['class_name']) ?></span>
                                            </p>
                                            <?php if ($rating['comment']): ?>
                                                <p class="rating-comment mb-0"><?= htmlspecialchars($rating['comment']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-star display-1 text-muted"></i>
                                <p class="text-muted mt-3">No ratings available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Classes and Students -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Classes and Students</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($classes)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x display-1 text-muted"></i>
                                <p class="text-muted mt-3">No classes created yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-white">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <h5 class="mb-2 mb-md-0">
                                                <i class="bi bi-book me-2 text-primary"></i>
                                                <?= htmlspecialchars($class['class_name']) ?>
                                            </h5>
                                            <div>
                                                <span class="badge bg-primary stats-badge">
                                                    <i class="bi bi-people me-1"></i><?= $class['student_count'] ?> Students
                                                </span>
                                                <span class="badge bg-success stats-badge">
                                                    <i class="bi bi-check-circle me-1"></i><?= $class['passed_count'] ?> Passed
                                                </span>
                                                <span class="badge bg-danger stats-badge">
                                                    <i class="bi bi-x-circle me-1"></i><?= $class['failed_count'] ?> Failed
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if ($class['students']): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="ps-4" style="width: 50px;">#</th>
                                                            <th>Student Name</th>
                                                            <th>Grade</th>
                                                            <th class="pe-4">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $students = explode('|', $class['students']);
                                                        $row_number = 1;
                                                        foreach ($students as $student): 
                                                            list($name, $grade_info) = explode(' (', $student);
                                                            list($grade, $status) = explode(' - ', $grade_info);
                                                            $status = rtrim($status, ')');
                                                        ?>
                                                            <tr>
                                                                <td class="ps-4"><?= $row_number++ ?></td>
                                                                <td><?= htmlspecialchars($name) ?></td>
                                                                <td>
                                                                    <span class="badge bg-info">
                                                                        <?= htmlspecialchars($grade) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="pe-4">
                                                                    <span class="badge <?= $status === 'Passed' ? 'bg-success' : 'bg-danger' ?>">
                                                                        <?= htmlspecialchars($status) ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-people display-4 text-muted"></i>
                                                <p class="text-muted mt-3">No students enrolled in this class.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
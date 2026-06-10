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

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : null;
$anonymous_filter = isset($_GET['anonymous']) ? $_GET['anonymous'] : null;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
        error_log("Database Error in view_ratings.php: " . $e->getMessage());
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

// Build the query with filters
$query = "
    SELECT 
        r.id,
        r.rating,
        r.comment,
        r.is_anonymous,
        r.created_at,
        s.first_name,
        s.last_name
    FROM teacher_ratings r
    LEFT JOIN students s ON r.student_id = s.id
    WHERE r.class_id = ?
";
$params = [$class_id];

if ($rating_filter) {
    $query .= " AND r.rating = ?";
    $params[] = $rating_filter;
}

if ($anonymous_filter !== null) {
    $query .= " AND r.is_anonymous = ?";
    $params[] = $anonymous_filter;
}

if ($search) {
    $query .= " AND (r.comment LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
switch ($sort_by) {
    case 'rating_high':
        $query .= " ORDER BY r.rating DESC, r.created_at DESC";
        break;
    case 'rating_low':
        $query .= " ORDER BY r.rating ASC, r.created_at DESC";
        break;
    case 'name':
        $query .= " ORDER BY s.last_name ASC, s.first_name ASC, r.created_at DESC";
        break;
    default: // date
        $query .= " ORDER BY r.created_at DESC";
}

// Fetch ratings for this class
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ratings = $stmt->fetchAll();

    // Calculate statistics
    $total_ratings = count($ratings);
    $average_rating = $total_ratings > 0 ? 
        array_sum(array_column($ratings, 'rating')) / $total_ratings : 0;
    $anonymous_ratings = count(array_filter($ratings, function($rating) { 
        return $rating['is_anonymous']; 
    }));

    // Calculate rating distribution
    $rating_distribution = array_fill(1, 5, 0);
    foreach ($ratings as $rating) {
        $rating_distribution[$rating['rating']]++;
    }

} catch (PDOException $e) {
    die("Error fetching ratings: " . $e->getMessage());
}

// Function to build query string with parameters
function buildQueryString($params) {
    $query = [];
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $query[] = $key . '=' . urlencode($value);
        }
    }
    return $query ? '?' . implode('&', $query) : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ratings - <?= htmlspecialchars($class['class_name']) ?></title>
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
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            width: 100%;
            text-align: center;
        }
        .stats-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .stats-card p {
            font-size: 1rem;
            margin-bottom: 0;
            color: #6c757d;
        }
        .filter-section {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        .rating-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .rating-bar-fill {
            height: 100%;
            background-color: #ffc107;
            border-radius: 4px;
        }
        .search-box {
            position: relative;
        }
        .search-box .form-control {
            padding-left: 2.5rem;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .active-filter {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        .ratings-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        .ratings-card .card-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 1.5rem;
        }
        .rating-item {
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
            transition: background-color 0.2s;
        }
        .rating-item:hover {
            background-color: #f8f9fa;
        }
        .rating-item:last-child {
            border-bottom: none;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .rating-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .anonymous-badge {
            background-color: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
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
        .row {
            margin-right: -0.75rem;
            margin-left: -0.75rem;
        }
        .col-md-3, .col-md-4, .col-md-2, .col-md-1 {
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
            }
            .stats-card {
                margin-bottom: 1rem;
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
            .stats-card h3 {
                font-size: 1.75rem;
            }
            .stats-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Class Ratings</h2>
                <h3 class="mb-0"><?= htmlspecialchars($class['class_name']) ?></h3>
            </div>
            <div class="d-none d-md-block">
                <i class="bi bi-star" style="font-size: 3rem;"></i>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-primary">
                    <i class="bi bi-star"></i>
                </div>
                <h3><?= number_format($average_rating, 1) ?></h3>
                <p class="text-muted">Average Rating</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-success">
                    <i class="bi bi-chat-square-text"></i>
                </div>
                <h3><?= $total_ratings ?></h3>
                <p class="text-muted">Total Ratings</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-info">
                    <i class="bi bi-incognito"></i>
                </div>
                <h3><?= $anonymous_ratings ?></h3>
                <p class="text-muted">Anonymous Ratings</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-warning">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h3><?= $total_ratings > 0 ? round(($anonymous_ratings / $total_ratings) * 100) : 0 ?>%</h3>
                <p class="text-muted">Anonymous Rate</p>
            </div>
        </div>
    </div>

    <!-- Rating Distribution -->
    <div class="filter-section mb-4">
        <h5 class="mb-3">Rating Distribution</h5>
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <div class="row align-items-center mb-2">
                <div class="col-1 text-end">
                    <span class="text-muted"><?= $i ?></span>
                </div>
                <div class="col-9">
                    <div class="rating-bar">
                        <div class="rating-bar-fill" style="width: <?= $total_ratings > 0 ? ($rating_distribution[$i] / $total_ratings) * 100 : 0 ?>%"></div>
                    </div>
                </div>
                <div class="col-2">
                    <span class="text-muted"><?= $rating_distribution[$i] ?></span>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Filters and Search -->
    <div class="filter-section mb-4">
        <form method="GET" class="row g-3">
            <input type="hidden" name="class_id" value="<?= $class_id ?>">
            
            <div class="col-md-4">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search comments or names..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="rating" class="form-select">
                    <option value="">All Ratings</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $rating_filter == $i ? 'selected' : '' ?>>
                            <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="anonymous" class="form-select">
                    <option value="">All Types</option>
                    <option value="1" <?= $anonymous_filter === '1' ? 'selected' : '' ?>>Anonymous</option>
                    <option value="0" <?= $anonymous_filter === '0' ? 'selected' : '' ?>>Named</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="sort" class="form-select">
                    <option value="date" <?= $sort_by === 'date' ? 'selected' : '' ?>>Newest First</option>
                    <option value="rating_high" <?= $sort_by === 'rating_high' ? 'selected' : '' ?>>Highest Rating</option>
                    <option value="rating_low" <?= $sort_by === 'rating_low' ? 'selected' : '' ?>>Lowest Rating</option>
                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>By Name</option>
                </select>
            </div>
            
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Ratings List -->
    <div class="ratings-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-chat-square-text me-2"></i>Student Ratings & Comments
            </h5>
            <?php if ($search || $rating_filter || $anonymous_filter !== null): ?>
                <a href="?class_id=<?= $class_id ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-x-lg me-1"></i>Clear Filters
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($ratings)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-chat-square-text text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">
                        <?php if ($search || $rating_filter || $anonymous_filter !== null): ?>
                            No ratings match your current filters.
                        <?php else: ?>
                            No ratings have been submitted for this class yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($ratings as $rating): ?>
                    <div class="rating-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="rating-stars mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?= $i <= $rating['rating'] ? '-fill' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($rating['is_anonymous']): ?>
                                    <span class="anonymous-badge">
                                        <i class="bi bi-incognito me-1"></i>Anonymous
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="bi bi-person me-1"></i>
                                        <?= htmlspecialchars($rating['first_name'] . ' ' . $rating['last_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="rating-date">
                                <i class="bi bi-calendar me-1"></i>
                                <?= date('M d, Y h:i A', strtotime($rating['created_at'])) ?>
                            </span>
                        </div>
                        <?php if ($rating['comment']): ?>
                            <div class="mt-3">
                                <p class="mb-0"><?= nl2br(htmlspecialchars($rating['comment'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <a href="dashboard.php" class="btn back-btn" title="Back to Grades">
        <i class="bi bi-arrow-left"></i>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];

// Get statistics
$classes_query = "SELECT COUNT(*) as count FROM students_classes WHERE student_id = ?";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$total_classes = $classes_result->fetch_assoc()['count'];

// Count available exams
$exams_query = "SELECT COUNT(DISTINCT e.exam_id) as count FROM exams e 
               JOIN classes c ON e.class_id = c.class_id
               JOIN students_classes sc ON c.class_id = sc.class_id
               WHERE sc.student_id = ? AND e.status = 'published'";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$exams_result = $stmt->get_result();
$available_exams = $exams_result->fetch_assoc()['count'];

// Count completed exams
$completed_query = "SELECT COUNT(*) as count FROM results WHERE student_id = ? AND submitted_at IS NOT NULL";
$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$completed_result = $stmt->get_result();
$completed_exams = $completed_result->fetch_assoc()['count'];

// Get recent results
$recent_results_query = "SELECT r.result_id, e.exam_name, r.percentage, r.status, r.submitted_at FROM results r
                        JOIN exams e ON r.exam_id = e.exam_id
                        WHERE r.student_id = ?
                        ORDER BY r.submitted_at DESC
                        LIMIT 5";
$stmt = $conn->prepare($recent_results_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Online Examination System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard">
            <h1>Student Dashboard</h1>
            
            <div class="statistics">
                <div class="stat-card">
                    <h3>My Classes</h3>
                    <p class="stat-number"><?php echo $total_classes; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Available Exams</h3>
                    <p class="stat-number"><?php echo $available_exams; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Completed Exams</h3>
                    <p class="stat-number"><?php echo $completed_exams; ?></p>
                </div>
            </div>

            <div class="admin-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="classes.php" class="btn btn-primary">View My Classes</a>
                    <a href="available_exams.php" class="btn btn-primary">Available Exams</a>
                    <a href="my_results.php" class="btn btn-primary">My Results</a>
                </div>
            </div>

            <div class="recent-section">
                <h2>Recent Results</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                            <td><?php echo number_format($result['percentage'], 2); ?>%</td>
                            <td>
                                <span class="result-badge result-<?php echo strtolower($result['status']); ?>">
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($result['submitted_at'])); ?></td>
                            <td>
                                <a href="view_result_detail.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-small">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .result-badge {
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
        }
        .result-pass {
            background-color: #4caf50;
        }
        .result-fail {
            background-color: #f44336;
        }
    </style>
</body>
</html>

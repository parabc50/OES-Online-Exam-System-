<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Get statistics
$classes_query = "SELECT COUNT(*) as count FROM classes WHERE teacher_id = ?";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$total_classes = $classes_result->fetch_assoc()['count'];

$exams_query = "SELECT COUNT(*) as count FROM exams WHERE teacher_id = ?";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$exams_result = $stmt->get_result();
$total_exams = $exams_result->fetch_assoc()['count'];

$published_exams_query = "SELECT COUNT(*) as count FROM exams WHERE teacher_id = ? AND status = 'published'";
$stmt = $conn->prepare($published_exams_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$published_result = $stmt->get_result();
$published_exams = $published_result->fetch_assoc()['count'];

// Get class performance data for teacher's classes
$performance_query = "SELECT 
                        c.class_id,
                        c.class_name,
                        COUNT(DISTINCT r.result_id) as total_attempts,
                        ROUND(AVG(r.percentage), 2) as avg_percentage,
                        COUNT(CASE WHEN r.status = 'pass' THEN 1 END) as passed_count,
                        COUNT(CASE WHEN r.status = 'fail' THEN 1 END) as failed_count
                    FROM classes c
                    LEFT JOIN exams e ON c.class_id = e.class_id AND e.teacher_id = ?
                    LEFT JOIN results r ON e.exam_id = r.exam_id
                    WHERE c.teacher_id = ?
                    GROUP BY c.class_id, c.class_name
                    ORDER BY avg_percentage DESC";
$stmt = $conn->prepare($performance_query);
$stmt->bind_param("ii", $teacher_id, $teacher_id);
$stmt->execute();
$performance_result = $stmt->get_result();
$class_performance = $performance_result->fetch_all(MYSQLI_ASSOC);

// Prepare data for performance chart
$class_names = [];
$class_avg_scores = [];
$class_attempt_counts = [];
foreach ($class_performance as $perf) {
    if ($perf['total_attempts'] > 0) {
        $class_names[] = $perf['class_name'];
        $class_avg_scores[] = $perf['avg_percentage'];
        $class_attempt_counts[] = $perf['total_attempts'];
    }
}

// Get recent exams
$recent_exams_query = "SELECT e.exam_id, e.exam_name, c.class_name, e.status FROM exams e JOIN classes c ON e.class_id = c.class_id WHERE e.teacher_id = ? ORDER BY e.created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_exams_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$recent_exams_result = $stmt->get_result();
$recent_exams = $recent_exams_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Online Examination System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard">
            <h1>Teacher Dashboard</h1>
            
            <div class="statistics">
                <div class="stat-card">
                    <h3>My Classes</h3>
                    <p class="stat-number"><?php echo $total_classes; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Exams</h3>
                    <p class="stat-number"><?php echo $total_exams; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Published Exams</h3>
                    <p class="stat-number"><?php echo $published_exams; ?></p>
                </div>
            </div>

            <div class="admin-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="manage_students.php" class="btn btn-primary">Manage Students</a>
                    <a href="classes.php" class="btn btn-primary">View Classes</a>
                    <a href="create_exam.php" class="btn btn-primary">Create Exam</a>
                    <a href="manage_exams.php" class="btn btn-primary">Manage Exams</a>
                    <a href="view_results.php" class="btn btn-primary">View Results</a>
                    <a href="proctor_reports.php" class="btn btn-primary">Proctoring Reports</a>
                </div>
            </div>

            <div class="recent-section">
                <h2>Recent Exams</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_exams as $exam): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                            <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                            <td><?php echo ucfirst($exam['status']); ?></td>
                            <td>
                                <a href="edit_exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn btn-small">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="performance-section">
                <h2>Class Performance Analysis</h2>
                <?php if (count($class_names) > 0): ?>
                <div class="chart-container">
                    <canvas id="classPerformanceChart"></canvas>
                </div>
                <div class="performance-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Student Attempts</th>
                                <th>Average Score</th>
                                <th>Passed / Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_performance as $perf): ?>
                            <?php if ($perf['total_attempts'] > 0): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($perf['class_name']); ?></strong></td>
                                <td><?php echo $perf['total_attempts']; ?></td>
                                <td>
                                    <strong><?php echo $perf['avg_percentage']; ?>%</strong>
                                    <div class="progress-bar" style="width: 100px; height: 20px; background: #ecf0f1; border-radius: 3px; overflow: hidden; display: inline-block; margin-left: 10px;">
                                        <div style="height: 100%; background: <?php echo $perf['avg_percentage'] >= 50 ? '#27ae60' : '#e74c3c'; ?>; width: <?php echo $perf['avg_percentage']; ?>%; transition: width 0.3s;"></div>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: #27ae60; font-weight: bold;"><?php echo $perf['passed_count']; ?> ✓</span>
                                    <span style="color: #e74c3c; font-weight: bold;"><?php echo $perf['failed_count']; ?> ✗</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #7f8c8d; padding: 20px;">No exam attempts yet. Students will appear here once they start taking exams.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Class Performance Chart
        var ctx = document.getElementById('classPerformanceChart');
        if (ctx && <?php echo count($class_names) > 0 ? 'true' : 'false'; ?>) {
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: <?php echo json_encode($class_names); ?>,
                    datasets: [{
                        label: 'Average Score (%)',
                        data: <?php echo json_encode($class_avg_scores); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderWidth: 2,
                        pointBackgroundColor: '#3498db',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Class Performance Comparison'
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>

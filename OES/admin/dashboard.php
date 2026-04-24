<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('admin');

// Get statistics
$total_users_query = "SELECT COUNT(*) as count FROM users";
$total_users_result = $conn->query($total_users_query);
$total_users = $total_users_result->fetch_assoc()['count'];

$total_teachers_query = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher'";
$total_teachers_result = $conn->query($total_teachers_query);
$total_teachers = $total_teachers_result->fetch_assoc()['count'];

$total_students_query = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
$total_students_result = $conn->query($total_students_query);
$total_students = $total_students_result->fetch_assoc()['count'];

$total_classes_query = "SELECT COUNT(*) as count FROM classes";
$total_classes_result = $conn->query($total_classes_query);
$total_classes = $total_classes_result->fetch_assoc()['count'];

$total_exams_query = "SELECT COUNT(*) as count FROM exams WHERE status = 'published'";
$total_exams_result = $conn->query($total_exams_query);
$total_exams = $total_exams_result->fetch_assoc()['count'];

// Get class performance data (average scores per class)
$performance_query = "SELECT 
                        c.class_id,
                        c.class_name,
                        COUNT(DISTINCT r.result_id) as total_attempts,
                        ROUND(AVG(r.percentage), 2) as avg_percentage,
                        COUNT(CASE WHEN r.status = 'pass' THEN 1 END) as passed_count
                    FROM classes c
                    LEFT JOIN exams e ON c.class_id = e.class_id
                    LEFT JOIN results r ON e.exam_id = r.exam_id
                    GROUP BY c.class_id, c.class_name
                    ORDER BY avg_percentage DESC
                    LIMIT 10";
$performance_result = $conn->query($performance_query);
$class_performance = $performance_result->fetch_all(MYSQLI_ASSOC);

// Prepare data for performance chart
$class_names = [];
$class_avg_scores = [];
foreach ($class_performance as $perf) {
    if ($perf['total_attempts'] > 0) {
        $class_names[] = $perf['class_name'];
        $class_avg_scores[] = $perf['avg_percentage'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Examination System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard">
            <h1>Admin Dashboard</h1>
            
            <div class="statistics">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p class="stat-number"><?php echo $total_users; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Teachers</h3>
                    <p class="stat-number"><?php echo $total_teachers; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Students</h3>
                    <p class="stat-number"><?php echo $total_students; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Classes</h3>
                    <p class="stat-number"><?php echo $total_classes; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Published Exams</h3>
                    <p class="stat-number"><?php echo $total_exams; ?></p>
                </div>
            </div>

            <div class="admin-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
                    <a href="manage_classes.php" class="btn btn-primary">Manage Classes</a>
                    <a href="manage_students_classes.php" class="btn btn-primary">Assign Students to Classes</a>
                </div>
            </div>

            <div class="performance-section">
                <h2>Class Performance Overview</h2>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
                <?php if (empty($class_performance)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 20px;">No exam data available yet.</p>
                <?php else: ?>
                <div class="performance-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Total Attempts</th>
                                <th>Average Score</th>
                                <th>Passed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_performance as $perf): ?>
                            <?php if ($perf['total_attempts'] > 0): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($perf['class_name']); ?></td>
                                <td><?php echo $perf['total_attempts']; ?></td>
                                <td>
                                    <strong><?php echo $perf['avg_percentage']; ?>%</strong>
                                    <div class="progress-bar" style="width: 100px; height: 20px; background: #ecf0f1; border-radius: 3px; overflow: hidden; display: inline-block; margin-left: 10px;">
                                        <div style="height: 100%; background: <?php echo $perf['avg_percentage'] >= 50 ? '#27ae60' : '#e74c3c'; ?>; width: <?php echo $perf['avg_percentage']; ?>%; transition: width 0.3s;"></div>
                                    </div>
                                </td>
                                <td><?php echo $perf['passed_count']; ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Performance Chart
        var ctx = document.getElementById('performanceChart');
        if (ctx && <?php echo count($class_names) > 0 ? 'true' : 'false'; ?>) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($class_names); ?>,
                    datasets: [{
                        label: 'Average Score (%)',
                        data: <?php echo json_encode($class_avg_scores); ?>,
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
                            '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#16a085'
                        ],
                        borderColor: [
                            '#2980b9', '#27ae60', '#c0392b', '#d68910', '#8e44ad',
                            '#16a085', '#2c3e50', '#d35400', '#7f8c8d', '#117a65'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Class Average Exam Scores'
                        }
                    },
                    scales: {
                        x: {
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
</body>
</html>

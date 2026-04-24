<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Fetch all classes and their results
$classes_query = "SELECT DISTINCT c.class_id, c.class_name FROM classes c WHERE c.teacher_id = ? ORDER BY c.class_name";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get selected class
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : (count($classes) > 0 ? $classes[0]['class_id'] : 0);

$class_results = [];
if ($selected_class > 0) {
    $results_query = "SELECT r.result_id, r.student_id, u.first_name, u.last_name, e.exam_name, r.total_marks_obtained, r.total_marks, r.percentage, r.status, r.submitted_at
                     FROM results r
                     JOIN students_classes sc ON r.student_id = sc.student_id AND r.class_id = sc.class_id
                     JOIN users u ON r.student_id = u.user_id
                     JOIN exams e ON r.exam_id = e.exam_id
                     WHERE r.class_id = ? AND e.teacher_id = ?
                     ORDER BY r.submitted_at DESC";
    $stmt = $conn->prepare($results_query);
    $stmt->bind_param("ii", $selected_class, $teacher_id);
    $stmt->execute();
    $class_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate statistics
$pass_count = 0;
$fail_count = 0;
$total_students = 0;
$avg_percentage = 0;

if (count($class_results) > 0) {
    $total_percentage = 0;
    foreach ($class_results as $result) {
        if ($result['status'] == 'pass') $pass_count++;
        else if ($result['status'] == 'fail') $fail_count++;
        $total_percentage += $result['percentage'];
    }
    $avg_percentage = count($class_results) > 0 ? round($total_percentage / count($class_results), 2) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Class Results</h1>

        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 10px;">
                <label>Select Class:</label>
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">Choose a class</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_class > 0): ?>
        <div class="statistics">
            <div class="stat-card">
                <h3>Average Score</h3>
                <p class="stat-number"><?php echo $avg_percentage; ?>%</p>
            </div>
            <div class="stat-card">
                <h3>Pass Count</h3>
                <p class="stat-number"><?php echo $pass_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Fail Count</h3>
                <p class="stat-number"><?php echo $fail_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Submissions</h3>
                <p class="stat-number"><?php echo count($class_results); ?></p>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Exam Name</th>
                    <th>Marks Obtained</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_results as $result): ?>
                <tr>
                    <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                    <td><?php echo number_format($result['total_marks_obtained'], 2) . ' / ' . $result['total_marks']; ?></td>
                    <td><?php echo number_format($result['percentage'], 2); ?>%</td>
                    <td>
                        <span class="result-badge result-<?php echo strtolower($result['status']); ?>">
                            <?php echo ucfirst($result['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($result['submitted_at'])); ?></td>
                    <td>
                        <a href="view_student_answer.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-small">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($class_results) == 0): ?>
        <p>No results available for the selected class yet.</p>
        <?php endif; ?>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Dashboard</a>
    </div>

    <style>
        .filter-section {
            margin-bottom: 20px;
        }
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
        .result-pending {
            background-color: #ff9800;
        }
    </style>
</body>
</html>

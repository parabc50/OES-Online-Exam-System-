<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];

// Fetch all results for this student
$results_query = "SELECT r.result_id, e.exam_name, c.class_name, r.percentage, r.status, r.submitted_at, r.total_marks_obtained, r.total_marks
                 FROM results r
                 JOIN exams e ON r.exam_id = e.exam_id
                 JOIN classes c ON r.class_id = c.class_id
                 WHERE r.student_id = ? AND r.submitted_at IS NOT NULL
                 ORDER BY r.submitted_at DESC";
$stmt = $conn->prepare($results_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>My Results</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Marks Obtained</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                <tr>
                    <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                    <td><?php echo htmlspecialchars($result['class_name']); ?></td>
                    <td><?php echo number_format($result['total_marks_obtained'], 2) . ' / ' . $result['total_marks']; ?></td>
                    <td><?php echo number_format($result['percentage'], 2); ?>%</td>
                    <td>
                        <span class="result-badge result-<?php echo strtolower($result['status']); ?>">
                            <?php echo ucfirst($result['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($result['submitted_at'])); ?></td>
                    <td>
                        <a href="view_result_detail.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-small">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($results) == 0): ?>
        <p>You haven't completed any exams yet. <a href="available_exams.php">Start an exam</a></p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Dashboard</a>
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

<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Verify exam belongs to teacher
$verify_query = "SELECT e.exam_id, e.exam_name FROM exams e WHERE e.exam_id = ? AND e.teacher_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $exam_id, $teacher_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header('Location: manage_exams.php');
    exit();
}

// Fetch exam results
$results_query = "SELECT r.result_id, r.student_id, u.first_name, u.last_name, r.total_marks_obtained, r.total_marks, r.percentage, r.status, r.submitted_at
                 FROM results r
                 JOIN users u ON r.student_id = u.user_id
                 WHERE r.exam_id = ?
                 ORDER BY r.submitted_at DESC";
$stmt = $conn->prepare($results_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Exam Results</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Marks Obtained</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                <tr>
                    <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
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

        <?php if (count($results) == 0): ?>
        <p>No submissions yet for this exam.</p>
        <?php endif; ?>

        <a href="manage_exams.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Exams</a>
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

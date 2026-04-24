<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Fetch teacher's exams with class info
$query = "SELECT e.exam_id, e.exam_name, e.status, e.total_marks, c.class_name, 
                 (SELECT COUNT(*) FROM questions WHERE exam_id = e.exam_id) as question_count,
                 (SELECT COUNT(*) FROM results WHERE exam_id = e.exam_id) as submission_count
          FROM exams e 
          JOIN classes c ON e.class_id = c.class_id 
          WHERE e.teacher_id = ? 
          ORDER BY e.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Manage Exams</h1>

        <div class="action-buttons" style="margin-bottom: 20px;">
            <a href="create_exam.php" class="btn btn-primary">Create New Exam</a>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Questions</th>
                    <th>Total Marks</th>
                    <th>Submissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                    <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $exam['status']; ?>">
                            <?php echo ucfirst($exam['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $exam['question_count']; ?></td>
                    <td><?php echo $exam['total_marks']; ?></td>
                    <td><?php echo $exam['submission_count']; ?></td>
                    <td>
                        <a href="edit_exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn btn-small">Edit</a>
                        <a href="add_questions.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-small btn-secondary">Questions</a>
                        <a href="view_exam_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-small">Results</a>
                        <a href="delete_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this exam?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($exams) == 0): ?>
        <p>No exams created yet. <a href="create_exam.php">Create one now</a></p>
        <?php endif; ?>
    </div>

    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            color: white;
        }
        .status-draft {
            background-color: #ff9800;
        }
        .status-published {
            background-color: #4caf50;
        }
        .status-completed {
            background-color: #2196f3;
        }
    </style>
</body>
</html>

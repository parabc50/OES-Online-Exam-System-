<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Verify student is in this class
$verify_query = "SELECT class_id FROM students_classes WHERE student_id = ? AND class_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $student_id, $class_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header('Location: classes.php');
    exit();
}

// Fetch class info and exams
$class_query = "SELECT c.class_id, c.class_name FROM classes c WHERE c.class_id = ?";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

$exams_query = "SELECT e.exam_id, e.exam_name, e.exam_duration, e.status, 
                       (SELECT COUNT(*) FROM results WHERE exam_id = e.exam_id AND student_id = ?) as attempt_count
                FROM exams e
                WHERE e.class_id = ? AND e.status = 'published'
                ORDER BY e.created_at DESC";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("ii", $student_id, $class_id);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Exams - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Exams in <?php echo htmlspecialchars($class['class_name']); ?></h1>

        <table class="table">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Duration</th>
                    <th>Attempts</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                    <td><?php echo $exam['exam_duration']; ?> min</td>
                    <td><?php echo $exam['attempt_count']; ?></td>
                    <td>
                        <a href="take_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-primary">Start Exam</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($exams) == 0): ?>
        <p>No exams available in this class yet.</p>
        <?php endif; ?>

        <a href="classes.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Classes</a>
    </div>
</body>
</html>

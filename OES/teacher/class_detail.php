<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify class belongs to teacher
$verify_query = "SELECT class_id, class_name FROM classes WHERE class_id = ? AND teacher_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $class_id, $teacher_id);
$stmt->execute();
$class_result = $stmt->get_result()->fetch_assoc();

if (!$class_result) {
    header('Location: classes.php');
    exit();
}

// Fetch class students
$students_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name FROM users u 
                  JOIN students_classes sc ON u.user_id = sc.student_id 
                  WHERE sc.class_id = ? 
                  ORDER BY u.first_name";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch class exams
$exams_query = "SELECT exam_id, exam_name, status FROM exams WHERE class_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Details - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Class: <?php echo htmlspecialchars($class_result['class_name']); ?></h1>

        <div class="class-stats">
            <div class="stat-card">
                <h3>Total Students</h3>
                <p class="stat-number"><?php echo count($students); ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Exams</h3>
                <p class="stat-number"><?php echo count($exams); ?></p>
            </div>
        </div>

        <div class="section">
            <h2>Students in Class</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Exams in Class</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Exam Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $exam): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                        <td><?php echo ucfirst($exam['status']); ?></td>
                        <td>
                            <a href="view_exam_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-small">View Results</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="classes.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Classes</a>
    </div>

    <style>
        .section {
            margin: 20px 0;
        }
    </style>
</body>
</html>

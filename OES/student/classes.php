<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];

// Fetch student's classes
$query = "SELECT c.class_id, c.class_name, c.class_code, c.description, c.semester, u.first_name, u.last_name
          FROM classes c
          JOIN students_classes sc ON c.class_id = sc.class_id
          JOIN users u ON c.teacher_id = u.user_id
          WHERE sc.student_id = ?
          ORDER BY c.class_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>My Classes</h1>

        <div class="classes-grid">
            <?php foreach ($classes as $class): ?>
            <div class="class-card">
                <h3><?php echo htmlspecialchars($class['class_name']); ?></h3>
                <p><strong>Code:</strong> <?php echo htmlspecialchars($class['class_code']); ?></p>
                <p><strong>Teacher:</strong> <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?></p>
                <p><strong>Semester:</strong> <?php echo htmlspecialchars($class['semester']); ?></p>
                <p><?php echo htmlspecialchars($class['description']); ?></p>
                <div class="card-actions">
                    <a href="class_exams.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-primary">View Exams</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($classes) == 0): ?>
        <p>You are not enrolled in any classes yet.</p>
        <?php endif; ?>
    </div>

    <style>
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .class-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            background: #f9f9f9;
        }
        .card-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
    </style>
</body>
</html>

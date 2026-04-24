<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Fetch teacher's classes
$query = "SELECT * FROM classes WHERE teacher_id = ? ORDER BY class_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher</title>
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
                <p><?php echo htmlspecialchars($class['description']); ?></p>
                <p><strong>Semester:</strong> <?php echo htmlspecialchars($class['semester']); ?></p>
                <div class="card-actions">
                    <a href="class_detail.php?id=<?php echo $class['class_id']; ?>" class="btn btn-primary">View Details</a>
                    <a href="create_exam.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-primary">Create Exam</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($classes) == 0): ?>
        <p>No classes assigned yet.</p>
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

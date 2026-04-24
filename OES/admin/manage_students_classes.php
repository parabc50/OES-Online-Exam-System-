<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('admin');

$message = '';
$error = '';

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $class_id = intval($_POST['class_id']);

    if ($student_id > 0 && $class_id > 0) {
        $check_query = "SELECT id FROM students_classes WHERE student_id = ? AND class_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $student_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Student already enrolled in this class!';
        } else {
            $insert_query = "INSERT INTO students_classes (student_id, class_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ii", $student_id, $class_id);
            if ($stmt->execute()) {
                $message = 'Student enrolled successfully!';
            } else {
                $error = 'Failed to enroll student!';
            }
        }
        $stmt->close();
    } else {
        $error = 'Please select both student and class!';
    }
}

// Handle removal
if (isset($_GET['remove'])) {
    $enrollment_id = intval($_GET['remove']);
    $delete_query = "DELETE FROM students_classes WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $enrollment_id);
    if ($stmt->execute()) {
        $message = 'Student removed from class!';
    } else {
        $error = 'Failed to remove student!';
    }
    $stmt->close();
}

// Fetch students
$students_query = "SELECT user_id, first_name, last_name FROM users WHERE role = 'student' ORDER BY first_name";
$students_result = $conn->query($students_query);
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Fetch classes
$classes_query = "SELECT class_id, class_name FROM classes ORDER BY class_name";
$classes_result = $conn->query($classes_query);
$classes = $classes_result->fetch_all(MYSQLI_ASSOC);

// Fetch enrollments with student and class info
$enrollments_query = "SELECT sc.id, sc.student_id, sc.class_id, u.first_name, u.last_name, c.class_name 
                     FROM students_classes sc 
                     JOIN users u ON sc.student_id = u.user_id 
                     JOIN classes c ON sc.class_id = c.class_id 
                     ORDER BY c.class_name, u.first_name";
$enrollments_result = $conn->query($enrollments_query);
$enrollments = $enrollments_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students to Classes - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Assign Students to Classes</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Enroll Student in Class</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Select Student:</label>
                    <select name="student_id" required>
                        <option value="">Choose a student</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['user_id']; ?>">
                            <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Class:</label>
                    <select name="class_id" required>
                        <option value="">Choose a class</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Enroll Student</button>
            </form>
        </div>

        <div class="table-section">
            <h2>Current Enrollments</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Class Name</th>
                        <th>Enrolled Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td><?php echo $enrollment['first_name'] . ' ' . $enrollment['last_name']; ?></td>
                        <td><?php echo htmlspecialchars($enrollment['class_name']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($enrollment['enrolled_at'] ?? 'now')); ?></td>
                        <td>
                            <a href="?remove=<?php echo $enrollment['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('admin');

$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $class_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $class_id);
    if ($stmt->execute()) {
        $message = 'Class deleted successfully!';
    } else {
        $error = 'Failed to delete class!';
    }
    $stmt->close();
}

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $class_code = $conn->real_escape_string($_POST['class_code']);
    $description = $conn->real_escape_string($_POST['description']);
    $teacher_id = intval($_POST['teacher_id']);
    $semester = $conn->real_escape_string($_POST['semester']);

    if (empty($class_name) || empty($class_code) || $teacher_id <= 0) {
        $error = 'All required fields must be filled!';
    } else {
        if ($class_id > 0) {
            // Update
            $update_query = "UPDATE classes SET class_name = ?, class_code = ?, description = ?, teacher_id = ?, semester = ? WHERE class_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssisi", $class_name, $class_code, $description, $teacher_id, $semester, $class_id);
            if ($stmt->execute()) {
                $message = 'Class updated successfully!';
            } else {
                $error = 'Failed to update class!';
            }
        } else {
            // Insert
            $insert_query = "INSERT INTO classes (class_name, class_code, description, teacher_id, semester) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssss", $class_name, $class_code, $description, $teacher_id, $semester);
            if ($stmt->execute()) {
                $message = 'Class added successfully!';
            } else {
                $error = 'Failed to add class!';
            }
        }
        $stmt->close();
    }
}

// Fetch classes with teacher info
$query = "SELECT c.*, u.first_name, u.last_name FROM classes c JOIN users u ON c.teacher_id = u.user_id ORDER BY c.created_at DESC";
$result = $conn->query($query);
$classes = $result->fetch_all(MYSQLI_ASSOC);

// Fetch teachers
$teachers_query = "SELECT user_id, first_name, last_name FROM users WHERE role = 'teacher'";
$teachers_result = $conn->query($teachers_query);
$teachers = $teachers_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Manage Classes</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add/Update Class</h2>
            <form method="POST">
                <input type="hidden" id="class_id" name="class_id" value="">
                
                <div class="form-group">
                    <label>Class Name:</label>
                    <input type="text" name="class_name" id="class_name" required>
                </div>

                <div class="form-group">
                    <label>Class Code:</label>
                    <input type="text" name="class_code" id="class_code" required>
                </div>

                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" id="description"></textarea>
                </div>

                <div class="form-group">
                    <label>Teacher:</label>
                    <select name="teacher_id" id="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['user_id']; ?>">
                            <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester:</label>
                    <input type="text" name="semester" id="semester" placeholder="e.g., Fall 2024">
                </div>

                <button type="submit" class="btn btn-primary">Save Class</button>
            </form>
        </div>

        <div class="table-section">
            <h2>Classes List</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Class Code</th>
                        <th>Teacher</th>
                        <th>Semester</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                        <td><?php echo htmlspecialchars($class['class_code']); ?></td>
                        <td><?php echo $class['first_name'] . ' ' . $class['last_name']; ?></td>
                        <td><?php echo htmlspecialchars($class['semester']); ?></td>
                        <td>
                            <button type="button" class="btn btn-small" onclick="editClass(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>', '<?php echo htmlspecialchars($class['class_code']); ?>', '<?php echo htmlspecialchars($class['description']); ?>', '<?php echo $class['teacher_id']; ?>', '<?php echo htmlspecialchars($class['semester']); ?>')">Edit</button>
                            <a href="?delete=<?php echo $class['class_id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editClass(id, name, code, description, teacherId, semester) {
            document.getElementById('class_id').value = id;
            document.getElementById('class_name').value = name;
            document.getElementById('class_code').value = code;
            document.getElementById('description').value = description;
            document.getElementById('teacher_id').value = teacherId;
            document.getElementById('semester').value = semester;
            document.querySelector('.form-section h2').textContent = 'Edit Class';
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>

<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

/**
 * Verification function to ensure a student belongs to the teacher's classes
 */
function isStudentAssignedToTeacher($conn, $teacher_id, $student_id) {
    $check_query = "SELECT 1 FROM students_classes sc 
                    JOIN classes c ON sc.class_id = c.class_id 
                    WHERE c.teacher_id = ? AND sc.student_id = ? LIMIT 1";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $teacher_id, $student_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'All required fields must be filled!';
    } else {
        // Security check: ensure teacher owns this student record
        if (isStudentAssignedToTeacher($conn, $teacher_id, $user_id)) {
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ? AND role = 'student'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
            if ($stmt->execute()) {
                $message = 'Student information updated successfully!';
            } else {
                $error = 'Failed to update student! Email might already be in use.';
            }
            $stmt->close();
        } else {
            $error = 'Access denied. You can only edit students assigned to your classes.';
        }
    }
}

// Fetch only students enrolled in this teacher's classes
$query = "SELECT DISTINCT u.* 
          FROM users u
          JOIN students_classes sc ON u.user_id = sc.student_id
          JOIN classes c ON sc.class_id = c.class_id
          WHERE c.teacher_id = ? AND u.role = 'student'
          ORDER BY u.first_name ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>My Students</h1>
        <p>You can view and edit information for students enrolled in your assigned classes.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Edit Form (Initially Hidden) -->
        <div id="edit-form-section" class="form-section" style="display: none; background: #fdfdfd; border: 1px solid #3498db;">
            <h2>Edit Student Information</h2>
            <form method="POST">
                <input type="hidden" id="user_id" name="user_id" value="">
                
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>

                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" id="phone">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Student</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="table-section">
            <h2>Enrolled Students</h2>
            <?php if (count($students) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                        <td>
                            <button type="button" class="btn btn-small" onclick="editStudent(<?php echo $student['user_id']; ?>, '<?php echo htmlspecialchars($student['first_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['last_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['phone'], ENT_QUOTES); ?>')">Edit Info</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="alert alert-info">No students are currently enrolled in your classes.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function editStudent(id, firstName, lastName, email, phone) {
            document.getElementById('user_id').value = id;
            document.getElementById('first_name').value = firstName;
            document.getElementById('last_name').value = lastName;
            document.getElementById('email').value = email;
            document.getElementById('phone').value = phone;
            
            document.getElementById('edit-form-section').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelEdit() {
            document.getElementById('edit-form-section').style.display = 'none';
        }
    </script>
</body>
</html>
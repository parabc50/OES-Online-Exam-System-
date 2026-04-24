<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_name = $conn->real_escape_string($_POST['exam_name']);
    $exam_description = $conn->real_escape_string($_POST['exam_description']);
    $class_id = intval($_POST['class_id']);
    $total_marks = intval($_POST['total_marks']);
    $exam_duration = intval($_POST['exam_duration']);
    $passing_percentage = floatval($_POST['passing_percentage']);
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $show_answers = isset($_POST['show_answers']) ? 1 : 0;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;

    if (empty($exam_name) || $class_id <= 0 || $total_marks <= 0 || $exam_duration <= 0) {
        $error = 'Please fill all required fields!';
    } else {
        $status = 'draft';
        $insert_query = "INSERT INTO exams (exam_name, exam_description, class_id, teacher_id, total_marks, exam_duration, passing_percentage, shuffle_questions, show_answers, start_date, end_date, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssiiiidiiiss", $exam_name, $exam_description, $class_id, $teacher_id, $total_marks, $exam_duration, $passing_percentage, $shuffle_questions, $show_answers, $start_date, $end_date, $status);

        if ($stmt->execute()) {
            $exam_id = $conn->insert_id;
            $_SESSION['new_exam_id'] = $exam_id;
            header('Location: add_questions.php?exam_id=' . $exam_id);
            exit();
        } else {
            $error = 'Failed to create exam!';
        }
        $stmt->close();
    }
}

// Fetch teacher's classes
$classes_query = "SELECT class_id, class_name FROM classes WHERE teacher_id = ? ORDER BY class_name";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$classes = $classes_result->fetch_all(MYSQLI_ASSOC);

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Create New Exam</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <form method="POST">
                <div class="form-group">
                    <label>Exam Name: <span class="required">*</span></label>
                    <input type="text" name="exam_name" required>
                </div>

                <div class="form-group">
                    <label>Exam Description:</label>
                    <textarea name="exam_description" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label>Select Class: <span class="required">*</span></label>
                    <select name="class_id" required>
                        <option value="">Choose a class</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Total Marks: <span class="required">*</span></label>
                        <input type="number" name="total_marks" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Exam Duration (minutes): <span class="required">*</span></label>
                        <input type="number" name="exam_duration" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Passing Percentage:</label>
                        <input type="number" name="passing_percentage" min="0" max="100" value="40" step="0.01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Exam Start Date & Time:</label>
                        <input type="datetime-local" name="start_date">
                        <small style="color: #666;">When students can begin taking the exam</small>
                    </div>

                    <div class="form-group" style="background-color: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">
                        <label><strong>Exam Deadline (End Date & Time): <span class="required">*</span></strong></label>
                        <input type="datetime-local" name="end_date" required>
                        <small style="color: #856404;"><strong>ℹ️ Students cannot submit after this time</strong></small>
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="shuffle_questions"> Shuffle Questions
                    </label>
                    <label>
                        <input type="checkbox" name="show_answers"> Show Answers After Exam
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Create Exam & Add Questions</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <style>
        .required { color: red; }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .checkbox-group label {
            display: block;
            margin: 10px 0;
        }
    </style>
</body>
</html>

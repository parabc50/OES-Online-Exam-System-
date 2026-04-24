<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($exam_id <= 0) {
    header('Location: manage_exams.php');
    exit();
}

// Verify exam belongs to teacher
$verify_query = "SELECT e.exam_id FROM exams e WHERE e.exam_id = ? AND e.teacher_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $exam_id, $teacher_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header('Location: manage_exams.php');
    exit();
}

// Fetch exam details
$exam_query = "SELECT * FROM exams WHERE exam_id = ?";
$stmt = $conn->prepare($exam_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

// Fetch questions
$questions_query = "SELECT * FROM questions WHERE exam_id = ? ORDER BY question_order";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch options for each question
$question_options = [];
foreach ($questions as $q) {
    $opt_query = "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order";
    $opt_stmt = $conn->prepare($opt_query);
    $opt_stmt->bind_param("i", $q['question_id']);
    $opt_stmt->execute();
    $question_options[$q['question_id']] = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch teacher's classes
$classes_query = "SELECT class_id, class_name FROM classes WHERE teacher_id = ? ORDER BY class_name";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$classes = $classes_result->fetch_all(MYSQLI_ASSOC);

$message = '';
$error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_name = $conn->real_escape_string($_POST['exam_name']);
    $exam_description = $conn->real_escape_string($_POST['exam_description']);
    $class_id = intval($_POST['class_id']);
    $total_marks = intval($_POST['total_marks']);
    $exam_duration = intval($_POST['exam_duration']);
    $passing_percentage = floatval($_POST['passing_percentage']);
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $show_answers = isset($_POST['show_answers']) ? 1 : 0;

    if (empty($exam_name) || $class_id <= 0) {
        $error = 'Please fill all required fields!';
    } else {
        // Ensure new total marks is not less than already assigned question marks
        $sum_query = "SELECT IFNULL(SUM(marks),0) as sum_marks FROM questions WHERE exam_id = ?";
        $sum_stmt = $conn->prepare($sum_query);
        $sum_stmt->bind_param("i", $exam_id);
        $sum_stmt->execute();
        $sum_res = $sum_stmt->get_result()->fetch_assoc();
        $current_assigned = intval($sum_res['sum_marks']);
        $sum_stmt->close();

        if ($total_marks < $current_assigned) {
            $error = 'Total marks cannot be less than already assigned question marks (' . $current_assigned . ').';
        } else {
        $update_query = "UPDATE exams SET exam_name = ?, exam_description = ?, class_id = ?, total_marks = ?, exam_duration = ?, passing_percentage = ?, shuffle_questions = ?, show_answers = ? WHERE exam_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssiiidiii", $exam_name, $exam_description, $class_id, $total_marks, $exam_duration, $passing_percentage, $shuffle_questions, $show_answers, $exam_id);

        if ($stmt->execute()) {
            $message = 'Exam updated successfully!';
            // Refresh exam data
            $exam_query = "SELECT * FROM exams WHERE exam_id = ?";
            $stmt = $conn->prepare($exam_query);
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $exam = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update exam!';
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Edit Exam: <?php echo htmlspecialchars($exam['exam_name']); ?></h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Exam Details</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Exam Name: <span class="required">*</span></label>
                    <input type="text" name="exam_name" value="<?php echo htmlspecialchars($exam['exam_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Exam Description:</label>
                    <textarea name="exam_description" rows="4"><?php echo htmlspecialchars($exam['exam_description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Select Class: <span class="required">*</span></label>
                    <select name="class_id" required>
                        <option value="">Choose a class</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" <?php echo $exam['class_id'] == $class['class_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Total Marks: <span class="required">*</span></label>
                        <input type="number" name="total_marks" value="<?php echo $exam['total_marks']; ?>" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Exam Duration (minutes): <span class="required">*</span></label>
                        <input type="number" name="exam_duration" value="<?php echo $exam['exam_duration']; ?>" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Passing Percentage:</label>
                        <input type="number" name="passing_percentage" value="<?php echo $exam['passing_percentage']; ?>" min="0" max="100" step="0.01">
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="shuffle_questions" <?php echo $exam['shuffle_questions'] ? 'checked' : ''; ?>> Shuffle Questions
                    </label>
                    <label>
                        <input type="checkbox" name="show_answers" <?php echo $exam['show_answers'] ? 'checked' : ''; ?>> Show Answers After Exam
                    </label>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Exam</button>
                    <a href="manage_exams.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="admin-actions">
            <h2>Manage Questions</h2>
            <div class="action-buttons">
                <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">Add/Edit Questions</a>
            </div>
        </div>

        <div class="questions-section">
            <h2>Questions (<?php echo count($questions); ?>)</h2>
            <?php if (count($questions) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Marks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $index => $question): ?>
                    <tr>
                        <td><?php echo ($index + 1) . '. ' . htmlspecialchars(substr($question['question_text'], 0, 50)); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                        <td><?php echo $question['marks']; ?></td>
                        <td>
                            <a href="delete_question.php?id=<?php echo $question['question_id']; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this question?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No questions added yet. <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>">Add questions</a></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .required { color: red; }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .checkbox-group label {
            display: block;
            margin: 10px 0;
        }
    </style>
</body>
</html>

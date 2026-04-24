<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    header('Location: manage_exams.php');
    exit();
}

// Verify exam belongs to this teacher
$verify_query = "SELECT exam_id FROM exams WHERE exam_id = ? AND teacher_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $exam_id, $teacher_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header('Location: manage_exams.php');
    exit();
}

// Fetch exam details (needed before handling POST so $exam is available)
$exam_query = "SELECT e.*, c.class_name FROM exams e JOIN classes c ON e.class_id = c.class_id WHERE e.exam_id = ?";
$stmt = $conn->prepare($exam_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

// Calculate currently assigned marks and remaining marks
$sum_query = "SELECT IFNULL(SUM(marks),0) as sum_marks FROM questions WHERE exam_id = ?";
$sum_stmt = $conn->prepare($sum_query);
$sum_stmt->bind_param("i", $exam_id);
$sum_stmt->execute();
$sum_res = $sum_stmt->get_result()->fetch_assoc();
$current_assigned = intval($sum_res['sum_marks']);
$sum_stmt->close();
$remaining_marks = intval($exam['total_marks']) - $current_assigned;

$message = '';
$error = '';

// Handle add question
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action == 'add_question') {
        $question_text = $conn->real_escape_string($_POST['question_text']);
        $question_type = $conn->real_escape_string($_POST['question_type']);
        $marks = intval($_POST['marks']);

        if (empty($question_text) || empty($question_type) || $marks <= 0) {
            $error = 'All question fields are required!';
        } else {
            // Ensure total assigned marks do not exceed exam total
            $sum_query = "SELECT IFNULL(SUM(marks),0) as sum_marks FROM questions WHERE exam_id = ?";
            $sum_stmt = $conn->prepare($sum_query);
            $sum_stmt->bind_param("i", $exam_id);
            $sum_stmt->execute();
            $sum_res = $sum_stmt->get_result()->fetch_assoc();
            $current_assigned = intval($sum_res['sum_marks']);
            $sum_stmt->close();

            // Robustly fetch exam total marks from DB (avoid relying on $exam in case it's undefined)
            $exam_total_marks = 0;
            $tm_stmt = $conn->prepare("SELECT total_marks FROM exams WHERE exam_id = ?");
            $tm_stmt->bind_param("i", $exam_id);
            $tm_stmt->execute();
            $tm_res = $tm_stmt->get_result()->fetch_assoc();
            if ($tm_res) {
                $exam_total_marks = intval($tm_res['total_marks']);
            }
            $tm_stmt->close();

            if (($current_assigned + $marks) > $exam_total_marks) {
                $error = 'Cannot add question: total question marks would exceed exam total (remaining: ' . ($exam_total_marks - $current_assigned) . ').';
            } else {
            $insert_query = "INSERT INTO questions (exam_id, question_text, question_type, marks) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("issi", $exam_id, $question_text, $question_type, $marks);

            if ($stmt->execute()) {
                $question_id = $conn->insert_id;
                
                // Add options if multiple choice or true/false
                if ($question_type != 'descriptive') {
                    if ($question_type == 'true_false') {
                        $options = ['True', 'False'];
                        $correct_answer = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : '';
                    } else {
                        $options = [
                            $_POST['option_1'],
                            $_POST['option_2'],
                            $_POST['option_3'],
                            $_POST['option_4']
                        ];
                        $correct_answer = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : '';
                    }

                    foreach ($options as $index => $option_text) {
                        if (!empty($option_text)) {
                            $is_correct = (($index + 1) == $correct_answer) ? 1 : 0;
                            $option_insert = "INSERT INTO question_options (question_id, option_text, option_order, is_correct) VALUES (?, ?, ?, ?)";
                            $opt_stmt = $conn->prepare($option_insert);
                            $opt_stmt->bind_param("isii", $question_id, $option_text, $index, $is_correct);
                            $opt_stmt->execute();
                            $opt_stmt->close();
                        }
                    }
                }
                
                $message = 'Question added successfully!';
                // Recalculate assigned/remaining marks for display
                $sum_stmt = $conn->prepare("SELECT IFNULL(SUM(marks),0) as sum_marks FROM questions WHERE exam_id = ?");
                $sum_stmt->bind_param("i", $exam_id);
                $sum_stmt->execute();
                $sum_res = $sum_stmt->get_result()->fetch_assoc();
                $current_assigned = intval($sum_res['sum_marks']);
                $sum_stmt->close();
                $remaining_marks = $exam_total_marks - $current_assigned;
            } else {
                $error = 'Failed to add question!';
            }
            $stmt->close();
            }
        }
    } elseif ($action == 'publish_exam') {
        // Check if exam has questions
        $check_questions = "SELECT COUNT(*) as count FROM questions WHERE exam_id = ?";
        $stmt = $conn->prepare($check_questions);
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $question_count = $result->fetch_assoc()['count'];

        if ($question_count == 0) {
            $error = 'Exam must have at least one question before publishing!';
        } else {
            // Ensure total question marks matches exam total before publishing
            $sum_query = "SELECT IFNULL(SUM(marks),0) as sum_marks FROM questions WHERE exam_id = ?";
            $sum_stmt = $conn->prepare($sum_query);
            $sum_stmt->bind_param("i", $exam_id);
            $sum_stmt->execute();
            $sum_res = $sum_stmt->get_result()->fetch_assoc();
            $current_assigned = intval($sum_res['sum_marks']);
            $sum_stmt->close();

            $exam_total_marks = intval($exam['total_marks']);

            if ($current_assigned != $exam_total_marks) {
                $error = 'Cannot publish exam: total marks assigned to questions (' . $current_assigned . ") must equal exam total (" . $exam_total_marks . ').';
            } else {
                $update_query = "UPDATE exams SET status = 'published' WHERE exam_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("i", $exam_id);
                if ($stmt->execute()) {
                    $message = 'Exam published successfully!';
                } else {
                    $error = 'Failed to publish exam!';
                }
            }
        }
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Add Questions to Exam: <?php echo htmlspecialchars($exam['exam_name']); ?></h1>
        <p><strong>Class:</strong> <?php echo htmlspecialchars($exam['class_name']); ?></p>
        <div style="margin:10px 0; padding:10px; background:#f1f1f1; border-radius:6px; display:flex; gap:20px;">
            <div><strong>Exam Total Marks:</strong> <?php echo intval($exam['total_marks']); ?></div>
            <div><strong>Assigned Marks:</strong> <?php echo $current_assigned; ?></div>
            <div><strong>Remaining Marks:</strong> <?php echo $remaining_marks; ?></div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add Question</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_question">

                <div class="form-group">
                    <label>Question Type: <span class="required">*</span></label>
                    <select name="question_type" id="question_type" onchange="updateQuestionForm()" required>
                        <option value="">Select Type</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="descriptive">Descriptive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Question Text: <span class="required">*</span></label>
                    <textarea name="question_text" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Marks: <span class="required">*</span></label>
                    <input type="number" name="marks" required min="1">
                </div>

                <div id="options-container" style="display: none;">
                    <h3>Options</h3>
                    <div id="multiple-choice-options">
                        <div class="form-group" id="opt-group-1">
                            <label>Option 1: <span class="required">*</span></label>
                            <input type="text" name="option_1" id="option_1">
                        </div>
                        <div class="form-group" id="opt-group-2">
                            <label>Option 2: <span class="required">*</span></label>
                            <input type="text" name="option_2" id="option_2">
                        </div>
                        <div class="form-group" id="opt-group-3">
                            <label>Option 3:</label>
                            <input type="text" name="option_3" id="option_3">
                        </div>
                        <div class="form-group" id="opt-group-4">
                            <label>Option 4:</label>
                            <input type="text" name="option_4" id="option_4">
                        </div>
                    </div>

                    <div class="form-group">
                        <label id="correct_answer_label">Correct Answer: <span class="required">*</span></label>
                        <select name="correct_answer" id="correct_answer">
                            <option value="">Select Correct Answer</option>
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3" id="correct_opt_3">Option 3</option>
                            <option value="4" id="correct_opt_4">Option 4</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Question</button>
            </form>
        </div>

        <div class="questions-section">
            <h2>Questions Added (<?php echo count($questions); ?>)</h2>
            <?php foreach ($questions as $index => $question): ?>
            <div class="question-item">
                <h3><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></p>
                <p><strong>Marks:</strong> <?php echo $question['marks']; ?></p>

                <?php if ($question['question_type'] != 'descriptive'): ?>
                <div class="options-list">
                    <strong>Options:</strong>
                    <ul>
                        <?php foreach ($question_options[$question['question_id']] as $option): ?>
                        <li>
                            <?php echo htmlspecialchars($option['option_text']); ?>
                            <?php if ($option['is_correct']): ?>
                            <span class="correct-indicator">(Correct)</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <a href="delete_question.php?id=<?php echo $question['question_id']; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this question?')">Delete</a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="action-buttons">
            <?php if ($exam['status'] == 'draft'): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="publish_exam">
                <button type="submit" class="btn btn-success">Publish Exam</button>
            </form>
            <?php endif; ?>
            <a href="manage_exams.php" class="btn btn-secondary">Back to Exams</a>
        </div>
    </div>

    <script>
        function updateQuestionForm() {
            var type = document.getElementById('question_type').value;
            var container = document.getElementById('options-container');
            var opt3 = document.getElementById('opt-group-3');
            var opt4 = document.getElementById('opt-group-4');
            var copt3 = document.getElementById('correct_opt_3');
            var copt4 = document.getElementById('correct_opt_4');
            
            if (type == 'descriptive') {
                container.style.display = 'none';
            } else {
                container.style.display = 'block';
                
                if (type == 'true_false') {
                    document.getElementById('option_1').value = 'True';
                    document.getElementById('option_2').value = 'False';
                    opt3.style.display = 'none';
                    opt4.style.display = 'none';
                    copt3.style.display = 'none';
                    copt4.style.display = 'none';
                } else {
                    opt3.style.display = 'block';
                    opt4.style.display = 'block';
                    copt3.style.display = 'block';
                    copt4.style.display = 'block';
                    // Clear values if they were True/False
                    if (document.getElementById('option_1').value == 'True') document.getElementById('option_1').value = '';
                    if (document.getElementById('option_2').value == 'False') document.getElementById('option_2').value = '';
                }
            }
        }
    </script>

    <style>
        .required { color: red; }
        .question-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .options-list ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .options-list li {
            margin: 5px 0;
        }
        .correct-indicator {
            color: green;
            font-weight: bold;
        }
        .action-buttons {
            margin-top: 20px;
        }
    </style>
</body>
</html>

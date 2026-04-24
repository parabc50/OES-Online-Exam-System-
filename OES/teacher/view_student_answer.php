<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;

// Fetch result and verify it belongs to teacher's exam
$result_query = "SELECT r.result_id, r.student_id, u.first_name, u.last_name, r.exam_id, e.exam_name, r.total_marks_obtained, r.total_marks, r.percentage, r.status, r.submitted_at
                FROM results r
                JOIN users u ON r.student_id = u.user_id
                JOIN exams e ON r.exam_id = e.exam_id
                WHERE r.result_id = ? AND e.teacher_id = ?";
$stmt = $conn->prepare($result_query);
$stmt->bind_param("ii", $result_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header('Location: manage_exams.php');
    exit();
}

// Fetch student answers
$answers_query = "SELECT sa.answer_id, sa.question_id, q.question_text, q.question_type, q.marks, q.question_id, sa.option_id, sa.descriptive_answer, sa.marks_obtained, qo.option_text
                 FROM student_answers sa
                 JOIN questions q ON sa.question_id = q.question_id
                 LEFT JOIN question_options qo ON sa.option_id = qo.option_id
                 WHERE sa.result_id = ?
                 ORDER BY q.question_order";
$stmt = $conn->prepare($answers_query);
$stmt->bind_param("i", $result_id);
$stmt->execute();
$answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch correct answers for each question
$correct_answers = [];
foreach ($answers as $answer) {
    if (!isset($correct_answers[$answer['question_id']])) {
        $opt_query = "SELECT option_text FROM question_options WHERE question_id = ? AND is_correct = 1";
        $opt_stmt = $conn->prepare($opt_query);
        $opt_stmt->bind_param("i", $answer['question_id']);
        $opt_stmt->execute();
        $opt_result = $opt_stmt->get_result();
        if ($opt_row = $opt_result->fetch_assoc()) {
            $correct_answers[$answer['question_id']] = $opt_row['option_text'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Answer Details - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Student Answer Details</h1>
        
        <div class="result-info">
            <h2><?php echo htmlspecialchars($result['exam_name']); ?></h2>
            <p><strong>Student:</strong> <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></p>
            <p><strong>Score:</strong> <?php echo number_format($result['total_marks_obtained'], 2); ?> / <?php echo $result['total_marks']; ?> (<?php echo number_format($result['percentage'], 2); ?>%)</p>
            <p><strong>Status:</strong> <span class="result-badge result-<?php echo strtolower($result['status']); ?>"><?php echo ucfirst($result['status']); ?></span></p>
            <p><strong>Submitted At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($result['submitted_at'])); ?></p>
        </div>

        <div class="answers-section">
            <h2>Answers</h2>
            <?php foreach ($answers as $index => $answer): ?>
            <div class="answer-item">
                <h3><?php echo ($index + 1) . '. ' . htmlspecialchars($answer['question_text']); ?></h3>
                <p><strong>Marks:</strong> <?php echo $answer['marks_obtained']; ?> / <?php echo $answer['marks']; ?></p>

                <?php if ($answer['question_type'] == 'multiple_choice' || $answer['question_type'] == 'true_false'): ?>
                <p><strong>Student Answer:</strong> <?php echo $answer['option_text'] ? htmlspecialchars($answer['option_text']) : 'Not answered'; ?></p>
                <p><strong>Correct Answer:</strong> <?php echo isset($correct_answers[$answer['question_id']]) ? htmlspecialchars($correct_answers[$answer['question_id']]) : 'N/A'; ?></p>
                <?php else: ?>
                <p><strong>Student Answer:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($answer['descriptive_answer'])); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="manage_exams.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Exams</a>
    </div>

    <style>
        .result-info {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .result-badge {
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
        }
        .result-pass {
            background-color: #4caf50;
        }
        .result-fail {
            background-color: #f44336;
        }
        .answer-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
    </style>
</body>
</html>

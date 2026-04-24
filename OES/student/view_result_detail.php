<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;

// Fetch result and verify it belongs to student
$result_query = "SELECT r.result_id, r.student_id, r.exam_id, r.percentage, r.status, r.total_marks_obtained, r.total_marks, r.submitted_at, e.exam_name, e.show_answers
                FROM results r
                JOIN exams e ON r.exam_id = e.exam_id
                WHERE r.result_id = ? AND r.student_id = ?";
$stmt = $conn->prepare($result_query);
$stmt->bind_param("ii", $result_id, $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header('Location: my_results.php');
    exit();
}

// Fetch student answers
$answers_query = "SELECT sa.answer_id, sa.question_id, q.question_text, q.question_type, q.marks, sa.option_id, sa.descriptive_answer, sa.marks_obtained, qo.option_text, qo.is_correct
                 FROM student_answers sa
                 JOIN questions q ON sa.question_id = q.question_id
                 LEFT JOIN question_options qo ON sa.option_id = qo.option_id
                 WHERE sa.result_id = ?
                 ORDER BY q.question_order";
$stmt = $conn->prepare($answers_query);
$stmt->bind_param("i", $result_id);
$stmt->execute();
$answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch correct answers if show_answers is enabled
$correct_answers = [];
if ($result['show_answers']) {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Details - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Exam Result: <?php echo htmlspecialchars($result['exam_name']); ?></h1>
        
        <div class="result-info">
            <div class="score-display">
                <h2 class="final-score"><?php echo number_format($result['percentage'], 2); ?>%</h2>
                <p class="score-details"><?php echo number_format($result['total_marks_obtained'], 2) . ' / ' . $result['total_marks']; ?> Marks</p>
                <p class="status-text">
                    <span class="result-badge result-<?php echo strtolower($result['status']); ?>">
                        <?php echo strtoupper($result['status']); ?>
                    </span>
                </p>
            </div>
            <p><strong>Submitted:</strong> <?php echo date('Y-m-d H:i:s', strtotime($result['submitted_at'])); ?></p>
        </div>

        <div class="answers-section">
            <h2>Detailed Answers</h2>
            <?php foreach ($answers as $index => $answer): ?>
            <div class="answer-item <?php echo ($answer['marks_obtained'] > 0) ? 'correct' : 'incorrect'; ?>">
                <h3>Q<?php echo ($index + 1); ?>) <?php echo htmlspecialchars($answer['question_text']); ?></h3>
                <p><strong>Marks:</strong> <?php echo $answer['marks_obtained']; ?> / <?php echo $answer['marks']; ?></p>

                <?php if ($answer['question_type'] == 'multiple_choice' || $answer['question_type'] == 'true_false'): ?>
                <p><strong>Your Answer:</strong> <?php echo $answer['option_text'] ? htmlspecialchars($answer['option_text']) : 'Not answered'; ?></p>
                <?php if ($result['show_answers'] && isset($correct_answers[$answer['question_id']])): ?>
                <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($correct_answers[$answer['question_id']]); ?></p>
                <?php endif; ?>
                <?php else: ?>
                <p><strong>Your Answer:</strong></p>
                <div class="descriptive-answer-display">
                    <?php echo nl2br(htmlspecialchars($answer['descriptive_answer'])); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="my_results.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Results</a>
    </div>

    <style>
        .result-info {
            border: 2px solid #ddd;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f5f5f5 0%, #fafafa 100%);
            text-align: center;
        }
        .score-display {
            margin-bottom: 15px;
        }
        .final-score {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        .score-details {
            font-size: 18px;
            color: #666;
        }
        .status-text {
            margin-top: 15px;
        }
        .result-badge {
            padding: 8px 16px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 16px;
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
            margin: 15px 0;
            border-radius: 5px;
            border-left: 5px solid #ddd;
        }
        .answer-item.correct {
            border-left-color: #4caf50;
            background-color: #f1f8f4;
        }
        .answer-item.incorrect {
            border-left-color: #f44336;
            background-color: #fef1f0;
        }
        .descriptive-answer-display {
            background: white;
            padding: 10px;
            border-radius: 3px;
            border-left: 3px solid #2196f3;
            margin: 10px 0;
        }
    </style>
</body>
</html>

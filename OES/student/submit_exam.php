<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;

if ($exam_id <= 0 || $result_id <= 0) {
    header('Location: available_exams.php');
    exit();
}

// Fetch exam details and questions
$exam_query = "SELECT * FROM exams WHERE exam_id = ?";
$stmt = $conn->prepare($exam_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

$questions_query = "SELECT * FROM questions WHERE exam_id = ?";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process answers and calculate marks
$total_marks_obtained = 0;

foreach ($questions as $question) {
    $question_id = $question['question_id'];
    $answer_value = isset($_POST['question_' . $question_id]) ? trim($_POST['question_' . $question_id]) : '';
    
    $marks_obtained = 0;
    $option_id = null;

    if (!empty($answer_value)) {
        if ($question['question_type'] == 'descriptive') {
            // For descriptive, teacher will manually grade
            $marks_obtained = 0;
        } else {
            // Check if answer is correct
            $option_id = intval($answer_value);
            $correct_query = "SELECT is_correct FROM question_options WHERE option_id = ?";
            $opt_stmt = $conn->prepare($correct_query);
            $opt_stmt->bind_param("i", $option_id);
            $opt_stmt->execute();
            $correct_result = $opt_stmt->get_result()->fetch_assoc();
            
            if ($correct_result && $correct_result['is_correct']) {
                $marks_obtained = $question['marks'];
            }
        }
    }

    // Insert student answer
    $insert_query = "INSERT INTO student_answers (result_id, question_id, option_id, descriptive_answer, marks_obtained) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    
    $desc_answer = ($question['question_type'] == 'descriptive') ? $answer_value : null;
    $stmt->bind_param("iiisi", $result_id, $question_id, $option_id, $desc_answer, $marks_obtained);
    $stmt->execute();

    $total_marks_obtained += $marks_obtained;
}

// Calculate percentage
$percentage = round(($total_marks_obtained / $exam['total_marks']) * 100, 2);

// Determine pass/fail
$status = $percentage >= $exam['passing_percentage'] ? 'pass' : 'fail';

// Update result record
$update_query = "UPDATE results SET total_marks_obtained = ?, percentage = ?, status = ?, submitted_at = NOW() WHERE result_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("ddsi", $total_marks_obtained, $percentage, $status, $result_id);
$stmt->execute();

// Redirect to results page
header('Location: view_result_detail.php?result_id=' . $result_id);
exit();
?>

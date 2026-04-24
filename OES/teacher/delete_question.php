<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$question_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($question_id > 0) {
    // Verify question belongs to teacher's exam
    $verify_query = "SELECT q.question_id FROM questions q JOIN exams e ON q.exam_id = e.exam_id WHERE q.question_id = ? AND e.teacher_id = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $question_id, $teacher_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $delete_query = "DELETE FROM questions WHERE question_id = ?";
        $del_stmt = $conn->prepare($delete_query);
        $del_stmt->bind_param("i", $question_id);
        $del_stmt->execute();
    }
}

if ($exam_id > 0) {
    header('Location: add_questions.php?exam_id=' . $exam_id);
} else {
    header('Location: manage_exams.php');
}
exit();
?>

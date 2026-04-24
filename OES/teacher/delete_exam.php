<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Verify and delete
if ($exam_id > 0) {
    $query = "DELETE FROM exams WHERE exam_id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $exam_id, $teacher_id);
    $stmt->execute();
}

header('Location: manage_exams.php');
exit();
?>

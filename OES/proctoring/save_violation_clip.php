<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Ensure table exists
$create_table = "CREATE TABLE IF NOT EXISTS violation_clips (
    clip_id INT PRIMARY KEY AUTO_INCREMENT,
    result_id INT NOT NULL,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_final INT DEFAULT 0,
    FOREIGN KEY (result_id) REFERENCES results(result_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE
)";
$conn->query($create_table);

// Accept video clip upload via POST (FormData: exam_id, result_id, student_id, violation file, final)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$final = isset($_POST['final']) && $_POST['final'] === '1' ? 1 : 0;

if ($exam_id <= 0 || $result_id <= 0 || $student_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

if (!isset($_FILES['violation']) || $_FILES['violation']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No video uploaded']);
    exit();
}

$uploadDir = __DIR__ . '/violation_clips';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'violation_' . $student_id . '_' . $exam_id . '_' . time() . '.mp4';
$filePath = $uploadDir . '/' . $filename;

if (move_uploaded_file($_FILES['violation']['tmp_name'], $filePath)) {
    // Record path in database
    $stmt = $conn->prepare("INSERT INTO violation_clips (result_id, student_id, exam_id, file_path, recorded_at, is_final) VALUES (?,?,?,?,NOW(),?)");
    if ($stmt) {
        $stmt->bind_param("iiiis", $result_id, $student_id, $exam_id, $filePath, $final);
        $stmt->execute();
    }
    echo json_encode(['status' => 'success', 'file' => $filename]);
    exit();
}

http_response_code(500);
echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
exit();

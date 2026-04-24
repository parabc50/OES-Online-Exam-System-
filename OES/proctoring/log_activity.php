<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Accept JSON POST with event_type, event_details, result_id, student_id, exam_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

$event = isset($input['event_type']) ? $input['event_type'] : '';
$details = isset($input['event_details']) ? $input['event_details'] : '';
$result_id = isset($input['result_id']) ? intval($input['result_id']) : 0;
$student_id = isset($input['student_id']) ? intval($input['student_id']) : 0;
$exam_id = isset($input['exam_id']) ? intval($input['exam_id']) : 0;

if (!$event || !$result_id || !$student_id || !$exam_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

// Get or create session
$session_query = "SELECT session_id FROM proctor_sessions WHERE result_id = ? LIMIT 1";
$sstmt = $conn->prepare($session_query);
$sstmt->bind_param("i", $result_id);
$sstmt->execute();
$sres = $sstmt->get_result()->fetch_assoc();

if ($sres) {
    $session_id = $sres['session_id'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $insert_session = "INSERT INTO proctor_sessions (result_id, student_id, exam_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $ins = $conn->prepare($insert_session);
    $ins->bind_param("iiiis", $result_id, $student_id, $exam_id, $ip, $ua);
    $ins->execute();
    $session_id = $conn->insert_id;
}

$insert_activity = "INSERT INTO proctor_activity (session_id, event_type, event_details) VALUES (?, ?, ?)";
$astmt = $conn->prepare($insert_activity);
$astmt->bind_param("iss", $session_id, $event, $details);
$astmt->execute();

echo json_encode(['success' => true]);
exit();

<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Accept snapshot upload via POST (FormData: exam_id, result_id, student_id, snapshot file)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

if ($exam_id <= 0 || $result_id <= 0 || $student_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

if (!isset($_FILES['snapshot']) || $_FILES['snapshot']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No snapshot uploaded']);
    exit();
}

// Read snapshot file into memory as binary
$snapshotData = file_get_contents($_FILES['snapshot']['tmp_name']);
if (!$snapshotData) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to read snapshot file']);
    exit();
}

// Get or create proctor session for this result
$session_query = "SELECT session_id FROM proctor_sessions WHERE result_id = ? LIMIT 1";
$sstmt = $conn->prepare($session_query);
$sstmt->bind_param("i", $result_id);
$sstmt->execute();
$sres = $sstmt->get_result()->fetch_assoc();

if ($sres) {
    $session_id = $sres['session_id'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $insert_session = "INSERT INTO proctor_sessions (result_id, student_id, exam_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $ins = $conn->prepare($insert_session);
    // types: result_id (i), student_id (i), exam_id (i), ip_address (s), user_agent (s)
    $ins->bind_param("iiiss", $result_id, $student_id, $exam_id, $ip, $ua);
    $ins->execute();
    $session_id = $conn->insert_id;
}

// Insert snapshot blob into DB
// Insert snapshot blob into DB
$insert_snap = "INSERT INTO proctor_snapshots (session_id, snapshot_data) VALUES (?, ?)";
$snapstmt = $conn->prepare($insert_snap);
if (!$snapstmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit();
}
// For binary data, bind as string type (s) rather than blob type (b)
// This handles the PNG data correctly without complex blob handling
if (!$snapstmt->bind_param("is", $session_id, $snapshotData)) {
    http_response_code(500);
    echo json_encode(['error' => 'Bind failed: ' . $snapstmt->error]);
    exit();
}
if (!$snapstmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $snapstmt->error]);
    exit();
}
echo json_encode(['success' => true, 'snapshot_id' => $conn->insert_id]);
exit();

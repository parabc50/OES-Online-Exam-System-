<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];

// Fetch available exams for student
$query = "SELECT DISTINCT e.exam_id, e.exam_name, e.exam_duration, e.total_marks, e.status, e.start_date, e.end_date,
                 c.class_name, 
                 (SELECT COUNT(*) FROM results WHERE exam_id = e.exam_id AND student_id = ?) as attempt_count
          FROM exams e
          JOIN classes c ON e.class_id = c.class_id
          JOIN students_classes sc ON c.class_id = sc.class_id
          WHERE sc.student_id = ? AND e.status = 'published'
          ORDER BY e.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Exams - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- removed meta refresh; page will only reload automatically when an exam actually opens -->
    <script>
        // display a clock based on the server time provided when page loaded
        var serverOffset = 0; // difference between server and client ms
        function updateServerTime() {
            var el = document.getElementById('server-time');
            if (!el) return;
            var clientNow = new Date();
            var serverNow = new Date(clientNow.getTime() + serverOffset);
            el.textContent = serverNow.toLocaleString();
        }
        setInterval(updateServerTime, 1000);

        // update 'Coming Soon' rows with countdown; reload once when exam opens
        function refreshActions() {
            var now = Date.now();
            var needsReload = false;
            document.querySelectorAll('tr[data-status="coming-soon"]').forEach(function(row) {
                var start = parseInt(row.getAttribute('data-start'), 10);
                var actionCell = row.querySelector('.action-cell');
                if (now >= start) {
                    needsReload = true;
                } else if (actionCell) {
                    var diff = start - now;
                    var secs = Math.floor(diff/1000);
                    var mins = Math.floor(secs/60);
                    var hours = Math.floor(mins/60);
                    mins = mins % 60;
                    secs = secs % 60;
                    var timeStr = (hours > 0 ? hours + 'h ' : '') + mins + 'm ' + (secs < 10 ? '0' : '') + secs + 's';
                    actionCell.innerHTML = '<button class="btn btn-secondary" disabled>Starts in ' + timeStr + '</button>';
                }
            });
            if (needsReload) {
                location.reload();
            }
        }
        setInterval(refreshActions, 1000);

        window.addEventListener('load', function() {
            updateServerTime();
            refreshActions();
        });
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Available Exams</h1>
        <p>Server time: <span id="server-time"></span></p>
        <table class="table">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Duration</th>
                    <th>Total Marks</th>
                    <th>Start Time</th>
                    <th>Attempts</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                <?php 
                    $current_time = new DateTime();
                    $start_time = new DateTime($exam['start_date']);
                    $end_time = new DateTime($exam['end_date']);
                    $can_start = $current_time >= $start_time && $current_time <= $end_time;
                    $exam_started = $current_time >= $start_time;
                    $is_future = $current_time < $start_time;
                    // timestamp for client side countdown
                    $start_ts = $start_time->getTimestamp() * 1000;
                    $status_attr = $is_future ? 'coming-soon' : 'other';
                ?>
                <tr data-start="<?php echo $start_ts; ?>" data-status="<?php echo $status_attr; ?>">
                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                    <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                    <td><?php echo $exam['exam_duration']; ?> min</td>
                    <td><?php echo $exam['total_marks']; ?></td>
                    <td><?php echo $start_time->format('Y-m-d H:i'); ?></td>
                    <td><?php echo $exam['attempt_count']; ?></td>
                    <td class="action-cell">
                        <?php if ($exam['attempt_count'] > 0): ?>
                            <a href="view_result_detail.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-info">View Results</a>
                        <?php elseif ($can_start): ?>
                            <a href="take_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-primary start-btn" data-exam-id="<?php echo $exam['exam_id']; ?>">Start Exam</a>
                        <?php elseif ($exam_started): ?>
                            <button class="btn btn-secondary" disabled>Exam Closed</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Coming Soon</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($exams) == 0): ?>
        <p>No exams available. Please check back later.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 20px;">Back to Dashboard</a>
    </div>
</body>
</html>

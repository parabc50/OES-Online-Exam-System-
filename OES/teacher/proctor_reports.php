<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'exams'; // exams, students, logs, snapshots, violations

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proctoring Reports - Teacher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .proctor-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .nav-tabs { display: flex; gap: 10px; margin: 20px 0; border-bottom: 2px solid #ddd; }
        .nav-tabs a { padding: 10px 15px; border: none; background: #f1f1f1; cursor: pointer; text-decoration: none; color: #333; }
        .nav-tabs a.active { background: #3498db; color: white; }
        .snapshot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .snapshot-card { border: 1px solid #ddd; padding: 10px; border-radius: 6px; text-align: center; background: #f9f9f9; }
        .snapshot-card img, .snapshot-card video { max-width: 100%; border-radius: 4px; }
        .snapshot-card p { font-size: 12px; color: #666; margin: 5px 0 0 0; }
        .activity-log { border: 1px solid #ddd; max-height: 400px; overflow-y: auto; padding: 10px; border-radius: 6px; }
        .activity-entry { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; }
        .activity-entry .timestamp { font-weight: bold; color: #3498db; }
        .activity-entry .type { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .violation-badge { background: #e74c3c; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2c3e50; color: white; }
        tr:hover { background: #f5f5f5; }
        a { color: #3498db; cursor: pointer; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="proctor-container">
        <h1>Proctoring Reports & Audit</h1>

        <?php if ($view === 'exams' || $exam_id === 0): ?>
        <!-- View: List of exams with proctoring data -->
        <h2>Exams Conducted</h2>
        <table>
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Students Examined</th>
                    <th>Proctoring Sessions</th>
                    <th>Violations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $exams_query = "SELECT e.exam_id, e.exam_name, c.class_name, 
                                COUNT(DISTINCT r.student_id) as student_count,
                                COUNT(DISTINCT ps.session_id) as session_count,
                                (SELECT COUNT(*) FROM violation_clips WHERE exam_id = e.exam_id) as violation_count
                                FROM exams e
                                JOIN classes c ON e.class_id = c.class_id
                                LEFT JOIN results r ON e.exam_id = r.exam_id
                                LEFT JOIN proctor_sessions ps ON r.result_id = ps.result_id
                                WHERE e.teacher_id = ?
                                GROUP BY e.exam_id
                                ORDER BY e.created_at DESC";
                $stmt = $conn->prepare($exams_query);
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($exams as $exam):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                    <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                    <td><?php echo $exam['student_count']; ?></td>
                    <td><?php echo $exam['session_count']; ?></td>
                    <td>
                        <?php if ($exam['violation_count'] > 0): ?>
                            <span class="violation-badge"><?php echo $exam['violation_count']; ?> Clips</span>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td><a href="?view=students&exam_id=<?php echo $exam['exam_id']; ?>">View Details</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($view === 'students' && $exam_id > 0): ?>
        <!-- View: Students who took a specific exam -->
        <h2>Students & Proctoring Data for Exam</h2>
        <a href="?view=exams">← Back to Exams</a>

        <?php
        $exam_query = "SELECT exam_name FROM exams WHERE exam_id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($exam_query);
        $stmt->bind_param("ii", $exam_id, $teacher_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        if (!$exam) {
            echo "<p>Exam not found.</p>";
        } else {
            echo "<h3>" . htmlspecialchars($exam['exam_name']) . "</h3>";
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Snapshots</th>
                    <th>Activity Events</th>
                    <th>Violations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $students_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, 
                                   r.result_id, ps.session_id, COUNT(DISTINCT psn.snapshot_id) as snapshot_count,
                                   COUNT(DISTINCT pa.activity_id) as activity_count,
                                   (SELECT COUNT(*) FROM violation_clips WHERE result_id = r.result_id) as violation_count,
                                   TIMEDIFF(COALESCE(ps.ended_at, NOW()), ps.started_at) as duration
                                   FROM exams e
                                   JOIN results r ON e.exam_id = r.exam_id
                                   JOIN users u ON r.student_id = u.user_id
                                   LEFT JOIN proctor_sessions ps ON r.result_id = ps.result_id
                                   LEFT JOIN proctor_snapshots psn ON ps.session_id = psn.snapshot_id -- fix: was snapshot_id
                                   LEFT JOIN proctor_snapshots psn2 ON ps.session_id = psn2.session_id -- fix alias
                                   LEFT JOIN proctor_activity pa ON ps.session_id = pa.session_id
                                   WHERE e.exam_id = ? AND e.teacher_id = ?
                                   GROUP BY u.user_id, r.result_id
                                   ORDER BY u.first_name ASC";
                
                // Let's rewrite for cleaner join and correct count
                $students_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, r.result_id,
                                   (SELECT COUNT(*) FROM proctor_snapshots psn JOIN proctor_sessions ps ON psn.session_id = ps.session_id WHERE ps.result_id = r.result_id) as snapshot_count,
                                   (SELECT COUNT(*) FROM proctor_activity pa JOIN proctor_sessions ps ON pa.session_id = ps.session_id WHERE ps.result_id = r.result_id) as activity_count,
                                   (SELECT COUNT(*) FROM violation_clips WHERE result_id = r.result_id) as violation_count
                                   FROM exams e
                                   JOIN results r ON e.exam_id = r.exam_id
                                   JOIN users u ON r.student_id = u.user_id
                                   WHERE e.exam_id = ? AND e.teacher_id = ?
                                   ORDER BY u.first_name ASC";
                
                $stmt = $conn->prepare($students_query);
                $stmt->bind_param("ii", $exam_id, $teacher_id);
                $stmt->execute();
                $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($students as $student):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td><?php echo $student['snapshot_count']; ?></td>
                    <td><?php echo $student['activity_count']; ?></td>
                    <td>
                        <?php if ($student['violation_count'] > 0): ?>
                            <span class="violation-badge"><?php echo $student['violation_count']; ?> Clips</span>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?view=snapshots&exam_id=<?php echo $exam_id; ?>&student_id=<?php echo $student['user_id']; ?>">Snapshots</a> | 
                        <a href="?view=logs&exam_id=<?php echo $exam_id; ?>&student_id=<?php echo $student['user_id']; ?>">Logs</a> |
                        <a href="?view=violations&exam_id=<?php echo $exam_id; ?>&student_id=<?php echo $student['user_id']; ?>">Violations</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($view === 'violations' && $exam_id > 0 && $student_id > 0): ?>
        <!-- View: Violation video clips for a student -->
        <h2>Violation Video Clips</h2>
        <a href="?view=students&exam_id=<?php echo $exam_id; ?>">← Back to Students</a>

        <?php
        $student_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($student_query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        echo "<h3>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</h3>";

        $clips_query = "SELECT * FROM violation_clips WHERE exam_id = ? AND student_id = ? ORDER BY recorded_at DESC";
        $stmt = $conn->prepare($clips_query);
        $stmt->bind_param("ii", $exam_id, $student_id);
        $stmt->execute();
        $clips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($clips) === 0) {
            echo "<p>No violation clips available for this student.</p>";
        } else {
            echo "<div class='snapshot-grid'>";
            foreach ($clips as $clip):
                // The file path is relative to proctoring/
                $relative_path = '../proctoring/violation_clips/' . basename($clip['file_path']);
            ?>
            <div class="snapshot-card" style="width: 320px;">
                <video controls style="width: 100%;">
                    <source src="<?php echo $relative_path; ?>">
                    Your browser does not support the video tag.
                </video>
                <p><strong>Time:</strong> <?php echo date('M d, H:i:s', strtotime($clip['recorded_at'])); ?></p>
                <p><?php echo $clip['is_final'] ? '<span style="color:red">Final Submission Clip</span>' : 'Incidental Violation'; ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        <?php } ?>

        <?php elseif ($view === 'snapshots' && $exam_id > 0 && $student_id > 0): ?>
        <!-- View: Webcam snapshots for a student -->
        <h2>Webcam Snapshots</h2>
        <a href="?view=students&exam_id=<?php echo $exam_id; ?>">← Back to Students</a>

        <?php
        $student_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($student_query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        echo "<h3>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</h3>";

        $snapshots_query = "SELECT psn.snapshot_id, psn.snapshot_data, psn.captured_at
                           FROM results r
                           JOIN proctor_sessions ps ON r.result_id = ps.result_id
                           JOIN proctor_snapshots psn ON ps.session_id = psn.session_id
                           WHERE r.exam_id = ? AND r.student_id = ?
                           ORDER BY psn.captured_at DESC";
        $stmt = $conn->prepare($snapshots_query);
        $stmt->bind_param("ii", $exam_id, $student_id);
        $stmt->execute();
        $snapshots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($snapshots) === 0) {
            echo "<p>No snapshots available for this student.</p>";
        } else {
            echo "<div class='snapshot-grid'>";
            foreach ($snapshots as $snapshot):
                // Detect MIME type from binary data and build proper data URI
                $mime = 'image/png';
                if (!empty($snapshot['snapshot_data'])) {
                    if (function_exists('finfo_open')) {
                        $f = finfo_open(FILEINFO_MIME_TYPE);
                        $det = finfo_buffer($f, $snapshot['snapshot_data']);
                        if ($det) $mime = $det;
                        finfo_close($f);
                    } elseif (function_exists('getimagesizefromstring')) {
                        $info = @getimagesizefromstring($snapshot['snapshot_data']);
                        if (!empty($info['mime'])) $mime = $info['mime'];
                    }
                }
                $img_data = base64_encode($snapshot['snapshot_data']);
                $img_src = 'data:' . $mime . ';base64,' . $img_data;
            ?>
            <div class="snapshot-card">
                <img src="<?php echo $img_src; ?>" alt="Snapshot">
                <p><?php echo date('M d, H:i:s', strtotime($snapshot['captured_at'])); ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        <?php } ?>

        <?php elseif ($view === 'logs' && $exam_id > 0 && $student_id > 0): ?>
        <!-- View: Activity logs for a student -->
        <h2>Activity & Event Logs</h2>
        <a href="?view=students&exam_id=<?php echo $exam_id; ?>">← Back to Students</a>

        <?php
        $student_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($student_query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        echo "<h3>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</h3>";

        $activity_query = "SELECT pa.event_type, pa.event_details, pa.recorded_at
                          FROM results r
                          JOIN proctor_sessions ps ON r.result_id = ps.result_id
                          JOIN proctor_activity pa ON ps.session_id = pa.session_id
                          WHERE r.exam_id = ? AND r.student_id = ?
                          ORDER BY pa.recorded_at DESC";
        $stmt = $conn->prepare($activity_query);
        $stmt->bind_param("ii", $exam_id, $student_id);
        $stmt->execute();
        $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($activities) === 0) {
            echo "<p>No activity logs available for this student.</p>";
        } else {
            echo "<div class='activity-log'>";
            foreach ($activities as $activity):
            ?>
            <div class="activity-entry">
                <span class="timestamp"><?php echo date('H:i:s', strtotime($activity['recorded_at'])); ?></span>
                <span class="type"><?php echo htmlspecialchars($activity['event_type']); ?></span>
                <p><?php echo htmlspecialchars($activity['event_details']); ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        <?php } ?>

        <?php endif; ?>

        <div style="margin-top: 30px; padding: 20px; background: #f1f1f1; border-radius: 6px;">
            <h3>About Proctoring Data</h3>
            <ul>
                <li>Snapshots are retained for 30 days and automatically deleted.</li>
                <li>Activity events include tab switches, paste attempts, blocked shortcuts, and other security events.</li>
                <li>IP address and browser user-agent are logged for each session.</li>
                <li>Use this data to verify exam integrity and investigate suspicious activity.</li>
            </ul>
        </div>
    </div>
</body>
</html>

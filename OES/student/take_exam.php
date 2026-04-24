<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('student');

$student_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    header('Location: available_exams.php');
    exit();
}

// Verify student has access to this exam
$verify_query = "SELECT e.exam_id FROM exams e
                JOIN classes c ON e.class_id = c.class_id
                JOIN students_classes sc ON c.class_id = sc.class_id
                WHERE e.exam_id = ? AND sc.student_id = ? AND e.status = 'published'";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header('Location: available_exams.php');
    exit();
}

// Check exam start time and deadline
$time_check = "SELECT start_date, end_date FROM exams WHERE exam_id = ?";
$stmt = $conn->prepare($time_check);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$time_result = $stmt->get_result()->fetch_assoc();
$exam_start = $time_result['start_date'];
$exam_deadline = $time_result['end_date'];
$current_time = new DateTime();
$start_time = new DateTime($exam_start);
$deadline_time = new DateTime($exam_deadline);

if ($current_time < $start_time) {
    $_SESSION['start_error'] = 'This exam has not started yet. It will be available from ' . $start_time->format('Y-m-d H:i') . '.';
    header('Location: available_exams.php');
    exit();
}

if ($current_time > $deadline_time) {
    $_SESSION['deadline_error'] = 'This exam deadline has passed. You cannot take this exam anymore.';
    header('Location: available_exams.php');
    exit();
}

// Check if student already has a result for this exam
$result_query = "SELECT result_id, submitted_at FROM results WHERE exam_id = ? AND student_id = ?";
$stmt = $conn->prepare($result_query);
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
$existing_attempt = $stmt->get_result()->fetch_assoc();

// Fetch exam details
$exam_query = "SELECT * FROM exams WHERE exam_id = ?";
$stmt = $conn->prepare($exam_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

// Fetch questions for this exam
$questions_query = "SELECT * FROM questions WHERE exam_id = ? ORDER BY RAND()";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch options for each question
$question_options = [];
foreach ($questions as $q) {
    $opt_query = "SELECT * FROM question_options WHERE question_id = ? ORDER BY RAND()";
    $opt_stmt = $conn->prepare($opt_query);
    $opt_stmt->bind_param("i", $q['question_id']);
    $opt_stmt->execute();
    $question_options[$q['question_id']] = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Create or get result record
if ($existing_attempt) {
    $result_id = $existing_attempt['result_id'];
    if ($existing_attempt['submitted_at'] !== null) {
        header('Location: my_results.php');
        exit();
    }
} else {
    $class_query = "SELECT class_id FROM exams WHERE exam_id = ?";
    $stmt = $conn->prepare($class_query);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $class_id = $stmt->get_result()->fetch_assoc()['class_id'];
    
    $insert_query = "INSERT INTO results (student_id, exam_id, class_id, total_marks, attempted_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iiii", $student_id, $exam_id, $class_id, $exam['total_marks']);
    $stmt->execute();
    $result_id = $conn->insert_id;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['exam_name']); ?> - Exam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/exam.css">
</head>
<body>
    <div id="consentModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:9999;">
        <div style="background:white;padding:20px;border-radius:8px;max-width:720px;width:100%;">
            <h2>Exam Rules & Consent</h2>
            <p>Please read and accept the following before starting the exam:</p>
            <ul>
                <li>Your webcam and microphone will be required for AI-based proctoring.</li>
                <li>All activity (tab switching, copy/paste, navigation) will be logged.</li>
                <li><strong>Maximum of 3 violations allowed</strong> before the exam is automatically submitted.</li>
            </ul>
            <label style="display:block;margin:10px 0;"><input type="checkbox" id="consentCheckbox"> I have read and I consent to the above</label>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button id="consentCancel" class="btn btn-secondary">Cancel</button>
                <button id="consentAccept" class="btn btn-primary" disabled>Start Exam</button>
            </div>
        </div>
    </div>

    <!-- Visible Camera Preview -->
    <div id="cameraPreviewContainer" style="position:fixed; bottom:20px; left:20px; width:160px; height:120px; border:2px solid #333; border-radius:8px; overflow:hidden; z-index:9998; background:#000; box-shadow:0 4px 12px rgba(0,0,0,0.5); display:none;">
        <video id="proctorVideo" autoplay playsinline style="width:100%; height:100%; object-fit:cover;"></video>
        <div style="position:absolute; top:5px; left:5px; background:rgba(0,0,0,0.5); color:#0f0; font-size:10px; padding:2px 4px; border-radius:3px;">LIVE</div>
    </div>

    <div id="violationNotice" style="display:none; position:fixed; top:20px; right:20px; background:#ff4d4d; color:white; padding:15px; border-radius:5px; z-index:10000; box-shadow:0 2px 10px rgba(0,0,0,0.3); max-width:300px;">
        <strong>AI Violation Warning!</strong>
        <p id="violationMsg" style="margin:5px 0 0 0; font-size:0.9em;"></p>
    </div>

    <div id="aiStatus" style="position:fixed; bottom:20px; right:20px; background:rgba(0,0,0,0.7); color:white; padding:8px 12px; border-radius:20px; font-size:12px; display:none; align-items:center; gap:8px; z-index:9999;">
        <span style="width:10px; height:10px; background:#00ff00; border-radius:50%; display:inline-block;" id="aiStatusDot"></span>
        <span id="aiStatusText">AI Monitoring Active</span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface"></script>

    <script>
        var consentModal = document.getElementById('consentModal');
        var consentCheckbox = document.getElementById('consentCheckbox');
        var consentAccept = document.getElementById('consentAccept');
        var consentCancel = document.getElementById('consentCancel');
        var proctorVideo = document.getElementById('proctorVideo');
        var violationNotice = document.getElementById('violationNotice');
        var violationMsg = document.getElementById('violationMsg');
        var aiStatus = document.getElementById('aiStatus');
        var cameraPreviewContainer = document.getElementById('cameraPreviewContainer');
        var localStream = null;
        var mediaRecorder = null;
        var recordedBlobs = [];
        var bufferDuration = 10000; // 10 seconds buffer
        var violationCount = 0;
        var maxViolations = 3; // FIXED TO 3
        var blazefaceModel = null;
        var lastViolationTime = 0;
        
        var examId = <?php echo $exam_id; ?>;
        var resultId = <?php echo $result_id; ?>;
        var studentId = <?php echo $student_id; ?>;

        consentCheckbox.addEventListener('change', function() {
            consentAccept.disabled = !consentCheckbox.checked;
        });

        consentCancel.addEventListener('click', function() {
            window.location.href = 'available_exams.php';
        });

        consentAccept.addEventListener('click', async function() {
            consentAccept.disabled = true;
            consentAccept.textContent = 'Initializing AI...';

            try {
                blazefaceModel = await blazeface.load();
                const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 }, audio: true });
                localStream = stream;
                proctorVideo.srcObject = stream;
                await new Promise(resolve => proctorVideo.onloadedmetadata = resolve);
                proctorVideo.play();

                startMediaRecorder();
                setupFaceMonitoring();

                aiStatus.style.display = 'flex';
                cameraPreviewContainer.style.display = 'block';

                if (document.fullscreenEnabled) {
                    var el = document.documentElement;
                    if (el.requestFullscreen) el.requestFullscreen().catch(function(e){});
                }
                
                consentModal.style.display = 'none';
                document.querySelector('.exam-container').style.filter = 'none';
                
                if (typeof startTimer === 'function') startTimer();
                
                document.addEventListener('fullscreenchange', function() {
                    if (!document.fullscreenElement) {
                        logEvent('fullscreen_exit', 'Student exited fullscreen');
                        handleViolation('fullscreen_exit');
                    }
                });
                
                logEvent('proctor_start', 'AI Proctoring started');

            } catch (err) {
                alert('Initialization error: ' + err.message);
                consentAccept.disabled = false;
                consentAccept.textContent = 'Start Exam';
            }
        });

        function showViolation(msg) {
            violationMsg.textContent = msg;
            violationNotice.style.display = 'block';
            setTimeout(function() { violationNotice.style.display = 'none'; }, 5000);
        }

        function logEvent(type, details) {
            fetch('../proctoring/log_activity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_type: type, event_details: details, result_id: resultId, student_id: studentId, exam_id: examId })
            }).catch(e => console.error(e));
        }

        function startMediaRecorder() {
            if (!localStream) return;
            try {
                // Try MP4 first for better compatibility
                var options = { mimeType: 'video/mp4' };
                if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options = { mimeType: 'video/webm' };
                }
                mediaRecorder = new MediaRecorder(localStream, options);
            } catch (e) {
                mediaRecorder = new MediaRecorder(localStream);
            }
            mediaRecorder.ondataavailable = function(e) {
                if (e.data && e.data.size > 0) {
                    recordedBlobs.push({time: Date.now(), blob: e.data});
                    var cutoff = Date.now() - bufferDuration;
                    recordedBlobs = recordedBlobs.filter(item => item.time >= cutoff);
                }
            };
            mediaRecorder.start(1000); // 1-second chunks
        }

        async function setupFaceMonitoring() {
            if (!blazefaceModel) return;
            setInterval(async function() {
                if (proctorVideo.readyState < 2) return;
                const predictions = await blazefaceModel.estimateFaces(proctorVideo, false);

                if (predictions.length === 0) {
                    handleViolation('no_face_detected');
                } else if (predictions.length > 1) {
                    handleViolation('multiple_faces_detected');
                } else {
                    const face = predictions[0];
                    const landmarks = face.landmarks;
                    const size = [face.bottomRight[0] - face.topLeft[0], face.bottomRight[1] - face.topLeft[1]];
                    const eyeMidX = (landmarks[0][0] + landmarks[1][0]) / 2;
                    const gazeOffset = (landmarks[2][0] - eyeMidX) / size[0];
                    if (Math.abs(gazeOffset) > 0.15) {
                         handleViolation('looking_away');
                    }
                }
            }, 1000);
        }

        function handleViolation(reason) {
            var now = Date.now();
            if (now - lastViolationTime < 5000) return;
            lastViolationTime = now;
            
            logEvent('ai_violation', reason);
            violationCount++;
            
            var vc = document.getElementById('violation-count');
            if (vc) vc.textContent = violationCount + '/' + maxViolations;
            
            var friendlyMsg = '';
            switch(reason) {
                case 'no_face_detected': friendlyMsg = 'No face detected!'; break;
                case 'multiple_faces_detected': friendlyMsg = 'Multiple people detected!'; break;
                case 'looking_away': friendlyMsg = 'Please look at the screen.'; break;
                case 'fullscreen_exit': friendlyMsg = 'Fullscreen exited.'; break;
                case 'tab_switch': friendlyMsg = 'Tab switched.'; break;
                default: friendlyMsg = 'Violation detected.';
            }

            showViolation('Violation: ' + friendlyMsg + ' (' + violationCount + '/' + maxViolations + ')');
            
            // Record 10s video and upload
            uploadViolationClip(violationCount >= maxViolations);

            if (violationCount >= maxViolations) {
                setTimeout(function() {
                    alert('Maximum violations reached. Exam submitted.');
                    if (mediaRecorder) mediaRecorder.stop();
                    document.getElementById('examForm').submit();
                }, 1000);
            }
        }

        function uploadViolationClip(final) {
            if (recordedBlobs.length === 0) return;
            // Use the mimeType from the first recorded blob or default to mp4
            var mimeType = recordedBlobs[0].blob.type || 'video/mp4';
            var superBlob = new Blob(recordedBlobs.map(i => i.blob), {type: mimeType});
            
            // Clear the buffer immediately after creating the superBlob 
            // so the next violation starts fresh
            recordedBlobs = [];

            var fd = new FormData();
            fd.append('exam_id', examId);
            fd.append('result_id', resultId);
            fd.append('student_id', studentId);
            fd.append('violation', superBlob, 'violation.mp4');
            fd.append('final', final ? '1' : '0');
            fetch('../proctoring/save_violation_clip.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => console.log('Violation recorded:', data))
            .catch(e => console.error('Recording failed:', e));
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                logEvent('tab_hidden', 'Tab switched');
                handleViolation('tab_switch');
            }
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && ['c', 'v', 'x', 'p', 's'].includes(e.key.toLowerCase())) || e.key === 'PrintScreen') {
                e.preventDefault();
                logEvent('blocked_shortcut', 'Blocked key: ' + e.key);
            }
        });
        document.addEventListener('paste', e => { e.preventDefault(); logEvent('paste_attempt', 'Paste blocked'); });
        document.addEventListener('contextmenu', e => { e.preventDefault(); logEvent('context_menu', 'Right-click blocked'); });

        window.addEventListener('beforeunload', function() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            logEvent('end_exam_navigation', 'Page left or refreshed');
        });
    </script>

    <div class="exam-container" style="filter: blur(3px);">
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['exam_name']); ?></h1>
            <div class="exam-info">
                <div class="info-item"><span>Duration:</span> <strong><?php echo $exam['exam_duration']; ?> min</strong></div>
                <div class="info-item"><span>Total Marks:</span> <strong><?php echo $exam['total_marks']; ?></strong></div>
                <div class="info-item"><span>Questions:</span> <strong><?php echo count($questions); ?></strong></div>
                <div class="info-item timer-box"><span>Time Remaining:</span> <strong id="timer" class="timer-display"><?php echo $exam['exam_duration']; ?>:00</strong></div>
                <div class="info-item deadline-box"><span>Deadline:</span> <strong><?php echo date('M d, Y h:i A', strtotime($exam_deadline)); ?></strong></div>
                <div class="info-item violations-box"><span>Violations:</span> <strong id="violation-count">0/3</strong></div>
            </div>
        </div>

        <form id="examForm" method="POST" action="submit_exam.php">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="result_id" value="<?php echo $result_id; ?>">

            <div class="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-block">
                    <h3>Q<?php echo ($index + 1); ?>) <?php echo htmlspecialchars($question['question_text']); ?></h3>
                    <p class="question-marks">Marks: <?php echo $question['marks']; ?></p>

                    <?php if ($question['question_type'] == 'true_false' || $question['question_type'] == 'multiple_choice'): ?>
                    <div class="options">
                        <?php foreach ($question_options[$question['question_id']] as $option): ?>
                        <label class="option-label">
                            <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="<?php echo $option['option_id']; ?>">
                            <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <textarea class="descriptive-answer" name="question_<?php echo $question['question_id']; ?>" rows="5" placeholder="Write your answer here..."></textarea>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="exam-actions">
                <button type="submit" class="btn btn-success btn-large">Submit Exam</button>
                <button type="button" class="btn btn-secondary btn-large" onclick="if(confirm('Are you sure you want to leave? Your answers won\'t be saved.')) { location.href='dashboard.php'; }">Exit Without Submitting</button>
            </div>
        </form>
    </div>

    <script>
        var timeInSeconds = <?php echo $exam['exam_duration'] * 60; ?>;
        var timerInterval;
        var examDeadline = new Date('<?php echo date('c', strtotime($exam_deadline)); ?>');

        function startTimer() {
            timerInterval = setInterval(function() {
                var now = new Date();
                var timeUntilDeadline = Math.floor((examDeadline - now) / 1000);
                if (timeUntilDeadline <= 0) {
                    clearInterval(timerInterval);
                    alert('Exam deadline reached! Submitting...');
                    document.getElementById('examForm').submit();
                    return;
                }
                timeInSeconds = Math.min(timeInSeconds, timeUntilDeadline);
                var minutes = Math.floor(timeInSeconds / 60);
                var seconds = timeInSeconds % 60;
                document.getElementById('timer').textContent = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                if (timeInSeconds <= 300) document.getElementById('timer').style.color = '#ff6b6b';
                if (timeInSeconds <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('examForm').submit();
                }
                timeInSeconds--;
            }, 1000);
        }

        window.onbeforeunload = function() {
            if (timeInSeconds > 0) return "Your answers will be lost!";
        };
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Examination System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Welcome Page -->
        <div style="text-align: center; padding: 60px 20px;">
            <h1 style="font-size: 48px; color: #2c3e50; border: none; margin-bottom: 20px;">
                Online Examination System
            </h1>
            <p style="font-size: 18px; color: #7f8c8d; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
                A modern web-based platform for conducting digital exams, managing classes, and evaluating student performance with instant results.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
                <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="color: #3498db; margin-top: 0;">For Admins</h3>
                    <p>Manage users, create classes, assign teachers, and monitor system-wide activities.</p>
                </div>
                <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="color: #27ae60; margin-top: 0;">For Teachers</h3>
                    <p>Create exams, add questions, manage classes, and view student results with analytics.</p>
                </div>
                <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="color: #e74c3c; margin-top: 0;">For Students</h3>
                    <p>Take exams, view results instantly, and track your academic progress.</p>
                </div>
            </div>

            <div style="margin-bottom: 40px;">
                <h2 style="border: none; margin-bottom: 20px;">Key Features</h2>
                <ul style="list-style: none; padding: 0; text-align: left; max-width: 600px; margin: 0 auto;">
                    <li style="padding: 8px 0;">• Role-based access control (Admin, Teacher, Student)</li>
                    <li style="padding: 8px 0;">• Multiple question types (Multiple Choice, True/False, Descriptive)</li>
                    <li style="padding: 8px 0;">• Timer-based exam sessions</li>
                    <li style="padding: 8px 0;">• Automatic evaluation for objective questions</li>
                    <li style="padding: 8px 0;">• Class-based exam management</li>
                    <li style="padding: 8px 0;">• Instant results and analytics</li>
                    <li style="padding: 8px 0;">• Multi-class support for teachers</li>
                    <li style="padding: 8px 0;">• Secure authentication system</li>
                </ul>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="auth/login.php" class="btn btn-primary btn-large">Login</a>
                <a href="auth/register.php" class="btn btn-secondary btn-large">Register as Student</a>
            </div>

            <div style="margin-top: 40px; padding: 20px; background: #ecf0f1; border-radius: 8px; max-width: 600px; margin-left: auto; margin-right: auto;">
                <h3 style="margin-top: 0;">Demo Credentials</h3>
                <p style="margin: 10px 0;"><strong>Admin:</strong> admin@example.com / password123</p>
                <p style="margin: 10px 0;"><strong>Teacher:</strong> teacher@example.com / password123</p>
                <p style="margin: 10px 0;"><strong>Student:</strong> student@example.com / password123</p>
            </div>
        </div>
        <?php else: ?>
        <!-- Logged In Home -->
        <div style="text-align: center; padding: 40px 20px;">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <p style="font-size: 18px; color: #7f8c8d;">You are logged in as: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>

            <div style="margin-top: 30px;">
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <p><a href="admin/dashboard.php" class="btn btn-primary btn-large">Go to Admin Dashboard</a></p>
                <?php elseif ($_SESSION['role'] == 'teacher'): ?>
                    <p><a href="teacher/dashboard.php" class="btn btn-primary btn-large">Go to Teacher Dashboard</a></p>
                <?php elseif ($_SESSION['role'] == 'student'): ?>
                    <p><a href="student/dashboard.php" class="btn btn-primary btn-large">Go to Student Dashboard</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer style="background-color: #2c3e50; color: white; text-align: center; padding: 20px; margin-top: 40px;">
        <p>&copy; 2024 Online Examination System. All Rights Reserved.</p>
    </footer>
</body>
</html>

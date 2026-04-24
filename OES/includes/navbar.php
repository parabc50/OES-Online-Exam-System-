<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<nav>
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div class="brand">
            OES
        </div>
        
        <ul style="flex: 1; justify-content: center;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li><a href="../admin/dashboard.php">Dashboard</a></li>
                    <li><a href="../admin/manage_users.php">Users</a></li>
                    <li><a href="../admin/manage_classes.php">Classes</a></li>
                <?php elseif ($_SESSION['role'] == 'teacher'): ?>
                    <li><a href="../teacher/dashboard.php">Dashboard</a></li>
                    <li><a href="../teacher/manage_students.php">Students</a></li>
                    <li><a href="../teacher/classes.php">Classes</a></li>
                    <li><a href="../teacher/manage_exams.php">Exams</a></li>
                    <li><a href="../teacher/view_results.php">Results</a></li>
                <?php elseif ($_SESSION['role'] == 'student'): ?>
                    <li><a href="../student/dashboard.php">Dashboard</a></li>
                    <li><a href="../student/classes.php">Classes</a></li>
                    <li><a href="../student/available_exams.php">Exams</a></li>
                    <li><a href="../student/my_results.php">Results</a></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <div style="display: flex; align-items: center; gap: 10px;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span style="color: white;">
                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                </span>
                <a href="../auth/logout.php" style="background-color: #e74c3c; padding: 10px 15px;">Logout</a>
            <?php else: ?>
                <a href="auth/login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
    nav ul {
        margin: 0;
        padding: 0;
        display: flex;
        list-style: none;
    }

    nav li {
        margin: 0;
    }

    nav a {
        color: white;
        text-decoration: none;
        padding: 15px 20px;
        display: block;
        transition: background-color 0.3s;
    }

    nav a:hover {
        background-color: rgba(255,255,255,0.2);
    }
</style>

<!--
    The automatic logout-on-refresh script caused problems: navigating between
    pages sometimes triggered a perceived "reload" and immediately sent the
    user back to the login screen.  For simplicity we no longer attempt to
    detect refresh/back-forward and forcibly log the user out; session
    lifetime is controlled by PHP configuration instead.

    If you really need this behaviour, you could re-add it with proper
    conditions.  For now the code has been removed to prevent unintended
    logouts.
-->

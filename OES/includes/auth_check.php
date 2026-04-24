<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check user role
function checkRole($required_role) {
    if ($_SESSION['role'] != $required_role) {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Get user full name
function getUserFullName() {
    return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
}

// Get user role
function getUserRole() {
    return $_SESSION['role'];
}
?>

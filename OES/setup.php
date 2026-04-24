<?php
/**
 * Database Setup Script
 * Run this file once to create database and insert demo data
 * Access: http://localhost/OES/setup.php
 */

// First, connect without selecting a database
$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$create_db = "CREATE DATABASE IF NOT EXISTS online_exam_system";
if ($conn->query($create_db) === TRUE) {
    echo "✓ Database created successfully<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db('online_exam_system');

// Read and execute SQL file
$sql_file = file_get_contents(__DIR__ . '/config/database.sql');
$statements = array_filter(array_map('trim', preg_split('/;/', $sql_file)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            // echo "✓ Executed statement<br>";
        } else {
            echo "Note: " . $conn->error . "<br>";
        }
    }
}

echo "<br>✓ Database tables created successfully<br><br>";

// Insert demo data
$demo_data = [
    // Admin user
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('Admin', 'User', 'admin@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'admin')",
    
    // Teacher users
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('John', 'Teacher', 'teacher@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'teacher')",
    
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('Sarah', 'Smith', 'sarah.smith@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'teacher')",
    
    // Student users
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('Jane', 'Student', 'student@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'student')",
    
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('Mark', 'Johnson', 'mark.johnson@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'student')",
    
    "INSERT INTO users (first_name, last_name, email, password, role) 
     VALUES ('Alice', 'Brown', 'alice.brown@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'student')",
];

foreach ($demo_data as $sql) {
    if ($conn->query($sql) === TRUE) {
        // echo "✓ Inserted record<br>";
    } else {
        // Skip if already exists
    }
}

echo "✓ Demo users created<br><br>";

// Create sample classes (need teacher IDs first)
$teachers = $conn->query("SELECT user_id FROM users WHERE role = 'teacher'");
$teacher_ids = [];
while ($row = $teachers->fetch_assoc()) {
    $teacher_ids[] = $row['user_id'];
}

if (count($teacher_ids) > 0) {
    $class_sql = "INSERT IGNORE INTO classes (class_name, class_code, description, teacher_id, semester) 
                  VALUES ('Advanced Programming', 'PROG-101', 'Learn advanced programming concepts', " . $teacher_ids[0] . ", 'Fall 2024')";
    if ($conn->query($class_sql) === TRUE) {
        echo "✓ Sample class created<br>";
    }
}

echo "<br>";
echo "<h2>✓ Setup Complete!</h2>";
echo "<p><strong>Demo Credentials:</strong></p>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin@example.com / password123</li>";
echo "<li><strong>Teacher:</strong> teacher@example.com / password123</li>";
echo "<li><strong>Student:</strong> student@example.com / password123</li>";
echo "</ul>";
echo "<p><a href='index.php'>Go to Home Page</a></p>";

$conn->close();
?>

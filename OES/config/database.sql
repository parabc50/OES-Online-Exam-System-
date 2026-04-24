-- Create Database
CREATE DATABASE IF NOT EXISTS online_exam_system;
USE online_exam_system;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Classes Table
CREATE TABLE IF NOT EXISTS classes (
    class_id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(100) NOT NULL,
    class_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    semester VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Student Classes Mapping (Junction Table)
CREATE TABLE IF NOT EXISTS students_classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_class (student_id, class_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE
);

-- Exams Table
CREATE TABLE IF NOT EXISTS exams (
    exam_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(200) NOT NULL,
    exam_description TEXT,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    total_questions INT NOT NULL,
    exam_duration INT NOT NULL COMMENT 'Duration in minutes',
    total_marks INT NOT NULL,
    passing_percentage DECIMAL(5,2) DEFAULT 40.00,
    status ENUM('draft', 'published', 'completed') DEFAULT 'draft',
    start_date DATETIME,
    end_date DATETIME,
    show_answers BOOLEAN DEFAULT FALSE,
    shuffle_questions BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Questions Table
CREATE TABLE IF NOT EXISTS questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'descriptive') NOT NULL,
    marks INT NOT NULL DEFAULT 1,
    question_order INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE
);

-- Question Options Table (for multiple choice and true/false)
CREATE TABLE IF NOT EXISTS question_options (
    option_id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    option_order INT,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE
);

-- Results Table
CREATE TABLE IF NOT EXISTS results (
    result_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    class_id INT NOT NULL,
    total_marks_obtained DECIMAL(10,2),
    total_marks INT,
    percentage DECIMAL(5,2),
    status ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    UNIQUE KEY unique_student_exam (student_id, exam_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE
);

-- Student Answers Table
CREATE TABLE IF NOT EXISTS student_answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    option_id INT NULL,
    descriptive_answer TEXT,
    marks_obtained DECIMAL(10,2) DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (result_id) REFERENCES results(result_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES question_options(option_id) ON DELETE SET NULL
);

-- Create some useful indexes
-- CREATE INDEX idx_user_email ON users(email);
-- CREATE INDEX idx_user_role ON users(role);
-- CREATE INDEX idx_class_teacher ON classes(teacher_id);
-- CREATE INDEX idx_exam_class ON exams(class_id);
-- CREATE INDEX idx_exam_teacher ON exams(teacher_id);
-- CREATE INDEX idx_exam_status ON exams(status);
-- CREATE INDEX idx_result_student ON results(student_id);
-- CREATE INDEX idx_result_exam ON results(exam_id);
-- CREATE INDEX idx_result_class ON results(class_id);
-- CREATE INDEX idx_student_answer_result ON student_answers(result_id);
-- CREATE INDEX idx_student_answer_question ON student_answers(question_id);

-- Proctoring: sessions, snapshots and activity logs
CREATE TABLE IF NOT EXISTS proctor_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    result_id INT NOT NULL,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    CONSTRAINT fk_proctor_result FOREIGN KEY (result_id) REFERENCES results(result_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS proctor_snapshots (
    snapshot_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    snapshot_data LONGBLOB NOT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES proctor_sessions(session_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS proctor_activity (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_details TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES proctor_sessions(session_id) ON DELETE CASCADE
);

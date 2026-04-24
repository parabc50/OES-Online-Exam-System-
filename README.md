# Online Examination System (OES) with AI Proctoring

A modern web-based platform for conducting digital exams, managing classes, and evaluating student performance with instant results. Built with PHP and MySQL, OES with AI Proctoring supports a comprehensive exam management system with intelligent proctoring capabilities, role-based access control, and advanced security features for administrators, teachers, and students.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-336791?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)

### demo :- http://oes.infinityfreeapp.com/
(Please use desktop to view the project; mobile view of the project is not fully furnished)

## Features

### 🎓 Core Features
- **Role-based Access Control** - Separate dashboards and features for Admin, Teacher, and Student roles
- **Multiple Question Types** - Support for Multiple Choice, True/False, and Descriptive questions
- **Timer-based Exam Sessions** - Timed exams with automatic submission
- **Automatic Evaluation** - Instant grading for objective questions
- **Class-based Exam Management** - Organize exams by classes and sections
- **Instant Results & Analytics** - Real-time result generation and performance analytics
- **Student Performance Tracking** - Comprehensive results and progress tracking

### 👨‍💼 Admin Dashboard
- Manage users (create, edit, delete)
- Create and manage classes
- Assign teachers to classes
- Assign students to classes
- Monitor system-wide activities
- View user statistics

### 👨‍🏫 Teacher Features
- Create and manage exams
- Add and edit questions with various types
- Manage class students
- View exam results and analytics
- Generate detailed reports
- Monitor student performance
- **Access AI Proctoring Reports** - Review flagged violations and suspicious activities
- **Analyze Answer Similarities** - Identify potential plagiarism with AI detection
- Check exam attempt details

### 👨‍🎓 Student Features
- View available exams
- Access class-specific exams
- Take exams with timer
- Submit exam responses
- View instant results
- Track academic progress
- View detailed result breakdowns

### 🤖 AI-Powered Proctoring & Security
- **AI Answer Similarity Detection** - Detect plagiarism and similar answers using advanced algorithms
- **Violation Clip Recording** - Automatic capture of suspicious activities
- **Activity Logging** - Real-time monitoring and logging during exams
- **Session Management** - Secure session handling and user authentication
- **Secure Password Handling** - Industry-standard password encryption and hashing
- **Intelligent Monitoring** - AI-powered analysis of student behavior patterns

## Requirements

- **XAMPP 8.2.12** (or any XAMPP installation with PHP 8.2+)
- **PHP 8.2+**
- **MySQL 5.7+**
- **Modern Web Browser** (Chrome, Edge)
- **Minimum 500MB** disk space

## Installation & Setup

### Step 1: Download and Place the Project

1. Extract the OES project in your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\OES\
   ```

### Step 2: Start XAMPP Services

1. Open XAMPP Control Panel
2. Click **Start** on Apache
3. Click **Start** on MySQL
4. Wait for both services to show as running

### Step 3: Initialize the Database

1. Open your web browser and navigate to:
   ```
   http://localhost/OES/setup.php
   ```

2. This will:
   - Create the `online_exam_system` database
   - Create all necessary tables
   - Insert demo users and sample data

3. You should see success messages:
   ```
   ✓ Database created successfully
   ✓ Database tables created successfully
   ```

### Step 4: Access the Application

Open your browser and navigate to:
```
http://localhost/OES/
```

## Demo Login Credentials

After running setup.php, use these demo accounts:

### Admin Account
- **Email**: admin@example.com
- **Password**: password123

### Teacher Account
- **Email**: teacher@example.com
- **Password**: password123

### Student Accounts
- **Email**: student1@example.com
- **Password**: password123
- **Email**: student2@example.com
- **Password**: password123

## Project Structure

```
OES/
├── index.php                    # Home page and landing page
├── setup.php                    # Database initialization script
├── admin/                       # Admin dashboard pages
│   ├── dashboard.php           # Admin dashboard
│   ├── manage_users.php        # User management
│   ├── manage_classes.php      # Class management
│   └── manage_students_classes.php  # Student-class assignment
├── teacher/                    # Teacher features
│   ├── dashboard.php           # Teacher dashboard
│   ├── create_exam.php         # Exam creation
│   ├── add_questions.php       # Question management
│   ├── manage_exams.php        # Exam management
│   ├── manage_students.php     # Student management
│   ├── view_results.php        # Results view
│   └── proctor_reports.php     # Proctoring reports
├── student/                    # Student features
│   ├── dashboard.php           # Student dashboard
│   ├── available_exams.php     # Available exams list
│   ├── take_exam.php           # Exam taking interface
│   ├── my_results.php          # Results history
│   └── view_result_detail.php  # Detailed result view
├── auth/                       # Authentication
│   ├── login.php               # Login page
│   ├── register.php            # Registration page
│   └── logout.php              # Logout handler
├── api/                        # API endpoints
├── config/                     # Configuration files
│   ├── db.php                  # Database configuration
│   ├── database.sql            # SQL schema
│   └── migration_violation_clips.sql
├── includes/                   # Reusable components
│   ├── auth_check.php          # Authentication check
│   └── navbar.php              # Navigation bar
├── proctoring/                 # Proctoring features
│   ├── save_snapshot.php       # Save exam snapshots
│   ├── check_answer_similarity.php  # Plagiarism detection
│   ├── save_violation_clip.php      # Save violation recordings
│   ├── log_activity.php        # Activity logging
│   └── violation_clips/        # Stored violation clips
├── assets/                     # Static assets
│   ├── css/
│   │   ├── style.css          # Main styles
│   │   └── exam.css           # Exam-specific styles
│   └── js/
│       └── main.js            # Main JavaScript
└── README.md                   # This file
```

## Configuration

### Database Configuration

Edit [config/db.php](config/db.php) to modify database settings:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP MySQL password is empty
define('DB_NAME', 'online_exam_system');
```

### Timezone Configuration

The system is configured for Asia/Kolkata timezone. To change it, edit [config/db.php](config/db.php):

```php
date_default_timezone_set('Asia/Kolkata');  // Change as needed
```

## Usage Guide

### For Admins
1. Login with admin credentials
2. Navigate to dashboard
3. Create classes and assign teachers
4. Manage system users
5. Monitor system activities

### For Teachers
1. Login with teacher credentials
2. Access your assigned classes
3. Create exams with questions
4. Set exam duration and timing
5. View student results and analytics
6. Generate proctoring reports

### For Students
1. Login with student credentials
2. View available exams in dashboard
3. Click on an exam to start
4. Answer questions within the timer
5. Submit exam
6. View instant results

## Common Issues & Troubleshooting

### Issue: Database Connection Failed
**Solution:**
- Ensure MySQL is running in XAMPP
- Check database credentials in [config/db.php](config/db.php)
- Verify database name is `online_exam_system`

### Issue: setup.php doesn't create database
**Solution:**
- Make sure MySQL is running
- Check file permissions on the config directory
- Access setup.php directly: http://localhost/OES/setup.php

### Issue: Session/Login issues
**Solution:**
- Ensure PHP sessions are enabled
- Clear browser cookies
- Check session directory permissions

### Issue: Exam timer not working
**Solution:**
- Check browser console for JavaScript errors
- Verify JavaScript is enabled in browser
- Clear browser cache

## Database Backup

To backup your database:

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select `online_exam_system` database
3. Click **Export** tab
4. Click **Go** to download SQL file

To restore:
1. Create new database in phpMyAdmin
2. Click **Import** tab
3. Select your SQL file
4. Click **Go**

## Security Recommendations

1. **Change Default Passwords** - Update all demo user passwords after first login
2. **Enable HTTPS** - Use SSL certificates in production
3. **Regular Backups** - Backup database regularly
4. **Update Dependencies** - Keep PHP and MySQL updated
5. **Limit File Uploads** - Restrict exam violation clips storage
6. **Use Strong Passwords** - Enforce strong password requirements


## Support & Contribution

For issues, questions, or contributions, please contact me or submit an issue in the project repository.

## License

This project is provided as-is for educational purposes.

## Version

- **Current Version**: 1.0
- **Last Updated**: April 2026
- **XAMPP Compatibility**: 8.2.12+

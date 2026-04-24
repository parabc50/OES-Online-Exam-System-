<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

checkRole('admin');

$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM users WHERE user_id = ? AND role != 'admin'";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = 'User deleted successfully!';
    } else {
        $error = 'Failed to delete user!';
    }
    $stmt->close();
}

// Handle add/update
$created_credentials = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    $phone = $conn->real_escape_string($_POST['phone']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
        $error = 'All required fields must be filled!';
    } else {
        if ($user_id > 0) {
            // Update existing user
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, phone = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $role, $phone, $user_id);
            if ($stmt->execute()) {
                $message = 'User updated successfully!';
            } else {
                $error = 'Failed to update user!';
            }
        } else {
            // Add new user - Generate unique password
            $plain_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            $password = password_hash($plain_password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (first_name, last_name, email, password, role, phone) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssss", $first_name, $last_name, $email, $password, $role, $phone);
            if ($stmt->execute()) {
                $created_credentials = [
                    'email' => $email,
                    'password' => $plain_password,
                    'name' => $first_name . ' ' . $last_name,
                    'role' => $role
                ];
                $message = 'User added successfully! Copy the credentials below to share with the user.';
            } else {
                $error = 'Failed to add user!';
            }
        }
        $stmt->close();
    }
}

// Fetch users
$query = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <h1>Manage Users</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($created_credentials) && !empty($created_credentials)): ?>
            <div class="credentials-box">
                <h3>Login Credentials Created</h3>
                <p><strong>Email:</strong> <code><?php echo htmlspecialchars($created_credentials['email']); ?></code></p>
                <p><strong>Password:</strong> <code><?php echo htmlspecialchars($created_credentials['password']); ?></code></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($created_credentials['name']); ?></p>
                <p><strong>Role:</strong> <?php echo htmlspecialchars($created_credentials['role']); ?></p>
                <p style="color: #666; font-size: 0.9em; margin-top: 10px;">ℹ️ Please share these credentials with the user. The password can be changed after first login.</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add/Update User</h2>
            <form method="POST">
                <input type="hidden" id="user_id" name="user_id" value="">
                
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>

                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label>Role:</label>
                    <select name="role" id="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" id="phone">
                </div>

                <button type="submit" class="btn btn-primary">Save User</button>
            </form>
        </div>

        <div class="table-section">
            <h2>Users List</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo ucfirst($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <button type="button" class="btn btn-small" onclick="editUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name']); ?>', '<?php echo htmlspecialchars($user['last_name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['phone']); ?>')">Edit</button>
                            <?php if ($user['role'] != 'admin'): ?>
                            <a href="?delete=<?php echo $user['user_id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editUser(id, firstName, lastName, email, role, phone) {
            document.getElementById('user_id').value = id;
            document.getElementById('first_name').value = firstName;
            document.getElementById('last_name').value = lastName;
            document.getElementById('email').value = email;
            document.getElementById('role').value = role;
            document.getElementById('phone').value = phone;
            document.querySelector('.form-section h2').textContent = 'Edit User';
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>

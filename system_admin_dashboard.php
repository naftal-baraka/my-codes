 <?php 
// Include database connection and start session
require_once 'db.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if the user is a System Administrator
if ($_SESSION['role_id'] != 4) { // Assuming role_id 4 is for System Administrator
    die("Access denied. You are not authorized to view this page.");
}

// Fetch all users for CRUD operations
$users_sql = "SELECT * FROM users";
$users_result = $conn->query($users_sql);
$users = [];
while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}

// Fetch system logs for auditing
$logs_sql = "SELECT * FROM system_logs ORDER BY log_date DESC";
$logs_result = $conn->query($logs_sql);
$logs = [];
while ($log = $logs_result->fetch_assoc()) {
    $logs[] = $log;
}

// Handle CRUD operations for user accounts
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_user'])) {
        // Add new user
        $username = trim($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $role_id = trim($_POST['role_id']);

        $sql_insert_user = "INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql_insert_user);
        $stmt->bind_param("ssi", $username, $password, $role_id);
        if ($stmt->execute()) {
            echo "<script>alert('User added successfully!'); window.location='system_admin_dashboard.php';</script>";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_user'])) {
        // Delete user
        $user_id = trim($_POST['user_id']);
        $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql_delete_user);
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $admin_id = $_SESSION['user_id']; // System Admin performing the action
        $log_action = "Admin (ID: $admin_id) added user '$username' with role ID $role_id";
        $sql_log = "INSERT INTO system_logs (user_id, action) VALUES (?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("is", $admin_id, $log_action);
        $stmt_log->execute();
        $stmt_log->close();
        // Log the deletion
        $admin_id = $_SESSION['user_id'];
        $log_action = "Admin (ID: $admin_id) deleted user '$deleted_username'";
        $sql_log = "INSERT INTO system_logs (user_id, action) VALUES (?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("is", $admin_id, $log_action);
        $stmt_log->execute();
        $stmt_log->close();
            echo "<script>alert('User deleted successfully!'); window.location='system_admin_dashboard.php';</script>";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a2e;
            color: white;
            margin: 0;
            padding: 0;
            display: flex;
        }
        .sidebar {
            width: 200px;
            height: 100vh;
            background: #16213e;
            padding: 20px;
            position: fixed;
            overflow-y: auto;
        }
        .sidebar button {
            width: 100%;
            padding: 10px;
            background: #0f3460;
            border: none;
            color: white;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .sidebar button:hover {
            background: #1b4b91;
        }
        .content {
            margin-left: 270px;
            padding: 20px;
            width: calc(100% - 270px);
        }
        .hidden { display: none; }
        .form-container, .table-container {
            background: #0f3460;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        input, textarea, select {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .export-btn {
            background: #68d391;
            color: #1a1a2e;
            padding: 10px;
            border: none;
            cursor: pointer;
        }
        .export-btn:hover {
            background: #4caf50;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>System Admin</h2>
        <button onclick="window.location.href='dashboard.php'">Main Dashboard</button>
        <button onclick="toggleSection('manage_users')">Manage Users</button>
        <button onclick="toggleSection('system_logs')">System Logs</button>
        <button onclick="toggleSection('system_settings')">System Settings</button>
        <button onclick="confirmLogout()">Logout</button>
    </div>

    <div class="content">
        <h3>Welcome, System Administrator!</h3>
        
        <!-- Manage Users -->
        <div id="manage_users" class="form-container hidden">
            <h4>Manage User Accounts</h4>
            <form method="POST">
                <label>Username:</label>
                <input type="text" name="username" required>
                
                <label>Password:</label>
                <input type="password" name="password" required>
                
                <label>Role:</label>
                <select name="role_id" required>
                    <option value="1">Investigator</option>
                    <option value="2">Forensic Examiner</option>
                    <option value="3">Lab Personnel</option>
                    <option value="4">System Admin</option>
                </select>
                
                <button type="submit" name="add_user">Add User</button>
            </form>
            
            <h4>User List</h4>
            <table>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo $user['role_id']; ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" name="delete_user">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- System Logs -->
        <div id="system_logs" class="hidden table-container">
            <h4>System Logs</h4>
            <button class="export-btn" onclick="window.location.href='http://localhost/phpmyadmin/index.php?route=/database/structure&db=chain_of_custody_db'">View Database</button>
            <table>
                <tr>
                    <th>Log ID</th>
                    <th>Action</th>
                    <th>Date</th>
                    <th>User</th>
                </tr>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['log_id']; ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo $log['log_date']; ?></td>
                        <td><?php echo $log['user_id']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- System Settings -->
        <div id="system_settings" class="hidden form-container">
            <h4>Configure System Settings</h4>
            <form method="POST">
                <label>Password Policy:</label>
                <select name="password_policy">
                    <option value="weak">Weak</option>
                    <option value="medium">Medium</option>
                    <option value="strong">Strong</option>
                </select>
                
                <label>Enable MFA:</label>
                <input type="checkbox" name="mfa_enabled">
                
                <button type="submit">Save Settings</button>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSection(section) {
            var el = document.getElementById(section);
            el.classList.toggle('hidden');
        }
        
        function confirmLogout() {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = 'logout.php';
            }
        }

        function exportLogs() {
            alert("Exporting system logs...");
            window.location.href = 'system_admin_dashboard.php';
            // Implement export functionality (e.g., CSV export)
        }
    </script>
</body>
</html>

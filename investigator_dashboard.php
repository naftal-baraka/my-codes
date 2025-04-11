<?php
// Database connection
$host = "localhost";
$username = "root";
$password = "";
$dbname = "cocs2";
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if the user is an Investigator
if ($_SESSION['role_id'] != 1) { // Assuming role_id 1 is for Investigator
    die("Access denied. You are not authorized to view this page.");
}

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set Kenyan timezone
date_default_timezone_set('Africa/Nairobi');

// Handle transferring evidence to forensic examiner
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transfer_evidence_id'])) {
    $evidence_id = $_POST['transfer_evidence_id'];
    $current_user_id = $_SESSION['user_id']; // Get the current user's ID
    $transfer_to_role = 'Forensic Examiner'; // The next role in the process

    // Find an available forensic examiner
    $examiner_sql = "SELECT user_id FROM users WHERE role_id = 2 ORDER BY user_id ASC LIMIT 1"; // Assuming role_id 2 is for Forensic Examiner
    $examiner_result = $conn->query($examiner_sql);

    if ($examiner_result && $examiner_result->num_rows > 0) {
        $examiner = $examiner_result->fetch_assoc();
        $assigned_examiner_id = $examiner['user_id'];

        // Update the evidence status, assign to examiner, and transfer
        $update_sql = "UPDATE evidence SET status = 'transferred', assigned_to = ?, assigned_examiner_id = ?, transfer_status = 'Transferred to Lab' WHERE evidence_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $transfer_to_role, $assigned_examiner_id, $evidence_id);

        if ($stmt->execute()) {
            // Log the transfer action
            $log_action = "Evidence ID {$evidence_id} transferred to Forensic Examiner (User ID: {$assigned_examiner_id}) by Investigator (User ID: {$current_user_id})";
            $log_sql = "INSERT INTO system_logs (user_id, action) VALUES (?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $current_user_id, $log_action);
            $log_stmt->execute();
            
            echo "<script>alert('Evidence transferred successfully!'); window.location='investigator_dashboard.php';</script>";
        } else {
            echo "<script>alert('Transfer failed. Please try again.');</script>";
        }
        $stmt->close();
        $log_stmt->close();
    } else {
        echo "<script>alert('No available forensic examiner. Transfer cannot proceed.');</script>";
    }
}

// Handle adding a new case
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['case_id']) && isset($_POST['case_name'])) {
    $case_id = $_POST['case_id'];
    $case_name = $_POST['case_name'];

    // Insert the new case into the database
    $insert_sql = "INSERT INTO cases (case_id, case_name) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ss", $case_id, $case_name);

    if ($stmt->execute()) {
        echo "<script>alert('Case added successfully!'); window.location='investigator_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to add case. Please try again.');</script>";
    }
    $stmt->close();
}

// Handle submitting new evidence
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['evidence_name']) && isset($_POST['description']) && isset($_POST['case_id'])) {
    $evidence_name = $_POST['evidence_name'];
    $description = $_POST['description'];
    $case_id = $_POST['case_id'];
    $collection_date = date('Y-m-d H:i:s'); // Current date and time
    $investigator_id = $_SESSION['user_id']; // Get the current user's ID (investigator)

    // Insert the new evidence into the database
    $insert_sql = "INSERT INTO evidence (evidence_name, description, collection_date, case_id, investigator_id, status, received_status, transfer_status) 
                   VALUES (?, ?, ?, ?, ?, 'collected', 'Pending', 'Not Transferred')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssssi", $evidence_name, $description, $collection_date, $case_id, $investigator_id);

    if ($stmt->execute()) {
        echo "<script>alert('Evidence submitted successfully!'); window.location='investigator_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to submit evidence. Please try again.');</script>";
    }
    $stmt->close();
}

// Fetch cases
$cases_sql = "SELECT case_id, case_name FROM cases";
$cases_result = $conn->query($cases_sql);
$cases = [];
while ($case = $cases_result->fetch_assoc()) {
    $cases[] = $case;
}

// Fetch evidence
$evidence_sql = "SELECT e.evidence_id, e.evidence_name, e.description, e.collection_date, c.case_name, e.status, e.received_status 
                 FROM evidence e 
                 JOIN cases c ON e.case_id = c.case_id";

$evidence_result = $conn->query($evidence_sql);

if (!$evidence_result) {
    die("Error in query: " . $conn->error); // This will show the exact SQL error
}

$evidence_data = [];
while ($row = $evidence_result->fetch_assoc()) {
    $evidence_data[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investigator Dashboard</title>
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
            width: 250px;
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
        input, textarea {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
        }
        select {
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

        /* Styled Transfer Button */
        .transfer-btn {
            padding: 10px 20px;
            background-color: #4CAF50; /* Green */
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .transfer-btn:hover {
            background-color: #45a049; /* Darker green */
            transform: scale(1.05); /* Slight zoom effect */
        }
        .transfer-btn:active {
            background-color: #3e8e41; /* Even darker green */
            transform: scale(1);
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Investigator</h2>
        <button onclick="window.location.href='dashboard.php'">Main Dashboard</button>
        <button onclick="showSection('cases')">Cases</button>
        <button onclick="showSection('new_evidence')">New Evidence</button>
        <button onclick="showSection('collected_evidence')">Collected Evidence</button>
        <button onclick="confirmLogout()">Logout</button>
    </div>

    <div class="content">
        <h3>Welcome, Investigator!</h3>
        
        <!-- Cases -->
        <div id="cases" class="hidden form-container">
            <h4>Add New Case</h4>
            <form method="POST">
                <label>Case ID:</label>
                <input type="text" name="case_id" required>
                
                <label>Case Name:</label>
                <input type="text" name="case_name" required>
                
                <button type="submit">Add Case</button>
            </form>
            
            <h4>Available Cases</h4>
            <table>
                <tr>
                    <th>Case ID</th>
                    <th>Case Name</th>
                </tr>
                <?php foreach ($cases as $case): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($case['case_id']); ?></td>
                        <td><?php echo htmlspecialchars($case['case_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- New Evidence -->
        <div id="new_evidence" class="hidden form-container">
            <h4>Submit New Evidence</h4>
            <form method="POST">
                <label>Evidence Name:</label>
                <input type="text" name="evidence_name" required>

                <label>Description:</label>
                <textarea name="description" required></textarea>

                <label>Case:</label>
                <select name="case_id" required>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>"><?php echo $case['case_name']; ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Submit Evidence</button>
            </form>
        </div>

        <!-- Collected Evidence -->
        <div id="collected_evidence" class="hidden table-container">
            <h4>Collected Evidence</h4>
            <table>
                <tr>
                    <th>Evidence ID</th>
                    <th>Evidence Name</th>
                    <th>Description</th>
                    <th>Collection Date</th>
                    <th>Status</th>
                    <th>Received Status</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($evidence_data as $evidence): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($evidence['evidence_id']); ?></td>
                        <td><?php echo htmlspecialchars($evidence['evidence_name']); ?></td>
                        <td><?php echo htmlspecialchars($evidence['description']); ?></td>
                        <td><?php echo htmlspecialchars($evidence['collection_date']); ?></td>
                        <td><?php echo htmlspecialchars($evidence['status']); ?></td>
                        <td><?php echo htmlspecialchars($evidence['received_status']); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="transfer_evidence_id" value="<?php echo $evidence['evidence_id']; ?>">
                                <button type="submit" class="transfer-btn">Transfer to Forensic Examiner</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

    <script>
        function showSection(section) {
            document.getElementById('cases').classList.add('hidden');
            document.getElementById('new_evidence').classList.add('hidden');
            document.getElementById('collected_evidence').classList.add('hidden');
            
            document.getElementById(section).classList.remove('hidden');
        }

        function confirmLogout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>

</body>
</html>









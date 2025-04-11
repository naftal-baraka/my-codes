<?php  
// Start session to get the logged-in user  
session_start();  

// Database connection  
$host = "localhost";  
$username = "root";  
$password = "";  
$dbname = "cocs2";  

$conn = new mysqli($host, $username, $password, $dbname);  
if ($conn->connect_error) {  
    die("Database Connection Failed: " . $conn->connect_error);  
}  

// Set Kenyan timezone  
date_default_timezone_set('Africa/Nairobi');  

// Function to log actions into the system_logs table  
function logAction($conn, $user_id, $action, $evidence_id, $performed_by, $from_role, $to_role) {  
    $ip_address = $_SERVER['REMOTE_ADDR'];  
    $user_agent = $_SERVER['HTTP_USER_AGENT'];  
    $log_date = date("Y-m-d H:i:s");  

    // Fetch role names from the database  
    $from_role_name = getRoleName($conn, $from_role);  
    $to_role_name = getRoleName($conn, $to_role);  

    // Format the action message dynamically  
    $action_message = "Evidence ID $evidence_id transferred to $to_role_name (User ID: $to_role) by $from_role_name (User ID: $performed_by)";  

    $sql = "INSERT INTO system_logs (user_id, action, ip_address, user_agent, log_date, evidence_id, performed_by, from_role, to_role)  
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";  
    $stmt = $conn->prepare($sql);  
    $stmt->bind_param("issssiiii", $user_id, $action_message, $ip_address, $user_agent, $log_date, $evidence_id, $performed_by, $from_role, $to_role);  
    $stmt->execute();  
    $stmt->close();  
}  

// Function to get role name based on role ID from the database  
function getRoleName($conn, $role_id) {  
    $sql = "SELECT role_name FROM roles WHERE role_id = ?";  
    $stmt = $conn->prepare($sql);  
    $stmt->bind_param("i", $role_id);  
    $stmt->execute();  
    $stmt->bind_result($role_name);  
    $stmt->fetch();  
    $stmt->close();  
    return $role_name ?? "Unknown Role";  
}  

// Fetch received evidence  
$evidence_sql = "SELECT e.evidence_id, e.evidence_name, e.description, e.collection_date, c.case_name, u.username AS investigator_name,   
                        e.received_status, e.transfer_status, e.examiner_received, e.investigator_id, e.lab_personnel_id, e.received_lab_personnel_status  
                 FROM evidence e  
                 JOIN cases c ON e.case_id = c.case_id  
                 JOIN users u ON e.investigator_id = u.user_id  
                 WHERE e.status = 'Transferred' OR e.status = 'Received' OR e.status = 'Analysis Complete'";  
$evidence_result = $conn->query($evidence_sql);  
$evidence_data = [];  
while ($row = $evidence_result->fetch_assoc()) {  
    $evidence_data[] = $row;  
}  

// Fetch all lab personnel from the users table (only users with role_id = 4)  
$lab_personnel_sql = "SELECT user_id, username FROM users WHERE role_id = 4"; // Role ID 4 is for Lab Personnel  
$lab_personnel_result = $conn->query($lab_personnel_sql);  
$lab_personnel_data = [];  
while ($row = $lab_personnel_result->fetch_assoc()) {  
    $lab_personnel_data[] = $row;  
}  

// Handle evidence received  
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['evidence_received'])) {  
    $evidence_id = $_POST['evidence_id'];  
    $received_date = date("Y-m-d H:i:s");  
    $examiner_id = $_SESSION['user_id'];  

    // Update evidence received status to 'Received'  
    $sql_received = "UPDATE evidence SET received_status='Received', received_date=?, examiner_received=TRUE, examiner_id=? WHERE evidence_id=?";  
    $stmt = $conn->prepare($sql_received);  
    $stmt->bind_param("sii", $received_date, $examiner_id, $evidence_id);  
    $stmt->execute();  
    $stmt->close();  

    // Log the evidence received action  
    logAction($conn, $examiner_id, "Evidence Received", $evidence_id, $examiner_id, 3, 2); // Assuming 3 is Forensic Examiner role ID and 2 is Investigator role ID  

    echo "<script>alert('Evidence marked as received!'); window.location='forensic_examiner_dashboard.php';</script>";  
}  

// Handle evidence transfer to lab personnel  
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transfer_evidence'])) {  
    $evidence_id = $_POST['evidence_id'];  
    $transfer_date = date("Y-m-d H:i:s");  
    $examiner_id = $_SESSION['user_id'];  
    $lab_personnel_id = $_POST['lab_personnel_id'];  

    // Update evidence transfer status to 'Transferred to Lab'  
    $sql_transfer = "UPDATE evidence SET transfer_status='Transferred', transfer_date=?, lab_personnel_id=? WHERE evidence_id=?";  
    $stmt = $conn->prepare($sql_transfer);  
    $stmt->bind_param("sii", $transfer_date, $lab_personnel_id, $evidence_id);  
    $stmt->execute();  
    $stmt->close();  

    // Log the transfer action  
    logAction($conn, $examiner_id, "Evidence Transfer", $evidence_id, $examiner_id, 3, 4); // Assuming 3 is Forensic Examiner role ID and 4 is Lab Personnel role ID  

    echo "<script>alert('Evidence transferred to Lab Personnel!'); window.location='forensic_examiner_dashboard.php';</script>";  
}  

// Handle evidence update  
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_evidence'])) {  
    $evidence_id = $_POST['evidence_id'];  
    $analysis_notes = trim($_POST['analysis_notes']);  
    $status = $_POST['status'];  
    $examiner_id = $_SESSION['user_id'];  
    $analysis_date = date("Y-m-d H:i:s");  

    // Update evidence details  
    $sql_update = "UPDATE evidence SET status=?, analysis_notes=?, analysis_date=?, examiner_id=? WHERE evidence_id=?";  
    $stmt = $conn->prepare($sql_update);  
    $stmt->bind_param("ssssi", $status, $analysis_notes, $analysis_date, $examiner_id, $evidence_id);  
    $stmt->execute();  
    $stmt->close();  

    echo "<script>alert('Evidence updated successfully!'); window.location='forensic_examiner_dashboard.php';</script>";  
}  

$conn->close();  
?>  

<!DOCTYPE html>  
<html lang="en">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Forensic Examiner Dashboard</title>  
    <style>  
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: white; display: flex; }  
        .sidebar { width: 250px; background: #16213e; padding: 20px; position: fixed; height: 100vh; }  
        .sidebar button { width: 100%; padding: 10px; background: #0f3460; border: none; color: white; margin-bottom: 10px; cursor: pointer; }  
        .sidebar button:hover { background: #1b4b91; }  
        .content { margin-left: 270px; padding: 20px; width: calc(100% - 270px); }  
        .form-container { background: #0f3460; padding: 20px; border-radius: 8px; margin-top: 20px; }  
        input, textarea, select { width: 100%; padding: 8px; margin: 10px 0; }  
        table { width: 100%; margin-top: 10px; border-collapse: collapse; }  
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }  
        button[disabled] { background: #555; cursor: not-allowed; }  
    </style>  
</head>  
<body>  
    <div class="sidebar">  
        <h2>Forensic Examiner</h2>  
        <button onclick="window.location.href='dashboard.php'">Main Dashboard</button>  
        <button onclick="toggleSection('received_evidence')">Received Evidence</button>  
        <button onclick="toggleSection('update_evidence')">Update Evidence</button>  
        <button onclick="toggleSection('transfer_evidence')">Transfer to Lab Personnel</button>  
        <button onclick="confirmLogout()">Logout</button>  
    </div>  
    <div class="content">  
        <h3>Welcome, Forensic Examiner!</h3>  

        <!-- Received Evidence Section -->  
        <div id="received_evidence" class="form-container" style="display:none;">  
            <h4>Received Evidence</h4>  
            <table>  
                <tr>  
                    <th>ID</th>  
                    <th>Evidence Name</th>  
                    <th>Description</th>  
                    <th>Case</th>  
                    <th>Investigator</th>  
                    <th>Actions</th>  
                </tr>  
                <?php foreach ($evidence_data as $evidence): ?>  
                <tr>  
                    <td><?php echo $evidence['evidence_id']; ?></td>  
                    <td><?php echo htmlspecialchars($evidence['evidence_name']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['description']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['case_name']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['investigator_name']); ?></td>  
                    <td>  
                        <?php if ($evidence['received_status'] === 'Pending'): ?>  
                        <form method="POST" action="">  
                            <input type="hidden" name="evidence_id" value="<?php echo $evidence['evidence_id']; ?>">  
                            <button type="submit" name="evidence_received">Mark as Received</button>  
                        </form>  
                        <?php else: ?>  
                        <span>Received</span>  
                        <?php endif; ?>  
                    </td>  
                </tr>  
                <?php endforeach; ?>  
            </table>  
        </div>  

        <!-- Update Evidence Section -->  
        <div id="update_evidence" class="form-container" style="display:none;">  
            <h4>Update Evidence</h4>  
            <form method="POST">  
                <label>Select Evidence:</label>  
                <select name="evidence_id" required>  
                    <option value="">-- Select Evidence --</option>  
                    <?php foreach ($evidence_data as $evidence): ?>  
                        <option value="<?php echo $evidence['evidence_id']; ?>"><?php echo htmlspecialchars($evidence['evidence_name']); ?></option>  
                    <?php endforeach; ?>  
                </select>  

                <label>Status:</label>  
                <select name="status" required>  
                    <option value="Pending">Pending</option>  
                    <option value="Under Review">Under Review</option>  
                    <option value="Analysis Complete">Analysis Complete</option>  
                </select>  

                <label>Assign Lab Personnel:</label>  
                <select name="lab_personnel_id" required>  
                    <option value="">-- Select Lab Personnel --</option>  
                    <?php foreach ($lab_personnel_data as $lab_personnel): ?>  
                        <option value="<?php echo $lab_personnel['user_id']; ?>"><?php echo htmlspecialchars($lab_personnel['username']); ?></option>  
                    <?php endforeach; ?>  
                </select>  

                <label>Analysis Notes:</label>  
                <textarea name="analysis_notes" placeholder="Enter analysis notes..." required></textarea>  

                <button type="submit" name="update_evidence">Update Evidence</button>  
            </form>  
        </div>  

        <!-- Transfer Evidence Section -->  
        <div id="transfer_evidence" class="form-container" style="display:none;">  
            <h4>Transfer Evidence to Lab Personnel</h4>  
            <table>  
                <tr>  
                    <th>ID</th>  
                    <th>Evidence Name</th>  
                    <th>Description</th>  
                    <th>Case</th>  
                    <th>Investigator</th>  
                    <th>Transfer Status</th>  
                    <th>Received Lab Personnel Status</th>  
                    <th>Assigned Lab Personnel</th>  
                    <th>Actions</th>  
                </tr>  
                <?php foreach ($evidence_data as $evidence): ?>  
                <tr>  
                    <td><?php echo $evidence['evidence_id']; ?></td>  
                    <td><?php echo htmlspecialchars($evidence['evidence_name']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['description']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['case_name']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['investigator_name']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['transfer_status']); ?></td>  
                    <td><?php echo htmlspecialchars($evidence['received_lab_personnel_status']); ?></td>  
                    <td>  
                        <?php  
                        if ($evidence['lab_personnel_id']) {  
                            $lab_personnel_name = "Unknown";  
                            foreach ($lab_personnel_data as $lab_personnel) {  
                                if ($lab_personnel['user_id'] == $evidence['lab_personnel_id']) {  
                                    $lab_personnel_name = $lab_personnel['username'];  
                                    break;  
                                }  
                            }  
                            echo htmlspecialchars($lab_personnel_name);  
                        } else {  
                            echo "Not Assigned";  
                        }  
                        ?>  
                    </td>  
                    <td>  
                        <form method="POST" action="">  
                            <input type="hidden" name="evidence_id" value="<?php echo $evidence['evidence_id']; ?>">  
                            <select name="lab_personnel_id" <?php echo ($evidence['transfer_status'] === 'Transferred') ? 'disabled' : ''; ?> required>  
                                <option value="">-- Select Lab Personnel --</option>  
                                <?php foreach ($lab_personnel_data as $lab_personnel): ?>  
                                    <option value="<?php echo $lab_personnel['user_id']; ?>"><?php echo htmlspecialchars($lab_personnel['username']); ?></option>  
                                <?php endforeach; ?>  
                            </select>  
                            <button type="submit" name="transfer_evidence" <?php echo ($evidence['transfer_status'] === 'Transferred') ? 'disabled' : ''; ?>>  
                                <?php echo ($evidence['transfer_status'] === 'Transferred') ? 'Transferred' : 'Transfer to Lab'; ?>  
                            </button>  
                        </form>  
                    </td>  
                </tr>  
                <?php endforeach; ?>  
            </table>  
        </div>  
    </div>  

    <script>  
        function toggleSection(sectionId) {  
            // Get all section elements  
            const sections = document.querySelectorAll('.form-container');  
            sections.forEach(function(section) {  
                if (section.id === sectionId) {  
                    section.style.display = section.style.display === 'none' ? 'block' : 'none';  
                } else {  
                    section.style.display = 'none';  
                }  
            });  
        }  

        function confirmLogout() {  
            if (confirm("Are you sure you want to log out?")) {  
                window.location.href = "logout.php";  
            }  
        }  
    </script>  
</body>  
</html>
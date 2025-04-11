<?php
session_start();

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "cocs2";

$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Clear the message when the page loads (not a form submission)
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $message = "";
}

// Handle Storage Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['store_evidence'])) {
    if (isset($_POST['evidence_id']) && !empty($_POST['evidence_id'])) {
        $evidence_id = intval($_POST['evidence_id']);
        $storage_location = isset($_POST['storage_location']) ? $_POST['storage_location'] : '';

        // Check if evidence ID exists before updating
        $check_query = "SELECT evidence_id FROM evidence WHERE evidence_id = '$evidence_id'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Update lab_status to 'Stored' and storage_location
            $update_query = "UPDATE evidence SET lab_status='Stored', storage_location='$storage_location' WHERE evidence_id='$evidence_id'";
            if (mysqli_query($conn, $update_query)) {
                $message = "Evidence stored successfully!";
            } else {
                $message = "Error storing evidence: " . mysqli_error($conn);
            }
        } else {
            $message = "Error: Evidence ID does not exist.";
        }
    } else {
        $message = "Error: Evidence ID is missing.";
    }
}

// Handle Prepare Evidence Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['prepare_evidence'])) {
    $evidence_id = intval($_POST['evidence_id']);
    $storage_location = $_POST['storage_location'];
    $court_prep_status = $_POST['court_prep_status'];
    $report_generated = isset($_POST['report_generated']) ? 1 : 0;
    $notes = $_POST['notes'];

    // Update evidence preparation details
    $update_query = "UPDATE evidence SET storage_location='$storage_location', court_prep_status='$court_prep_status', report_generated='$report_generated', notes='$notes' WHERE evidence_id='$evidence_id'";
    if (mysqli_query($conn, $update_query)) {
        $message = "Evidence preparation details updated successfully!";
    } else {
        $message = "Error updating evidence preparation details: " . mysqli_error($conn);
    }
}

// Handle Receive Evidence Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive_evidence'])) {
    $evidence_id = intval($_POST['evidence_id']);

    // Update received_lab_personnel_status to 'Received'
    $update_query = "UPDATE evidence SET received_lab_personnel_status='Received' WHERE evidence_id='$evidence_id'";
    if (mysqli_query($conn, $update_query)) {
        $message = "Evidence received successfully!";
    } else {
        $message = "Error receiving evidence: " . mysqli_error($conn);
    }
}

// Fetch evidence data
$result = mysqli_query($conn, "SELECT * FROM evidence");

// Fetch evidence data for JavaScript
$evidence_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $evidence_data[$row['evidence_id']] = $row['description'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Personnel Dashboard</title>
    <style>
        /* Your CSS styles remain unchanged */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background: #1e1e1e; /* Dark background */
            color: white; /* White text */
        }
        .sidebar {
            width: 200px;
            background: #2c3e50; /* Dark sidebar background */
            color: white;
            padding: 20px;
            height: 100vh;
            position: fixed; /* Fixed sidebar */
            overflow-y: auto; /* Allow scrolling if content is too long */
        }
        .sidebar h2 {
            text-align: center;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .sidebar button {
            display: block;
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background: #34495e; /* Dark button background */
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
        }
        .sidebar button:hover {
            background: #1abc9c; /* Hover effect */
        }
        .content {
            flex-grow: 1;
            padding: 20px;
            background: #1e1e1e; /* Dark background */
            margin-left: 250px; /* Offset for fixed sidebar */
        }
        h1 {
            text-align: center;
            font-size: 28px;
            color: white; /* White text */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #2c3e50; /* Dark table background */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
            color: white; /* White text */
        }
        table, th, td {
            border: 1px solid #444; /* Dark border */
        }
        th {
            background: #34495e; /* Dark header background */
            color: white;
            padding: 10px;
            text-align: center;
        }
        td {
            padding: 10px;
            text-align: center;
        }
        .success-message {
            background-color: #4CAF50; /* Green success message */
            color: white;
            padding: 10px;
            margin-bottom: 15px;
            text-align: center;
            display: none;
        }
        .store-button {
            background: #1abc9c; /* Green button */
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .store-button:hover {
            background: #16a085; /* Darker green on hover */
        }
        .form-container {
            background: #2c3e50; /* Dark form background */
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            color: white; /* White text */
        }
        .form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-container input[type="text"],
        .form-container select,
        .form-container textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #444; /* Dark border */
            border-radius: 4px;
            background: #34495e; /* Dark input background */
            color: white; /* White text */
        }
        .form-container input[type="radio"] {
            margin-right: 10px;
        }
        .form-container input[type="checkbox"] {
            margin-right: 10px;
        }
        .form-container button {
            background: #1abc9c; /* Green button */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background: #16a085; /* Darker green on hover */
        }
    </style>
    <script>
        // Evidence data from PHP
        const evidenceData = <?php echo json_encode($evidence_data); ?>;

        // Function to toggle section visibility
        function toggleSection(section) {
            const sectionElement = document.getElementById(section);
            if (sectionElement.style.display === "none" || sectionElement.style.display === "") {
                // Hide all sections
                document.getElementById("received_evidence").style.display = 'none';
                document.getElementById("prepare_evidence").style.display = 'none';
                document.getElementById("transfer_evidence").style.display = 'none';

                // Show the selected section
                sectionElement.style.display = 'block';
            } else {
                // Hide the selected section
                sectionElement.style.display = 'none';
            }
        }

        function showMessage() {
            document.getElementById("success-message").style.display = "block";
            setTimeout(() => {
                document.getElementById("success-message").style.display = "none";
            }, 3000);
        }

        // Automatically hide all sections on page load
        window.onload = function() {
            document.getElementById("received_evidence").style.display = 'none';
            document.getElementById("prepare_evidence").style.display = 'none';
            document.getElementById("transfer_evidence").style.display = 'none';
        };

        // Function to populate Evidence Description based on selected Evidence ID
        function populateDescription() {
            const evidenceId = document.getElementById("evidence_id").value;
            const evidenceDescription = document.getElementById("evidence_description");

            // Populate the description from the evidenceData object
            if (evidenceData[evidenceId]) {
                evidenceDescription.value = evidenceData[evidenceId];
            } else {
                evidenceDescription.value = "";
            }
        }

        // Function to confirm logout
        function confirmLogout() {
            if (confirm("Are you sure you want to log out?")) {
                // If user confirms, redirect to logout.php
                window.location.href = 'logout.php';
            } else {
                // If user cancels, redirect to dashboard.php
                window.location.href = 'dashboard.php';
            }
        }

        // Function to handle receiving evidence
        function receiveEvidence(evidenceId) {
            if (confirm("Are you sure you want to mark this evidence as received?")) {
                // Submit the form to update the database
                document.getElementById("receive_evidence_form_" + evidenceId).submit();
            }
        }
    </script>
</head>
<body>

<div class="sidebar">
    <h2>Lab Personnel</h2>
    <button onclick="window.location.href='dashboard.php'">Main Dashboard</button>
    <button onclick="toggleSection('received_evidence')">Received Evidence</button>
    <button onclick="toggleSection('prepare_evidence')">Prepare Evidence</button>
    <button onclick="toggleSection('transfer_evidence')">Transfer Evidence</button>
    <button onclick="confirmLogout()">Logout</button>
</div>

<div class="content">
    <h1>Welcome to Lab Personnel Dashboard</h1>

    <!-- Success Message -->
    <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($message)) { ?>
        <div class="success-message" id="success-message">
            <?php echo $message; ?>
        </div>
        <script>showMessage();</script>
    <?php } ?>

    <!-- Received Evidence Section -->
    <div id="received_evidence" style="display: none;">
        <h2>Received Evidence</h2>
        <table>
            <tr>
                <th>Evidence ID</th>
                <th>Evidence Name</th>
                <th>Description</th>
                <th>Collection Date</th>
                <th>Case ID</th>
                <th>Receive Evidence</th>
            </tr>
            <?php
            mysqli_data_seek($result, 0);
            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['evidence_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['evidence_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['collection_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['case_id']); ?></td>
                        <td>
                            <?php if ($row['received_lab_personnel_status'] != 'Received') { ?>
                                <form id="receive_evidence_form_<?php echo $row['evidence_id']; ?>" method="post" action="">
                                    <input type="hidden" name="evidence_id" value="<?php echo $row['evidence_id']; ?>">
                                    <button type="submit" name="receive_evidence" class="store-button">Receive</button>
                                </form>
                            <?php } else { ?>
                                <span style="color: green;">Received</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php }
            } else { ?>
                <tr><td colspan="6">No records found.</td></tr>
            <?php } ?>
        </table>
    </div>

    <!-- Prepare Evidence Section -->
    <div id="prepare_evidence" style="display: none;">
        <h2>Prepare Evidence</h2>
        <div class="form-container">
            <form method="post" action="">
                <label for="evidence_id">Evidence ID:</label>
                <select name="evidence_id" id="evidence_id" onchange="populateDescription()" required>
                    <option value="">Select Evidence ID</option>
                    <?php
                    mysqli_data_seek($result, 0);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) { ?>
                            <option value="<?php echo htmlspecialchars($row['evidence_id']); ?>">
                                <?php echo htmlspecialchars($row['evidence_id']); ?>
                            </option>
                        <?php }
                    } ?>
                </select>

                <label for="evidence_description">Evidence Description:</label>
                <input type="text" id="evidence_description" name="evidence_description" readonly>

                <label for="storage_location">Storage Location:</label>
                <select name="storage_location" id="storage_location" required>
                    <option value="">Select Storage Location</option>
                    <option value="Cloud Storage - AWS S3">Cloud Storage - AWS S3</option>
                    <option value="Cloud Storage - Google Drive">Cloud Storage - Google Drive</option>
                    <option value="On-Premises Server - Server A">On-Premises Server - Server A</option>
                    <option value="On-Premises Server - Server B">On-Premises Server - Server B</option>
                    <option value="External Hard Drive - Drive 1">External Hard Drive - Drive 1</option>
                    <option value="External Hard Drive - Drive 2">External Hard Drive - Drive 2</option>
                    <option value="Network Attached Storage (NAS)">Network Attached Storage (NAS)</option>
                    <option value="Encrypted USB Drive">Encrypted USB Drive</option>
                </select>

                <label>Court Prep Status:</label>
                <div>
                    <input type="radio" id="pending" name="court_prep_status" value="Pending" required>
                    <label for="pending">Pending</label>
                    <input type="radio" id="completed" name="court_prep_status" value="Completed">
                    <label for="completed">Completed</label>
                </div>

                <label for="report_generated">Report Generated:</label>
                <div>
                    <input type="checkbox" id="report_generated" name="report_generated" value="1">
                    <label for="report_generated">Yes</label>
                </div>

                <label for="notes">Notes/Comments:</label>
                <textarea id="notes" name="notes" rows="4"></textarea>

                <button type="submit" name="prepare_evidence">Submit</button>
            </form>
        </div>
    </div>

    <!-- Transfer Evidence Section -->
    <div id="transfer_evidence" style="display: none;">
        <h2>Transfer Evidence</h2>
        <table>
            <tr>
                <th>Evidence ID</th>
                <th>Evidence Name</th>
                <th>Case Name</th>
                <th>Description</th>
                <th>Collection Date</th>
                <th>Storage Location</th>
                <th>Storage Action</th>
                <th>Lab Status</th>
            </tr>
            <?php
            mysqli_data_seek($result, 0);
            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['evidence_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['evidence_name']); ?></td>
                        <td>Case #<?php echo htmlspecialchars($row['case_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['collection_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['storage_location']); ?></td>
                        <td>
                            <form method="post" action="">
                                <input type="hidden" name="evidence_id" value="<?php echo htmlspecialchars($row['evidence_id']); ?>">
                                <input type="hidden" name="storage_location" value="<?php echo htmlspecialchars($row['storage_location']); ?>">
                                <button type="submit" name="store_evidence" class="store-button">Store</button>
                            </form>
                        </td>
                        <td>
                            <?php echo ($row['lab_status'] == 'Stored') ? '<span style="color: green;">Stored</span>' : '<span style="color: red;">Not Stored</span>'; ?>
                        </td>
                    </tr>
                <?php }
            } else { ?>
                <tr><td colspan="8">No records found.</td></tr>
            <?php } ?>
        </table>
    </div>

</div>

</body>
</html>
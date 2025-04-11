<?php
session_start();
include 'db.php'; // Database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

// Map role IDs to role names
$roleNames = [
    1 => "Investigator",
    2 => "Forensic Examiner",
    3 => "Lab Personnel",
    4 => "System Admin"
];

// Get the user's role ID from the session
$role_id = $_SESSION['role_id']; // Ensure this is set during login
$user_role = $roleNames[$role_id]; // Map role ID to role name

// Fetch submitted evidence (evidence transferred from Lab Personnel)
$sql_submitted = "SELECT * FROM submitted_evidence";
$submitted_result = $conn->query($sql_submitted);

if (!$submitted_result) {
    die("Error fetching submitted evidence: " . $conn->error);
}

// Initialize $tracking_result
$tracking_result = null;

// Fetch evidence tracking details with joined data
$sql_tracking = "
    SELECT 
        e.evidence_id,
        e.evidence_name,
        c.case_name,
        COALESCE(et.from_role, 'Investigator') AS collected_by, -- Default to 'Investigator' if missing
        COALESCE(e.assigned_to, 'N/A') AS transferred_to, -- Use assigned_to for Transferred To
        e.status,
        e.collection_date,
        e.storage_location -- Add storage_location from the evidence table
    FROM evidence e
    LEFT JOIN cases c ON e.case_id = c.case_id
    LEFT JOIN evidence_tracking et ON e.evidence_id = et.evidence_id
";
$tracking_result = $conn->query($sql_tracking);

if (!$tracking_result) {
    die("Error fetching tracked evidence: " . $conn->error);
}

// Fetch evidence data for the Received Evidence section
$sql_received = "SELECT * FROM evidence";
$received_result = $conn->query($sql_received);

if (!$received_result) {
    die("Error fetching received evidence: " . $conn->error);
}

// Fetch all evidence IDs for the dropdown
$sql_evidence_ids = "SELECT evidence_id FROM evidence";
$evidence_ids_result = $conn->query($sql_evidence_ids);

if (!$evidence_ids_result) {
    die("Error fetching evidence IDs: " . $conn->error);
}

// Handle search for evidence details
$search_result = null;
if (isset($_GET['search_id'])) {
    $evidence_id = $_GET['search_id'];
    $sql_search = "
        SELECT 
            e.evidence_id,
            e.evidence_name,
            c.case_name,
            COALESCE(et.from_role, 'Investigator') AS collected_by,
            COALESCE(e.assigned_to, 'N/A') AS transferred_to,
            e.status,
            e.collection_date,
            e.storage_location -- Add storage_location from the evidence table
        FROM evidence e
        LEFT JOIN cases c ON e.case_id = c.case_id
        LEFT JOIN evidence_tracking et ON e.evidence_id = et.evidence_id
        WHERE e.evidence_id = '$evidence_id'
    ";
    $search_result = $conn->query($sql_search);

    if (!$search_result) {
        die("Error fetching search results: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence Storage Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #0A1F3D; /* Dark forensic blue */
            color: white;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #0A1A2F; /* Darker blue sidebar */
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            color: white;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            padding: 10px;
            border-bottom: 1px solid #1B4F72;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .sidebar ul li a:hover {
            background: #1B4F72;
        }
        .content {
            margin-left: 270px;
            padding: 20px;
        }
        .section {
            display: none;
            background: #0F2A47;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #1B4F72;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background: #1B4F72;
            color: white;
        }
        .search-bar {
            margin-bottom: 20px;
        }
        .search-bar input[type="text"] {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #1B4F72;
            width: 300px;
        }
        .search-bar input[type="submit"] {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            background: #1B4F72;
            color: white;
            cursor: pointer;
        }
        .search-bar input[type="submit"]:hover {
            background: #0F2A47;
        }
        .print-button {
            margin-bottom: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            background: #1B4F72;
            color: white;
            cursor: pointer;
        }
        .print-button:hover {
            background: #0F2A47;
        }
    </style>
    <!-- Include jsPDF, AutoTable, and FileSaver.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script>
        window.jsPDF = window.jspdf.jsPDF; // Initialize jsPDF
    </script>
</head>
<body>

<div class="sidebar">
    <h2>Storage Dashboard</h2>
    <ul>
        <li><a href="dashboard.php">Main Dashboard</a></li>
        <li><a href="#" onclick="toggleSection('received'); return false;">Received Evidence</a></li>
        <li><a href="#" onclick="toggleSection('tracked'); return false;">Tracked Evidence</a></li>
        <li><a href="#" onclick="toggleSection('storage'); return false;">Storage Location</a></li>
        <li><a href="#" class="logout-btn" onclick="confirmLogout(); return false;">Logout</a></li>
    </ul>
</div>

<div class="content">
    <h1>Evidence Storage Management</h1>

    <!-- Received Evidence Section -->
    <div id="received" class="section" style="display: none;">
        <h2>Received Evidence</h2>
        <button class="print-button" onclick="exportToPDF('receivedTable', 'Received_Evidence.pdf')">Print as PDF</button>
        <table id="receivedTable">
            <tr>
                <th>Evidence ID</th>
                <th>Evidence Name</th>
                <th>Case Name</th>
                <th>Description</th>
                <th>Collection Date</th>
                <th>Storage Location</th>
            </tr>
            <?php
            if ($received_result->num_rows > 0) {
                while ($row = $received_result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$row['evidence_id']}</td>
                            <td>{$row['evidence_name']}</td>
                            <td>{$row['case_id']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['collection_date']}</td>
                            <td>{$row['storage_location']}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No records found</td></tr>";
            }
            ?>
        </table>
    </div>

    <!-- Tracked Evidence Section -->
    <div id="tracked" class="section" style="display: none;">
        <h2>Tracked Evidence</h2>
        <button class="print-button" onclick="exportToPDF('trackedTable', 'Tracked_Evidence.pdf')">Print as PDF</button>
        <table id="trackedTable">
            <tr>
                <th>Evidence ID</th>
                <th>Evidence Name</th>
                <th>Case Name</th>
                <th>Collected By</th>
                <th>Transferred To</th>
                <th>Current Status</th>
                <th>Collection Date</th>
                <th>Storage Location</th>
            </tr>
            <?php
            if ($tracking_result && $tracking_result->num_rows > 0) {
                while ($row = $tracking_result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$row['evidence_id']}</td>
                            <td>{$row['evidence_name']}</td>
                            <td>" . ($row['case_name'] ?? 'N/A') . "</td>
                            <td>" . ($row['collected_by'] ?? 'N/A') . "</td>
                            <td>" . ($row['transferred_to'] ?? 'N/A') . "</td>
                            <td>{$row['status']}</td>
                            <td>" . ($row['collection_date'] ?? 'N/A') . "</td>
                            <td>{$row['storage_location']}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No tracked evidence found.</td></tr>";
            }
            ?>
        </table>
    </div>

    <!-- Storage Location Section -->
    <div id="storage" class="section" style="display: none;">
        <h2>Storage Location</h2>
        <div class="search-bar">
            <form method="GET" action="">
                <select name="search_id" required>
                    <option value="">Select Evidence ID</option>
                    <?php
                    if ($evidence_ids_result->num_rows > 0) {
                        while ($row = $evidence_ids_result->fetch_assoc()) {
                            echo "<option value='{$row['evidence_id']}'>{$row['evidence_id']}</option>";
                        }
                    }
                    ?>
                </select>
                <input type="submit" value="Search">
            </form>
        </div>
        <?php if (isset($search_result)): ?>
            <table>
                <tr>
                    <th>Evidence ID</th>
                    <th>Evidence Name</th>
                    <th>Case Name</th>
                    <th>Collected By</th>
                    <th>Transferred To</th>
                    <th>Current Status</th>
                    <th>Collection Date</th>
                    <th>Storage Location</th>
                </tr>
                <?php
                if ($search_result->num_rows > 0) {
                    while ($row = $search_result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['evidence_id']}</td>
                                <td>{$row['evidence_name']}</td>
                                <td>" . ($row['case_name'] ?? 'N/A') . "</td>
                                <td>" . ($row['collected_by'] ?? 'N/A') . "</td>
                                <td>" . ($row['transferred_to'] ?? 'N/A') . "</td>
                                <td>{$row['status']}</td>
                                <td>" . ($row['collection_date'] ?? 'N/A') . "</td>
                                <td>{$row['storage_location']}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No records found for the given Evidence ID</td></tr>";
                }
                ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSection(section) {
    // Get the selected section
    var selectedSection = document.getElementById(section);

    // Toggle visibility
    if (selectedSection.style.display === "none" || selectedSection.style.display === "") {
        selectedSection.style.display = "block"; // Show the section
    } else {
        selectedSection.style.display = "none"; // Hide the section
    }
}

function confirmLogout() {
    let confirmAction = confirm("Are you sure you want to log out?");
    if (confirmAction) {
        window.location.href = "logout.php";
    }
}

// Function to export table data to PDF
function exportToPDF(tableId, fileName) {
    const table = document.getElementById(tableId);
    if (table.rows.length <= 1) { // Check if the table has no data (only headers)
        alert("No data to export!");
        return;
    }
    const doc = new jsPDF();
    doc.autoTable({ html: `#${tableId}` }); // Use template literal
    doc.save(fileName);
}
</script>

</body>
</html>
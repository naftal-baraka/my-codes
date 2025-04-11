<?php   
// Include database connection and start session
require_once 'db.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user details from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_id = $_SESSION['role_id'];

// Fetch role from database
$sql = "SELECT * FROM roles WHERE role_id='$role_id' LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $role = $result->fetch_assoc();
} else {
    die("Role not found.");
}

// Role-based dashboard content
$role_content = "Welcome, $username!";
$allowed_role = strtolower(str_replace(' ', '_', $role['role_name']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Secure Chain of Custody Dashboard</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      background-image: url('images/background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .navbar {
      background-color: #0A1F3D;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      color: white;
      width: 160px;
      position: fixed;
      height: 100%;
      transition: width 0.3s ease-in-out;
      overflow: hidden;
    }

    .hamburger-container {
      display: flex;
      align-items: center;
      cursor: pointer;
      margin-bottom: 20px;
    }
    .navbar:hover{
      width: 13%;
    }

    .hamburger {
      width: 30px;
      right: 5px;
      height: 25px;
      position: relative;
    }

    .hamburger div {
      width: 100%;
      height: 5px;
      background-color: white;
      margin: 5px 0;
      transition: transform 0.3s, opacity 0.3s;
    }

    .navbar.open .menu {
      visibility: visible;
      opacity: 1;
    }

    .navbar a {
      text-decoration: none;
      color: white;
      margin-bottom: 20px;
      font-size: 1.1rem;
      transition: color 0.3s;
      cursor: pointer;
    }

    .menu {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Styles for the role and evidence storage buttons */
    .menu a {
      display: block;
      width: 90%;
      text-align: center;
      padding: 12px;
      margin: 5px 0;
      background-color: #4b5563;
      border-radius: 8px;
      transition: background-color 0.3s;
      font-size: 1.1rem;
      color: white;
    }

    .menu a:hover {
      background-color: #374151;
      width: 95%;
    }

    /* Adding symbols to the buttons */
    .roles-btn {
      background-color: #1d4ed8; /* Blue for roles button */
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .roles-btn:before {
      content: '👥'; /* Add a user group icon */
      margin-right: 10px;
    }

    .evidence-storage-btn {
      background-color: #10b981; /* Green for evidence storage button */
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .evidence-storage-btn:before {
      content: '📦'; /* Add a package box icon */
      margin-right: 10px;
    }

    /* Roles list styles */
    .roles-list {
      display: none;
      flex-direction: column;
      margin-top: 20px;
    }

    .roles-list .icon {
      width: 90px;
      height: 50px;
      background-color: #374151;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
      cursor: pointer;
      transition: background-color 0.3s;
      text-align: center;
      padding: 0.5rem;
    }

    .roles-list .icon:hover {
      background-color: #4b5563;
      width: 60%;
    }

    .main-content {
      flex: 1;
      padding: 2rem;
      overflow-y: auto;
      margin-left: 180px;
      color: white;
      position: relative;
    }

    /* Logout button styling */
    .logout-container {
      position: absolute;
      top: 20px;
      right: 20px;
    }

    .logout-btn {
      background-color: #ff4d4d;
      color: white;
      border: none;
      padding: 10px 20px;
      cursor: pointer;
      font-size: 16px;
      border-radius: 5px;
      transition: background 0.3s;
      display: flex;
      align-items: center;
    }

    .logout-btn:hover {
      background-color: #cc0000;
    }

    .logout-btn i {
      margin-right: 8px;
    }
    footer {
      background-color: #00193186;
      color: white;
      text-align: center;
      padding: 1rem 1;
      position: relative; /* Needed for sticky footer */
      bottom: 0; /* Stick to the bottom */
      width: 85%;
      left: 15%;
      top: 0%;
    }
  </style>
</head>
<body>
  <div class="navbar" id="navbar">
    <div class="hamburger-container" onclick="toggleMenu()">
      <div class="hamburger">
        <div></div>
        <div></div>
        <div></div>
      </div>
      <span>HOME</span>
    </div>
    <div class="menu" id="menu" style="display: none;">
      <!-- Roles button -->
      <a href="#" class="roles-btn" onclick="toggleRoles()">Roles</a>
      <div class="roles-list" id="roles-list" style="display: none;">
        <div class="icon" onclick="navigateTo('investigator')" title="Investigator">
          🔍<span>Investigator</span>
        </div>
        <div class="icon" onclick="navigateTo('forensic_examiner')" title="Digital Forensic Examiner">
          🧪<span>Forensic Examiner</span>
        </div>
        <div class="icon" onclick="navigateTo('lab_personnel')" title="Lab Personnel">
          🧫<span>Lab Personnel</span>
        </div>
        <div class="icon" onclick="navigateTo('system_admin')" title="System Administrator">
          ⚙<span>System Admin</span>
        </div>
      </div>
      <!-- Evidence Storage button -->
      <a href="evidence_storage.php" class="evidence-storage-btn">Evidence Storage</a>
    </div>
  </div>

  <div class="main-content">
    <h1><?php echo $role_content; ?></h1>
    <p>You're logged in as a <?php echo htmlspecialchars($role['role_name']); ?></p>

    <!-- Logout Button -->
    <div class="logout-container">
      <button class="logout-btn" onclick="confirmLogout()">
        🚪 Logout
      </button>
    </div>
  </div>
<footer >
  <p>&copy;2025 team 16 (Linet,Annette,Naftal)</p>
  </footer>
  
  <script>
    function toggleMenu() {
      let menu = document.getElementById("menu");
      menu.style.display = menu.style.display === "none" ? "block" : "none";
    }

    function toggleRoles() {
      let rolesList = document.getElementById("roles-list");
      rolesList.style.display = rolesList.style.display === "none" ? "flex" : "none";
    }

    function navigateTo(role) {
      let allowedRole = "<?php echo $allowed_role; ?>";
      if (role !== allowedRole) {
        alert("Access Denied: You are not authorized to access this dashboard.");
        return;
      }
      window.location.href = role + "_dashboard.php";
    }

    function confirmLogout() {
      let confirmAction = confirm("Are you sure you want to log out?");
      if (confirmAction) {
        window.location.href = "logout.php";
      }
    }
  </script>
</body>
</html>
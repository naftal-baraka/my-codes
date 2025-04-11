<?php 
// Database connection details
$host = "localhost"; // If using XAMPP, this is correct
$db_name = "cocs2"; // Your database name
$username = "root"; // Default XAMPP MySQL username
$password = ""; // Default is empty in XAMPP

// Create a connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check if connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure OTP fields exist in the users table
$otp_table_check = "
    ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS otp_expires DATETIME DEFAULT NULL;
";
$conn->query($otp_table_check);
?>

<?php
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$credential = $data['credential'];

error_log("Received credential: " . $credential); // Log the received data

$sql = "SELECT fingerprint_data FROM fingerprints WHERE fingerprint_data = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $credential);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}

$stmt->close();
?>
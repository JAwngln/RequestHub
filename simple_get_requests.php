<?php
include 'connect.php';

header('Content-Type: application/json');

// Check database connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Create table if it doesn't exist (same as in simple_submit.php)
$createTableSQL = "CREATE TABLE IF NOT EXISTS vehicle_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    travel_date DATE NOT NULL,
    destination VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    passengers VARCHAR(50) NOT NULL,
    requested_by VARCHAR(100) NOT NULL,
    request_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    reviewed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableSQL)) {
    echo json_encode(['error' => 'Table creation failed: ' . $conn->error]);
    exit;
}

// Migrate older installs that used the 'rejected' status name to 'declined'
$conn->query("UPDATE vehicle_requests SET status = 'declined' WHERE status = 'rejected'");
$statusCol = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'status'");
if ($statusCol && ($statusRow = $statusCol->fetch_assoc()) && strpos($statusRow['Type'], "'rejected'") !== false) {
    $conn->query("ALTER TABLE vehicle_requests MODIFY status ENUM('pending', 'approved', 'declined') DEFAULT 'pending'");
}

// Get all requests from database.
// Explicit columns: attachment_data is a LONGBLOB, and "SELECT *" made the json_encode()
// below return false on malformed UTF-8, so this endpoint emitted an empty body.
$sql = "SELECT id, ticket_no, ticket_year, travel_date, destination, purpose, passengers,
               requested_by, user_email, attachment_name, request_date, status,
               reviewed_at, notes, created_at
        FROM vehicle_requests ORDER BY created_at DESC";
$result = $conn->query($sql);

$requests = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
} else {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit;
}

echo json_encode($requests);
?>

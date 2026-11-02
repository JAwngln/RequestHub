<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Debug logging
error_log("get_users.php called");
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is a planning officer
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    error_log("Unauthorized access attempt");
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get all users from the database
$sql = "SELECT id, firstName, lastName, email, password, role FROM users ORDER BY id DESC";
error_log("SQL Query: " . $sql);
$stmt = $conn->prepare($sql);

if ($stmt) {
    error_log("Statement prepared successfully");
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("Query executed, result: " . ($result ? "success" : "failed"));
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'email' => $row['email'],
            'password' => $row['password'],
            'role' => $row['role']
        ];
    }
    
    error_log("Users found: " . count($users));
    error_log("Final response: " . json_encode(['success' => true, 'users' => $users]));
    echo json_encode(['success' => true, 'users' => $users]);
    $stmt->close();
} else {
    error_log("Failed to prepare statement: " . $conn->error);
    echo json_encode(['error' => 'Failed to prepare statement: ' . $conn->error]);
}

$conn->close();
?>

<?php
session_start();
require_once '../../connect.php';
require_once '../../activity_log.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentEmail = $_SESSION['email'];
    
    // Validate inputs
    if (empty($firstName) || empty($lastName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    // Check if email is being changed and if it already exists for another user
    if ($email !== $currentEmail) {
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND email != ?");
        $checkEmail->bind_param("ss", $email, $currentEmail);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        $checkEmail->close();
    }
    
    // Update user information
    $stmt = $conn->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ? WHERE email = ?");
    $stmt->bind_param("ssss", $firstName, $lastName, $email, $currentEmail);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        
        // Update session variables
        $_SESSION['firstName'] = $firstName;
        $_SESSION['lastName'] = $lastName;
        $_SESSION['email'] = $email;

        if ($affectedRows > 0) {
            $description = $email !== $currentEmail
                ? "Updated account details and changed email from {$currentEmail} to {$email}."
                : 'Updated account details.';
            log_activity($conn, 'account_updated', $description);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Account updated successfully. Rows affected: ' . $affectedRows,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update account: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>

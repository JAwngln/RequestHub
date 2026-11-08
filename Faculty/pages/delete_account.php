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
    $email = $_SESSION['email'];
    
    // Delete user account
    $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Record the deletion while the session still identifies the user.
            log_activity($conn, 'account_deleted', 'Deleted their faculty account.');

            // Destroy session
            session_unset();
            session_destroy();

            echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Account not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>

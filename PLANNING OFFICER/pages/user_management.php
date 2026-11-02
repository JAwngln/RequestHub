<?php
session_start();
require_once '../../connect.php';

// Clear any output buffers to prevent HTML mixing with JSON
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

// Check if user is logged in and is a planning officer
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'view_user':
        $userId = $_GET['id'] ?? 0;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }
        
        $sql = "SELECT id, firstName, lastName, email, password, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
        $stmt->close();
        break;
        
    case 'delete_user':
        error_log("Delete user request received");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));
        
        $userId = $_POST['id'] ?? 0;
        error_log("User ID to delete: " . $userId);
        
        if ($userId <= 0) {
            error_log("Invalid user ID: " . $userId);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }
        
        // Prevent deleting the current user
        if ($userId == $_SESSION['user_id'] ?? 0) {
            error_log("Attempted to delete own account");
            echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
            exit;
        }
        
        $sql = "DELETE FROM users WHERE id = ?";
        error_log("SQL: " . $sql);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("i", $userId);
        error_log("Bound parameter: " . $userId);
        
        if ($stmt->execute()) {
            error_log("Statement executed successfully");
            error_log("Affected rows: " . $stmt->affected_rows);
            
            if ($stmt->affected_rows > 0) {
                error_log("User deleted successfully");
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                error_log("No rows affected - user not found");
                echo json_encode(['success' => false, 'error' => 'User not found or already deleted']);
            }
        } else {
            error_log("Failed to execute statement: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Failed to delete user: ' . $stmt->error]);
        }
        $stmt->close();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
?>

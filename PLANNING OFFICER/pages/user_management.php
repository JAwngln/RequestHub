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
        
    case 'update_role':
        $userId = (int)($_POST['id'] ?? 0);
        $newRole = trim((string)($_POST['role'] ?? ''));
        $allowedRoles = ['faculty', 'planning_officer'];

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }
        if (!in_array($newRole, $allowedRoles, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid role']);
            break;
        }

        // Resolve the signed-in account so an officer cannot change their own
        // role and lock themselves out of the dashboard.
        $currentUserId = (int)($_SESSION['userId'] ?? 0);
        if ($currentUserId <= 0 && !empty($_SESSION['email'])) {
            $currentStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $currentStmt->bind_param("s", $_SESSION['email']);
            $currentStmt->execute();
            $currentRow = $currentStmt->get_result()->fetch_assoc();
            $currentStmt->close();
            $currentUserId = (int)($currentRow['id'] ?? 0);
        }
        if ($currentUserId > 0 && $userId === $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'You cannot change your own role.']);
            break;
        }

        $lookup = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $lookup->bind_param("i", $userId);
        $lookup->execute();
        $targetUser = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$targetUser) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            break;
        }

        if ($targetUser['role'] === $newRole) {
            echo json_encode([
                'success' => true,
                'role' => $newRole,
                'message' => 'That user already has this role.'
            ]);
            break;
        }

        $update = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $update->bind_param("si", $newRole, $userId);

        if ($update->execute()) {
            $roleLabel = $newRole === 'planning_officer' ? 'Admin' : 'Faculty';
            echo json_encode([
                'success' => true,
                'role' => $newRole,
                'message' => 'User role changed to ' . $roleLabel . '. The user may need to sign in again.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update user role: ' . $update->error]);
        }
        $update->close();
        break;

    case 'update_password':
        $userId = (int)($_POST['id'] ?? 0);
        $newPassword = trim((string)($_POST['password'] ?? ''));
        $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            break;
        }
        if ($newPassword === '' || $confirmPassword === '') {
            echo json_encode(['success' => false, 'error' => 'Enter and confirm the new password.']);
            break;
        }
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'error' => 'Use at least 6 characters for the new password.']);
            break;
        }
        if (strlen($newPassword) > 100) {
            echo json_encode(['success' => false, 'error' => 'The new password is too long (maximum 100 characters).']);
            break;
        }
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'error' => 'The passwords do not match.']);
            break;
        }

        // Resolve the signed-in account. Officers change their own password
        // from Account Settings, not from the user list.
        $currentUserId = (int)($_SESSION['userId'] ?? 0);
        if ($currentUserId <= 0 && !empty($_SESSION['email'])) {
            $currentStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $currentStmt->bind_param("s", $_SESSION['email']);
            $currentStmt->execute();
            $currentRow = $currentStmt->get_result()->fetch_assoc();
            $currentStmt->close();
            $currentUserId = (int)($currentRow['id'] ?? 0);
        }
        if ($currentUserId > 0 && $userId === $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'You cannot change your own password from this page. Use Account Settings instead.']);
            break;
        }

        $lookup = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $lookup->bind_param("i", $userId);
        $lookup->execute();
        $targetUser = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$targetUser) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            break;
        }

        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $newPassword, $userId);

        if ($update->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password updated successfully.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update the password: ' . $update->error]);
        }
        $update->close();
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

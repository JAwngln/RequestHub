<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

header('Content-Type: application/json');

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'submit_borrowed_item':
        // Handle borrowed item submission
        $names = isset($_SESSION['firstName']) && isset($_SESSION['lastName']) 
            ? $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] 
            : (isset($_SESSION['email']) ? $_SESSION['email'] : 'Faculty User');
        
        $items = $_POST['items'] ?? '';
        $quantity = $_POST['quantity'] ?? 0;
        $timeBorrow = $_POST['timeBorrow'] ?? '';
        $timeReturn = $_POST['timeReturn'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if (!empty($names) && !empty($items) && !empty($timeBorrow) && !empty($timeReturn) && $quantity > 0 && !empty($reason)) {
            $sql = "INSERT INTO borrow_items (names, items, quantity, time_of_borrow, time_of_return, reason, action) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisss", $names, $items, $quantity, $timeBorrow, $timeReturn, $reason);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Borrowed item submitted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error submitting borrowed item']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        }
        break;
        
    case 'get_borrowed_items':
        // Get all borrowed items for the faculty
        $sql = "SELECT * FROM borrow_items WHERE names = ? ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $facultyName = isset($_SESSION['firstName']) && isset($_SESSION['lastName']) 
            ? $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] 
            : (isset($_SESSION['email']) ? $_SESSION['email'] : 'Faculty User');
        
        $stmt->bind_param("s", $facultyName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => $row['id'],
                'names' => $row['names'],
                'items' => $row['items'],
                'quantity' => $row['quantity'],
                'reason' => $row['reason'],
                'timeBorrow' => $row['time_of_borrow'],
                'timeReturn' => $row['time_of_return'],
                'requestDate' => $row['requestDate'],
                'status' => $row['status']
            ];
        }
        
        echo json_encode($items);
        break;
        
    case 'cancel_borrowed_item':
        // Update borrowed item status to cancelled
        $id = $_POST['id'] ?? '';
        $sql = "UPDATE borrow_items SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Borrowed item cancelled successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error cancelling item']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$stmt->close();
?>

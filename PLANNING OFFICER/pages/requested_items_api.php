<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

header('Content-Type: application/json');

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$action = $data['action'];
$requestId = $data['request_id'] ?? 0;

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit();
}

switch ($action) {
    case 'approveRequest':
        approveRequest($requestId);
        break;
    case 'declineRequest':
        declineRequest($requestId, $data['reason'] ?? '');
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

function approveRequest($requestId) {
    global $conn;
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get request details
        $getRequestSql = "SELECT items, quantity, action FROM requested_items WHERE id = ?";
        $stmt = $conn->prepare($getRequestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Request not found');
        }
        
        $request = $result->fetch_assoc();
        $itemName = $request['items'];
        $quantity = (int)$request['quantity'];
        $currentStatus = $request['action'];
        
        // Check if already approved
        if ($currentStatus === 'approved') {
            throw new Exception('Request is already approved');
        }
        
        $newQuantity = null;
        
        // Stock is reserved when the request is submitted, so a pending request
        // needs no further deduction. Re-approving a declined request reserves
        // the stock again.
        if ($currentStatus === 'declined') {
            $getInventorySql = "SELECT quantity FROM inventory_management WHERE item_name = ?";
            $stmt = $conn->prepare($getInventorySql);
            $stmt->bind_param("s", $itemName);
            $stmt->execute();
            $inventoryResult = $stmt->get_result();
            
            if ($inventoryResult->num_rows === 0) {
                throw new Exception('Item not found in inventory');
            }
            
            $inventory = $inventoryResult->fetch_assoc();
            $currentInventoryQuantity = (int)$inventory['quantity'];
            
            if ($currentInventoryQuantity < $quantity) {
                throw new Exception("Not enough stock. Available: $currentInventoryQuantity, Requested: $quantity");
            }
            
            $newQuantity = $currentInventoryQuantity - $quantity;
            
            $updateInventorySql = "UPDATE inventory_management SET quantity = ? WHERE item_name = ?";
            $stmt = $conn->prepare($updateInventorySql);
            $stmt->bind_param("is", $newQuantity, $itemName);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update inventory');
            }
        }
        
        // Update request status
        $updateRequestSql = "UPDATE requested_items SET action = 'approved' WHERE id = ?";
        $stmt = $conn->prepare($updateRequestSql);
        $stmt->bind_param("i", $requestId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update request status');
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Request approved successfully!',
            'inventory_deducted' => $currentStatus === 'declined' ? $quantity : 0,
            'new_quantity' => $newQuantity
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function declineRequest($requestId, $reason) {
    global $conn;
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get request details
        $getRequestSql = "SELECT items, quantity, action FROM requested_items WHERE id = ?";
        $stmt = $conn->prepare($getRequestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Request not found');
        }
        
        $request = $result->fetch_assoc();
        $itemName = $request['items'];
        $quantity = $request['quantity'];
        $currentStatus = $request['action'];
        
        // Check if already declined
        if ($currentStatus === 'declined') {
            throw new Exception('Request is already declined');
        }
        
        // Stock was reserved at submission, so declining always releases it.
        $getInventorySql = "SELECT quantity FROM inventory_management WHERE item_name = ?";
        $stmt = $conn->prepare($getInventorySql);
        $stmt->bind_param("s", $itemName);
        $stmt->execute();
        $inventoryResult = $stmt->get_result();
        
        if ($inventoryResult->num_rows === 0) {
            throw new Exception('Item not found in inventory');
        }
        
        $inventory = $inventoryResult->fetch_assoc();
        $currentInventoryQuantity = (int)$inventory['quantity'];
        $newQuantity = $currentInventoryQuantity + $quantity;
        
        $updateInventorySql = "UPDATE inventory_management SET quantity = ? WHERE item_name = ?";
        $stmt = $conn->prepare($updateInventorySql);
        $stmt->bind_param("is", $newQuantity, $itemName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to restore inventory');
        }
        
        // Update request status
        $updateRequestSql = "UPDATE requested_items SET action = 'declined' WHERE id = ?";
        $stmt = $conn->prepare($updateRequestSql);
        $stmt->bind_param("i", $requestId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update request status');
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Request declined successfully! Inventory quantity restored.',
            'inventory_restored' => $quantity
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

$conn->close();
?>

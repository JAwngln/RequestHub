<?php
require_once 'connect.php';

header('Content-Type: application/json');

// The inventory dashboard polls this endpoint, so responses must never be cached.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Get input - support BOTH JSON body and standard FormData (multipart/form-data)
$data = null;

$json_input = file_get_contents('php://input');
if (!empty($json_input)) {
    $parsed = json_decode($json_input, true);
    if (is_array($parsed)) {
        $data = $parsed;
    }
}

// If JSON parsing failed or body was empty, fall back to $_POST (FormData)
if ($data === null) {
    $data = $_POST;
}

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request: missing action']);
    exit();
}

$action = $data['action'];

switch ($action) {
    case 'get_items':
        getInventoryItems();
        break;
    case 'add_item':
        addItem($data);
        break;
    case 'update_quantity':
        updateQuantity($data);
        break;
    case 'delete_item':
        deleteItem($data);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

function getInventoryItems() {
    global $conn;
    
    $lowStockThreshold = 5;

    // Alphabetical order so the dashboard always renders item names A-Z
    $sql = "SELECT id, item_name, quantity FROM inventory_management ORDER BY item_name ASC";
    $result = $conn->query($sql);
    
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $quantity = (int)$row['quantity'];
            $items[] = [
                'id' => $row['id'],
                'item_name' => $row['item_name'],
                'quantity' => $quantity,
                'stock_status' => $quantity <= 0 ? 'out' : ($quantity <= $lowStockThreshold ? 'low' : 'in')
            ];
        }
    }
    
    echo json_encode(['success' => true, 'low_stock_threshold' => $lowStockThreshold, 'items' => $items]);
}

function addItem($data) {
    global $conn;
    
    $name = trim($data['name'] ?? '');
    $quantity = (int)($data['quantity'] ?? 0);
    
    if (empty($name) || $quantity < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid name or quantity']);
        return;
    }
    
    // Check if item already exists
    $check_sql = "SELECT id FROM inventory_management WHERE item_name = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Item already exists']);
        return;
    }
    
    // Insert new item
    $sql = "INSERT INTO inventory_management (item_name, quantity) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $name, $quantity);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'item_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}

function updateQuantity($data) {
    global $conn;
    
    $item_id = (int)($data['item_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);
    
    if ($item_id <= 0 || $quantity < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid item ID or quantity']);
        return;
    }
    
    $sql = "UPDATE inventory_management SET quantity = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $quantity, $item_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}

function deleteItem($data) {
    global $conn;
    
    $item_id = (int)($data['item_id'] ?? 0);
    
    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
        return;
    }
    
    $sql = "DELETE FROM inventory_management WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}

$conn->close();
?>

<?php
require_once 'connect.php';

header('Content-Type: application/json');

// Stock is polled by both the faculty picker and the inventory dashboard, so
// never let a browser or proxy serve a cached copy.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

define('LOW_STOCK_THRESHOLD', 5);

try {
    // Fetch inventory items from database (alphabetical A-Z by default)
    $sql = "SELECT id, item_name, quantity FROM inventory_management ORDER BY item_name ASC";
    $result = $conn->query($sql);
    
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $quantity = (int)$row['quantity'];
            $items[] = [
                'id' => $row['id'],
                'name' => $row['item_name'],
                'quantity' => $quantity,
                'stock_status' => $quantity <= 0 ? 'out' : ($quantity <= LOW_STOCK_THRESHOLD ? 'low' : 'in')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'low_stock_threshold' => LOW_STOCK_THRESHOLD,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

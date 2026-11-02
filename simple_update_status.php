<?php
include 'connect.php';

header('Content-Type: application/json');

// Get POST data
$requestId = $_POST['request_id'] ?? 0;
$status = $_POST['status'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate inputs
if (!$requestId || !in_array($status, ['approved', 'declined'])) {
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

// Update request status
$sql = "UPDATE vehicle_requests SET status = ?, reviewed_at = NOW(), notes = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $notes, $requestId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => "Request $status successfully"
    ]);
} else {
    echo json_encode(['error' => 'Failed to update request: ' . $stmt->error]);
}

$stmt->close();
?>

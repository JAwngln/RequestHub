<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

// Create calendar_events table
$sql = "CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $conn->query($sql);
    echo "Calendar events table created successfully";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>

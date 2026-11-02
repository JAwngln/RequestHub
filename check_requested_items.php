<?php
include 'connect.php';

echo "<h2>Checking requested_items table</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'requested_items'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Table 'requested_items' does not exist. Creating it...</p>";
    
    // Create the table
    $createTableSql = "CREATE TABLE IF NOT EXISTS requested_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        names VARCHAR(255) NOT NULL,
        items VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        reason TEXT,
        when_needed DATE,
        action ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'pending'
    )";
    
    if ($conn->query($createTableSql)) {
        echo "<p style='color: green;'>Table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>Error creating table: " . $conn->error . "</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>Table 'requested_items' exists.</p>";
}

// Show table structure
echo "<h3>Table structure:</h3>";
$result = $conn->query("DESCRIBE requested_items");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['Field']}</td>
            <td>{$row['Type']}</td>
            <td>{$row['Null']}</td>
            <td>{$row['Key']}</td>
            <td>{$row['Default']}</td>
        </tr>";
    }
    echo "</table>";
}

// Check if table has data
echo "<h3>Data in requested_items table:</h3>";
$result = $conn->query("SELECT * FROM requested_items");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr>";
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>Found {$result->num_rows} records.</p>";
} else {
    echo "<p style='color: orange;'>No data found in requested_items table. Adding sample data...</p>";
    
    // Add sample data
    $sampleData = [
        ['John Doe', 'Laptop', 1, 'For office work', '2024-01-15', 'pending'],
        ['Jane Smith', 'Projector', 2, 'For presentation', '2024-01-20', 'pending'],
        ['Mike Johnson', 'Chair', 5, 'For new office setup', '2024-01-25', 'approved'],
        ['Sarah Wilson', 'Whiteboard', 3, 'For conference room', '2024-02-01', 'declined']
    ];
    
    $insertSql = "INSERT INTO requested_items (names, items, quantity, reason, when_needed, action) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    
    foreach ($sampleData as $data) {
        $stmt->bind_param("ssisss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>Added: {$data[0]} - {$data[1]}</p>";
        } else {
            echo "<p style='color: red;'>Error adding {$data[0]} - {$data[1]}: " . $stmt->error . "</p>";
        }
    }
    
    echo "<p style='color: blue;'>Sample data added! You can now view the requested items.</p>";
}

$conn->close();
?>

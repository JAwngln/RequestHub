<?php
require_once 'connect.php';

// Check if users table exists and get its structure
echo "Database connection status: " . ($conn->connect_error ? "Failed: " . $conn->connect_error : "Connected") . "\n";

// Check if users table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->num_rows > 0) {
    echo "Users table exists\n";
    
    // Get table structure
    $structure = $conn->query("DESCRIBE users");
    echo "Users table structure:\n";
    while ($row = $structure->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ") " . 
             ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . 
             ($row['Default'] ? " DEFAULT " . $row['Default'] : "") . "\n";
    }
    
    // Show sample data
    $data = $conn->query("SELECT * FROM users LIMIT 3");
    echo "\nSample data:\n";
    while ($row = $data->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Email: " . $row['email'] . ", Role: " . $row['role'] . "\n";
    }
} else {
    echo "Users table does not exist\n";
    
    // Create users table
    $createTable = "
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstName VARCHAR(100) NOT NULL,
        lastName VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('faculty', 'planning_officer', 'admin') DEFAULT 'faculty',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($createTable)) {
        echo "Users table created successfully\n";
    } else {
        echo "Error creating users table: " . $conn->error . "\n";
    }
}

$conn->close();
?>

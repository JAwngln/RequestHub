<?php
require_once 'connect.php';

echo "<h2>Database Setup for Account Settings</h2>";

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
}

// Create users table if it doesn't exist
$createUsersTable = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('faculty', 'planning_officer', 'admin') DEFAULT 'faculty',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createUsersTable)) {
    echo "<p style='color: green;'>✓ Users table created/verified</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating users table: " . $conn->error . "</p>";
}

// Check if table structure is correct
$checkStructure = $conn->query("DESCRIBE users");
if ($checkStructure) {
    echo "<h3>Users Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $checkStructure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Insert sample data if table is empty
$checkData = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $checkData->fetch_assoc();

if ($row['count'] == 0) {
    echo "<h3>Inserting Sample Data:</h3>";
    
    $sampleUsers = [
        [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'role' => 'faculty'
        ],
        [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'password123',
            'role' => 'planning_officer'
        ],
        [
            'firstName' => 'Admin',
            'lastName' => 'User',
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'role' => 'admin'
        ]
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (firstName, lastName, email, password, role) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($sampleUsers as $user) {
        $stmt->bind_param("sssss", $user['firstName'], $user['lastName'], $user['email'], $user['password'], $user['role']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Added user: " . $user['email'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding user: " . $stmt->error . "</p>";
        }
    }
    $stmt->close();
} else {
    echo "<h3>Current Users in Database:</h3>";
    $users = $conn->query("SELECT id, firstName, lastName, email, role, created_at FROM users");
    
    if ($users->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr>";
        
        while ($row = $users->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['role'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in database.</p>";
    }
}

echo "<h3>Setup Complete!</h3>";
echo "<p>Your account settings system is now ready to use.</p>";
echo "<p><a href='account_settings.html'>Go to Account Settings</a></p>";

$conn->close();
?>

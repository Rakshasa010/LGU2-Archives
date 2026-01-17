<?php
// Database configuration for XAMPP MySQL
$servername = "localhost";
$username = "root";
$password = "";  // Default XAMPP MySQL password is empty
$dbname = "lgu2_archives";  // Database name

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
$conn->query($sql);

// Select the database
$conn->select_db($dbname);

// Create legislative_records table
$table_sql = "CREATE TABLE IF NOT EXISTS legislative_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year VARCHAR(4) NOT NULL,
    author VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL
)";

$conn->query($table_sql);

// Add last_accessed column if it doesn't exist (for existing databases)
$check_column = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'last_accessed'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE legislative_records ADD COLUMN last_accessed TIMESTAMP NULL AFTER created_at");
}

// Create users table
$users_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    profile_picture VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($users_sql);

// Add profile_picture column if it doesn't exist (for existing databases)
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER role");
}

// Optional: Set charset to utf8mb4 for better Unicode support
$conn->set_charset("utf8mb4");

// Database setup completed - no output when included
?>

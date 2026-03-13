<?php
// Database configuration for XAMPP MySQL
$servername = "localhost";
$username = "las_adminsql";
$password = "lasadminsql123";  // Default XAMPP MySQL password is empty
$dbname = "las_lgu2_archives";  // Database name

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
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL
)";

$conn->query($table_sql);

// Add last_accessed column if it doesn't exist (for existing databases)
$check_column = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'last_accessed'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE legislative_records ADD COLUMN last_accessed TIMESTAMP NULL AFTER created_at");
}

// Add file_path column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'file_path'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE legislative_records ADD COLUMN file_path VARCHAR(255) NULL AFTER author");
}

// Create users table
$users_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    status ENUM('pending','active','rejected') NOT NULL DEFAULT 'active',
    profile_picture VARCHAR(255) DEFAULT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($users_sql);

// Add profile_picture column if it doesn't exist (for existing databases)
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER role");
}

// Add status column if it doesn't exist (for existing databases)
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'active' AFTER role");
}

// Add must_change_password column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_picture");
}

// Add last_activity column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_activity DATETIME NULL AFTER updated_at");
}

// Add failed_attempts column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_attempts'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN failed_attempts INT DEFAULT 0 AFTER last_activity");
}

// Add lockout_until column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'lockout_until'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN lockout_until DATETIME NULL AFTER failed_attempts");
}

// Create analytics_events table (used by report_analytics.php)
$analytics_sql = "CREATE TABLE IF NOT EXISTS analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(32) NOT NULL,
    user_id INT(11) NULL,
    record_id INT(11) NULL,
    record_title VARCHAR(255) NULL,
    record_type VARCHAR(50) NULL,
    download_format VARCHAR(16) NULL,
    bytes BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type_created (event_type, created_at),
    INDEX idx_created_at (created_at),
    INDEX idx_record_type (record_type),
    INDEX idx_download_format (download_format)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($analytics_sql);

$folders_sql = "CREATE TABLE IF NOT EXISTS archive_folders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    created_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($folders_sql);

$files_sql = "CREATE TABLE IF NOT EXISTS archive_files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    folder_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_folder_id (folder_id)
)";
$conn->query($files_sql);

// Create notifications table
$notif_sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    message TEXT NULL,
    time VARCHAR(20) NULL,
    date DATE NULL,
    content VARCHAR(255) NULL,
    about VARCHAR(100) NULL,
    link VARCHAR(255) NULL,
    record_id INT(11) NULL,
    role VARCHAR(20) NULL,
    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    action VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($notif_sql);

// Add new columns if they don't exist
$check_cols = $conn->query("SHOW COLUMNS FROM notifications LIKE 'ip_address'");
if ($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN ip_address VARCHAR(45) NULL AFTER status");
    $conn->query("ALTER TABLE notifications ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address");
    $conn->query("ALTER TABLE notifications ADD COLUMN action VARCHAR(50) NULL AFTER user_agent");
}

// Create confidential_vault table for secure file storage
$vault_sql = "CREATE TABLE IF NOT EXISTS confidential_vault (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    pin_hash VARCHAR(255) NOT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($vault_sql);

// Create confidential_files table for files in the vault
$vault_files_sql = "CREATE TABLE IF NOT EXISTS confidential_files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    vault_id INT(11) NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    moved_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vault_id (vault_id)
)";
$conn->query($vault_files_sql);

// Optional: Set charset to utf8mb4 for better Unicode support
$conn->set_charset("utf8mb4");

// Database setup completed - no output when included

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$__timeout = 300;
$__script = basename($_SERVER['PHP_SELF'] ?? '');
if ($__script !== 'login.php') {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $__timeout)) {
        session_unset();
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
    
    // Update session timestamp
    $_SESSION['last_activity'] = time();

    // Update database last_activity timestamp for "Just now" tracking
    if (isset($_SESSION['user_id'])) {
        $update_activity = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        if ($update_activity) {
            $update_activity->bind_param("i", $_SESSION['user_id']);
            $update_activity->execute();
            $update_activity->close();
        }
    }
}
// Enforce mandatory password change across the app
if (isset($_SESSION['user_id'])) {
    $allowScripts = ['login.php', 'profile.php', 'forgot-password.php'];
    if (!in_array($__script, $allowScripts, true)) {
        $uid = (int)$_SESSION['user_id'];
        if ($uid > 0) {
            $chk = $conn->prepare("SELECT must_change_password FROM users WHERE id = ?");
            if ($chk) {
                $chk->bind_param("i", $uid);
                $chk->execute();
                $res = $chk->get_result();
                if ($res && $res->num_rows === 1) {
                    $row = $res->fetch_assoc();
                    if ((int)$row['must_change_password'] === 1) {
                        header("Location: profile.php?force=1");
                        exit();
                    }
                }
                $chk->close();
            }
        }
    }
}
?>

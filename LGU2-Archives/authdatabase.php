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

// Add must_change_password column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_picture");
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

// Optional: Set charset to utf8mb4 for better Unicode support
$conn->set_charset("utf8mb4");

// Database setup completed - no output when included
?>
<?php
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
    $_SESSION['last_activity'] = time();
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

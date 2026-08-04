<?php
// Database configuration for XAMPP MySQL
$servername = "localhost";
$username = "las_adminsql";
$password = "lasadminsql123";  // Default XAMPP MySQL password is empty
$dbname = "las_lgu2_archives";  // Database name

// Include guard: skip connection setup + migration if already connected
if (!isset($conn) || !($conn instanceof mysqli)) {

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone for consistent TIMESTAMP handling across all queries
$conn->query("SET time_zone = '+08:00'");

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
$conn->query($sql);

// Select the database
$conn->select_db($dbname);

// Helper function to generate clean document prefix from folder name
if (!function_exists('generate_document_prefix')) {
    function generate_document_prefix($folder_name) {
        // Remove special characters except spaces and hyphens
        $clean = preg_replace('/[^\w\s-]/', '', $folder_name);
        // Replace multiple spaces or hyphens with single hyphen
        $clean = preg_replace('/[\s-]+/', '-', $clean);
        // Trim hyphens from start and end
        $clean = trim($clean, '-');
        // If empty, use a default
        return $clean ?: 'Documents';
    }
}

// Create legislative_records table
$table_sql = "CREATE TABLE IF NOT EXISTS legislative_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year VARCHAR(4) NOT NULL,
    author VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    file_date DATE NULL,
    unique_number VARCHAR(100) NULL,
    version INT DEFAULT 1,
    parent_version_id INT NULL,
    folder_id INT NULL,
    file_size BIGINT NULL,
    version_notes TEXT NULL,
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

// Add missing columns to legislative_records
$leg_cols_needed = [
    'author' => "VARCHAR(255) DEFAULT NULL",
    'file_date' => "DATE DEFAULT NULL",
    'unique_number' => "VARCHAR(100) DEFAULT NULL",
    'version' => "INT DEFAULT 1",
    'parent_version_id' => "INT NULL",
    'folder_id' => "INT NULL",
    'file_size' => "BIGINT NULL",
    'version_notes' => "TEXT NULL"
];
foreach ($leg_cols_needed as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM legislative_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE legislative_records ADD COLUMN $col $def");
    }
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
    dark_mode TINYINT(1) NOT NULL DEFAULT 0,
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

// Add dark_mode column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'dark_mode'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN dark_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password");
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
    parent_id INT(11) NULL,
    created_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    document_prefix VARCHAR(255) NULL,
    last_sequence_number INT DEFAULT 0,
    INDEX idx_parent_id (parent_id)
)";
$conn->query($folders_sql);

// Add parent_id column if it doesn't exist (support nested folder metadata)
$check_column = $conn->query("SHOW COLUMNS FROM archive_folders LIKE 'parent_id'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE archive_folders ADD COLUMN parent_id INT(11) NULL AFTER slug");
    $conn->query("ALTER TABLE archive_folders ADD INDEX idx_parent_id (parent_id)");
}

// Add document_prefix and last_sequence_number columns to archive_folders
$archive_folder_cols = [
    'document_prefix' => "VARCHAR(255) NULL",
    'last_sequence_number' => "INT DEFAULT 0"
];
foreach ($archive_folder_cols as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM archive_folders LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE archive_folders ADD COLUMN $col $def");
    }
}

// Create legislative folders table
$leg_folders_sql = "CREATE TABLE IF NOT EXISTS legislative_folders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    parent_id INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    document_prefix VARCHAR(255) NULL,
    last_sequence_number INT DEFAULT 0,
    INDEX idx_parent_id (parent_id),
    INDEX idx_type (type)
)";
$conn->query($leg_folders_sql);

// Add document_prefix and last_sequence_number columns to legislative_folders
$leg_folder_cols = [
    'document_prefix' => "VARCHAR(255) NULL",
    'last_sequence_number' => "INT DEFAULT 0"
];
foreach ($leg_folder_cols as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM legislative_folders LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE legislative_folders ADD COLUMN $col $def");
    }
}

$files_sql = "CREATE TABLE IF NOT EXISTS archive_files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    folder_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    file_date DATE DEFAULT NULL,
    unique_number VARCHAR(100) DEFAULT NULL,
    version INT DEFAULT 1,
    parent_version_id INT NULL,
    file_size BIGINT NULL,
    version_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_folder_id (folder_id)
)";
$conn->query($files_sql);

// Add missing columns to archive_files
$archive_cols_needed = [
    'author' => "VARCHAR(255) DEFAULT NULL",
    'file_date' => "DATE DEFAULT NULL",
    'unique_number' => "VARCHAR(100) DEFAULT NULL",
    'version' => "INT DEFAULT 1",
    'parent_version_id' => "INT NULL",
    'file_size' => "BIGINT NULL",
    'version_notes' => "TEXT NULL"
];
foreach ($archive_cols_needed as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM archive_files LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE archive_files ADD COLUMN $col $def");
    }
}

// Create requests table
$requests_table_sql = "CREATE TABLE IF NOT EXISTS requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    file_id INT(11) NOT NULL,
    file_source ENUM('legislative', 'archive') NOT NULL DEFAULT 'archive',
    requester_name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NULL,
    date_requested DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purpose TEXT NULL,
    status ENUM('Pending', 'Approved', 'Released', 'Denied') NOT NULL DEFAULT 'Pending',
    contact_info VARCHAR(255) NULL,
    date_released DATETIME NULL,
    INDEX idx_file (file_id, file_source),
    INDEX idx_status (status),
    INDEX idx_date (date_requested)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($requests_table_sql);

// Insert sample data if table is empty
$check_requests = $conn->query("SELECT COUNT(*) AS cnt FROM requests");
if ($check_requests) {
    $row = $check_requests->fetch_assoc();
    if ($row['cnt'] == 0) {
        $sample_requests = [
            [1, 'archive', 'Juan Dela Cruz', 'Engineering Department', '2026-07-01 09:00:00', 'For project planning', 'Pending', 'juan@email.com'],
            [1, 'archive', 'Maria Santos', 'Finance Department', '2026-07-02 14:30:00', 'For budget review', 'Approved', 'maria@email.com'],
            [2, 'legislative', 'Pedro Reyes', 'Legal Office', '2026-07-03 10:15:00', 'For legal reference', 'Released', 'pedro@email.com'],
            [3, 'archive', 'Ana Lim', 'Public Information Office', '2026-07-04 16:45:00', 'For public disclosure', 'Pending', 'ana@email.com'],
            [2, 'legislative', 'Luis Cruz', 'Mayor\'s Office', '2026-07-05 11:20:00', 'For policy making', 'Denied', 'luis@email.com'],
            [3, 'archive', 'Carla Go', 'City Planning Office', '2026-07-06 08:50:00', 'For city planning', 'Pending', 'carla@email.com'],
            [4, 'legislative', 'Benny Tan', 'Audit Office', '2026-07-07 13:30:00', 'For audit purposes', 'Approved', 'benny@email.com']
        ];
        foreach ($sample_requests as $req) {
            $stmt = $conn->prepare("INSERT INTO requests (file_id, file_source, requester_name, department, date_requested, purpose, status, contact_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $req[0], $req[1], $req[2], $req[3], $req[4], $req[5], $req[6], $req[7]);
            $stmt->execute();
            $stmt->close();
        }
    }
}

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

// Create user_hidden_folders table for user-specific secure file storage
$hidden_folder_sql = "CREATE TABLE IF NOT EXISTS user_hidden_folders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL UNIQUE,
    pin_hash VARCHAR(255) NULL,
    is_setup BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
)";
$conn->query($hidden_folder_sql);

// Create hidden_files table for files in user hidden folders
$hidden_files_sql = "CREATE TABLE IF NOT EXISTS hidden_files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_source VARCHAR(50) NOT NULL,
    original_id INT(11) NOT NULL,
    moved_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_original_source_id (original_source, original_id)
)";
$conn->query($hidden_files_sql);

// Migrate existing vault data to user-specific system (if needed)
$check_old_vault = $conn->query("SHOW TABLES LIKE 'confidential_vault'");
if ($check_old_vault && $check_old_vault->num_rows > 0) {
    // Check if migration is needed
    $check_migration = $conn->query("SELECT COUNT(*) as count FROM confidential_vault");
    if ($check_migration && $check_migration->fetch_assoc()['count'] > 0) {
        // Get the admin user (or first user) to migrate vault data to
        $admin_user = $conn->query("SELECT id FROM users WHERE role = 'admin' OR role = 'Administrator' ORDER BY id ASC LIMIT 1");
        if ($admin_user && $admin_user->num_rows > 0) {
            $admin_id = $admin_user->fetch_assoc()['id'];
            
            // Migrate vault setup to admin user
            $migrate_vault = $conn->query("SELECT * FROM confidential_vault LIMIT 1");
            if ($migrate_vault && $migrate_vault->num_rows > 0) {
                $vault_data = $migrate_vault->fetch_assoc();
                $conn->query("INSERT IGNORE INTO user_hidden_folders (user_id, pin_hash, is_setup) VALUES ($admin_id, '{$vault_data['pin_hash']}', TRUE)");
            }
            
            // Migrate files to admin user
            $migrate_files = $conn->query("SELECT * FROM confidential_files");
            if ($migrate_files) {
                while ($file = $migrate_files->fetch_assoc()) {
                    $name = $conn->real_escape_string($file['name']);
                    $path = $conn->real_escape_string($file['file_path']);
                    $moved_by = $file['moved_by'];
                    $created_at = $file['created_at'];
                    
                    $conn->query("INSERT IGNORE INTO hidden_files (user_id, name, file_path, original_source, original_id, moved_by, created_at) VALUES ($admin_id, '$name', '$path', 'migrated', 0, $moved_by, '$created_at')");
                }
            }
        }
    }
}

// Optional: Set charset to utf8mb4 for better Unicode support
$conn->set_charset("utf8mb4");

} // end include guard

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

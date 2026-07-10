<?php
// Reset script to clean up all vault/hidden folder data and start fresh with Private Files
session_start();
require 'authdatabase.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Private Files Reset</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .success { border-color: #28a745; background: #d4edda; }
        .error { border-color: #dc3545; background: #f8d7da; }
        .warning { border-color: #ffc107; background: #fff3cd; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔄 Private Files System Reset</h1>
        <p>This will completely clean up all vault/hidden folder data and create a fresh Private Files system.</p>";

if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    echo "<div class='step warning'><strong>⚠️ PERFORMING RESET...</strong></div>";
    
    // Step 1: Drop old tables
    echo "<div class='step'>";
    echo "<h3>1. Cleaning up old tables...</h3>";
    $tables_to_drop = ['confidential_vault', 'confidential_files', 'user_hidden_folders', 'hidden_files'];
    foreach ($tables_to_drop as $table) {
        $result = $conn->query("DROP TABLE IF EXISTS $table");
        if ($result) {
            echo "✅ Dropped table: $table<br>";
        } else {
            echo "❌ Failed to drop table: $table<br>";
        }
    }
    echo "</div>";
    
    // Step 2: Create new tables
    echo "<div class='step'>";
    echo "<h3>2. Creating Private Files tables...</h3>";
    
    // Create user_private_files table
    $private_files_sql = "CREATE TABLE user_private_files (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL UNIQUE,
        pin_hash VARCHAR(255) NULL,
        is_setup BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    )";
    
    if ($conn->query($private_files_sql)) {
        echo "✅ Created table: user_private_files<br>";
    } else {
        echo "❌ Failed to create table: user_private_files - " . $conn->error . "<br>";
    }
    
    // Create private_files table  
    $private_files_storage_sql = "CREATE TABLE private_files (
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
    
    if ($conn->query($private_files_storage_sql)) {
        echo "✅ Created table: private_files<br>";
    } else {
        echo "❌ Failed to create table: private_files - " . $conn->error . "<br>";
    }
    echo "</div>";
    
    // Step 3: Clear sessions
    echo "<div class='step'>";
    echo "<h3>3. Clearing old session data...</h3>";
    $session_keys = ['vault_unlocked', 'vault_unlock_time', 'hidden_folder_unlocked'];
    foreach ($session_keys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            echo "✅ Cleared session: $key<br>";
        }
    }
    echo "</div>";
    
    // Step 4: Clean notifications
    echo "<div class='step'>";
    echo "<h3>4. Cleaning up old notifications...</h3>";
    $cleanup_notifications = $conn->query("DELETE FROM notifications WHERE about IN ('Vault', 'Hidden Folder')");
    if ($cleanup_notifications) {
        echo "✅ Cleaned up old vault/hidden folder notifications<br>";
    } else {
        echo "❌ Failed to clean notifications<br>";
    }
    echo "</div>";
    
    echo "<div class='step success'>";
    echo "<h3>🎉 Reset Complete!</h3>";
    echo "<p>The Private Files system has been reset successfully. All users will need to set up their Private Files again.</p>";
    echo "<a href='storage.php'><button>Go to Storage</button></a>";
    echo "</div>";
    
} else {
    // Show warning and confirmation
    echo "<div class='step error'>";
    echo "<h3>⚠️ WARNING</h3>";
    echo "<p>This action will:</p>";
    echo "<ul>";
    echo "<li>Delete ALL existing vault/hidden folder data</li>";
    echo "<li>Remove ALL files stored in vaults/hidden folders</li>";
    echo "<li>Reset ALL user PINs</li>";
    echo "<li>Clear ALL related notifications</li>";
    echo "</ul>";
    echo "<p><strong>This action CANNOT be undone!</strong></p>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Current Data Status:</h3>";
    
    // Check existing data
    $tables = ['confidential_vault', 'confidential_files', 'user_hidden_folders', 'hidden_files'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_result ? $count_result->fetch_assoc()['count'] : 0;
            echo "📁 Table '$table' exists with $count records<br>";
        } else {
            echo "❌ Table '$table' does not exist<br>";
        }
    }
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='?action=reset'><button class='danger'>🗑️ RESET EVERYTHING</button></a>";
    echo "<a href='storage.php'><button>Cancel</button></a>";
    echo "</div>";
}

echo "</div></body></html>";
?>
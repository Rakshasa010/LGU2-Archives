<?php
/**
 * Complete Database Fix for Export Fulfillment
 * Fixes both audit_logs table and requests columns
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die("ERROR: Please login first. <a href='login.php'>Go to Login</a>");
}

require 'authdatabase.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Export Fulfillment - Database Fix</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .step { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #333; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        table td, table th { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background-color: #333; color: white; }
    </style>
</head>
<body>
    <h1>🔧 Export Fulfillment - Complete Database Fix</h1>
    <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
    <hr>

<?php

$allSuccess = true;
$fixes = [];

// ==================== FIX 1: Create audit_logs table ====================
echo "<div class='step'>";
echo "<h2>Step 1: Audit Logs Table</h2>";

try {
    $result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    
    if ($result->num_rows > 0) {
        echo "<p class='warning'>⚠️ Table <code>audit_logs</code> already exists (SKIP)</p>";
        $fixes[] = "audit_logs table: Already exists ✓";
    } else {
        echo "<p>🔧 Creating <code>audit_logs</code> table...</p>";
        
        $sql = "CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            file_id VARCHAR(100) NULL,
            details TEXT NULL,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p class='success'>✅ Table <code>audit_logs</code> created successfully!</p>";
            $fixes[] = "audit_logs table: Created ✓";
        } else {
            throw new Exception("Failed to create audit_logs: " . $conn->error);
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    $allSuccess = false;
}

echo "</div>";

// ==================== FIX 2: Add columns to requests table ====================
echo "<div class='step'>";
echo "<h2>Step 2: Requests Table Columns</h2>";

try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'requests'");
    if ($result->num_rows === 0) {
        throw new Exception("Table 'requests' does not exist!");
    }
    
    // Get existing columns
    $columns = $conn->query("DESCRIBE requests");
    $existingColumns = [];
    while ($row = $columns->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    // Define columns to add
    $columnsToAdd = [
        'staged_file_id' => "VARCHAR(100) NULL COMMENT 'Unique ID of staged file for export'",
        'staged_file_name' => "VARCHAR(255) NULL COMMENT 'Original filename of staged file'",
        'staged_file_size' => "INT NULL COMMENT 'File size in bytes'",
        'fulfilled_at' => "DATETIME NULL COMMENT 'Timestamp when export was fulfilled'"
    ];
    
    foreach ($columnsToAdd as $columnName => $columnDef) {
        if (in_array($columnName, $existingColumns)) {
            echo "<p class='warning'>⚠️ Column <code>$columnName</code> already exists (SKIP)</p>";
            $fixes[] = "requests.$columnName: Already exists ✓";
        } else {
            echo "<p>🔧 Adding column <code>$columnName</code>...</p>";
            
            $sql = "ALTER TABLE requests ADD COLUMN $columnName $columnDef";
            
            if ($conn->query($sql)) {
                echo "<p class='success'>✅ Column <code>$columnName</code> added successfully!</p>";
                $fixes[] = "requests.$columnName: Added ✓";
            } else {
                throw new Exception("Failed to add $columnName: " . $conn->error);
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    $allSuccess = false;
}

echo "</div>";

// ==================== FINAL SUMMARY ====================
echo "<hr>";

if ($allSuccess) {
    echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 8px;'>";
    echo "<h2 class='success'>✅ All Database Fixes Completed Successfully!</h2>";
    echo "<p>Your database is now ready for Export Fulfillment.</p>";
    echo "<h3>Changes Made:</h3>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
    echo "<hr>";
    echo "<p><strong>Next Step:</strong> <a href='export.php' style='font-size: 18px; color: #007bff;'>Go to Export Page</a> and test the 'Make a Copy' button!</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 8px;'>";
    echo "<h2 class='error'>❌ Some Fixes Failed</h2>";
    echo "<p>Please review the errors above and fix them manually.</p>";
    echo "</div>";
}

// Show final table structures
echo "<hr>";
echo "<h2>Final Database Structure</h2>";

// Show audit_logs
$result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if ($result->num_rows > 0) {
    echo "<h3>audit_logs Table</h3>";
    $columns = $conn->query("DESCRIBE audit_logs");
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show requests
echo "<h3>requests Table (Export-Related Columns)</h3>";
$columns = $conn->query("DESCRIBE requests");
echo "<table>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while ($row = $columns->fetch_assoc()) {
    $highlight = in_array($row['Field'], ['id', 'staged_file_id', 'staged_file_name', 'staged_file_size', 'fulfilled_at', 'status']);
    $style = $highlight ? "background-color: #fff3cd;" : "";
    echo "<tr style='$style'>";
    echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "</tr>";
}
echo "</table>";

?>

</body>
</html>

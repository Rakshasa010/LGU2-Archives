<?php
/**
 * Database Migration: Add Export Fulfillment Columns
 * Automatically adds missing columns to requests table
 */

session_start();

// Security check - only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    die("ERROR: Please login first before running this migration script. <a href='login.php'>Go to Login</a>");
}

require 'authdatabase.php';

// Note: User is logged in as user_id: " . $_SESSION['user_id'];

echo "<html><head><title>Database Migration</title></head><body>";
echo "<h1>Export Fulfillment - Database Migration</h1>";
echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Adding required columns to <code>requests</code> table...</p>";
echo "<hr>";

$errors = [];
$success = [];

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
    
    echo "<h2>Current Table Structure</h2>";
    echo "<pre>" . print_r($existingColumns, true) . "</pre>";
    
    // Define columns to add
    $columnsToAdd = [
        'staged_file_id' => "VARCHAR(100) NULL COMMENT 'Unique ID of staged file for export'",
        'staged_file_name' => "VARCHAR(255) NULL COMMENT 'Original filename of staged file'",
        'staged_file_size' => "INT NULL COMMENT 'File size in bytes'",
        'fulfilled_at' => "DATETIME NULL COMMENT 'Timestamp when export was fulfilled'"
    ];
    
    echo "<h2>Migration Steps</h2>";
    
    foreach ($columnsToAdd as $columnName => $columnDef) {
        if (in_array($columnName, $existingColumns)) {
            echo "<p>✅ <strong>$columnName</strong> - Already exists (SKIP)</p>";
            $success[] = "$columnName already exists";
        } else {
            echo "<p>🔧 <strong>$columnName</strong> - Adding...</p>";
            
            $sql = "ALTER TABLE requests ADD COLUMN $columnName $columnDef";
            
            if ($conn->query($sql)) {
                echo "<p style='color: green;'>✅ <strong>$columnName</strong> - Added successfully!</p>";
                $success[] = "Added column: $columnName";
            } else {
                $error = "Failed to add $columnName: " . $conn->error;
                echo "<p style='color: red;'>❌ <strong>$columnName</strong> - ERROR: " . htmlspecialchars($conn->error) . "</p>";
                $errors[] = $error;
            }
        }
    }
    
    echo "<hr>";
    
    if (count($errors) === 0) {
        echo "<h2 style='color: green;'>✅ Migration Completed Successfully!</h2>";
        echo "<p>All required columns are now present in the <code>requests</code> table.</p>";
        echo "<p><strong>Next step:</strong> Go back to <a href='export.php'>export.php</a> and try clicking 'Make a Copy' again.</p>";
    } else {
        echo "<h2 style='color: red;'>❌ Migration Failed</h2>";
        echo "<p>Some columns could not be added. Please fix these errors manually:</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
    // Show final structure
    echo "<h2>Final Table Structure</h2>";
    $finalColumns = $conn->query("DESCRIBE requests");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $finalColumns->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>FATAL ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>

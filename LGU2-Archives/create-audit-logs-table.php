<?php
/**
 * Create Audit Logs Table
 * For tracking export fulfillment actions
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die("ERROR: Please login first. <a href='login.php'>Go to Login</a>");
}

require 'authdatabase.php';

echo "<html><head><title>Create Audit Logs Table</title></head><body>";
echo "<h1>Create Audit Logs Table</h1>";
echo "<hr>";

try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    
    if ($result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Table <code>audit_logs</code> already exists!</p>";
        
        // Show structure
        $columns = $conn->query("DESCRIBE audit_logs");
        echo "<h2>Current Structure</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
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
        
        echo "<p>✅ Table already exists, no action needed!</p>";
        
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
            echo "<p style='color: green;'>✅ Table <code>audit_logs</code> created successfully!</p>";
            
            // Show structure
            $columns = $conn->query("DESCRIBE audit_logs");
            echo "<h2>Table Structure</h2>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
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
            
            echo "<hr>";
            echo "<h2>✅ Success!</h2>";
            echo "<p>The <code>audit_logs</code> table is now ready to track export fulfillment actions.</p>";
            echo "<p><strong>Next step:</strong> <a href='export.php'>Go to export.php</a> and try clicking 'Make a Copy' again.</p>";
            
        } else {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>

<?php
/**
 * Quick Error Log Viewer
 * Shows PHP error log location and recent errors
 */

echo "<h1>PHP Error Log Information</h1>";
echo "<h2>Configuration</h2>";
echo "<pre>";
echo "Error Logging: " . ini_get('log_errors') . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";
echo "Error Log File: " . ini_get('error_log') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "</pre>";

echo "<h2>Common XAMPP Log Locations</h2>";
echo "<ul>";
echo "<li>C:\\xampp\\php\\logs\\php_error_log</li>";
echo "<li>C:\\xampp\\apache\\logs\\error.log</li>";
echo "</ul>";

echo "<h2>Check These Files for Errors</h2>";

$logFiles = [
    'C:\\xampp\\php\\logs\\php_error_log',
    'C:\\xampp\\apache\\logs\\error.log',
    ini_get('error_log')
];

foreach ($logFiles as $logFile) {
    if (empty($logFile)) continue;
    
    echo "<h3>$logFile</h3>";
    
    if (file_exists($logFile)) {
        echo "<p style='color: green;'>✅ File exists</p>";
        
        // Show last 50 lines
        $lines = file($logFile);
        if ($lines) {
            $recentLines = array_slice($lines, -50);
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: scroll; font-size: 11px;'>";
            echo htmlspecialchars(implode('', $recentLines));
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ File not found</p>";
    }
}

echo "<hr>";
echo "<p><strong>To test the stage-export-copy.php API:</strong></p>";
echo "<ol>";
echo "<li>Click a 'Make a Copy' button</li>";
echo "<li>Refresh this page to see new error logs</li>";
echo "<li>Look for lines starting with '=== STAGE EXPORT COPY API CALLED ==='</li>";
echo "</ol>";
?>

<?php
// Test script for Hidden Folder functionality
session_start();
require 'authdatabase.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Hidden Folder Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test { margin: 10px 0; padding: 10px; border-left: 3px solid #007bff; background: #f8f9fa; }
        .success { border-color: #28a745; background: #d4edda; }
        .error { border-color: #dc3545; background: #f8d7da; }
    </style>
</head>
<body>";

echo "<h2>Hidden Folder System Test</h2>";

// Test 1: Check if new tables exist
echo "<div class='test'>";
echo "<h3>1. Database Tables Check</h3>";
$tables = ['user_hidden_folders', 'hidden_files'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' missing<br>";
    }
}
echo "</div>";

// Test 2: Check table structure
echo "<div class='test'>";
echo "<h3>2. Table Structure Check</h3>";

// Check user_hidden_folders structure
$result = $conn->query("DESCRIBE user_hidden_folders");
if ($result) {
    echo "✅ user_hidden_folders structure:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']}: {$row['Type']}<br>";
    }
} else {
    echo "❌ Failed to check user_hidden_folders structure<br>";
}

echo "<br>";

// Check hidden_files structure
$result = $conn->query("DESCRIBE hidden_files");
if ($result) {
    echo "✅ hidden_files structure:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']}: {$row['Type']}<br>";
    }
} else {
    echo "❌ Failed to check hidden_files structure<br>";
}
echo "</div>";

// Test 3: Check session and user
echo "<div class='test'>";
echo "<h3>3. Session Check</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User session active (ID: {$_SESSION['user_id']})<br>";
    
    $is_unlocked = isset($_SESSION['hidden_folder_unlocked']) && $_SESSION['hidden_folder_unlocked'] === true;
    echo "Hidden folder unlocked: " . ($is_unlocked ? "✅ Yes" : "❌ No") . "<br>";
} else {
    echo "❌ No user session<br>";
}
echo "</div>";

// Test 4: API Endpoint Test
echo "<div class='test'>";
echo "<h3>4. API Test</h3>";
echo "<button onclick='testAPI()'>Test Hidden Folder Status API</button>";
echo "<div id='api-result'></div>";
echo "</div>";

echo "<script>
function testAPI() {
    fetch('storage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hidden_folder_check_status' })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('api-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(e => {
        document.getElementById('api-result').innerHTML = '<div style=\"color: red;\">Error: ' + e.message + '</div>';
    });
}
</script>";

// Test 5: Migration Check
echo "<div class='test'>";
echo "<h3>5. Migration Check</h3>";
$old_vault = $conn->query("SHOW TABLES LIKE 'confidential_vault'");
if ($old_vault && $old_vault->num_rows > 0) {
    $vault_data = $conn->query("SELECT COUNT(*) as count FROM confidential_vault");
    if ($vault_data) {
        $count = $vault_data->fetch_assoc()['count'];
        echo "⚠️ Old vault table still exists with $count records<br>";
        echo "Consider running migration if needed<br>";
    }
} else {
    echo "✅ Old vault table properly cleaned up<br>";
}
echo "</div>";

echo "</body></html>";
?>
<?php
// Simple test file to check vault functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'authdatabase.php';

echo "<h2>Vault System Test</h2>";

// Test 1: Check if tables exist
echo "<h3>1. Database Tables Check</h3>";
$tables = ['confidential_vault', 'confidential_files'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' missing<br>";
    }
}

// Test 2: Check if user is logged in
echo "<h3>2. Session Check</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in (ID: " . $_SESSION['user_id'] . ")<br>";
} else {
    echo "❌ User not logged in<br>";
}

// Test 3: Check vault status
echo "<h3>3. Vault Status</h3>";
$vault_exists = false;
$check = $conn->query("SELECT id FROM confidential_vault LIMIT 1");
if ($check && $check->num_rows > 0) {
    $vault_exists = true;
    echo "✅ Vault exists<br>";
} else {
    echo "❌ Vault not created yet<br>";
}

$is_unlocked = isset($_SESSION['vault_unlocked']) && $_SESSION['vault_unlocked'] === true;
echo "Vault unlocked: " . ($is_unlocked ? "✅ Yes" : "❌ No") . "<br>";

// Test 4: Test AJAX endpoint
echo "<h3>4. AJAX Endpoint Test</h3>";
echo "<button onclick='testVaultStatus()'>Test Vault Status API</button>";
echo "<div id='test-result'></div>";

$conn->close();
?>

<script>
function testVaultStatus() {
    fetch('storage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'vault_check_status' })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('test-result').innerHTML = 
            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(e => {
        document.getElementById('test-result').innerHTML = 
            '<span style="color: red;">Error: ' + e.message + '</span>';
    });
}
</script>
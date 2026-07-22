<?php
/**
 * Test Stage Export API
 * Simple test to verify basic connectivity and session
 */

header('Content-Type: application/json');

error_log("=== TEST STAGE EXPORT API CALLED ===");

// Test 1: Session
session_start();
$hasSession = isset($_SESSION['user_id']);
$userId = $hasSession ? $_SESSION['user_id'] : null;

error_log("Session check: " . ($hasSession ? "YES, user_id=" . $userId : "NO"));

// Test 2: Database
try {
    require '../authdatabase.php';
    $dbConnected = isset($conn) && $conn->ping();
    error_log("Database connection: " . ($dbConnected ? "YES" : "NO"));
} catch (Exception $e) {
    $dbConnected = false;
    error_log("Database error: " . $e->getMessage());
}

// Test 3: Input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

error_log("Raw input: " . $rawInput);
error_log("Parsed input: " . json_encode($input));

// Test 4: Staging directory
$stagingDir = '../storage/temp_exports';
$stagingDirExists = is_dir($stagingDir);
$stagingDirWritable = is_writable($stagingDir) || is_writable(dirname($stagingDir));

error_log("Staging dir exists: " . ($stagingDirExists ? "YES" : "NO"));
error_log("Staging dir writable: " . ($stagingDirWritable ? "YES" : "NO"));

// Return test results
echo json_encode([
    'success' => true,
    'test_results' => [
        'session' => [
            'has_session' => $hasSession,
            'user_id' => $userId
        ],
        'database' => [
            'connected' => $dbConnected
        ],
        'input' => [
            'raw' => $rawInput,
            'parsed' => $input
        ],
        'staging' => [
            'directory' => $stagingDir,
            'exists' => $stagingDirExists,
            'writable' => $stagingDirWritable
        ],
        'php_info' => [
            'version' => PHP_VERSION,
            'error_log' => ini_get('error_log'),
            'log_errors' => ini_get('log_errors')
        ]
    ],
    'message' => 'All tests completed. Check error logs for details.'
]);

error_log("=== TEST COMPLETED ===");
?>

<?php
/**
 * MongoDB Atlas Connection Test Page
 * 
 * For deployed web environment - tests MongoDB Atlas Data API connectivity
 * 
 * This page validates the MongoDB Atlas configuration and tests connectivity
 * without exposing sensitive credentials in the output.
 */

// Determine if running in CLI mode
$isCli = php_sapi_name() === 'cli';

// If running in web mode, output HTML; otherwise output simple CLI text
if (!$isCli) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MongoDB Atlas Connection Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .env-variable { color: #6c757d; font-family: monospace; }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body class="p-4">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">MongoDB Atlas Connection Test</h1>
        
        <div class="card rounded-lg shadow border border-gray-200 dark:border-slate-700">
            <div class="card-header bg-gray-50 dark:bg-slate-800 font-medium">
                <h2 class="text-lg text-gray-700 dark:text-gray-300">Configuration Status</h2>
            </div>
            <div class="card-body p-6">
<?php
    }

// Include the MongoDB Atlas class
require __DIR__ . '/LGU2-Archives/includes/mongodb_atlas.php';

$atlas = new MongoDBAtlas();

// Test results
$tests = [];

// Test 1: Configuration loaded
$configLoaded = !empty($atlas->baseUrl) || !empty($atlas->dataApiKey);
$tests[] = [
    'name' => 'MongoDB Atlas Configuration',
    'status' => $configLoaded ? 'good' : 'error',
    'message' => $configLoaded ? 'Configuration loaded successfully' : 'Missing MongoDB Atlas configuration in .env'
];

// Test 2: Base URL (using getter)
$tests[] = [
    'name' => 'Base URL',
    'status' => !empty($atlas->getBaseUrl()) ? 'good' : 'error',
    'message' => 'Endpoint: ' . ($atlas->getBaseUrl() ?? 'Not configured')
];

// Test 3: Data API Key (using getter)
$tests[] = [
    'name' => 'Data API Key',
    'status' => !empty($atlas->getDataApiKey()) ? 'good' : 'warning',
    'message' => 'Data API Key: ' . (strlen($atlas->getDataApiKey()) > 10 ? substr($atlas->getDataApiKey(), 0, 10) . '******' : 'Short key')
];

// Test 4: Database Name (using getter)
$tests[] = [
    'name' => 'Database Name',
    'status' => !empty($atlas->getDbName()) ? 'good' : 'error',
    'message' => $atlas->getDbName() ?? 'Not configured'
];

// Test 5: Collection Name (using getter)
$tests[] = [
    'name' => 'Collection Name',
    'status' => !empty($atlas->getCollectionName()) ? 'good' : 'error',
    'message' => $atlas->getCollectionName() ?? 'Not configured'
];

// Test 6: Connection test (findOne with empty filter - best effort)
try {
    $result = $atlas->findOne([]);
    $connectionOk = true;
} catch (Exception $e) {
    $connectionOk = false;
}

$tests[] = [
    'name' => 'Connection Test',
    'status' => $connectionOk ? 'good' : 'error',
    'message' => $connectionOk ? 'Atlas API response: document found' : 'Connection failed: ' . $e->getMessage()
];

// Count statuses
$goodCount = 0;
$warningCount = 0;
$errorCount = 0;

foreach ($tests as $test) {
    switch ($test['status']) {
        case 'good':    $goodCount++; break;
        case 'warning': $warningCount++; break;
        case 'error':   $errorCount++; break;
    }
}

// Display results in web mode
if (!$isCli) {
?>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <?php foreach ($tests as $test): ?>
                        <div class="p-3 rounded <?= $test['status'] === 'good' ? 'bg-green-100' : ($test['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100') ?> border <?= $test['status'] === 'good' ? 'border-green-400' : ($test['status'] === 'warning' ? 'border-yellow-400' : 'border-red-400') ?>">
                            <div class="font-medium text-sm <?= $test['status'] === 'good' ? 'text-green-800' : ($test['status'] === 'warning' ? 'text-yellow-800' : 'text-red-800') ?>">
                                <i class="bi bi-<?= $test['status'] === 'good' ? 'check-circle' : ($test['status'] === 'warning' ? 'exclamation-triangle' : 'x-circle') ?> me-2"></i>
                                <?= htmlspecialchars($test['name']) ?>
                            </div>
                            <p class="text-xs mt-1 <?= $test['status'] === 'good' ? 'text-green-600' : ($test['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') ?>">
                                <?= htmlspecialchars($test['message']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 p-4 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <h3 class="font-medium text-blue-800 dark:text-blue-200 mb-3">Connection Details</h3>
                    <p class="text-sm"><strong>Base URL:</strong> <span class="env-variable"><?= htmlspecialchars($atlas->baseUrl ?? 'Not set') ?></span></p>
                    <p class="text-sm"><strong>Database:</strong> <span class="env-variable"><?= htmlspecialchars($atlas->dbName ?? 'Not set') ?></span></p>
                    <p class="text-sm"><strong>Collection:</strong> <span class="env-variable"><?= htmlspecialchars($atlas->collectionName ?? 'Not set') ?></span></p>
                </div>
                
                <div class="mt-6">
                    <a href="index.php" class="btn btn-primary">Return to Application</a>
                    <a href=".env" class="btn btn-link ms-2" target="_blank">Edit .env Configuration</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    // CLI mode output
} else {
    echo "MongoDB Atlas Connection Test (CLI Mode)\n";
    echo "========================================\n\n";
    
    $configLoaded = !empty($atlas->getBaseUrl()) || !empty($atlas->getDataApiKey());
    echo "Configuration Loaded: " . ($configLoaded ? 'YES' : 'NO') . "\n";
    echo "Base URL: " . ($atlas->getBaseUrl() ?? 'Not set') . "\n";
    echo "Database Name: " . ($atlas->getDbName() ?? 'Not set') . "\n";
    echo "Collection: " . ($atlas->getCollectionName() ?? 'Not set') . "\n";
    echo "Data API Key: " . (strlen($atlas->getDataApiKey()) > 8 ? substr($atlas->getDataApiKey(), 0, 8) . '******' : 'Short key') . "\n";
    echo "\n";
    
    // Try connection test
    try {
        $result = $atlas->findOne([]);
        echo "Connection Test: SUCCESS\n";
        echo "API Response: " . (isset($result['document']) ? 'document found' : 'API reachable, no document') . "\n";
    } catch (Exception $e) {
        echo "Connection Test: FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
}
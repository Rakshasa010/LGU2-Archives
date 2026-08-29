<?php
/**
 * LLRM Integration Configuration
 *
 * Stores API credentials and endpoint URLs for communicating
 * with the LLRM Legislative Records Management System.
 *
 * API key is loaded from the .env file in the project root.
 */

// Load .env if not already loaded
function llrm_config_load_env($path) {
    $vars = [];
    if (!file_exists($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value, " \t\n\r\0\"'");
    }
    return $vars;
}

$envPath = __DIR__ . '/../../.env';
$env = llrm_config_load_env($envPath);

return [
    // API Key provided by LLRM system
    'api_key'          => $env['LLRM_API_KEY'] ?? '',
    'module_name'      => 'archives',
    'api_version'      => 'v1',

    // LLRM Archive API base URL (list, search, get, create, update, delete, download, stats)
    'archive_api_url'  => 'https://llrm.spvalenzuela.com/modules/document-management/api/archive.php',

    // LLRM Receive endpoint (push documents FROM LAS TO LLRM)
    'receive_url'      => 'https://llrm.spvalenzuela.com/modules/integration/api/receive_document.php',

    // LAS system identity
    'source_system'    => 'las',

    // Cache settings (seconds) — 0 to disable
    'cache_ttl'        => 300,  // 5 minutes for stats/types
    'cache_dir'        => __DIR__ . '/../cache/llrm',

    // Request settings
    'timeout'          => 30,   // cURL timeout in seconds
    'max_file_size'     => 50 * 1024 * 1024, // 50MB (LLRM limit)

    // Allowed file types for upload to LLRM
    'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'],
];

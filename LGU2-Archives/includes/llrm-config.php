<?php
/**
 * LLRM Integration Configuration
 * 
 * Stores API credentials and endpoint URLs for communicating
 * with the LLRM Legislative Records Management System.
 */

return [
    // API Key provided by LLRM system
    'api_key'          => 'ar_c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8',
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

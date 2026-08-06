<?php
/**
 * Archive Intake API — External Integration Endpoint
 * 
 * Receives archived files from external systems (e.g., LLRM at llrm.spvalenzuela.com)
 * when a record is marked as "archived" in the source system.
 *
 * Authentication: API key via Bearer token or X-API-Key header
 * Method: POST (JSON body or multipart for file upload)
 *
 * Request body (JSON):
 * {
 *   "api_key": "your-api-key",
 *   "title": "Ordinance No. 1234",
 *   "type": "Ordinance",           // Ordinance, Resolution, Meeting, Public Hearing, Billing, or custom
 *   "author": "Juan Dela Cruz",
 *   "month": "January",
 *   "year": "2026",
 *   "source_system": "LLRM",
 *   "source_record_id": 123,       // ID in the LLRM system (for reference)
 *   "file_content": "base64...",   // Base64-encoded file content (optional)
 *   "file_name": "ordinance_1234.pdf",
 *   "folder_name": "Ordinances",   // Target folder name (created if not exists)
 *   "metadata": {                  // Optional extra metadata
 *     "session_date": "2026-01-15",
 *     "committee": "Appropriations"
 *   }
 * }
 *
 * Response:
 * {"success": true, "id": 42, "message": "Record archived successfully"}
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Read raw input BEFORE any includes (some hosts buffer php://input)
$raw_input = file_get_contents('php://input');
if (empty($raw_input) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
    $raw_input = $GLOBALS['HTTP_RAW_POST_DATA'];
}

require_once '../authdatabase.php';
require_once __DIR__ . '/../includes/pinata.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// --- Parse JSON body first (for both auth and data) ---
$json_input = json_decode($raw_input, true);
if (!is_array($json_input)) {
    // Try form data as fallback
    if (!empty($_POST)) {
        $json_input = $_POST;
    } else {
        $json_input = null;
    }
}

// --- API Key Authentication ---
// Configure your API key here (share this with the LLRM system)
$VALID_API_KEY = 'llrm_archive_intake_2026_secure_key';

// Check Bearer token or X-API-Key header or body param
$api_key = '';
$headers = getallheaders();
if (isset($headers['Authorization']) && preg_match('/Bearer\s+(.+)/i', $headers['Authorization'], $m)) {
    $api_key = trim($m[1]);
} elseif (isset($headers['X-API-Key'])) {
    $api_key = $headers['X-API-Key'];
} elseif (isset($_POST['api_key'])) {
    $api_key = $_POST['api_key'];
} elseif ($json_input && isset($json_input['api_key'])) {
    $api_key = $json_input['api_key'];
}

if (empty($api_key) || $api_key !== $VALID_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing API key']);
    exit;
}

// --- Parse request data ---
// Support both JSON body and multipart form-data
$data = [];
if ($json_input) {
    $data = $json_input;
} else {
    $data = $_POST;
}

// Validate required fields
$required = ['title', 'type', 'author'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $missing)]);
    exit;
}

$title       = $data['title'];
$type        = $data['type'];
$author      = $data['author'];
$month       = $data['month'] ?? date('F');
$year        = $data['year'] ?? date('Y');
$source_system    = $data['source_system'] ?? 'LLRM';
$source_record_id = isset($data['source_record_id']) ? (int)$data['source_record_id'] : null;
$folder_name = $data['folder_name'] ?? $type;
$metadata    = $data['metadata'] ?? [];

// --- Auto-route the document into the archive (Main Storage) ---
require_once __DIR__ . '/../includes/llrm-intake.php';

$doc = [
    'title'             => $title,
    'type'              => $type,
    'author'            => $author,
    'source_system'     => $source_system,
    'source_record_id'  => $source_record_id,
];
if (!empty($data['document_date'])) {
    $doc['document_date'] = $data['document_date'];
} elseif (!empty($metadata['session_date'])) {
    $doc['document_date'] = $metadata['session_date'];
}

$fileSpec = null;
if (!empty($data['file_content']) && !empty($data['file_name'])) {
    $fileSpec = ['base64' => $data['file_content'], 'orig_name' => $data['file_name']];
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileSpec = ['tmp_path' => $_FILES['file']['tmp_name'], 'orig_name' => $_FILES['file']['name'], 'copy' => false];
}

$result = llrm_intake_route($conn, $doc, $fileSpec, ['notification_prefix' => $source_system]);

if (!empty($result['duplicate'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'Duplicate record already exists',
        'message' => 'A record with the same title and type already exists in the archive.',
        'existing_id' => $result['existing_id'] ?? null,
    ]);
    $conn->close();
    exit;
}

if (empty($result['success'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Intake failed']);
    $conn->close();
    exit;
}

echo json_encode([
    'success'          => true,
    'id'               => $result['record_id'],
    'message'          => 'Record archived successfully',
    'kind'             => $result['kind'],
    'folder_id'        => $result['folder_id'],
    'folder_name'      => $result['folder_name'],
    'file_path'        => $result['file_path'],
    'unique_number'    => $result['unique_number'],
    'ipfs_cid'         => $result['ipfs_cid'],
    'ipfs_url'         => $result['ipfs_url'],
    'source_system'    => $source_system,
    'source_record_id' => $source_record_id
]);

$conn->close();
?>

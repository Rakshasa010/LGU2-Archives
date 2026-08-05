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

// --- Find or create legislative folder ---
$folder_id = null;

// Try to find existing folder by name and type
$findFolder = $conn->prepare("SELECT id FROM legislative_folders WHERE name = ? AND type = ? LIMIT 1");
$findFolder->bind_param("ss", $folder_name, $type);
$findFolder->execute();
$folderRes = $findFolder->get_result();
if ($folderRow = $folderRes->fetch_assoc()) {
    $folder_id = (int)$folderRow['id'];
}
$findFolder->close();

// Create folder if not found
if (!$folder_id) {
    $createFolder = $conn->prepare("INSERT INTO legislative_folders (name, type, created_by) VALUES (?, ?, NULL)");
    $createFolder->bind_param("ss", $folder_name, $type);
    if ($createFolder->execute()) {
        $folder_id = (int)$conn->insert_id;
    }
    $createFolder->close();
}

// --- Handle file content (if provided) ---
$file_path = null;
$version = 1;
$parent_version_id = null;

if (!empty($data['file_content']) && !empty($data['file_name'])) {
    // Decode base64 file content
    $file_data = base64_decode($data['file_content'], true);
    if ($file_data === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid base64 file content']);
        exit;
    }

    // Create upload directory: uploads/legislative/{Type}/{Year}/
    $clean_type = preg_replace('/[^a-zA-Z0-9]/', '', $type);
    $target_dir = __DIR__ . '/../uploads/legislative/' . $clean_type . '/' . $year . '/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Generate safe filename
    $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $data['file_name']);
    $filename = 'v1_' . $safe_name;
    $target_path = $target_dir . $filename;

    // Ensure unique filename
    $counter = 1;
    while (file_exists($target_path)) {
        $filename = 'v1_' . $counter . '_' . $safe_name;
        $target_path = $target_dir . $filename;
        $counter++;
    }

    // Write file to disk
    if (file_put_contents($target_path, $file_data) !== false) {
        // Store relative path
        $file_path = 'uploads/legislative/' . $clean_type . '/' . $year . '/' . $filename;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save file to disk']);
        exit;
    }
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // Handle multipart file upload
    $clean_type = preg_replace('/[^a-zA-Z0-9]/', '', $type);
    $target_dir = __DIR__ . '/../uploads/legislative/' . $clean_type . '/' . $year . '/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $_FILES['file']['name']);
    $filename = 'v1_' . $safe_name;
    $target_path = $target_dir . $filename;

    $counter = 1;
    while (file_exists($target_path)) {
        $filename = 'v1_' . $counter . '_' . $safe_name;
        $target_path = $target_dir . $filename;
        $counter++;
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        $file_path = 'uploads/legislative/' . $clean_type . '/' . $year . '/' . $filename;
    }
}

// --- Pin the received file to Pinata IPFS (best-effort; local copy is always kept) ---
$ipfsCid = null;
$mimeType = null;
if (!empty($file_path) && isset($target_path) && is_file($target_path)) {
    $mimeType = function_exists('mime_content_type') ? mime_content_type($target_path) : null;
    if (!$mimeType) { $mimeType = 'application/octet-stream'; }
    $pinataGroupId = null;
    if (!empty($folder_id)) {
        $groupInfo = pinata_ensure_folder_group($conn, 'legislative_folders', $folder_id, $folder_name ?? '');
        if ($groupInfo['success']) {
            $pinataGroupId = $groupInfo['id'];
        } elseif (!empty($groupInfo['error'])) {
            error_log('Pinata group setup failed for folder #' . $folder_id . ': ' . $groupInfo['error']);
        }
    }
    $pinataResult = pinata_upload_file($target_path, basename($target_path), ['record' => 'legislative', 'source_system' => $source_system], $pinataGroupId);
    if ($pinataResult['success']) {
        $ipfsCid = $pinataResult['cid'];
        if (!empty($pinataResult['group']) && empty($pinataResult['group']['success'])) {
            error_log('Pinata group add failed for ' . basename($target_path) . ': ' . ($pinataResult['group']['error'] ?? 'unknown error'));
        }
    } else {
        error_log('Pinata pin failed for ' . basename($target_path) . ': ' . ($pinataResult['error'] ?? 'unknown error'));
    }
}

// --- Check for duplicates (same title + type from same source system) ---
$duplicate = false;
if ($source_record_id) {
    $checkDup = $conn->prepare("SELECT id FROM legislative_records WHERE title = ? AND type = ? AND author = ? ORDER BY id DESC LIMIT 1");
    $checkDup->bind_param("sss", $title, $type, $author);
    $checkDup->execute();
    $dupRes = $checkDup->get_result();
    if ($dupRes->fetch_assoc()) {
        $duplicate = true;
    }
    $checkDup->close();
}

if ($duplicate) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error' => 'Duplicate record already exists',
        'message' => 'A record with the same title, type, and author already exists in the archive.'
    ]);
    exit;
}

// --- Insert into legislative_records ---
$insertSql = "INSERT INTO legislative_records (title, type, month, year, author, file_path, folder_id, version, parent_version_id, created_at, ipfs_cid, mime_type)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("ssssssiiiss",
    $title,
    $type,
    $month,
    $year,
    $author,
    $file_path,
    $folder_id,
    $version,
    $parent_version_id,
    $ipfsCid,
    $mimeType
);

if ($insertStmt->execute()) {
    $new_id = (int)$conn->insert_id;
    $insertStmt->close();

    // Log the intake
    $logSql = "INSERT INTO analytics_events (event_type, record_id, record_title, record_type, created_at)
               VALUES ('external_intake', ?, ?, ?, NOW())";
    $logStmt = $conn->prepare($logSql);
    $logStmt->bind_param("iss", $new_id, $title, $type);
    $logStmt->execute();
    $logStmt->close();

    // Create notification for admins
    $notifContent = "New archived record from {$source_system}: {$title}";
    $notifSql = "INSERT INTO notifications (time, date, content, about, status, created_at)
                 VALUES (?, CURDATE(), ?, 'External Intake', 'unread', NOW())";
    $notifStmt = $conn->prepare($notifSql);
    $timeStr = date('h:i A');
    $notifStmt->bind_param("ss", $timeStr, $notifContent);
    $notifStmt->execute();
    $notifStmt->close();

    echo json_encode([
        'success' => true,
        'id' => $new_id,
        'message' => 'Record archived successfully',
        'folder_id' => $folder_id,
        'file_path' => $file_path,
        'ipfs_cid' => $ipfsCid,
        'ipfs_url' => $ipfsCid ? pinata_gateway_url($ipfsCid) : null,
        'source_system' => $source_system,
        'source_record_id' => $source_record_id
    ]);
} else {
    $insertStmt->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $conn->error
    ]);
}

$conn->close();
?>

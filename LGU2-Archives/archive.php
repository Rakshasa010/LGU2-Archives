<?php
/**
 * LAS Archive API — Document Reception Endpoint
 * 
 * Receives archived documents from external systems (e.g., LLRM at llrm.spvalenzuela.com)
 * when a document is marked as "archived" in the source system and pushed to LAS.
 *
 * Authentication: API key via X-API-Key header
 * Method: POST (multipart/form-data for file uploads)
 *
 * Actions:
 *   - create    Receive a new archived document (POST, multipart/form-data)
 *   - list      List archived documents received from external systems (GET)
 *   - get       Get a single document by ID (GET)
 *   - stats     Get archive statistics (GET)
 *   - (empty)   API health check / metadata
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Read raw input BEFORE any includes
$raw_input = file_get_contents('php://input');

require_once 'authdatabase.php';
require_once __DIR__ . '/includes/pinata.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- API Key Authentication ---
$VALID_API_KEY = 'ar_c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8';

$api_key = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
if (isset($headers['X-API-Key'])) {
    $api_key = $headers['X-API-Key'];
} elseif (isset($headers['x-api-key'])) {
    $api_key = $headers['x-api-key'];
} elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
}

if (empty($api_key) || $api_key !== $VALID_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing API key']);
    exit;
}

// --- Parse action ---
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// --- Ensure required tables exist ---
$conn->query("CREATE TABLE IF NOT EXISTS external_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL DEFAULT 'archive',
    document_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'archived',
    description TEXT NULL,
    tags VARCHAR(500) NULL,
    reference_number VARCHAR(100) NULL,
    file_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    file_size BIGINT DEFAULT 0,
    file_type VARCHAR(100) NULL,
    ipfs_cid VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    source_system VARCHAR(50) NOT NULL DEFAULT 'llrm',
    external_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    case '':
        // API Health Check / Metadata
        echo json_encode([
            'success'       => true,
            'api_name'      => 'las_archive',
            'version'       => 'v1',
            'authenticated_as' => 'archives',
            'endpoints'     => [
                'GET  ?action=stats'       => 'Get archive statistics',
                'GET  ?action=list'        => 'List archived documents (paginated)',
                'GET  ?action=get&id={id}' => 'Get single document details',
                'POST ?action=create'      => 'Receive a new archived document (multipart/form-data)',
            ],
        ]);
        break;

    case 'create':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
            break;
        }

        // --- Validate required fields ---
        $title = trim($_POST['title'] ?? '');
        $documentType = trim($_POST['document_type'] ?? 'archive');
        $documentDate = trim($_POST['document_date'] ?? '');
        $status = trim($_POST['status'] ?? 'archived');
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $referenceNumber = trim($_POST['reference_number'] ?? '');
        $sourceSystem = trim($_POST['source_system'] ?? 'llrm');
        $externalId = trim($_POST['external_id'] ?? '');

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required field: title']);
            break;
        }

        // --- Validate reference number uniqueness ---
        if (!empty($referenceNumber)) {
            $checkRef = $conn->prepare("SELECT id FROM external_documents WHERE reference_number = ? LIMIT 1");
            $checkRef->bind_param("s", $referenceNumber);
            $checkRef->execute();
            $refRes = $checkRef->get_result();
            if ($refRes->fetch_assoc()) {
                $checkRef->close();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error'   => 'A document with reference_number "' . $referenceNumber . '" already exists',
                ]);
                break;
            }
            $checkRef->close();
        }

        // --- Handle file upload ---
        $filePath = null;
        $fileName = null;
        $fileSize = 0;
        $fileType = null;
        $ipfsCid = null;
        $mimeType = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileName = $_FILES['file']['name'];
            $fileSize = (int)$_FILES['file']['size'];
            $fileType = $_FILES['file']['type'];

            // Validate file extension
            $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'File type not allowed: .' . $ext]);
                break;
            }

            // Validate file size (50MB max)
            $maxSize = 50 * 1024 * 1024;
            if ($fileSize > $maxSize) {
                http_response_code(413);
                echo json_encode(['success' => false, 'error' => 'File exceeds 50MB limit']);
                break;
            }

            // Create target directory: uploads/external/{YYYY-MM}/
            $targetDir = __DIR__ . '/uploads/external/' . date('Y-m') . '/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            // Generate safe filename
            $safeName = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $fileName);
            $finalName = time() . '_' . $safeName;
            $targetPath = $targetDir . $finalName;

            // Ensure unique
            $counter = 1;
            while (file_exists($targetPath)) {
                $finalName = time() . '_' . $counter . '_' . $safeName;
                $targetPath = $targetDir . $finalName;
                $counter++;
            }

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $filePath = 'uploads/external/' . date('Y-m') . '/' . $finalName;

                // Pin the uploaded file to Pinata IPFS (best-effort; local copy is always kept)
                $mimeType = function_exists('mime_content_type') ? mime_content_type($targetPath) : null;
                if (!$mimeType) { $mimeType = $fileType ?: 'application/octet-stream'; }
                $pinataGroupId = null;
                $groupInfo = pinata_ensure_group('LAS/External Documents');
                if ($groupInfo['success']) {
                    $pinataGroupId = $groupInfo['id'];
                } elseif (!empty($groupInfo['error'])) {
                    error_log('Pinata group setup failed for External Documents: ' . $groupInfo['error']);
                }
                $pinataResult = pinata_upload_file($targetPath, $finalName, ['record' => 'external_document', 'reference_number' => $referenceNumber], $pinataGroupId);
                if ($pinataResult['success']) {
                    $ipfsCid = $pinataResult['cid'];
                    if (!empty($pinataResult['group']) && empty($pinataResult['group']['success'])) {
                        error_log('Pinata group add failed for ' . $finalName . ': ' . ($pinataResult['group']['error'] ?? 'unknown error'));
                    }
                } else {
                    error_log('Pinata pin failed for ' . $finalName . ': ' . ($pinataResult['error'] ?? 'unknown error'));
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
                break;
            }
        }

        // --- Normalize document date ---
        if (empty($documentDate)) {
            $documentDate = date('Y-m-d');
        }

        // --- Insert into external_documents ---
        $insertSql = "INSERT INTO external_documents 
            (title, document_type, document_date, status, description, tags, reference_number, 
             file_path, file_name, file_size, file_type, ipfs_cid, mime_type, source_system, external_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sssssssssssssss",
            $title,
            $documentType,
            $documentDate,
            $status,
            $description,
            $tags,
            $referenceNumber,
            $filePath,
            $fileName,
            $fileSize,
            $fileType,
            $ipfsCid,
            $mimeType,
            $sourceSystem,
            $externalId
        );

        if ($insertStmt->execute()) {
            $newId = (int)$conn->insert_id;
            $insertStmt->close();

            // Log the intake
            $logSql = "INSERT INTO analytics_events (event_type, record_id, record_title, record_type, created_at)
                       VALUES ('external_intake', ?, ?, ?, NOW())";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("iss", $newId, $title, $documentType);
            $logStmt->execute();
            $logStmt->close();

            // Create notification for admins
            $notifContent = "New archived document from {$sourceSystem}: {$title}";
            $notifSql = "INSERT INTO notifications (time, date, content, about, status, created_at)
                         VALUES (?, CURDATE(), ?, 'External Intake', 'unread', NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $timeStr = date('h:i A');
            $notifStmt->bind_param("ss", $timeStr, $notifContent);
            $notifStmt->execute();
            $notifStmt->close();

            // Auto-register LLRM-sourced documents into the archive (Main Storage) with routing
            $autoRouted = null;
            if (stripos($sourceSystem, 'llrm') !== false && !empty($filePath)) {
                require_once __DIR__ . '/includes/llrm-intake.php';
                $routeDoc = [
                    'title'            => $title,
                    'type'             => $documentType,
                    'author'           => trim($_POST['uploaded_by_name'] ?? $_POST['author'] ?? 'LLRM Import'),
                    'document_date'    => $documentDate,
                    'source_system'    => $sourceSystem,
                    'source_record_id' => ($externalId !== '' && ctype_digit($externalId)) ? (int)$externalId : null,
                ];
                $routeFileSpec = [
                    'tmp_path'  => $targetPath,
                    'orig_name' => $fileName ?: ($title . '.pdf'),
                    'copy'      => true,
                ];
                $routeResult = llrm_intake_route($conn, $routeDoc, $routeFileSpec, ['notification_prefix' => $sourceSystem]);
                if (!empty($routeResult['success'])) {
                    $autoRouted = [
                        'id'            => $routeResult['record_id'],
                        'kind'          => $routeResult['kind'],
                        'folder_id'     => $routeResult['folder_id'],
                        'folder_name'   => $routeResult['folder_name'],
                        'unique_number' => $routeResult['unique_number'],
                        'file_path'     => $routeResult['file_path'],
                    ];
                } else {
                    error_log('LLRM auto-route failed for "' . $title . '": ' . ($routeResult['error'] ?? 'unknown error'));
                }
            }

            http_response_code(201);
            echo json_encode([
                'success'  => true,
                'document' => [
                    'id'              => $newId,
                    'title'           => $title,
                    'document_type'   => $documentType,
                    'reference_number' => $referenceNumber,
                    'status'          => $status,
                    'file_path'       => $filePath,
                    'file_name'       => $fileName,
                    'file_size'       => $fileSize,
                    'ipfs_cid'        => $ipfsCid,
                    'ipfs_url'        => $ipfsCid ? pinata_gateway_url($ipfsCid) : null,
                    'created_at'      => date('Y-m-d H:i:s'),
                ],
                'auto_routed' => $autoRouted,
                'message'  => 'Document received and saved successfully.',
            ]);
        } else {
            $insertStmt->close();
            // Check if it's a duplicate key error
            if (strpos($conn->error, 'unique_ref') !== false) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error'   => 'A document with reference_number "' . $referenceNumber . '" already exists',
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Database error: ' . $conn->error,
                ]);
            }
        }
        break;

    case 'list':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use GET']);
            break;
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $search = $_GET['search'] ?? '';
        $docType = $_GET['type'] ?? '';
        $sourceSystem = $_GET['source_system'] ?? '';

        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $where .= " AND (title LIKE ? OR description LIKE ? OR reference_number LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "sss";
        }
        if (!empty($docType)) {
            $where .= " AND document_type = ?";
            $params[] = $docType;
            $types .= "s";
        }
        if (!empty($sourceSystem)) {
            $where .= " AND source_system = ?";
            $params[] = $sourceSystem;
            $types .= "s";
        }

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM external_documents $where";
        $countStmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Fetch page
        $listSql = "SELECT * FROM external_documents $where ORDER BY created_at DESC LIMIT ?, ?";
        $listStmt = $conn->prepare($listSql);
        $allParams = array_merge($params, [$offset, $perPage]);
        $allTypes = $types . "ii";
        $listStmt->bind_param($allTypes, ...$allParams);
        $listStmt->execute();
        $res = $listStmt->get_result();
        $documents = [];
        while ($row = $res->fetch_assoc()) {
            $documents[] = $row;
        }
        $listStmt->close();

        echo json_encode([
            'success'   => true,
            'documents' => $documents,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => $total > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ]);
        break;

    case 'get':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use GET']);
            break;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        $stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $doc = $res->fetch_assoc();
        $stmt->close();

        if (!$doc) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            break;
        }

        echo json_encode(['success' => true, 'document' => $doc]);
        break;

    case 'stats':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use GET']);
            break;
        }

        $totalResult = $conn->query("SELECT COUNT(*) as total FROM external_documents");
        $total = (int)$totalResult->fetch_assoc()['total'];

        $byType = [];
        $typeRes = $conn->query("SELECT document_type, COUNT(*) as cnt FROM external_documents GROUP BY document_type");
        while ($row = $typeRes->fetch_assoc()) {
            $byType[$row['document_type']] = (int)$row['cnt'];
        }

        $bySource = [];
        $sourceRes = $conn->query("SELECT source_system, COUNT(*) as cnt FROM external_documents GROUP BY source_system");
        while ($row = $sourceRes->fetch_assoc()) {
            $bySource[$row['source_system']] = (int)$row['cnt'];
        }

        $recentRes = $conn->query("SELECT COUNT(*) as cnt FROM external_documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $recent = (int)$recentRes->fetch_assoc()['cnt'];

        echo json_encode([
            'success' => true,
            'stats'   => [
                'total_documents'    => $total,
                'recent_uploads_7d'  => $recent,
                'by_type'            => $byType,
                'by_source_system'   => $bySource,
            ],
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid action',
            'available_actions' => ['', 'create', 'list', 'get', 'stats'],
        ]);
}

$conn->close();

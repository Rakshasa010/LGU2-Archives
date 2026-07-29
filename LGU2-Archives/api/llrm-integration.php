<?php
/**
 * LLRM Integration API
 * 
 * Handles push/pull/sync operations between LAS and LLRM.
 * Requires admin session for all operations.
 * 
 * Actions:
 *   - push        Push a single LAS record to LLRM (POST: record_id)
 *   - batch_push  Push multiple LAS records to LLRM (POST: batch_size)
 *   - pull        Pull documents from LLRM to LAS (GET: type, status, page, per_page, save)
 *   - list        List LLRM documents (GET: type, status, page, per_page, search, sort_by, sort_dir)
 *   - get         Get single LLRM document (GET: id)
 *   - download    Download LLRM document (GET: id)
 *   - stats       Get LLRM archive statistics
 *   - types       Get LLRM document types and statuses
 *   - search      Search LLRM documents (GET: q, type, status, page, per_page)
 *   - update      Update LLRM document metadata (PUT: id, body)
 *   - delete      Delete LLRM document (DELETE: id)
 *   - health      Check LLRM API health
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../authdatabase.php';
require_once __DIR__ . '/../includes/llrm-service.php';

// Verify admin role
$userId = (int)$_SESSION['user_id'];
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes->fetch_assoc();
$roleStmt->close();

if (!$user || strtolower($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$llrm = new LLRMService();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Parse JSON body for PUT/POST
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (!is_array($jsonBody)) {
    $jsonBody = null;
}

switch ($action) {

    case 'health':
        echo json_encode($llrm->healthCheck());
        break;

    case 'push':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use POST']);
            break;
        }
        $recordId = (int)($_POST['record_id'] ?? ($jsonBody['record_id'] ?? 0));
        if ($recordId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing record_id']);
            break;
        }
        $sourceType = $_POST['source_type'] ?? ($jsonBody['source_type'] ?? 'legislative');
        if ($sourceType === 'archive') {
            echo json_encode($llrm->pushArchiveFileById($conn, $recordId));
        } else {
            echo json_encode($llrm->pushRecordById($conn, $recordId));
        }
        break;

    case 'batch_push':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use POST']);
            break;
        }
        $batchSize = (int)($_POST['batch_size'] ?? ($jsonBody['batch_size'] ?? 50));
        $result = $llrm->batchPushToLLRM($conn, $batchSize);
        echo json_encode(['success' => true, 'results' => $result]);
        break;

    case 'pull':
        $params = [
            'page'     => (int)($_GET['page'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 20),
            'type'     => $_GET['type'] ?? '',
            'status'   => $_GET['status'] ?? '',
            'source_system' => $_GET['source_system'] ?? '',
        ];
        $saveToDb = isset($_GET['save']) && $_GET['save'] === '1';
        echo json_encode($llrm->pullDocuments($params, $saveToDb, $conn));
        break;

    case 'list':
        $params = [
            'page'     => (int)($_GET['page'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 20),
            'search'   => $_GET['search'] ?? '',
            'type'     => $_GET['type'] ?? '',
            'status'   => $_GET['status'] ?? '',
            'source_system' => $_GET['source_system'] ?? '',
            'sort_by'  => $_GET['sort_by'] ?? 'created_at',
            'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
            'tags'      => $_GET['tags'] ?? '',
            'reference' => $_GET['reference'] ?? '',
        ];
        echo json_encode($llrm->listDocuments($params));
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        echo json_encode($llrm->getDocument($id));
        break;

    case 'download':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        $result = $llrm->downloadDocument($id);
        if (isset($result['success']) && $result['success']) {
            // Stream file to browser
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="llrm_document_' . $id . '"');
            echo $result['data'];
        } else {
            echo json_encode($result);
        }
        break;

    case 'stats':
        echo json_encode($llrm->getStats());
        break;

    case 'types':
        echo json_encode($llrm->getTypes());
        break;

    case 'search':
        $q = $_GET['q'] ?? '';
        if (empty($q)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing query parameter "q"']);
            break;
        }
        $params = [
            'page'     => (int)($_GET['page'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 20),
            'type'     => $_GET['type'] ?? '',
            'status'   => $_GET['status'] ?? '',
        ];
        echo json_encode($llrm->searchDocuments($q, $params));
        break;

    case 'update':
        if ($method !== 'PUT' && $method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use PUT or POST']);
            break;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        $data = $jsonBody ?? $_POST;
        echo json_encode($llrm->updateDocument($id, $data));
        break;

    case 'delete':
        if ($method !== 'DELETE' && $method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Use DELETE or POST']);
            break;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        echo json_encode($llrm->deleteDocument($id));
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid action',
            'available_actions' => [
                'health', 'push', 'batch_push', 'pull', 'list', 'get',
                'download', 'stats', 'types', 'search', 'update', 'delete'
            ],
        ]);
}

$conn->close();

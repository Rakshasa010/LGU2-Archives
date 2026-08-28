<?php
/**
 * Route External Document API
 *
 * Manually routes a pending document from the External Documents queue into a
 * folder of main storage (archive_files / legislative_records) chosen by the user.
 *
 * Actions:
 *   GET  ?action=folders            List all routable folders (grouped)
 *   GET  ?action=suggest&type=...   Suggested folder for a document type
 *   POST (external_id, folder_kind, folder_id)      Route a single document
 *   POST (external_ids[], folder_kind, folder_id)   Route multiple documents in bulk
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../authdatabase.php';
require_once __DIR__ . '/../includes/llrm-intake.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'folders') {
    $folders = [];
    $leg = $conn->query("SELECT MIN(id) AS id, name, MAX(type) AS type FROM legislative_folders WHERE parent_id IS NULL GROUP BY name ORDER BY name ASC");
    while ($row = $leg ? $leg->fetch_assoc() : null) {
        $folders[] = [
            'kind' => 'legislative',
            'id'   => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
        ];
    }
    $arch = $conn->query("SELECT id, name FROM archive_folders ORDER BY name ASC");
    while ($row = $arch ? $arch->fetch_assoc() : null) {
        $folders[] = [
            'kind' => 'archive',
            'id'   => (int)$row['id'],
            'name' => $row['name'],
            'type' => null,
        ];
    }
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

if ($action === 'suggest') {
    $type = $_GET['type'] ?? 'archive';
    $normalized = llrm_intake_normalize_type($type);
    $kinds = [];
    if ($normalized['kind'] === 'legislative') {
        $st = $conn->prepare("SELECT id, name FROM legislative_folders WHERE type = ? AND parent_id IS NULL LIMIT 1");
        $st->bind_param("s", $normalized['leg_type']);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $kinds[] = ['kind' => 'legislative', 'id' => (int)$row['id']];
        }
    } else {
        $st = $conn->prepare("SELECT id, name FROM archive_folders WHERE name = ? LIMIT 1");
        $st->bind_param("s", $normalized['folder_name']);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $kinds[] = ['kind' => 'archive', 'id' => (int)$row['id']];
        }
    }
    echo json_encode(['success' => true, 'suggestions' => $kinds, 'type' => $type]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$externalId = (int)($_POST['external_id'] ?? 0);
$externalIds = $_POST['external_ids'] ?? [];
$folderKind = $_POST['folder_kind'] ?? '';
$folderId = (int)($_POST['folder_id'] ?? 0);

$isBulk = is_array($externalIds) && count($externalIds) > 0;

if ($isBulk) {
    if (!in_array($folderKind, ['archive', 'legislative'], true) || $folderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing folder_kind or folder_id']);
        exit;
    }
} elseif ($externalId <= 0 || !in_array($folderKind, ['archive', 'legislative'], true) || $folderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing external_id, folder_kind or folder_id']);
    exit;
}

// Verify the target folder exists once
$foldersTbl = $folderKind === 'legislative' ? 'legislative_folders' : 'archive_folders';
$chk = $conn->prepare("SELECT id FROM $foldersTbl WHERE id = ? LIMIT 1");
$chk->bind_param("i", $folderId);
$chk->execute();
$folderExists = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$folderExists) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target folder not found']);
    exit;
}

// Route one pending external document into the chosen folder
$routeOne = function ($conn, $externalId, $folderKind, $folderId) {
    $stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ?");
    $stmt->bind_param("i", $externalId);
    $stmt->execute();
    $ext = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ext) {
        return ['success' => false, 'code' => 'not_found', 'error' => 'External document not found'];
    }

    if (strtolower($ext['status'] ?? '') === 'routed') {
        return ['success' => false, 'code' => 'already_routed', 'error' => 'Document has already been routed'];
    }

    // Build route document + file source from the staged record
    $routeDoc = [
        'title'             => $ext['title'],
        'type'              => $ext['document_type'] ?? 'archive',
        'author'            => 'LLRM Import',
        'document_date'     => $ext['document_date'] ?? null,
        'source_system'     => $ext['source_system'] ?? 'LLRM',
        'source_record_id'  => $ext['external_id'] !== null && ctype_digit($ext['external_id']) ? (int)$ext['external_id'] : null,
        'target_folder_kind' => $folderKind,
        'target_folder_id'  => $folderId,
    ];

    $fileSpec = null;
    if (!empty($ext['file_path'])) {
        $absFile = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ext['file_path']);
        if (file_exists($absFile)) {
            $fileSpec = [
                'tmp_path'  => $absFile,
                'orig_name' => $ext['file_name'] ?: ($ext['title'] . '.pdf'),
                'copy'      => false,
            ];
        }
    }

    $result = llrm_intake_route($conn, $routeDoc, $fileSpec, ['notification_prefix' => $ext['source_system']]);

    if (empty($result['success'])) {
        return [
            'success'     => false,
            'code'        => !empty($result['duplicate']) ? 'duplicate' : 'route_failed',
            'error'       => $result['error'] ?? 'Routing failed',
            'existing_id' => $result['existing_id'] ?? null,
        ];
    }

    // Remove the document from the External Documents queue (source file was moved)
    $del = $conn->prepare("DELETE FROM external_documents WHERE id = ?");
    $del->bind_param("i", $externalId);
    $del->execute();
    $del->close();

    return [
        'success'       => true,
        'record_id'     => $result['record_id'],
        'kind'          => $result['kind'],
        'folder_id'     => $result['folder_id'],
        'folder_name'   => $result['folder_name'],
        'unique_number' => $result['unique_number'],
        'file_path'     => $result['file_path'],
        'ipfs_cid'      => $result['ipfs_cid'],
        'ipfs_url'      => $result['ipfs_url'],
        'message'       => 'Document routed to "' . $result['folder_name'] . '" successfully.',
    ];
};

if ($isBulk) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $externalIds), function ($id) { return $id > 0; })));
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid external_ids provided']);
        exit;
    }

    $results = [];
    $routed = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $res = $routeOne($conn, $id, $folderKind, $folderId);
        $res['id'] = $id;
        $results[] = $res;
        if (!empty($res['success'])) $routed++; else $failed++;
    }

    echo json_encode([
        'success' => true,
        'bulk'    => true,
        'total'   => count($ids),
        'routed'  => $routed,
        'failed'  => $failed,
        'results' => $results,
    ]);
    $conn->close();
    exit;
}

$res = $routeOne($conn, $externalId, $folderKind, $folderId);
if (empty($res['success'])) {
    $code = $res['code'] ?? 'route_failed';
    $httpCode = $code === 'not_found' ? 404 : ($code === 'already_routed' ? 409 : 400);
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $res['error']]);
    exit;
}

echo json_encode($res);

$conn->close();

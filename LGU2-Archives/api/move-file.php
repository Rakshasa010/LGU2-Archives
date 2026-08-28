<?php
/**
 * Move File API
 *
 * Moves a file (archive_files or legislative_records) to another folder.
 *
 * POST params:
 *   file_kind  string  'archive' or 'legislative'
 *   file_id    int     The file/record ID
 *   target_folder_id int  The target folder ID
 *
 * GET:
 *   action=folders  List all folders grouped by kind for the move modal
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../authdatabase.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'folders') {
    $folders = [];
    $arch = $conn->query("SELECT id, name FROM archive_folders ORDER BY name ASC");
    while ($row = $arch ? $arch->fetch_assoc() : null) {
        $folders[] = [
            'kind' => 'archive',
            'id'   => (int)$row['id'],
            'name' => $row['name'],
        ];
    }
    $leg = $conn->query("SELECT MIN(id) AS id, name, MAX(type) AS type FROM legislative_folders WHERE parent_id IS NULL GROUP BY name ORDER BY name ASC");
    while ($row = $leg ? $leg->fetch_assoc() : null) {
        $folders[] = [
            'kind' => 'legislative',
            'id'   => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
        ];
    }
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$fileKind = $_POST['file_kind'] ?? '';
$fileId = (int)($_POST['file_id'] ?? 0);
$targetFolderId = (int)($_POST['target_folder_id'] ?? 0);

if (!in_array($fileKind, ['archive', 'legislative'], true) || $fileId <= 0 || $targetFolderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid file_kind, file_id, or target_folder_id']);
    exit;
}

// Verify target folder exists (must be same kind as the file)
$foldersTbl = $fileKind === 'legislative' ? 'legislative_folders' : 'archive_folders';
$chk = $conn->prepare("SELECT id, name FROM $foldersTbl WHERE id = ? LIMIT 1");
$chk->bind_param("i", $targetFolderId);
$chk->execute();
$targetFolder = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$targetFolder) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target folder not found']);
    exit;
}

// Move the file
$filesTbl = $fileKind === 'legislative' ? 'legislative_records' : 'archive_files';
$folderCol = 'folder_id';

$stmt = $conn->prepare("UPDATE $filesTbl SET $folderCol = ? WHERE id = ?");
$stmt->bind_param("ii", $targetFolderId, $fileId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $conn->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'File moved to "' . $targetFolder['name'] . '" successfully.',
            'target_folder' => [
                'id'   => (int)$targetFolder['id'],
                'name' => $targetFolder['name'],
            ],
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'File not found or already in that folder']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();

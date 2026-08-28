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

$userId = (int)$_SESSION['user_id'];

// Get file info before moving (for audit log)
$filesTbl = $fileKind === 'legislative' ? 'legislative_records' : 'archive_files';
$titleCol = $fileKind === 'legislative' ? 'title' : 'name';
$srcStmt = $conn->prepare("SELECT $titleCol, folder_id FROM $filesTbl WHERE id = ?");
$srcStmt->bind_param("i", $fileId);
$srcStmt->execute();
$fileRow = $srcStmt->get_result()->fetch_assoc();
$srcStmt->close();

if (!$fileRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

$fileName = $fileRow[$titleCol] ?? 'Unknown';
$sourceFolderId = (int)$fileRow['folder_id'];

// Get source folder name
$srcFolderStmt = $conn->prepare("SELECT name FROM $foldersTbl WHERE id = ?");
$srcFolderStmt->bind_param("i", $sourceFolderId);
$srcFolderStmt->execute();
$srcFolderRow = $srcFolderStmt->get_result()->fetch_assoc();
$srcFolderStmt->close();
$sourceFolderName = $srcFolderRow['name'] ?? 'Unknown';

// Move the file
$folderCol = 'folder_id';
$updStmt = $conn->prepare("UPDATE $filesTbl SET $folderCol = ? WHERE id = ?");
$updStmt->bind_param("ii", $targetFolderId, $fileId);

if ($updStmt->execute()) {
    // Audit log via notifications table (same as audit-logs.php reads from)
    $notifTime = date('h:i A');
    $notifDate = date('Y-m-d');
    $notifContent = "File moved: \"$fileName\" from \"$sourceFolderName\" to \"" . $targetFolder['name'] . "\"";
    $notifAbout = 'Storage';
    $notifStatus = 'unread';

    // Get user name
    $userName = null;
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $uRes = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    if ($uRes) $userName = trim($uRes['full_name'] ?? '');

    $logStmt = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status) VALUES (?, ?, ?, ?, ?, ?)");
    $logStmt->bind_param('ssssss', $notifTime, $notifDate, $notifContent, $notifAbout, $userName, $notifStatus);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'File moved to "' . $targetFolder['name'] . '" successfully.',
        'target_folder' => [
            'id'   => (int)$targetFolder['id'],
            'name' => $targetFolder['name'],
        ],
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$updStmt->close();
$conn->close();

<?php
/**
 * Fetch Storage Files/Folders API
 * Returns a hierarchical folder tree with files
 * Integrates with existing archive_files and archive_folders tables
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';

header('Content-Type: application/json');

$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $folders = [];
    $files = [];
    
    // Get all archive folders if no folder_id specified
    if ($folder_id === null) {
        $folderQuery = $conn->prepare("SELECT id, name FROM archive_folders ORDER BY name ASC");
        if (!$folderQuery) {
            throw new Exception("Folder query prepare failed: " . $conn->error);
        }
        
        if (!$folderQuery->execute()) {
            throw new Exception("Folder query execute failed: " . $folderQuery->error);
        }
        
        $folderResult = $folderQuery->get_result();
        while ($row = $folderResult->fetch_assoc()) {
            $folders[] = [
                'id' => $row['id'],
                'type' => 'folder',
                'name' => $row['name']
            ];
        }
        $folderResult->free();
        $folderQuery->close();
    }
    
    // Build the file query
    $fileQuery = "SELECT id, name, file_path, file_size, created_at FROM archive_files ";
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($folder_id !== null) {
        $conditions[] = "folder_id = ?";
        $params[] = $folder_id;
        $types .= 'i';
    }
    
    if (!empty($search)) {
        $conditions[] = "(name LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $fileQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $fileQuery .= " ORDER BY name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute file query
    $stmt = $conn->prepare($fileQuery);
    if (!$stmt) {
        throw new Exception("File query prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("File query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Determine file type from file_path extension
        $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
        $fileType = getFileType($ext);
        
        $files[] = [
            'id' => $row['id'],
            'type' => 'file',
            'name' => $row['name'],
            'file_type' => $fileType,
            'path' => $row['file_path'],
            'size' => (int)$row['file_size'],
            'size_formatted' => formatFileSize($row['file_size']),
            'uploaded_at' => $row['created_at']
        ];
    }
    $result->free();
    $stmt->close();
    
    // Get total count for pagination - need to recalculate without limit/offset
    $countQuery = "SELECT COUNT(*) as total FROM archive_files";
    $countParams = [];
    $countTypes = '';
    
    if ($folder_id !== null) {
        $countQuery .= " WHERE folder_id = ?";
        $countParams[] = $folder_id;
        $countTypes .= 'i';
    }
    
    if (!empty($search)) {
        if ($folder_id !== null) {
            $countQuery .= " AND (name LIKE ?)";
        } else {
            $countQuery .= " WHERE (name LIKE ?)";
        }
        $search_param = '%' . $search . '%';
        $countParams[] = $search_param;
        $countTypes .= 's';
    }
    
    $countStmt = $conn->prepare($countQuery);
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $row = $countResult->fetch_assoc();
    $total = (int)$row['total'];
    $countResult->free();
    $countStmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'folders' => $folders,
            'files' => $files,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getFileType($extension) {
    $map = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip'
    ];
    return $map[$extension] ?? 'application/octet-stream';
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

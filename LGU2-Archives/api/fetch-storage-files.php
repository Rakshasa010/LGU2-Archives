<?php
/**
 * Fetch Storage Files/Folders API
 * Returns a hierarchical folder tree with files
 * Supports pagination and folder filtering
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
    // Build folder hierarchy
    $folders = [];
    $files = [];
    
    // Get all archive folders if no folder_id specified
    if ($folder_id === null) {
        $folderQuery = $conn->prepare("SELECT id, name, slug, description FROM archive_folders ORDER BY name ASC");
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
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'children_count' => 0
            ];
        }
        $folderQuery->close();
    }
    
    // Get files - build search query
    $fileQuery = "SELECT id, file_name, file_type, file_size, archive_folder_id, uploaded_at, version FROM archive_files ";
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($folder_id !== null) {
        $conditions[] = "archive_folder_id = ?";
        $params[] = $folder_id;
        $types .= 'i';
    }
    
    if (!empty($search)) {
        $conditions[] = "(file_name LIKE ? OR file_type LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }
    
    if (!empty($conditions)) {
        $fileQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $fileQuery .= " ORDER BY file_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Prepare query with dynamic types
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
        $files[] = [
            'id' => $row['id'],
            'type' => 'file',
            'name' => $row['file_name'],
            'file_type' => $row['file_type'],
            'size' => (int)$row['file_size'],
            'size_formatted' => formatFileSize($row['file_size']),
            'uploaded_at' => $row['uploaded_at'],
            'version' => $row['version'] ?? '1.0'
        ];
    }
    $stmt->close();
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM archive_files";
    if (!empty($conditions)) {
        $countQuery .= " WHERE " . implode(" AND ", array_slice($conditions, 0, count($conditions)));
    }
    
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params) && count($params) > 2) {
        // Rebind for count query
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $total = (int)$countResult['total'];
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
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
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

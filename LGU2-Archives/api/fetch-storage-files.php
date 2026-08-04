<?php
/**
 * Fetch Storage Files/Folders API - COMPLETE STORAGE INTEGRATION
 * Returns ALL folders and files from the entire storage system:
 * - Archive folders (archive_folders + archive_files)
 * - Legislative folders (legislative_folders + legislative_records)
 * 
 * This provides the same complete storage view as storage.php
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';
require_once __DIR__ . '/../includes/pinata.php';

header('Content-Type: application/json');

$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;
$folder_type = isset($_GET['folder_type']) ? $_GET['folder_type'] : 'archive'; // 'archive' or 'legislative'
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $folders = [];
    $files = [];
    $total = 0;
    
    // ============================================
    // SHOW ALL FOLDERS (when no folder_id specified)
    // ============================================
    if ($folder_id === null) {
        // Get legislative folders
        $legQuery = $conn->prepare("SELECT id, name, type FROM legislative_folders WHERE parent_id IS NULL ORDER BY name ASC");
        if ($legQuery) {
            $legQuery->execute();
            $legResult = $legQuery->get_result();
            while ($row = $legResult->fetch_assoc()) {
                $folders[] = [
                    'id' => 'leg_' . $row['id'], // Prefix to distinguish from archive folders
                    'type' => 'folder',
                    'folder_type' => 'legislative',
                    'name' => $row['name'],
                    'icon' => 'bi-folder-fill',
                    'color' => getLegislativeColor($row['type'])
                ];
            }
            $legResult->free();
            $legQuery->close();
        }
        
        // Get archive folders
        $archiveQuery = $conn->prepare("SELECT id, name FROM archive_folders ORDER BY name ASC");
        if ($archiveQuery) {
            $archiveQuery->execute();
            $archiveResult = $archiveQuery->get_result();
            while ($row = $archiveResult->fetch_assoc()) {
                $folders[] = [
                    'id' => 'arch_' . $row['id'], // Prefix to distinguish
                    'type' => 'folder',
                    'folder_type' => 'archive',
                    'name' => $row['name'],
                    'icon' => 'bi-folder-fill',
                    'color' => 'slate'
                ];
            }
            $archiveResult->free();
            $archiveQuery->close();
        }
    }
    
    // ============================================
    // FETCH FILES FROM SPECIFIC FOLDER
    // ============================================
    if ($folder_id !== null) {
        // Determine if it's legislative or archive folder
        if (strpos($folder_id, 'leg_') === 0) {
            $actual_id = (int)substr($folder_id, 4);
            $files = fetchLegislativeFiles($conn, $actual_id, $search, $limit, $offset);
            $total = countLegislativeFiles($conn, $actual_id, $search);
        } elseif (strpos($folder_id, 'arch_') === 0) {
            $actual_id = (int)substr($folder_id, 5);
            $files = fetchArchiveFiles($conn, $actual_id, $search, $limit, $offset);
            $total = countArchiveFiles($conn, $actual_id, $search);
        }
    } else {
        // Show all files from all folders (for search across everything)
        if (!empty($search)) {
            $archiveFiles = fetchArchiveFiles($conn, null, $search, $limit, $offset);
            $legislativeFiles = fetchLegislativeFiles($conn, null, $search, 0, 0);
            $files = array_merge($archiveFiles, $legislativeFiles);
            $total = countArchiveFiles($conn, null, $search) + countLegislativeFiles($conn, null, $search);
            
            // Re-slice for pagination
            $files = array_slice($files, $offset, $limit);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'folders' => $folders,
            'files' => $files,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil(max($total, 1) / $limit)
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function fetchArchiveFiles($conn, $folder_id, $search, $limit, $offset) {
    $files = [];
    $query = "SELECT id, name, file_path, file_size, ipfs_cid, created_at, version FROM archive_files WHERE parent_version_id IS NULL ";
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
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY name ASC";
    
    if ($limit > 0) {
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    }
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
            
            // Get all versions for this file
            $versions = getArchiveVersions($conn, $row['id']);
            
            $files[] = [
                'id' => 'arch_file_' . $row['id'],
                'type' => 'file',
                'source' => 'archive',
                'name' => $row['name'],
                'file_type' => getFileType($ext),
                'path' => $row['file_path'],
                'size' => (int)$row['file_size'],
                'size_formatted' => formatFileSize($row['file_size']),
                'uploaded_at' => $row['created_at'],
                'version' => (int)($row['version'] ?? 1),
                'ipfs_cid' => $row['ipfs_cid'] ?? null,
                'ipfs_url' => !empty($row['ipfs_cid']) ? pinata_gateway_url($row['ipfs_cid']) : null,
                'versions' => $versions
            ];
        }
        
        $result->free();
        $stmt->close();
    }
    
    return $files;
}

function fetchLegislativeFiles($conn, $folder_id, $search, $limit, $offset) {
    $files = [];
    $query = "SELECT id, title, file_path, ipfs_cid, created_at, version FROM legislative_records WHERE parent_version_id IS NULL ";
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($folder_id !== null) {
        // Map folder_id to legislative type
        $typeQuery = $conn->prepare("SELECT type FROM legislative_folders WHERE id = ?");
        $typeQuery->bind_param("i", $folder_id);
        $typeQuery->execute();
        $typeResult = $typeQuery->get_result();
        if ($typeRow = $typeResult->fetch_assoc()) {
            $legislativeType = $typeRow['type'];
            $conditions[] = "type = ?";
            $params[] = $legislativeType;
            $types .= 's';
        }
        $typeResult->free();
        $typeQuery->close();
    }
    
    if (!empty($search)) {
        $conditions[] = "(title LIKE ?)";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY title ASC";
    
    if ($limit > 0) {
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    }
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $filePath = $row['file_path'];
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Get all versions for this file
            $versions = getLegislativeVersions($conn, $row['id']);
            
            $files[] = [
                'id' => 'leg_file_' . $row['id'],
                'type' => 'file',
                'source' => 'legislative',
                'name' => $row['title'],
                'file_type' => getFileType($ext),
                'path' => $filePath,
                'size' => $fileSize,
                'size_formatted' => formatFileSize($fileSize),
                'uploaded_at' => $row['created_at'],
                'version' => (int)($row['version'] ?? 1),
                'ipfs_cid' => $row['ipfs_cid'] ?? null,
                'ipfs_url' => !empty($row['ipfs_cid']) ? pinata_gateway_url($row['ipfs_cid']) : null,
                'versions' => $versions
            ];
        }
        
        $result->free();
        $stmt->close();
    }
    
    return $files;
}

function getLegislativeVersions($conn, $file_id) {
    $versions = [];
    $root_id = $file_id;
    
    // Find root id if this is a version
    $findParent = $conn->prepare("SELECT parent_version_id FROM legislative_records WHERE id = ?");
    if ($findParent) {
        $findParent->bind_param("i", $file_id);
        $findParent->execute();
        $res = $findParent->get_result();
        if ($row = $res->fetch_assoc()) {
            $root_id = $row['parent_version_id'] ? $row['parent_version_id'] : $file_id;
        }
        $res->free();
        $findParent->close();
    }
    
    $sql = "SELECT id, title, version, created_at, author, file_path, ipfs_cid FROM legislative_records WHERE id = ? OR parent_version_id = ? ORDER BY version DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $root_id, $root_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($v = $result->fetch_assoc()) {
            $versions[] = [
                'id' => 'leg_file_' . $v['id'],
                'version' => (int)$v['version'],
                'title' => $v['title'],
                'created_at' => $v['created_at'],
                'author' => $v['author'],
                'path' => $v['file_path'],
                'ipfs_cid' => $v['ipfs_cid'] ?? null,
                'ipfs_url' => !empty($v['ipfs_cid']) ? pinata_gateway_url($v['ipfs_cid']) : null
            ];
        }
        $result->free();
        $stmt->close();
    }
    return $versions;
}

function getArchiveVersions($conn, $file_id) {
    $versions = [];
    $root_id = $file_id;
    
    $findParent = $conn->prepare("SELECT parent_version_id FROM archive_files WHERE id = ?");
    if ($findParent) {
        $findParent->bind_param("i", $file_id);
        $findParent->execute();
        $res = $findParent->get_result();
        if ($row = $res->fetch_assoc()) {
            $root_id = $row['parent_version_id'] ? (int)$row['parent_version_id'] : $file_id;
        }
        $res->free();
        $findParent->close();
    }
    
    $sql = "SELECT id, name, version, created_at, file_path, ipfs_cid FROM archive_files WHERE id = ? OR parent_version_id = ? ORDER BY version DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $root_id, $root_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($v = $result->fetch_assoc()) {
            $versions[] = [
                'id' => 'arch_file_' . $v['id'],
                'version' => (int)$v['version'],
                'title' => $v['name'],
                'created_at' => $v['created_at'],
                'path' => $v['file_path'],
                'ipfs_cid' => $v['ipfs_cid'] ?? null,
                'ipfs_url' => !empty($v['ipfs_cid']) ? pinata_gateway_url($v['ipfs_cid']) : null
            ];
        }
        $result->free();
        $stmt->close();
    }
    return $versions;
}

function countArchiveFiles($conn, $folder_id, $search) {
    $query = "SELECT COUNT(*) as total FROM archive_files";
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
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = (int)$row['total'];
        $result->free();
        $stmt->close();
        return $total;
    }
    
    return 0;
}

function countLegislativeFiles($conn, $folder_id, $search) {
    $query = "SELECT COUNT(*) as total FROM legislative_records WHERE parent_version_id IS NULL";
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($folder_id !== null) {
        $typeQuery = $conn->prepare("SELECT type FROM legislative_folders WHERE id = ?");
        $typeQuery->bind_param("i", $folder_id);
        $typeQuery->execute();
        $typeResult = $typeQuery->get_result();
        if ($typeRow = $typeResult->fetch_assoc()) {
            $legislativeType = $typeRow['type'];
            $conditions[] = "type = ?";
            $params[] = $legislativeType;
            $types .= 's';
        }
        $typeResult->free();
        $typeQuery->close();
    }
    
    if (!empty($search)) {
        $conditions[] = "(title LIKE ?)";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = (int)$row['total'];
        $result->free();
        $stmt->close();
        return $total;
    }
    
    return 0;
}

function getLegislativeColor($type) {
    $map = [
        'Ordinance' => 'orange',
        'Resolution' => 'orange',
        'Public Hearing' => 'blue',
        'Meeting' => 'indigo'
    ];
    return $map[$type] ?? 'slate';
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

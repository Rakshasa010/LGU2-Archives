<?php
// Prevent any HTML error output from breaking the JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'authdatabase.php';

// Ensure no output has been sent before headers
if (headers_sent()) {
    // If headers were already sent, we can't set Content-Type JSON cleanly, 
    // but we can try to minimize damage. However, usually this means whitespace in included files.
    // Since we fixed authdatabase.php, this should be fine.
}
header('Content-Type: application/json');

$response = ['success' => false, 'files' => [], 'error' => ''];

try {
    $limit = 10;
    $files = [];

    // 1. Fetch from archive_files (Dynamic folders)
    // We join with archive_folders to get the folder name
    $sql1 = "SELECT f.id, f.name, f.file_path, f.created_at, 'Archive File' as type, 'archive' as source, f.folder_id, fo.name as folder_name 
             FROM archive_files f
             JOIN archive_folders fo ON f.folder_id = fo.id
             WHERE f.created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
             ORDER BY f.created_at DESC LIMIT ?";
    
    if ($stmt1 = $conn->prepare($sql1)) {
        $stmt1->bind_param("i", $limit);
        if ($stmt1->execute()) {
            $res1 = $stmt1->get_result();
            while ($row = $res1->fetch_assoc()) {
                $sizeBytes = 0;
                $p = $row['file_path'];
                if ($p && file_exists($p)) {
                    $size = @filesize($p);
                    if ($size !== false) $sizeBytes = (int)$size;
                } else {
                    $abs = __DIR__ . '/' . $p;
                    if ($p && file_exists($abs)) {
                        $size = @filesize($abs);
                        if ($size !== false) $sizeBytes = (int)$size;
                    }
                }
                $files[] = [
                    'id' => $row['id'],
                    'title' => $row['name'],
                    'type' => 'Archive File', 
                    'raw_date' => $row['created_at'],
                    'date' => date('M j, Y', strtotime($row['created_at'])),
                    'source' => 'archive',
                    'folder_id' => $row['folder_id'],
                    'folder_name' => $row['folder_name'],
                    'download_url' => 'download_file.php?id=' . $row['id'],
                    'preview_url' => 'download_file.php?id=' . $row['id'] . '&view=1',
                    'size_bytes' => $sizeBytes
                ];
            }
        }
        $stmt1->close();
    }

    // 2. Fetch from legislative_records (Ordinances, etc.)
    $sql2 = "SELECT lr.id, lr.title, lr.type, lr.month, lr.year, lr.author, lr.file_path, lr.created_at, lr.folder_id, lf.name as folder_name
             FROM legislative_records lr
             LEFT JOIN legislative_folders lf ON lr.folder_id = lf.id
             WHERE lr.created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
             ORDER BY lr.created_at DESC LIMIT ?";
    
    if ($stmt2 = $conn->prepare($sql2)) {
        $stmt2->bind_param("i", $limit);
        if ($stmt2->execute()) {
            $res2 = $stmt2->get_result();
            while ($row = $res2->fetch_assoc()) {
                $sizeBytes = 0;
                $p = $row['file_path'] ?? '';
                if ($p && file_exists($p)) {
                    $size = @filesize($p);
                    if ($size !== false) $sizeBytes = (int)$size;
                } else {
                    $abs = __DIR__ . '/' . $p;
                    if ($p && file_exists($abs)) {
                        $size = @filesize($abs);
                        if ($size !== false) $sizeBytes = (int)$size;
                    }
                }
                $params = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'month' => $row['month'],
                    'year' => $row['year'],
                    'author' => $row['author']
                ];
                $files[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'raw_date' => $row['created_at'],
                    'date' => date('M j, Y', strtotime($row['created_at'])),
                    'source' => 'legislative',
                    'author' => $row['author'],
                    'folder_id' => $row['folder_id'],
                    'folder_name' => $row['folder_name'] ?? '',
                    'download_params' => http_build_query($params),
                    'preview_url' => 'download.php?action=view&' . http_build_query($params),
                    'size_bytes' => $sizeBytes
                ];
            }
        }
        $stmt2->close();
    }

    // Sort combined results by created_at DESC
    usort($files, function($a, $b) {
        return strtotime($b['raw_date']) - strtotime($a['raw_date']);
    });

    // Slice to limit
    $files = array_slice($files, 0, $limit);

    $response['success'] = true;
    $response['files'] = $files;

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>

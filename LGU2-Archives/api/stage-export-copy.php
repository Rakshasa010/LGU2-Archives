<?php
/**
 * Stage Export Copy API
 * Creates a temporary duplicate of a file for export
 * Integrated with existing archive_files table
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$file_id = isset($input['file_id']) ? $input['file_id'] : ''; // Now accepts string with prefix
$request_id = isset($input['request_id']) ? (int)$input['request_id'] : 0;

if (empty($file_id) || $request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file or request ID']);
    exit;
}

try {
    // Ensure staging directory exists
    $stagingDir = '../storage/temp_exports';
    if (!is_dir($stagingDir)) {
        if (!mkdir($stagingDir, 0755, true)) {
            throw new Exception("Failed to create staging directory");
        }
    }
    
    $file = null;
    $originalPath = null;
    
    // Determine file source based on ID prefix
    if (strpos($file_id, 'arch_file_') === 0) {
        // Archive file
        $actual_id = (int)substr($file_id, 10);
        $stmt = $conn->prepare("SELECT name, file_path, file_size FROM archive_files WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Query prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $actual_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $file = $result->fetch_assoc();
            $originalPath = $file['file_path'];
        }
        
        $result->free();
        $stmt->close();
        
    } elseif (strpos($file_id, 'leg_file_') === 0) {
        // Legislative file
        $actual_id = (int)substr($file_id, 9);
        $stmt = $conn->prepare("SELECT title as name, file_path FROM legislative_records WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Query prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $actual_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $file = $result->fetch_assoc();
            $originalPath = $file['file_path'];
            
            // Calculate file size for legislative files
            if (file_exists($originalPath)) {
                $file['file_size'] = filesize($originalPath);
            } else {
                $file['file_size'] = 0;
            }
        }
        
        $result->free();
        $stmt->close();
    }
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found in database']);
        exit;
    }
    
    // Try multiple path variations to find the actual file
    if (!file_exists($originalPath)) {
        // Try with ../ prefix
        $testPath = '../' . $originalPath;
        if (file_exists($testPath)) {
            $originalPath = $testPath;
        } else {
            // Try uploads directory
            $testPath = '../uploads/' . basename($originalPath);
            if (file_exists($testPath)) {
                $originalPath = $testPath;
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Original file not found on server', 'searched_path' => $originalPath]);
                exit;
            }
        }
    }
    
    // Verify file is actually readable
    if (!is_file($originalPath) || !is_readable($originalPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cannot read file from server']);
        exit;
    }
    
    // Generate unique staging filename
    $fileInfo = pathinfo($file['name']);
    $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : 'pdf';
    $staged_file_id = 'export_' . $request_id . '_' . time() . '_' . bin2hex(random_bytes(4));
    $stagedFileName = $staged_file_id . '.' . $extension;
    $stagedFilePath = $stagingDir . '/' . $stagedFileName;
    
    // Copy file to staging area
    if (!copy($originalPath, $stagedFilePath)) {
        throw new Exception("Failed to copy file to staging area");
    }
    
    // Get actual file size after copy
    $actualFileSize = filesize($stagedFilePath);
    
    // Update request with staged file info
    $updateStmt = $conn->prepare("UPDATE requests SET staged_file_id = ?, staged_file_name = ?, staged_file_size = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("ssii", $staged_file_id, $file['name'], $actualFileSize, $request_id);
    if (!$updateStmt->execute()) {
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    $updateStmt->close();
    
    // Log audit action if audit_logs table exists
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, file_id, details, timestamp) VALUES (?, ?, ?, ?, NOW())");
    if ($auditStmt) {
        $action = 'File Staged for Export';
        $details = "File: {$file['name']}, Request ID: {$request_id}, Source: " . (strpos($file_id, 'leg_') === 0 ? 'Legislative' : 'Archive');
        $auditStmt->bind_param("isss", $_SESSION['user_id'], $action, $file_id, $details);
        $auditStmt->execute();
        $auditStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'staged_file_id' => $staged_file_id,
            'file_name' => $file['name'],
            'file_size' => $actualFileSize,
            'file_size_formatted' => formatFileSize($actualFileSize),
            'staged_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

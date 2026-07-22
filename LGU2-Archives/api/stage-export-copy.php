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
$file_id = isset($input['file_id']) ? (int)$input['file_id'] : 0;
$request_id = isset($input['request_id']) ? (int)$input['request_id'] : 0;

if ($file_id <= 0 || $request_id <= 0) {
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
    
    // Get file details from database using the correct table structure
    $stmt = $conn->prepare("SELECT name, file_path, file_size FROM archive_files WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $file_id);
    if (!$stmt->execute()) {
        throw new Exception("Query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found']);
        $stmt->close();
        exit;
    }
    
    $file = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    
    // Original file path - handle relative paths
    $originalPath = $file['file_path'];
    
    // Try multiple path variations
    if (!file_exists($originalPath)) {
        // Try with ../ prefix
        $originalPath = '../' . $originalPath;
        if (!file_exists($originalPath)) {
            // Try uploads directory
            $originalPath = '../uploads/' . $file['file_path'];
            if (!file_exists($originalPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Original file not found on server']);
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
    $staged_file_id = 'export_' . $request_id . '_' . time() . '_' . bin2hex(random_bytes(4));
    $stagedFileName = $staged_file_id . '.' . $fileInfo['extension'];
    $stagedFilePath = $stagingDir . '/' . $stagedFileName;
    
    // Copy file to staging area
    if (!copy($originalPath, $stagedFilePath)) {
        throw new Exception("Failed to copy file to staging area");
    }
    
    // Update request with staged file info
    $updateStmt = $conn->prepare("UPDATE requests SET staged_file_id = ?, staged_file_name = ?, staged_file_size = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("ssii", $staged_file_id, $file['name'], $file['file_size'], $request_id);
    if (!$updateStmt->execute()) {
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    $updateStmt->close();
    
    // Log audit action if audit_logs table exists
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, file_id, details, timestamp) VALUES (?, ?, ?, ?, NOW())");
    if ($auditStmt) {
        $action = 'File Staged for Export';
        $details = "File: {$file['name']}, Request ID: {$request_id}";
        $auditStmt->bind_param("isss", $_SESSION['user_id'], $action, $file_id, $details);
        $auditStmt->execute();
        $auditStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'staged_file_id' => $staged_file_id,
            'file_name' => $file['name'],
            'file_size' => (int)$file['file_size'],
            'file_size_formatted' => formatFileSize($file['file_size']),
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

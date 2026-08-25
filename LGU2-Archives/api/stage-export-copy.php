<?php
/**
 * Stage Export Copy API
 * Creates a temporary duplicate of a file for export
 * Integrated with existing archive_files table
 */

// Enable detailed error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("=== STAGE EXPORT COPY API CALLED ===");

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    error_log("ERROR: Unauthorized access");
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$file_id = isset($input['file_id']) ? $input['file_id'] : ''; // Now accepts string with prefix
$request_id = isset($input['request_id']) ? (int)$input['request_id'] : 0;

error_log("Received file_id: " . $file_id);
error_log("Received request_id: " . $request_id);

if (empty($file_id) || $request_id <= 0) {
    http_response_code(400);
    error_log("ERROR: Invalid parameters - file_id: '$file_id', request_id: $request_id");
    echo json_encode(['success' => false, 'error' => 'Invalid file or request ID', 'debug' => ['file_id' => $file_id, 'request_id' => $request_id]]);
    exit;
}

try {
    error_log("Starting file staging process...");
    
    // Ensure staging directory exists
    $stagingDir = '../storage/temp_exports';
    error_log("Staging directory: " . $stagingDir);
    
    if (!is_dir($stagingDir)) {
        error_log("Staging directory does not exist, creating...");
        if (!mkdir($stagingDir, 0755, true)) {
            throw new Exception("Failed to create staging directory");
        }
        error_log("Staging directory created successfully");
    }
    
    $file = null;
    $originalPath = null;
    
    error_log("Parsing file_id: " . $file_id);
    
    // Determine file source based on ID prefix
    if (strpos($file_id, 'arch_file_') === 0) {
        // Archive file
        $actual_id = (int)substr($file_id, 10);
        error_log("Detected archive file, ID: " . $actual_id);
        
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
            error_log("Archive file found: " . $file['name'] . ", path: " . $originalPath);
        } else {
            error_log("Archive file not found in database for ID: " . $actual_id);
        }
        
        $result->free();
        $stmt->close();
        
    } elseif (strpos($file_id, 'leg_file_') === 0) {
        // Legislative file
        $actual_id = (int)substr($file_id, 9);
        error_log("Detected legislative file, ID: " . $actual_id);
        
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
            error_log("Legislative file found: " . $file['name'] . ", path: " . $originalPath);
            
            // Calculate file size for legislative files
            if (file_exists($originalPath)) {
                $file['file_size'] = filesize($originalPath);
                error_log("Legislative file size: " . $file['file_size']);
            } else {
                $file['file_size'] = 0;
                error_log("WARNING: Legislative file path does not exist: " . $originalPath);
            }
        } else {
            error_log("Legislative file not found in database for ID: " . $actual_id);
        }
        
        $result->free();
        $stmt->close();
    } else {
        error_log("ERROR: Unrecognized file_id format: " . $file_id);
    }
    
    if (!$file) {
        http_response_code(404);
        error_log("ERROR: File not found in database after query");
        echo json_encode(['success' => false, 'error' => 'File not found in database', 'debug' => ['file_id' => $file_id]]);
        exit;
    }
    
    error_log("File found, checking physical file existence...");
    error_log("Original path: " . $originalPath);
    
    // Try multiple path variations to find the actual file
    if (!file_exists($originalPath)) {
        error_log("WARNING: File not found at original path: " . $originalPath);
        
        // Try with ../ prefix
        $testPath = '../' . $originalPath;
        error_log("Trying path: " . $testPath);
        if (file_exists($testPath)) {
            $originalPath = $testPath;
            error_log("SUCCESS: Found file at: " . $testPath);
        } else {
            // Try uploads directory
            $testPath = '../uploads/' . basename($originalPath);
            error_log("Trying path: " . $testPath);
            if (file_exists($testPath)) {
                $originalPath = $testPath;
                error_log("SUCCESS: Found file at: " . $testPath);
            } else {
                http_response_code(404);
                error_log("ERROR: Original file not found on server at any tested path");
                echo json_encode(['success' => false, 'error' => 'Original file not found on server', 'searched_path' => $originalPath, 'debug' => ['file_id' => $file_id, 'tested_paths' => [$originalPath, '../' . $originalPath, '../uploads/' . basename($originalPath)]]]);
                exit;
            }
        }
    } else {
        error_log("SUCCESS: File exists at original path: " . $originalPath);
    }
    
    // Verify file is actually readable
    if (!is_file($originalPath) || !is_readable($originalPath)) {
        http_response_code(404);
        error_log("ERROR: Cannot read file from server");
        echo json_encode(['success' => false, 'error' => 'Cannot read file from server', 'debug' => ['path' => $originalPath, 'is_file' => is_file($originalPath), 'is_readable' => is_readable($originalPath)]]);
        exit;
    }
    
    error_log("File is readable, proceeding to staging...");
    
    // Generate unique staging filename
    $fileInfo = pathinfo($file['name']);
    $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : 'pdf';
    $staged_file_id = 'export_' . $request_id . '_' . time() . '_' . bin2hex(random_bytes(4));
    $stagedFileName = $staged_file_id . '.' . $extension;
    $stagedFilePath = $stagingDir . '/' . $stagedFileName;
    
    error_log("Staged file ID: " . $staged_file_id);
    error_log("Staged file path: " . $stagedFilePath);
    
    // Copy file to staging area
    if (!copy($originalPath, $stagedFilePath)) {
        error_log("ERROR: Failed to copy file to staging area");
        throw new Exception("Failed to copy file to staging area");
    }
    
    error_log("File copied successfully to staging area");
    
    // Get actual file size after copy
    $actualFileSize = filesize($stagedFilePath);
    error_log("Actual file size: " . $actualFileSize);
    
    // Update request with staged file info
    error_log("Updating request record...");
    $updateStmt = $conn->prepare("UPDATE requests SET staged_file_id = ?, staged_file_name = ?, staged_file_size = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("ssii", $staged_file_id, $file['name'], $actualFileSize, $request_id);
    if (!$updateStmt->execute()) {
        error_log("ERROR: Update execute failed: " . $updateStmt->error);
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    error_log("Request updated successfully, affected rows: " . $updateStmt->affected_rows);
    $updateStmt->close();
    
    // Log to notifications table (using existing system)
    try {
        // Get user name for notification
        $userNameForNotif = null;
        if ($userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?")) {
            $userStmt->bind_param("i", $_SESSION['user_id']);
            $userStmt->execute();
            if ($userRes = $userStmt->get_result()) {
                if ($urow = $userRes->fetch_assoc()) {
                    $userNameForNotif = trim($urow['full_name'] ?? '');
                }
            }
            $userStmt->close();
        }
        $notifStmt = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status, created_at) VALUES (?, ?, ?, ?, ?, 'unread', NOW())");
        if ($notifStmt) {
            $time = date('h:i A');
            $date = date('Y-m-d');
            $content = "File '{$file['name']}' staged for export request #$request_id by user #{$_SESSION['user_id']}";
            $about = 'Export Fulfillment';
            
            $notifStmt->bind_param("sssss", $time, $date, $content, $about, $userNameForNotif);
            
            if ($notifStmt->execute()) {
                error_log("Notification logged successfully");
            } else {
                error_log("WARNING: Notification log failed (non-fatal): " . $notifStmt->error);
            }
            
            $notifStmt->close();
        }
    } catch (Exception $notifError) {
        // Notification logging is optional, don't fail the request
        error_log("WARNING: Notification logging failed (non-fatal): " . $notifError->getMessage());
    }
    
    error_log("SUCCESS: File staged successfully");
    
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
    error_log("EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'type' => get_class($e)]);
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

<?php
/**
 * Remove Staged File API
 * Clears the staged file from a request and deletes the temporary copy
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
$request_id = isset($input['request_id']) ? (int)$input['request_id'] : 0;

error_log("=== REMOVE STAGED FILE API CALLED ===");
error_log("Request ID: " . $request_id);
error_log("User ID: " . $_SESSION['user_id']);

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit;
}

try {
    // Get current staged file info
    $stmt = $conn->prepare("SELECT staged_file_id, staged_file_name FROM requests WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        $stmt->close();
        exit;
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    $stagedFileId = $request['staged_file_id'];
    $stagedFileName = $request['staged_file_name'];
    
    error_log("Staged file ID: " . $stagedFileId);
    error_log("Staged file name: " . $stagedFileName);
    
    // Delete physical file if it exists
    if (!empty($stagedFileId)) {
        $stagingDir = '../storage/temp_exports';
        
        // Try to find and delete the file
        if (is_dir($stagingDir)) {
            $files = glob($stagingDir . '/' . $stagedFileId . '.*');
            foreach ($files as $file) {
                if (file_exists($file) && is_file($file)) {
                    if (unlink($file)) {
                        error_log("Deleted physical file: " . $file);
                    } else {
                        error_log("WARNING: Could not delete physical file: " . $file);
                    }
                }
            }
        }
    }
    
    // Clear staged file info from database
    $updateStmt = $conn->prepare("UPDATE requests SET staged_file_id = NULL, staged_file_name = NULL, staged_file_size = NULL WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("i", $request_id);
    if (!$updateStmt->execute()) {
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    
    error_log("Database updated, affected rows: " . $updateStmt->affected_rows);
    $updateStmt->close();
    
    // Log to notifications table
    try {
        $notifStmt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
        if ($notifStmt) {
            $time = date('h:i A');
            $date = date('Y-m-d');
            $content = "Staged file removed from export request #$request_id by user #{$_SESSION['user_id']}";
            $about = 'Export Cancelled';
            
            $notifStmt->bind_param("ssss", $time, $date, $content, $about);
            $notifStmt->execute();
            $notifStmt->close();
            error_log("Notification logged successfully");
        }
    } catch (Exception $notifError) {
        error_log("WARNING: Notification logging failed: " . $notifError->getMessage());
    }
    
    error_log("SUCCESS: Staged file removed");
    
    echo json_encode([
        'success' => true,
        'message' => 'Staged file removed successfully',
        'data' => [
            'request_id' => $request_id,
            'removed_file' => $stagedFileName
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
/**
 * Process Export API
 * Finalizes the export, logs the action, and marks request as fulfilled
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

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify request exists and has staged file
    $stmt = $conn->prepare("SELECT id, staged_file_id, staged_file_name, requester_name, document_title FROM requests WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $request_id);
    if (!$stmt->execute()) {
        throw new Exception("Query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        $stmt->close();
        $conn->rollback();
        exit;
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($request['staged_file_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file staged for this request']);
        $conn->rollback();
        exit;
    }
    
    // Update request status to 'Released' (fulfilled)
    $updateStmt = $conn->prepare("UPDATE requests SET status = 'Released', fulfilled_at = NOW() WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("i", $request_id);
    if (!$updateStmt->execute()) {
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    $updateStmt->close();
    
    // Log to notifications table (using existing system)
    try {
        $notifStmt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
        if ($notifStmt) {
            $time = date('h:i A');
            $date = date('Y-m-d');
            $content = "Export request #{$request_id} fulfilled: '{$request['staged_file_name']}' for {$request['requester_name']}";
            $about = 'Export Completed';
            
            $notifStmt->bind_param("ssss", $time, $date, $content, $about);
            $notifStmt->execute();
            $notifStmt->close();
        }
    } catch (Exception $notifError) {
        // Non-fatal, continue
        error_log("WARNING: Notification logging failed: " . $notifError->getMessage());
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'request_id' => $request_id,
            'status' => 'Released',
            'message' => 'Export request fulfilled successfully',
            'file_name' => $request['staged_file_name'],
            'fulfilled_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

<?php
/**
 * Fetch Request Details API
 * Returns full metadata for a specific request
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';

header('Content-Type: application/json');

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
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
        exit;
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $request['id'],
            'requester_name' => $request['requester_name'],
            'department' => $request['department'],
            'contact_info' => $request['contact_info'],
            'document_title' => $request['document_title'] ?? 'N/A',
            'requested_version' => $request['requested_version'] ?? 'Latest',
            'purpose' => $request['purpose'] ?? '',
            'notes' => $request['notes'] ?? '',
            'status' => $request['status'],
            'date_requested' => $request['date_requested'],
            'needed_by_date' => $request['needed_by_date'] ?? null,
            'staged_file_id' => $request['staged_file_id'] ?? null,
            'staged_file_name' => $request['staged_file_name'] ?? null,
            'staged_file_size' => $request['staged_file_size'] ?? null
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

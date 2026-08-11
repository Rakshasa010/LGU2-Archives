<?php
// Test routing Ordinance No. 01-2024 to archive folder
require_once 'authdatabase.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Simulate the routing for external_id 31 (Ordinance No. 01-2024) to archive folder_id 7
$externalId = 31;
$folderKind = 'archive';
$folderId = 7;

// Verify the target folder exists
$foldersTbl = $folderKind === 'legislative' ? 'legislative_folders' : 'archive_folders';
$chk = $conn->prepare("SELECT id FROM $foldersTbl WHERE id = ? LIMIT 1");
$chk->bind_param("i", $folderId);
$chk->execute();
$folderExists = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$folderExists) {
    echo "ERROR: Target folder not found";
    exit;
}

// Check the external document
$stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ?");
$stmt->bind_param("i", $externalId);
$stmt->execute();
$ext = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ext) {
    echo "ERROR: External document not found";
    exit;
}

if (strtolower($ext['status'] ?? '') === 'routed') {
    echo "ERROR: Document has already been routed";
    exit;
}

// Build route document
$routeDoc = [
    'title'             => $ext['title'],
    'type'              => $ext['document_type'] ?? 'archive',
    'author'            => 'LLRM Import',
    'document_date'     => $ext['document_date'] ?? null,
    'source_system'     => $ext['source_system'] ?? 'LLRM',
    'source_record_id'  => $ext['external_id'] !== null && ctype_digit($ext['external_id']) ? (int)$ext['external_id'] : null,
    'target_folder_kind' => $folderKind,
    'target_folder_id'  => $folderId,
];

// Check for duplicate
if ($folderKind === 'legislative') {
    $chk = $conn->prepare("SELECT id FROM legislative_records WHERE title = ? AND type = ? LIMIT 1");
    $chk->bind_param("ss", $routeDoc['title'], $routeDoc['type']);
} else {
    $chk = $conn->prepare("SELECT id FROM archive_files WHERE name = ? AND folder_id = ? LIMIT 1");
    $chk->bind_param("si", $routeDoc['title'], $folderId);
}
$chk->execute();
$dup = $chk->get_result()->fetch_assoc();
$chk->close();

if ($dup) {
    echo "ERROR: Duplicate record already exists (existing_id: " . $dup['id'] . ")";
    exit;
}

// Continue with routing (simplified - just show it would pass)
echo "SUCCESS: No duplicate found. Document can be routed to folder_id=$folderId";
echo "\nTitle: " . $routeDoc['title'];
echo "\nFolder kind: " . $routeDoc['target_folder_kind'];
echo "\nFolder ID: " . $routeDoc['target_folder_id'];
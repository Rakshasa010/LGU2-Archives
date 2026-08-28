<?php
/**
 * download_ipfs.php — Pinata IPFS retrieval route.
 *
 * Resolves an archive document's stored IPFS CID and streams the content from
 * the Pinata dedicated gateway:  https://<PINATA_GATEWAY>/ipfs/<CID>
 *
 * Usage:
 *   download_ipfs.php?id=<record_id>&source=archive|legislative|external&view=1
 *
 *   source defaults to 'archive' (archive_files table).
 *   Add &view=1 to open inline instead of forcing a download.
 */

require 'authdatabase.php';
require_once __DIR__ . '/includes/pinata.php';
require_once __DIR__ . '/monitoring_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id     = (int)($_GET['id'] ?? 0);
$source = $_GET['source'] ?? 'archive';
$is_view = isset($_GET['view']) && (int)$_GET['view'] === 1;

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid file ID');
}

$cid = null;
$filename = null;

if ($source === 'legislative') {
    $stmt = $conn->prepare("SELECT ipfs_cid, file_path, title FROM legislative_records WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $cid = $row['ipfs_cid'];
        $filename = ($row['title'] !== '') ? $row['title'] : basename((string)$row['file_path']);
    }
} elseif ($source === 'external') {
    $stmt = $conn->prepare("SELECT ipfs_cid, file_name, title FROM external_documents WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $cid = $row['ipfs_cid'];
        $filename = ($row['file_name'] !== '') ? $row['file_name'] : $row['title'];
    }
} else {
    $stmt = $conn->prepare("SELECT ipfs_cid, name FROM archive_files WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $cid = $row['ipfs_cid'];
        $filename = $row['name'];
    }
}

if (empty($cid)) {
    http_response_code(404);
    exit('No IPFS content stored for this document');
}

// Log IPFS preview/download for monitored users
$action_type = $is_view ? 'File Preview' : 'File Download';
$verb = $is_view ? 'Previewed' : 'Downloaded';
log_monitored_user_action($conn, $_SESSION['user_id'], $action_type, $verb . ' IPFS file "' . htmlspecialchars($filename ?? 'Unknown') . '"');

// Log IPFS preview/download in audit logs (all users)
$_audit_about = $is_view ? 'File Preview' : 'File Download';
$_audit_content = $verb . ' IPFS file "' . htmlspecialchars($filename ?? 'Unknown') . '"';
$_uid = (int)$_SESSION['user_id'];
$_userName = null;
if ($_u = $conn->prepare("SELECT full_name FROM users WHERE id = ?")) {
    $_u->bind_param("i", $_uid);
    $_u->execute();
    $_r = $_u->get_result();
    if ($_r && $_ur = $_r->fetch_assoc()) $_userName = trim($_ur['full_name'] ?? '');
    $_u->close();
}
$_t = date('h:i A'); $_d = date('Y-m-d'); $_s = 'unread';
$_ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
if ($_ins) { $_ins->bind_param('ssssss', $_t, $_d, $_audit_content, $_audit_about, $_userName, $_s); $_ins->execute(); $_ins->close(); }

// Stream through the server so the dedicated gateway URL is never exposed to clients.
pinata_stream_cid($cid, $is_view, $filename);

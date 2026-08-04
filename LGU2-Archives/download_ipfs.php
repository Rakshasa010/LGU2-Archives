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

// Stream through the server so the dedicated gateway URL is never exposed to clients.
pinata_stream_cid($cid, $is_view, $filename);

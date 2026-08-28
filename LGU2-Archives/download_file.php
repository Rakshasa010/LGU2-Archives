<?php
require 'authdatabase.php';
require_once __DIR__ . '/includes/pinata.php';
require_once __DIR__ . '/monitoring_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("File ID not specified.");
}

$file_id = (int)$_GET['id'];

// Fetch file info from database
$stmt = $conn->prepare("SELECT * FROM archive_files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    die("File not found in database.");
}

$file_path = $file['file_path'];
$file_name = $file['name'];
$ipfs_cid = $file['ipfs_cid'] ?? null;

// Check for view action
$is_view = isset($_GET['view']) && $_GET['view'] == '1';

// Log file preview/download for monitored users
$action_type = $is_view ? 'File Preview' : 'File Download';
$verb = $is_view ? 'Previewed' : 'Downloaded';
log_monitored_user_action(
    $conn,
    $_SESSION['user_id'],
    $action_type,
    $verb . ' file "' . htmlspecialchars($file_name) . '"'
);

// Serve via the Pinata dedicated gateway when an IPFS CID is stored.
// Falls back to the local copy below when no CID exists or Pinata isn't configured.
if (!empty($ipfs_cid) && pinata_is_configured()) {
    pinata_stream_cid($ipfs_cid, $is_view, $file_name);
}

if (!file_exists($file_path)) {
    // Try absolute path
    $absolute_path = __DIR__ . '/' . $file_path;
    if (file_exists($absolute_path)) {
        $file_path = $absolute_path;
    } else {
        die("File not found on server.");
    }
}

// Determine MIME type
$mime_type = mime_content_type($file_path);
if (!$mime_type) {
    $mime_type = 'application/octet-stream';
}

$disposition = $is_view ? 'inline' : 'attachment';

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($file_name) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file content
readfile($file_path);
exit;
?>
<?php
require 'authdatabase.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/includes/docx_preview.php';

$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$file = docx_resolve_file($path);

header('Content-Type: application/json');

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'File not found or unsupported type.']);
    exit;
}

$text = docx_extract_text($file);
if ($text === null || trim($text) === '') {
    echo json_encode(['success' => false, 'error' => 'No readable text could be extracted from this document.']);
    exit;
}

$truncated = false;
if (function_exists('mb_strlen') && mb_strlen($text) > 200000) {
    $text = mb_substr($text, 0, 200000);
    $truncated = true;
} elseif (strlen($text) > 200000) {
    $text = substr($text, 0, 200000);
    $truncated = true;
}

echo json_encode(['success' => true, 'text' => $text, 'truncated' => $truncated], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

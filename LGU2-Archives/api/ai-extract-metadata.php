<?php
/**
 * AI Extract Metadata API
 *
 * POST { external_id: int }
 * Returns: { success, metadata? | error }
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('max_execution_time', '60');
@set_time_limit(60);

ob_start();
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $error['message']]);
    }
});

session_start();
if (!isset($_SESSION['user_id'])) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../authdatabase.php';
    require_once __DIR__ . '/../includes/gemini.php';
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Init failed: ' . $e->getMessage()]);
    exit;
}

while (ob_get_level()) { ob_end_clean(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

if (!function_exists('gemini_is_configured') || !gemini_is_configured()) {
    echo json_encode(['success' => false, 'error' => 'Gemini API key not configured on this server. Please fill fields manually.']);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $raw = @file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
}

$externalId = (int)($input['external_id'] ?? 0);
if ($externalId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid external_id']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $externalId);
$stmt->execute();
$ext = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ext) {
    echo json_encode(['success' => false, 'error' => 'External document not found']);
    exit;
}

$absFile = null;
if (!empty($ext['file_path'])) {
    $candidate = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ext['file_path']);
    if (is_file($candidate)) {
        $absFile = $candidate;
    }
}

if (!$absFile) {
    echo json_encode(['success' => false, 'error' => 'File not found on server']);
    exit;
}

$ext_lower = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));

$supportedText = ['txt', 'md', 'markdown', 'csv', 'text', 'log'];
$supportedPdf  = ['pdf'];
$supportedDocx = ['docx'];

$fileContent = null;
$mimeType = 'text/plain';

if (in_array($ext_lower, $supportedPdf)) {
    $size = (int)@filesize($absFile);
    if ($size <= 0 || $size > 30 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => $size <= 0 ? 'PDF file is empty' : 'File too large (max 30MB)']);
        exit;
    }
    $rawData = @file_get_contents($absFile);
    if ($rawData === false || $rawData === '') {
        echo json_encode(['success' => false, 'error' => 'Could not read PDF file']);
        exit;
    }
    $fileContent = base64_encode($rawData);
    $mimeType = 'application/pdf';
    unset($rawData);

} elseif (in_array($ext_lower, $supportedDocx)) {
    if (!function_exists('docx_extract_text')) {
        require_once __DIR__ . '/../includes/docx_preview.php';
    }
    $text = @docx_extract_text($absFile);
    if (empty($text) || trim($text) === '') {
        echo json_encode(['success' => false, 'error' => 'No text extracted from DOCX']);
        exit;
    }
    if (mb_strlen($text) > 100000) {
        $text = mb_substr($text, 0, 100000);
    }
    $fileContent = $text;

} elseif (in_array($ext_lower, $supportedText)) {
    $text = (string)@file_get_contents($absFile);
    if ($text === '') {
        echo json_encode(['success' => false, 'error' => 'Text file is empty']);
        exit;
    }
    if (mb_strlen($text) > 100000) {
        $text = mb_substr($text, 0, 100000);
    }
    $fileContent = $text;

} else {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type (.' . $ext_lower . ')']);
    exit;
}

$system = 'You are a metadata extraction assistant. Extract metadata from the document and return ONLY a JSON object with these keys: title, author, type, date (YYYY-MM-DD), reference_number. Use null for unknown fields.';

if ($mimeType === 'application/pdf') {
    $userMsg = 'Extract metadata from this PDF. Return ONLY the JSON object.';
    $parts = [
        ['text' => $userMsg],
        ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $fileContent]],
    ];
} else {
    $userMsg = "Extract metadata from this document. Return ONLY the JSON object.\n\nFILE: {$ext['file_name']}\n\nCONTENT:\n{$fileContent}";
    $parts = [['text' => $userMsg]];
}

$messages = [['role' => 'user', 'parts' => $parts]];

$result = gemini_generate($system, $messages, [
    'temperature'     => 0.1,
    'maxOutputTokens' => 2048,
    'topP'            => 0.9,
]);

if (!$result['success']) {
    $debugInfo = $result['status'] ?? 'no_status';
    error_log('[ai-extract-metadata] Gemini failed (HTTP ' . $debugInfo . '): ' . ($result['error'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'AI failed: ' . ($result['error'] ?? 'Unknown error')]);
    exit;
}

$responseText = trim($result['text'] ?? '');
$responseText = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
$responseText = preg_replace('/\s*```\s*$/i', '', $responseText);
$responseText = trim($responseText);

$metadata = json_decode($responseText, true);

if (!is_array($metadata) && preg_match('/\{[\s\S]*\}/', $responseText, $m)) {
    $metadata = json_decode($m[0], true);
}

if (!is_array($metadata)) {
    echo json_encode(['success' => false, 'error' => 'AI returned invalid response. Fill fields manually.']);
    exit;
}

$allowedTypes = ['ordinance', 'resolution', 'public hearing', 'meeting', 'executive order', 'memorandum', 'certificate', 'permit', 'contract', 'report', 'letter', 'form', 'other'];
$cleanType = strtolower(trim($metadata['type'] ?? ''));
if ($cleanType !== '' && !in_array($cleanType, $allowedTypes)) {
    $cleanType = 'other';
}

$cleanDate = null;
if (!empty($metadata['date'])) {
    $parsed = date_create($metadata['date']);
    if ($parsed) $cleanDate = $parsed->format('Y-m-d');
}

echo json_encode(['success' => true, 'metadata' => [
    'title'            => !empty($metadata['title']) ? trim($metadata['title']) : null,
    'author'           => !empty($metadata['author']) ? trim($metadata['author']) : null,
    'type'             => $cleanType !== '' ? $cleanType : null,
    'date'             => $cleanDate,
    'reference_number' => !empty($metadata['reference_number']) ? trim($metadata['reference_number']) : null,
]]);

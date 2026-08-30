<?php
/**
 * AI Extract Metadata API
 *
 * Scans an external document file via the Gemini API and extracts metadata
 * fields (Title, Author, Type, Date, Reference Number).
 *
 * POST { external_id: int }
 *
 * Returns:
 *   { success: true, metadata: { title, author, type, date, reference_number } }
 *   { success: false, error: "..." }
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/error.log');

ob_start();
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']]);
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
    require_once __DIR__ . '/../includes/docx_preview.php';
} catch (Throwable $e) {
    error_log('[ai-extract-metadata] Init failed: ' . $e->getMessage());
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server initialization failed: ' . $e->getMessage()]);
    exit;
}

while (ob_get_level()) { ob_end_clean(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

if (!gemini_is_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gemini API is not configured. Please set GEMINI_API_KEY in .env']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$externalId = (int)($input['external_id'] ?? 0);
if ($externalId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid external_id']);
    exit;
}

// Fetch the external document record
$stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $externalId);
$stmt->execute();
$ext = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ext) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'External document not found']);
    exit;
}

// Resolve the file path
$absFile = null;
if (!empty($ext['file_path'])) {
    $candidate = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ext['file_path']);
    if (is_file($candidate)) {
        $absFile = $candidate;
    }
}

if (!$absFile) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found on server']);
    exit;
}

$ext_lower = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));

// Supported types: PDF, DOCX, TXT, MD, CSV
$supportedText = ['txt', 'md', 'markdown', 'csv', 'text', 'log'];
$supportedPdf  = ['pdf'];
$supportedDocx = ['docx'];

$fileContent = null;
$mimeType = 'text/plain';

if (in_array($ext_lower, $supportedPdf)) {
    $size = (int)@filesize($absFile);
    if ($size <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'The PDF file is empty']);
        exit;
    }
    if ($size > 30 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File is too large for AI analysis (max 30MB)']);
        exit;
    }
    $rawData = @file_get_contents($absFile);
    if ($rawData === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not read the PDF file']);
        exit;
    }
    $fileContent = base64_encode($rawData);
    $mimeType = 'application/pdf';

} elseif (in_array($ext_lower, $supportedDocx)) {
    $text = docx_extract_text($absFile);
    if ($text === null || trim($text) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No readable text could be extracted from the DOCX file']);
        exit;
    }
    if (mb_strlen($text) > 600000) {
        $text = mb_substr($text, 0, 600000);
    }
    $fileContent = $text;
    $mimeType = 'text/plain';

} elseif (in_array($ext_lower, $supportedText)) {
    $text = (string)@file_get_contents($absFile);
    if ($text === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'The text file is empty']);
        exit;
    }
    if (mb_strlen($text) > 600000) {
        $text = mb_substr($text, 0, 600000);
    }
    $fileContent = $text;
    $mimeType = 'text/plain';

} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Unsupported file type (.' . $ext_lower . '). Supported: PDF, DOCX, TXT, MD, CSV',
    ]);
    exit;
}

// Build the Gemini prompt
$system = 'You are a metadata extraction assistant for a government archives system. '
    . 'Analyze the provided document and extract the following metadata fields. '
    . 'Return ONLY a valid JSON object with these exact keys, no markdown fences, no extra text:\n'
    . '{\n'
    . '  "title": "string or null",\n'
    . '  "author": "string or null",\n'
    . '  "type": "string or null",\n'
    . '  "date": "string in YYYY-MM-DD format or null",\n'
    . '  "reference_number": "string or null"\n'
    . '}\n\n'
    . 'Rules:\n'
    . '- title: The document title, ordinance/resolution number, or main heading. Extract the most prominent title.\n'
    . '- author: The author, authoring body, or office that created the document (e.g. "Sangguniang Panlungsod").\n'
    . '- type: Classify as one of: ordinance, resolution, public hearing, meeting, executive order, memorandum, certificate, permit, contract, report, letter, form, other.\n'
    . '- date: The document date, enactment date, or signing date in YYYY-MM-DD format. If only a partial date is available (e.g. "January 2024"), use the first day of that month.\n'
    . '- reference_number: Any reference number, tracking number, control number, or document ID found on the document.\n'
    . '- If a field cannot be determined with confidence, set it to null.\n'
    . '- Do not invent or guess values. Only extract what is clearly present in the document.';

if ($mimeType === 'application/pdf') {
    $userMsg = 'Extract metadata from this PDF document. Return ONLY the JSON object with title, author, type, date, and reference_number fields.';
    $parts = [
        ['text' => $userMsg],
        ['inline_data' => ['mime_type' => 'application/pdf', 'data' => $fileContent]],
    ];
} else {
    $userMsg = 'Extract metadata from the following document text. Return ONLY the JSON object with title, author, type, date, and reference_number fields.\n\nDOCUMENT CONTENT:\n' . $fileContent;
    $parts = [['text' => $userMsg]];
}

$messages = [['role' => 'user', 'parts' => $parts]];

$result = gemini_generate($system, $messages, [
    'temperature'     => 0.1,
    'maxOutputTokens' => 2048,
    'topP'            => 0.9,
]);

if (!$result['success']) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'AI extraction failed: ' . ($result['error'] ?? 'Unknown error')]);
    exit;
}

$responseText = trim($result['text'] ?? '');

// Parse the JSON response from Gemini
// Strip markdown code fences if present
$responseText = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
$responseText = preg_replace('/\s*```\s*$/i', '', $responseText);
$responseText = trim($responseText);

$metadata = json_decode($responseText, true);

if (!is_array($metadata)) {
    // Try to find JSON object in the response (handles extra text around it)
    if (preg_match('/\{[\s\S]*\}/', $responseText, $m)) {
        $metadata = json_decode($m[0], true);
    }
}

// If still no match, try extracting just the top-level keys we need
if (!is_array($metadata)) {
    $extracted = [];
    $keys = ['title', 'author', 'type', 'date', 'reference_number'];
    foreach ($keys as $k) {
        if (preg_match('/"' . $k . '"\s*:\s*(?:"([^"]*)"|null)/i', $responseText, $m)) {
            $extracted[$k] = ($m[1] ?? null) === 'null' ? null : ($m[1] ?? null);
        }
    }
    if (count($extracted) >= 2) {
        $metadata = $extracted;
    }
}

if (!is_array($metadata)) {
    error_log('[ai-extract-metadata] Failed to parse JSON. Raw: ' . substr($responseText, 0, 500));
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => 'AI returned an invalid response. Please try again or fill fields manually.',
    ]);
    exit;
}

// Sanitize and normalize the extracted metadata
$allowedTypes = ['ordinance', 'resolution', 'public hearing', 'meeting', 'executive order', 'memorandum', 'certificate', 'permit', 'contract', 'report', 'letter', 'form', 'other'];

$cleanType = strtolower(trim($metadata['type'] ?? ''));
if ($cleanType !== '' && !in_array($cleanType, $allowedTypes)) {
    // Try partial match
    foreach ($allowedTypes as $at) {
        if (strpos($at, $cleanType) !== false || strpos($cleanType, $at) !== false) {
            $cleanType = $at;
            break;
        }
    }
    if (!in_array($cleanType, $allowedTypes)) {
        $cleanType = 'other';
    }
}

// Validate date format
$cleanDate = null;
if (!empty($metadata['date'])) {
    $parsed = date_create($metadata['date']);
    if ($parsed) {
        $cleanDate = $parsed->format('Y-m-d');
    }
}

$extracted = [
    'title'            => !empty($metadata['title']) ? trim($metadata['title']) : null,
    'author'           => !empty($metadata['author']) ? trim($metadata['author']) : null,
    'type'             => $cleanType !== '' ? $cleanType : null,
    'date'             => $cleanDate,
    'reference_number' => !empty($metadata['reference_number']) ? trim($metadata['reference_number']) : null,
];

echo json_encode(['success' => true, 'metadata' => $extracted]);

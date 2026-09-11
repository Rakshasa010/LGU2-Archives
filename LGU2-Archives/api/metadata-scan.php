<?php
/**
 * AI Metadata Scanner — self-contained Gemini 3.5 Flash integration.
 * POST { external_id: int }
 * Returns: { success, metadata? | error }
 *
 * This file is completely standalone — it does NOT include gemini.php
 * to avoid OPcache issues with stale bytecode on the deployed server.
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('max_execution_time', '90');
@set_time_limit(90);

ob_start();
header('Content-Type: application/json');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $err['message']]);
    }
});

session_start();
if (!isset($_SESSION['user_id'])) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../authdatabase.php';

while (ob_get_level()) { ob_end_clean(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// ── Load .env directly ──────────────────────────────────────────────
$envFile = null;
foreach ([realpath(__DIR__ . '/../.env'), realpath(__DIR__ . '/../../.env')] as $p) {
    if ($p && is_file($p)) { $envFile = $p; break; }
}

$geminiKey   = '';
$geminiModel = 'gemini-2.5-flash';
if ($envFile) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\"'");
        if ($k === 'GEMINI_API_KEY' && $v !== '') $geminiKey = $v;
        if ($k === 'GEMINI_MODEL'   && $v !== '') $geminiModel = $v;
    }
}

if ($geminiKey === '') {
    echo json_encode(['success' => false, 'error' => 'Gemini API key not configured. Fill fields manually.']);
    exit;
}

// ── Parse input ─────────────────────────────────────────────────────
$input = $_POST;
if (empty($input)) {
    $raw = @file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
}
$externalId = (int)($input['external_id'] ?? 0);
if ($externalId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing external_id']);
    exit;
}

// ── Fetch document record ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $externalId);
$stmt->execute();
$ext = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ext) {
    echo json_encode(['success' => false, 'error' => 'Document not found']);
    exit;
}

// ── Resolve file on disk ────────────────────────────────────────────
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

// ── Read file content ───────────────────────────────────────────────
$ext_lower  = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
$mimeType   = 'text/plain';
$fileContent = null;

if ($ext_lower === 'pdf') {
    $size = (int)@filesize($absFile);
    if ($size <= 0 || $size > 30 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => $size <= 0 ? 'PDF is empty' : 'PDF too large (max 30 MB)']);
        exit;
    }
    $raw = @file_get_contents($absFile);
    if ($raw === '' || $raw === false) {
        echo json_encode(['success' => false, 'error' => 'Could not read PDF']);
        exit;
    }
    $fileContent = base64_encode($raw);
    $mimeType    = 'application/pdf';
    unset($raw);

} elseif ($ext_lower === 'docx') {
    if (!function_exists('docx_extract_text')) {
        require_once __DIR__ . '/../includes/docx_preview.php';
    }
    $text = @docx_extract_text($absFile);
    if (empty(trim($text))) {
        echo json_encode(['success' => false, 'error' => 'No text in DOCX']);
        exit;
    }
    $fileContent = mb_strlen($text) > 120000 ? mb_substr($text, 0, 120000) : $text;

} elseif (in_array($ext_lower, ['txt', 'md', 'csv', 'text', 'log'], true)) {
    $text = (string)@file_get_contents($absFile);
    if ($text === '') {
        echo json_encode(['success' => false, 'error' => 'Text file is empty']);
        exit;
    }
    $fileContent = mb_strlen($text) > 120000 ? mb_substr($text, 0, 120000) : $text;

} else {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type (.' . $ext_lower . ')']);
    exit;
}

// ── Build Gemini request ────────────────────────────────────────────
$systemPrompt = 'Extract metadata from the given document. '
    . 'Return ONLY a valid JSON object (no markdown, no fences) with exactly these keys: '
    . 'title, author, type, date, reference_number. '
    . 'Use null for any field you cannot determine. '
    . 'For "type" use one of: ordinance, resolution, public hearing, meeting, executive order, '
    . 'memorandum, certificate, permit, contract, report, letter, form, other. '
    . 'For "date" use YYYY-MM-DD format.';

if ($mimeType === 'application/pdf') {
    $parts = [
        ['text' => 'Extract metadata from this PDF document. Return ONLY the JSON object.'],
        ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $fileContent]],
    ];
} else {
    $fname = $ext['file_name'] ?? basename($absFile);
    $parts = [['text' => "Extract metadata from this document.\nFilename: {$fname}\n\nContent:\n{$fileContent}"]];
}

$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => $parts],
    ],
    'systemInstruction' => [
        'parts' => [['text' => $systemPrompt]],
    ],
    'generationConfig' => [
        'maxOutputTokens' => 4096,
    ],
];

// ── Call Gemini API ─────────────────────────────────────────────────
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent";

error_log("[metadata-scan] model={$geminiModel} file=" . basename($absFile) . " mime={$mimeType}");

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $geminiKey,
    ],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$body   = curl_exec($ch);
$curlErr = curl_error($ch);
$status  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($curlErr !== '') {
    error_log("[metadata-scan] cURL error: {$curlErr}");
    echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlErr]);
    exit;
}

$api = json_decode((string)$body, true);

error_log("[metadata-scan] HTTP {$status} body=" . substr((string)$body, 0, 800));

if ($status < 200 || $status >= 300) {
    $msg    = $api['error']['message'] ?? "HTTP {$status}";
    $detail = $api['error']['status']   ?? '';
    error_log("[metadata-scan] FAILED {$status} [{$detail}]: {$msg}");
    echo json_encode(['success' => false, 'error' => "Gemini error: {$msg}"]);
    exit;
}

// ── Extract text from response ──────────────────────────────────────
$text = '';
foreach (($api['candidates'][0]['content']['parts'] ?? []) as $part) {
    if (!empty($part['text'])) $text .= $part['text'];
}

if ($text === '' && isset($api['promptFeedback']['blockReason'])) {
    echo json_encode(['success' => false, 'error' => 'Content blocked: ' . $api['promptFeedback']['blockReason']]);
    exit;
}
if ($text === '') {
    $finish = $api['candidates'][0]['finishReason'] ?? 'unknown';
    echo json_encode(['success' => false, 'error' => "AI returned no text (finish: {$finish})"]);
    exit;
}

// ── Parse JSON from response ────────────────────────────────────────
$text = trim($text);
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```\s*$/i', '', $text);
$text = trim($text);

$metadata = json_decode($text, true);

// fallback: find first {…} block
if (!is_array($metadata) && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $metadata = json_decode($m[0], true);
}

if (!is_array($metadata)) {
    echo json_encode(['success' => false, 'error' => 'AI returned unparseable response', '_raw' => substr($text, 0, 500)]);
    exit;
}

// ── Normalize ───────────────────────────────────────────────────────
$allowed = ['ordinance', 'resolution', 'public hearing', 'meeting', 'executive order',
            'memorandum', 'certificate', 'permit', 'contract', 'report', 'letter', 'form', 'other'];
$type = strtolower(trim($metadata['type'] ?? ''));
if ($type !== '' && !in_array($type, $allowed)) $type = 'other';

$date = null;
if (!empty($metadata['date'])) {
    $p = date_create($metadata['date']);
    if ($p) $date = $p->format('Y-m-d');
}

echo json_encode(['success' => true, 'metadata' => [
    'title'            => !empty($metadata['title'])            ? trim($metadata['title'])            : null,
    'author'           => !empty($metadata['author'])           ? trim($metadata['author'])           : null,
    'type'             => $type !== '' ? $type : null,
    'date'             => $date,
    'reference_number' => !empty($metadata['reference_number']) ? trim($metadata['reference_number']) : null,
]]);

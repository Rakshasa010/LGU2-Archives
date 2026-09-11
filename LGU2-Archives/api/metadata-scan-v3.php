<?php
/**
 * AI Metadata Scanner v3 — bare-minimum Gemini call, no systemInstruction field.
 * POST { external_id: int }
 * Returns: { success, metadata? | error }
 */
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('max_execution_time', '90');
@set_time_limit(90);

ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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

$geminiKey = '';
$geminiModel = 'gemini-2.5-flash';
foreach ([realpath(__DIR__ . '/../.env'), realpath(__DIR__ . '/../../.env')] as $p) {
    if ($p && is_file($p)) {
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\"'");
            if ($k === 'GEMINI_API_KEY' && $v !== '') $geminiKey = $v;
            if ($k === 'GEMINI_MODEL'   && $v !== '') $geminiModel = $v;
        }
        break;
    }
}
if ($geminiKey === '') {
    echo json_encode(['success' => false, 'error' => 'Gemini API key not configured']);
    exit;
}

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

$stmt = $conn->prepare("SELECT * FROM external_documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $externalId);
$stmt->execute();
$ext = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ext) {
    echo json_encode(['success' => false, 'error' => 'Document not found']);
    exit;
}

$absFile = null;
if (!empty($ext['file_path'])) {
    $candidate = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ext['file_path']);
    if (is_file($candidate)) $absFile = $candidate;
}
if (!$absFile) {
    echo json_encode(['success' => false, 'error' => 'File not found on server']);
    exit;
}

$ext_lower = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
$mimeType = 'text/plain';
$fileContent = null;

if ($ext_lower === 'pdf') {
    $size = (int)@filesize($absFile);
    if ($size <= 0 || $size > 30 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => $size <= 0 ? 'PDF empty' : 'PDF too large']);
        exit;
    }
    $raw = @file_get_contents($absFile);
    if ($raw === '' || $raw === false) {
        echo json_encode(['success' => false, 'error' => 'Could not read PDF']);
        exit;
    }
    $fileContent = base64_encode($raw);
    $mimeType = 'application/pdf';
    // Keep $raw for text extraction below, free memory of nothing else.
} elseif ($ext_lower === 'docx') {
    if (!function_exists('docx_extract_text')) require_once __DIR__ . '/../includes/docx_preview.php';
    $text = @docx_extract_text($absFile);
    if (empty(trim($text))) {
        echo json_encode(['success' => false, 'error' => 'No text in DOCX']);
        exit;
    }
    $fileContent = mb_strlen($text) > 120000 ? mb_substr($text, 0, 120000) : $text;
} elseif (in_array($ext_lower, ['txt', 'md', 'csv', 'text', 'log'], true)) {
    $text = (string)@file_get_contents($absFile);
    if ($text === '') {
        echo json_encode(['success' => false, 'error' => 'Empty file']);
        exit;
    }
    $fileContent = mb_strlen($text) > 120000 ? mb_substr($text, 0, 120000) : $text;
} else {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type (.' . $ext_lower . ')']);
    exit;
}

$prompt = 'You are a metadata extraction assistant for Philippine LGU (Local Government Unit) documents. '
    . 'Extract metadata from the document below and return ONLY a JSON object (no markdown, no code fences) '
    . 'with these keys: title, author, type, date, reference_number. '
    . 'Use null for unknown fields. '
    . '"type" must be one of: ordinance, resolution, public hearing, meeting, executive order, '
    . 'memorandum, certificate, permit, contract, report, letter, form, other. '
    . '"date" must be YYYY-MM-DD format. '
    . '"author" is the person or office that created/authored the document. '
    . 'Look for these keywords (in order of priority): '
    . '"Authored by:", "Co-Authored by:", "Author:", "FROM:", "Filed by:", "Prepared by:", '
    . '"Submitted by:", "By:", "Issued by:", "Approved by:", "By order of:". '
    . 'If the document has a signing official (e.g. "Mayor", "City Mayor", "Governor"), '
    . 'use their name. For resolutions/ordinances, the author is usually the author/sponsor listed near the title.';

// ── Pre-scan text for author keywords ───────────────────────────────
function scan_for_authors($text) {
    $keywords = [
        'authored by', 'co-authored by', 'author:',
        'from:', 'filed by:', 'prepared by:',
        'submitted by:', 'issued by:', 'approved by:',
        'by order of:', 'signed by:',
    ];
    $candidates = [];
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
        $lower = strtolower(trim($line));
        foreach ($keywords as $kw) {
            $pos = strpos($lower, $kw);
            if ($pos !== false) {
                $value = trim(substr($line, $pos + strlen($kw)));
                $value = preg_replace('/^[:\-\s]+/', '', $value);
                $value = preg_replace('/\s+$/', '', $value);
                if (strlen($value) >= 2 && strlen($value) <= 120) {
                    $candidates[] = $kw . ' ' . $value;
                }
            }
        }
    }
    // Also look for "HON. NAME" or "HON. NAME, TITLE" patterns near "Approved" or "By:"
    if (preg_match_all('/(?:Approved|By)\s*[:.]?\s*(HON\.?\s+[A-Z][A-Z\s\.]+(?:,\s*[A-Z\s]+)?)/i', $text, $m)) {
        foreach ($m[1] as $name) {
            $clean = trim(preg_replace('/\s+/', ' ', $name));
            if (strlen($clean) >= 4 && !in_array($clean, $candidates)) {
                $candidates[] = 'Approved by: ' . $clean;
            }
        }
    }
    return array_unique($candidates);
}

if ($mimeType === 'application/pdf') {
    // Extract text from PDF — don't send raw PDF as inlineData (Gemini rejects it).
    $pdfText = null;

    // Method 1: pdftotext (poppler-utils, common on Linux hosting)
    if (function_exists('shell_exec')) {
        $tmpPdf = tempnam(sys_get_temp_dir(), 'scan_') . '.pdf';
        file_put_contents($tmpPdf, $raw);
        $escaped = escapeshellarg($tmpPdf);
        $pdftext = shell_exec("pdftotext {$escaped} - 2>/dev/null");
        @unlink($tmpPdf);
        if ($pdftext !== null && trim($pdftext) !== '') {
            $pdfText = $pdftext;
        }
    }

    // Method 2: Simple regex extraction from PDF streams
    if ($pdfText === null || trim($pdfText) === '') {
        $pdfRaw = $raw;
        $textChunks = [];
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $pdfRaw, $btMatches)) {
            foreach ($btMatches[1] as $bt) {
                if (preg_match_all('/\(([^)]*)\)\s*Tj/', $bt, $strMatches)) {
                    foreach ($strMatches[1] as $s) {
                        $textChunks[] = $s;
                    }
                }
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/', $bt, $hexMatches)) {
                    foreach ($hexMatches[1] as $h) {
                        $textChunks[] = hex2bin($h);
                    }
                }
            }
        }
        if (!empty($textChunks)) {
            $pdfText = implode("\n", $textChunks);
        }
    }

    // Method 3: Brute-force text extraction — find readable strings in the raw PDF
    if ($pdfText === null || trim($pdfText) === '') {
        $pdfRaw = $raw;
        $readable = [];
        if (preg_match_all('/[^\x00-\x1F]{4,}/', $pdfRaw, $m)) {
            foreach ($m[0] as $chunk) {
                $clean = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $chunk);
                if (strlen($clean) >= 4) {
                    $readable[] = $clean;
                }
            }
        }
        if (!empty($readable)) {
            $pdfText = implode("\n", $readable);
        }
    }

    if ($pdfText === null || trim($pdfText) === '') {
        echo json_encode(['success' => false, 'error' => 'Could not extract text from PDF. Fill fields manually.']);
        exit;
    }

    if (mb_strlen($pdfText) > 120000) {
        $pdfText = mb_substr($pdfText, 0, 120000);
    }

    // Run author pre-scan on extracted PDF text
    $authorHints = scan_for_authors($pdfText);

    $fname = $ext['file_name'] ?? basename($absFile);
    $hintBlock = !empty($authorHints) ? "\n\nAuthor candidates found in the document:\n- " . implode("\n- ", $authorHints) : '';
    $parts = [['text' => $prompt . $hintBlock . "\n\nFilename: {$fname}\n\nContent extracted from PDF:\n{$pdfText}"]];
    unset($raw);

} else {
    // Run author pre-scan on text/DOCX content
    $authorHints = scan_for_authors($fileContent);

    $fname = $ext['file_name'] ?? basename($absFile);
    $hintBlock = !empty($authorHints) ? "\n\nAuthor candidates found in the document:\n- " . implode("\n- ", $authorHints) : '';
    $parts = [['text' => $prompt . $hintBlock . "\n\nFilename: {$fname}\n\nContent:\n{$fileContent}"]];
}

$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => $parts],
    ],
    'generationConfig' => [
        'maxOutputTokens' => 4096,
    ],
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent";
error_log("[metadata-scan-v3] model={$geminiModel} file=" . basename($absFile) . " author_hints=" . count($authorHints));

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $geminiKey],
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
    error_log("[metadata-scan-v3] cURL error: {$curlErr}");
    echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlErr]);
    exit;
}

$api = json_decode((string)$body, true);
error_log("[metadata-scan-v3] HTTP {$status} body=" . substr((string)$body, 0, 1000));

if ($status < 200 || $status >= 300) {
    $msg = $api['error']['message'] ?? "HTTP {$status}";
    error_log("[metadata-scan-v3] FAILED: {$msg}");
    echo json_encode(['success' => false, 'error' => "Gemini error ({$status}): {$msg}"]);
    exit;
}

$text = '';
foreach (($api['candidates'][0]['content']['parts'] ?? []) as $part) {
    if (!empty($part['text'])) $text .= $part['text'];
}

if ($text === '' && isset($api['promptFeedback']['blockReason'])) {
    echo json_encode(['success' => false, 'error' => 'Blocked: ' . $api['promptFeedback']['blockReason']]);
    exit;
}
if ($text === '') {
    $finish = $api['candidates'][0]['finishReason'] ?? 'unknown';
    echo json_encode(['success' => false, 'error' => "No text (finish: {$finish})"]);
    exit;
}

$text = trim($text);
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```\s*$/i', '', $text);
$text = trim($text);

$metadata = json_decode($text, true);
if (!is_array($metadata) && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $metadata = json_decode($m[0], true);
}
if (!is_array($metadata)) {
    echo json_encode(['success' => false, 'error' => 'Unparseable response', '_raw' => substr($text, 0, 500)]);
    exit;
}

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

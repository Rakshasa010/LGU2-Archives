<?php
/**
 * Gemini API integration helper for LAS.
 *
 * Google does not publish an official PHP SDK for the Gemini API, so this
 * helper talks to the official Gemini REST API directly via cURL, following
 * the same pattern as the Pinata integration (includes/pinata.php).
 * Configured via GEMINI_API_KEY in the project .env file.
 *
 * Endpoints used (official Gen AI Developer API, v1beta):
 *   POST /models/{model}:generateContent
 *   POST /models/{model}:streamGenerateContent?alt=sse
 *
 * Public API:
 *   gemini_config()               : array{key}
 *   gemini_is_configured()        : bool
 *   gemini_generate($system, $messages, $opts)
 *                                 : array{success, text?, error?, status?, finish_reason?}
 *   gemini_stream_to_sse($system, $messages, $opts)
 *                                 : void (writes SSE events to the response)
 *   gemini_model()                : string (model id)
 */

function gemini_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $env = [];
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\"'");
        }
    }
    $key = $env['GEMINI_API_KEY'] ?? $_ENV['GEMINI_API_KEY'] ?? (getenv('GEMINI_API_KEY') ?: '');
    $config = ['key' => trim((string)$key)];
    return $config;
}

function gemini_is_configured() {
    $cfg = gemini_config();
    return !empty($cfg['key']);
}

function gemini_model() {
    return 'gemini-3.5-flash';
}

function gemini_endpoint($stream = false) {
    $suffix = $stream ? ':streamGenerateContent?alt=sse' : ':generateContent';
    return 'https://generativelanguage.googleapis.com/v1beta/models/' . gemini_model() . $suffix;
}

/**
 * Build the JSON request payload for generateContent.
 *
 * @param string $system  System instruction text (optional).
 * @param array  $messages List of ['role' => 'user'|'model', 'parts' => [['text' => ...]]].
 * @param array  $opts    temperature, maxOutputTokens, topP.
 * @return array
 */
function gemini_request_payload($system, array $messages, array $opts = []) {
    $payload = [
        'contents' => array_values($messages),
        'generationConfig' => [
            'temperature'     => $opts['temperature'] ?? 0.7,
            'maxOutputTokens' => $opts['maxOutputTokens'] ?? 2048,
            'topP'            => $opts['topP'] ?? 0.95,
        ],
    ];
    if (!empty($system)) {
        $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
    }
    return $payload;
}

/**
 * Human-friendly error message for a Gemini HTTP status.
 */
function gemini_http_error_message($status, $body = '') {
    $detail = '';
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $detail = $decoded['error']['message'];
        } elseif (is_array($decoded) && isset($decoded['error']['status'])) {
            $detail = $decoded['error']['status'];
        } else {
            $detail = substr($body, 0, 300);
        }
    }
    $known = [
        400 => 'The request to Gemini was invalid.',
        401 => 'Invalid Gemini API key (GEMINI_API_KEY).',
        403 => 'Access to the Gemini API was forbidden. Check your API key permissions.',
        404 => 'Gemini model not found on this API key.',
        429 => 'Gemini rate limit or quota exceeded. Please wait a moment and try again.',
        500 => 'Gemini API returned an internal server error.',
        503 => 'Gemini API is temporarily unavailable. Please try again shortly.',
    ];
    $base = $known[$status] ?? ('Gemini API error (HTTP ' . $status . ').');
    return $detail !== '' ? $base . ' ' . $detail : $base;
}

/**
 * Non-streaming call: single generateContent request.
 *
 * @return array{success: bool, text?: string, error?: string, status?: int, finish_reason?: string}
 */
function gemini_generate($system, array $messages, array $opts = []) {
    $cfg = gemini_config();
    if (empty($cfg['key'])) {
        return ['success' => false, 'error' => 'GEMINI_API_KEY is not configured'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'The PHP cURL extension is not enabled'];
    }

    $ch = curl_init(gemini_endpoint(false) . '&key=' . rawurlencode($cfg['key']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(gemini_request_payload($system, $messages, $opts)),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body   = curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        return ['success' => false, 'error' => 'Gemini request failed: ' . $err];
    }

    $data = json_decode((string)$body, true);
    if ($status >= 200 && $status < 300) {
        $text = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        if ($text === '' && isset($data['promptFeedback']['blockReason'])) {
            return [
                'success' => false,
                'error' => 'The request was blocked: ' . $data['promptFeedback']['blockReason'],
                'status' => $status,
            ];
        }
        return [
            'success'       => true,
            'text'          => $text,
            'status'        => $status,
            'finish_reason' => $data['candidates'][0]['finishReason'] ?? null,
        ];
    }

    return [
        'success' => false,
        'status'  => $status,
        'error'   => gemini_http_error_message($status, (string)$body),
    ];
}

/**
 * Write an SSE data event to the output buffer.
 */
function gemini_sse_write(array $data) {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    flush();
}

/**
 * Streaming call via streamGenerateContent?alt=sse.
 * Parses Gemini's SSE stream incrementally (via CURLOPT_WRITEFUNCTION) and
 * re-emits it as clean SSE events to the response:
 *   data: {"delta":"..."}   — text chunk
 *   data: {"finish":"..."}  — finish reason
 *   data: {"error":"..."}   — API error
 *   data: {"done":true}     — stream complete
 */
function gemini_stream_to_sse($system, array $messages, array $opts = []) {
    $cfg = gemini_config();
    if (empty($cfg['key'])) {
        gemini_sse_write(['error' => 'GEMINI_API_KEY is not configured']);
        return;
    }

    $url = gemini_endpoint(true) . '&key=' . rawurlencode($cfg['key']);
    $payload = gemini_request_payload($system, $messages, $opts);

    $buffer = '';
    $sawText = false;
    $errorBody = '';

    $handler = function ($ch, $chunk) use (&$buffer, &$sawText, &$errorBody) {
        $buffer .= $chunk;
        // Normalize CRLF -> LF. Gemini may deliver SSE blocks with \r\n\r\n
        // separators; splitting only on \n\n would leave the buffer never split
        // and yield an empty response despite a successful 200 stream.
        $buffer = str_replace("\r", '', $buffer);
        // Non-SSE bodies (e.g. plain JSON error responses) are captured for messaging.
        if (strpos($chunk, 'data:') === false && strpos($buffer, 'data:') === false) {
            $errorBody .= substr($chunk, 0, 4096);
        }
        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $block = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 2);
            foreach (explode("\n", $block) as $line) {
                if (strncmp($line, 'data:', 5) !== 0) {
                    continue;
                }
                $json = trim(substr($line, 5));
                if ($json === '' || $json === '[DONE]') {
                    continue;
                }
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    continue;
                }
                if (isset($data['error'])) {
                    gemini_sse_write(['error' => $data['error']['message'] ?? 'Gemini API error']);
                    $errorBody = '';
                    return strlen($chunk);
                }
                $text = '';
                foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
                if ($text !== '') {
                    $sawText = true;
                    gemini_sse_write(['delta' => $text]);
                }
                $finish = $data['candidates'][0]['finishReason'] ?? null;
                if ($finish !== null) {
                    gemini_sse_write(['finish' => $finish]);
                }
            }
        }
        return strlen($chunk);
    };

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION  => $handler,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        gemini_sse_write(['error' => 'Gemini request failed: ' . $err]);
        return;
    }
    if ($status >= 400 && !$sawText) {
        gemini_sse_write(['error' => gemini_http_error_message($status, $errorBody)]);
        return;
    }
    if (!$sawText) {
        $detail = trim($errorBody) !== '' ? ' Body: ' . substr(trim($errorBody), 0, 300) : '';
        error_log('[gemini] Empty stream from Gemini (HTTP ' . $status . ')' . $detail);
        gemini_sse_write(['error' => 'Gemini returned an empty response (HTTP ' . $status . ').']);
        return;
    }
    gemini_sse_write(['done' => true]);
}

/* ============================================================================
 * Document version comparison
 * ----------------------------------------------------------------------------
 * Resolves raw file references (DB record, local path, or Pinata CID), builds a
 * multimodal Gemini payload (base64 inline PDFs or extracted DOCX/text), and
 * asks the model for a structured diff (Summary / Additions / Removals /
 * Modifications).
 * ========================================================================== */

/**
 * Resolve a DB record reference to its stored file metadata.
 *
 * @param mysqli $conn
 * @param string $source 'archive'|'legislative'|'external'
 * @param int    $id     Record id.
 * @return array|null {path, cid, label, original_name} or null when not found.
 */
function gemini_lookup_record($conn, $source, $id) {
    if ($source === 'legislative') {
        $stmt = $conn->prepare("SELECT file_path, ipfs_cid, title, mime_type FROM legislative_records WHERE id = ?");
        $labelCol = 'title';
    } elseif ($source === 'external') {
        $stmt = $conn->prepare("SELECT file_path, ipfs_cid, title, file_name, mime_type FROM external_documents WHERE id = ?");
        $labelCol = 'file_name';
    } else {
        $stmt = $conn->prepare("SELECT file_path, ipfs_cid, name, mime_type FROM archive_files WHERE id = ?");
        $labelCol = 'name';
    }
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $label = '';
    if ($labelCol === 'file_name') {
        $label = !empty($row['file_name']) ? $row['file_name'] : ($row['title'] ?? ('#'.$id));
    } else {
        $label = $row[$labelCol] ?? '#' . $id;
    }
    return [
        'path'          => (string)($row['file_path'] ?? ''),
        'cid'           => (string)($row['ipfs_cid'] ?? ''),
        'label'         => trim((string)$label) !== '' ? (string)$label : '#'.$id,
        'mime'          => (string)($row['mime_type'] ?? ''),
    ];
}

/**
 * Resolve a local (project-relative) path to a safe absolute file path.
 */
function gemini_resolve_local_path($rel) {
    $rel = rawurldecode(trim((string)$rel));
    if ($rel === '' || strpos($rel, "\0") !== false) {
        return null;
    }
    // Reject traversal only when '..' is a whole path segment (allow it inside
    // filenames, e.g. "report..v2.pdf"), never an escape out of the project.
    if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $rel) === 1) {
        return null;
    }
    $rel = str_replace('\\', '/', $rel);
    if ($rel[0] === '/') {
        return null;
    }
    $baseRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $abs  = realpath($baseRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($abs === false || !is_file($abs)) {
        return null;
    }
    if (stripos($abs . DIRECTORY_SEPARATOR, $baseRoot . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $abs;
}

/**
 * Download a Pinata CID's content to a temp file and return its path.
 */
function gemini_fetch_cid_to_temp($cid) {
    if (!function_exists('pinata_gateway_url')) {
        require_once __DIR__ . '/pinata.php';
    }
    if (!function_exists('curl_init')) {
        return null;
    }
    $url = pinata_gateway_url($cid);
    $tmp = @tempnam(sys_get_temp_dir(), 'gemv');
    if ($tmp === false) {
        return null;
    }
    $fp = @fopen($tmp, 'wb');
    if ($fp === false) {
        @unlink($tmp);
        return null;
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'LAS-Gemini-Comparator/1.0',
    ]);
    curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($curl);
    curl_close($curl);
    @fclose($fp);
    $bytes = (int)@filesize($tmp);
    if ($err !== '' || $http >= 400 || $bytes <= 0) {
        @unlink($tmp);
        return null;
    }
    return $tmp;
}

/**
 * Resolve any supported file reference (record array, path string, or CID)
 * into an on-disk file description.
 */
function gemini_resolve_file($ref, $conn) {
    if (is_array($ref)) {
        $source = isset($ref['source']) ? (string)$ref['source'] : 'archive';
        $id     = (int)($ref['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $rec = gemini_lookup_record($conn, $source, $id);
        if (!$rec) {
            return null;
        }
        if ($rec['path'] !== '') {
            $local = gemini_resolve_local_path($rec['path']);
            if ($local) {
                return ['path' => $local, 'label' => $rec['label'], 'cid' => $rec['cid']];
            }
        }
        if ($rec['cid'] !== '') {
            $tmp = gemini_fetch_cid_to_temp($rec['cid']);
            if ($tmp) {
                return ['path' => $tmp, 'label' => $rec['label'], 'cid' => $rec['cid'], 'temp' => true];
            }
        }
        return null;
    }

    $s = trim((string)$ref);
    if ($s === '') {
        return null;
    }
    // CID detection: no path separators and a real IPFS CID shape.
    //   CIDv0: "Qm" + 44 base58btc chars (excludes 0/O/I/l).
    //   CIDv1: "b" + base32 (lowercase a-z + 2-7) or base58btc payload.
    $isCid = strpos($s, '/') === false && strpos($s, '\\') === false
        && (preg_match('/^Qm[1-9A-HJ-NP-Za-km-z]{44}$/', $s) === 1
            || preg_match('/^b[a-z2-7]{56,60}$/', $s) === 1
            || preg_match('/^b[1-9A-HJ-NP-Za-km-z]{48,}$/', $s) === 1);
    if ($isCid) {
        $tmp = gemini_fetch_cid_to_temp($s);
        if ($tmp) {
            return ['path' => $tmp, 'label' => 'CID ' . substr($s, 0, 12) . '…', 'cid' => $s, 'temp' => true];
        }
        return null;
    }
    $local = gemini_resolve_local_path($s);
    if ($local) {
        return ['path' => $local, 'label' => basename($local), 'cid' => ''];
    }
    return null;
}

/**
 * Build a Gemini content part from an on-disk file.
 * PDFs become base64 inline_data; DOCX and text files become raw text parts.
 *
 * @return array|null ['part' => array] on success, ['error' => string] on failure.
 */
function gemini_file_content_part($absPath, $label = '') {
    if (!is_file($absPath) || !is_readable($absPath)) {
        return ['error' => 'File is not readable on the server.'];
    }
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

    // PDF → inline application/pdf part.
    if ($ext === 'pdf') {
        $size = (int)@filesize($absPath);
        if ($size <= 0) {
            return ['error' => 'The PDF file is empty or unreadable.'];
        }
        if ($size > 30 * 1024 * 1024) {
            return ['error' => 'The PDF is too large to compare (max 30MB).'];
        }
        $data = (string)@file_get_contents($absPath);
        if ($data === '') {
            return ['error' => 'Could not read the PDF file.'];
        }
        return [
            'part'  => [
                'inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data'      => base64_encode($data),
                ],
            ],
            'label' => $label !== '' ? $label : basename($absPath),
        ];
    }

    // DOCX = extract text server-side (no ZipArchive needed).
    if ($ext === 'docx') {
        if (!function_exists('docx_extract_text')) {
            require_once __DIR__ . '/docx_preview.php';
        }
        $text = docx_extract_text($absPath);
        if ($text === null || trim($text) === '') {
            return ['error' => 'No readable text could be extracted from the DOCX file.'];
        }
        if (mb_strlen($text) > 600000) {
            $text = mb_substr($text, 0, 600000);
        }
        return [
            'part'  => ['text' => '<DOCUMENT_BODY>' . $text . '</DOCUMENT_BODY>'],
            'label' => $label !== '' ? $label : basename($absPath),
        ];
    }

    // Plain text / markdown / CSV etc. = read directly.
    $textables = ['txt', 'md', 'markdown', 'csv', 'text', 'log', 'ini', 'rtf'];
    if (in_array($ext, $textables, true)) {
        $text = (string)@file_get_contents($absPath);
        if ($text === '') {
            return ['error' => 'Could not read the text file.'];
        }
        if (mb_strlen($text) > 600000) {
            $text = mb_substr($text, 0, 600000);
        }
        return [
            'part'  => ['text' => '<DOCUMENT_CONTENT>' . $text . '</DOCUMENT_CONTENT>'],
            'label' => $label !== '' ? $label : basename($absPath),
        ];
    }

    return ['error' => 'Unsupported file type (.'.$ext.'). Supported: PDF, DOCX, TXT.'];
}

/**
 * Compare two document versions via the Gemini API.
 *
 * @param mixed $v1   Reference to version 1 (record array, path, or CID).
 * @param mixed $v2   Reference to version 2.
 * @param mysqli $conn
 * @param array $opts generation options.
 * @return array gemini_generate() result (+ file_v1_name / file_v2_name).
 */
function gemini_compare_versions($v1, $v2, $conn, array $opts = []) {
    $opts += [
        'temperature'     => 0.2,
        'maxOutputTokens' => 8192,
    ];
    $r1 = gemini_resolve_file($v1, $conn);
    if (!$r1) {
        return ['success' => false, 'error' => 'Could not resolve the Version 1 file.'];
    }
    $r2 = gemini_resolve_file($v2, $conn);
    if (!$r2) {
        if (isset($r1['temp']) && $r1['temp']) {
            @unlink($r1['path']);
        }
        return ['success' => false, 'error' => 'Could not resolve the Version 2 file.'];
    }

    $p1 = gemini_file_content_part($r1['path'], $r1['label']);
    $p2 = gemini_file_content_part($r2['path'], $r2['label']);

    if (isset($r1['temp']) && $r1['temp']) {
        @unlink($r1['path']);
    }
    if (isset($r2['temp']) && $r2['temp']) {
        @unlink($r2['path']);
    }

    if (!isset($p1['part'])) {
        return ['success' => false, 'error' => 'Version 1: ' . $p1['error']];
    }
    if (!isset($p2['part'])) {
        return ['success' => false, 'error' => 'Version 2: ' . $p2['error']];
    }

    $system = 'You are a meticulous legal and administrative document version-comparison assistant. '
        . 'Compare the two versions of the document provided by the user (Version 1 = first, Version 2 = second) '
        . 'and produce a structured comparison in Markdown with exactly these sections, in this order, each with its own ## heading:\n\n'
        . '## Summary of Changes\nA high-level overview of what changed between the versions.\n'
        . '## Additions\nSpecific text, clauses, or sections that were ADDED in Version 2. Quote them precisely and cite section/clause references where visible.\n'
        . '## Removals\nSpecific text that was DELETED from Version 1 and is absent in Version 2. Quote the removed wording.\n'
        . '## Modifications\nReworded sentences, and updated numbers, dates, amounts, or values. Describe the from → to change precisely.\n\n'
        . 'Rules:\n'
        . '- The two versions are appended in the SAME message: the first document block is Version 1, the second is Version 2.\n'
        . '- Be specific: quote exact wording and references; do not invent content that is not present.\n'
        . '- If there are no changes in a category, state: Edit: None: no changes detected.\n'
        . '- Keep each section concise but complete. End with a one-line note on which version is the newer/more authoritative only if it is evident from the documents.';

    $parts = [
        ['text' => 'Compare these two versions of the same document. '
                 . 'Version 1 file: ' . $p1['label'] . '. Version 2 file: ' . $p2['label'] . '. '
                 . 'The first document block is VERSION 1; the second document block is VERSION 2.'],
        $p1['part'],
        $p2['part'],
    ];
    $messages = [['role' => 'user', 'parts' => $parts]];

    $result = gemini_generate($system, $messages, $opts);
    $result['file_v1_name'] = $r1['label'];
    $result['file_v2_name'] = $r2['label'];
    return $result;
}

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

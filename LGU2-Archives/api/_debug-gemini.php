<?php
/**
 * Quick diagnostic — hit this URL in browser to verify Gemini config.
 * DELETE this file after debugging.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

// Read .env
$geminiKey = '';
$geminiModel = '';
$envPaths = [];
foreach (['../.env', '../../.env'] as $rel) {
    $p = realpath(__DIR__ . '/' . $rel);
    if ($p) $envPaths[] = $p;
    if ($p && is_file($p)) {
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v, " \t\n\r\0\"'");
            if ($k === 'GEMINI_API_KEY') $geminiKey = $v;
            if ($k === 'GEMINI_MODEL') $geminiModel = $v;
        }
        break;
    }
}

// Minimal test call
$testResult = null;
if ($geminiKey !== '') {
    $model = $geminiModel !== '' ? $geminiModel : 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => 'Say hello in one word.']]]],
        'generationConfig' => ['maxOutputTokens' => 32],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $geminiKey],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $testResult = [
        'http_status' => $status,
        'curl_error' => $err ?: null,
        'response' => json_decode((string)$body, true) ?: substr((string)$body, 0, 500),
    ];
}

echo json_encode([
    'env_paths_checked' => $envPaths,
    'gemini_key_set' => $geminiKey !== '',
    'gemini_key_prefix' => $geminiKey !== '' ? substr($geminiKey, 0, 6) . '...' : '(empty)',
    'gemini_model' => $geminiModel ?: '(empty — would default to gemini-2.5-flash)',
    'test_call' => $testResult,
], JSON_PRETTY_PRINT);

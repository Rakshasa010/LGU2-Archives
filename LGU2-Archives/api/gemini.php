<?php
/**
 * api/gemini.php — Unified Gemini endpoint for LAS.
 *
 * Two modes:
 *
 *  1) VERSION COMPARISON — when BOTH file_v1 and file_v2 are supplied:
 *       POST { "file_v1": <ref>, "file_v2": <ref> }
 *     where each <ref> is either:
 *       - { "source": "archive"|"legislative"|"external", "id": <record_id> }
 *       - a project-relative path (e.g. "uploads/legislative/2024/ord.pdf")
 *       - a Pinata IPFS CID
 *     PDFs are sent as base64 inline application/pdf parts; DOCX/TXT are
 *     extracted on the server and sent as text. Returns a structured
 *     Markdown diff: Summary / Additions / Removals / Modifications.
 *
 *  2) CHAT — normal conversational assistant (same behaviour as
 *     api/ai-assistant.php), streaming SSE when stream:true.
 *
 * Both modes require an authenticated session and a configured GEMINI_API_KEY.
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');
ini_set('display_errors', 0);

session_start();
require_once dirname(__DIR__) . '/authdatabase.php';
require_once dirname(__DIR__) . '/includes/gemini.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Authentication ---------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ---- Method + input validation ----------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// ---- Version comparison mode -------------------------------------------------
$fileV1 = $input['file_v1'] ?? null;
$fileV2 = $input['file_v2'] ?? null;
if ($fileV1 !== null || $fileV2 !== null) {
    if ($fileV1 === null || $fileV2 === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Both file_v1 and file_v2 are required for version comparison.']);
        exit;
    }
    if (!gemini_is_configured()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'GEMINI_API_KEY is not configured on the server.']);
        exit;
    }
    $result = gemini_compare_versions($fileV1, $fileV2, $conn);
    if ($result['success']) {
        echo json_encode([
            'success'     => true,
            'mode'        => 'compare',
            'response'    => $result['text'],
            'file_v1_name'=> $result['file_v1_name'],
            'file_v2_name'=> $result['file_v2_name'],
            'finish_reason'=> $result['finish_reason'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code($result['status'] >= 400 && $result['status'] < 600 ? $result['status'] : 500);
        echo json_encode(['success' => false, 'mode' => 'compare', 'error' => $result['error']]);
    }
    exit;
}

// ---- Chat mode ---------------------------------------------------------------
$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}
if (mb_strlen($message) > 4000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is too long (max 4000 characters)']);
    exit;
}

$stream  = !empty($input['stream']);
$history = is_array($input['history'] ?? null) ? $input['history'] : [];

// ---- Lightweight per-session throttling -------------------------------------
$now = time();
if (isset($_SESSION['last_ai_chat_at']) && ($now - (int)$_SESSION['last_ai_chat_at']) < 2) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Please wait a moment before sending another message.']);
    exit;
}
$_SESSION['last_ai_chat_at'] = $now;

// ---- API key check -----------------------------------------------------------
if (!gemini_is_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'GEMINI_API_KEY is not configured on the server.']);
    exit;
}

// ---- Build conversation -------------------------------------------------------
$messages = [];
foreach (array_slice($history, -20) as $turn) {
    if (!is_array($turn)) {
        continue;
    }
    $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
    $text = trim((string)($turn['parts'][0]['text'] ?? $turn['text'] ?? ''));
    if ($text === '') {
        continue;
    }
    $messages[] = ['role' => $role, 'parts' => [['text' => $text]]];
}
$messages[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$system = gemini_chat_system_prompt($conn);

// ---- Streaming (SSE) ---------------------------------------------------------
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    gemini_stream_to_sse($system, $messages);
    exit;
}

// ---- Non-streaming (JSON) ----------------------------------------------------
$result = gemini_generate($system, $messages);
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'mode'    => 'chat',
        'response' => $result['text'],
        'finish_reason' => $result['finish_reason'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code($result['status'] >= 400 && $result['status'] < 600 ? $result['status'] : 500);
    echo json_encode(['success' => false, 'mode' => 'chat', 'error' => $result['error']]);
}
exit;

// ---- Chat system prompt --------------------------------------------------------
/**
 * Build a system prompt with live document context for the assistant.
 */
function gemini_chat_system_prompt($conn) {
    $ctx = [
        'legislative' => [],
        'archive'     => [],
        'external'    => [],
        'folders'     => [],
    ];

    $leg = $conn->query(
        "SELECT lr.title, lr.type, lr.month, lr.year, lr.author, lr.version,
                lr.unique_number, COALESCE(lf.name, 'Unfiled') AS folder
         FROM legislative_records lr
         LEFT JOIN legislative_folders lf ON lr.folder_id = lf.id
         ORDER BY lr.created_at DESC LIMIT 40"
    );
    if ($leg) {
        while ($row = $leg->fetch_assoc()) {
            $ctx['legislative'][] = $row;
        }
    }

    $arc = $conn->query(
        "SELECT af.name, af.author, af.version, af.unique_number, af.file_date,
                COALESCE(fo.name, 'Unfiled') AS folder
         FROM archive_files af
         LEFT JOIN archive_folders fo ON af.folder_id = fo.id
         ORDER BY af.created_at DESC LIMIT 40"
    );
    if ($arc) {
        while ($row = $arc->fetch_assoc()) {
            $ctx['archive'][] = $row;
        }
    }

    $ext = $conn->query(
        "SELECT title, document_type, reference_number, description, status, created_at
         FROM external_documents ORDER BY created_at DESC LIMIT 20"
    );
    if ($ext) {
        while ($row = $ext->fetch_assoc()) {
            $ctx['external'][] = $row;
        }
    }

    foreach ([['archive_folders', 'archive'], ['legislative_folders', 'legislative']] as [$table, $kind]) {
        $r = $conn->query("SELECT name FROM $table ORDER BY name");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $ctx['folders'][] = $kind . ': ' . $row['name'];
            }
        }
    }

    return "You are the Archive Assistant for the SP Valenzuela Legislative Archive System (LAS). "
        . "You help users track document versions and retrieve archived documents "
        . "(ordinances, resolutions, and other legislative/archive files).\n\n"
        . "Live document metadata (JSON):\n"
        . json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        . "\n\nRules:\n"
        . "- Refer to documents by their exact titles and mention version numbers.\n"
        . "- When a user asks where to find a file, point them to storage.php, "
        . "folder_view.php?id=<id>, or download.php as appropriate.\n"
        . "- If you do not know an answer, say so instead of inventing data.\n"
        . "- Keep responses concise, friendly, and professional.";
}

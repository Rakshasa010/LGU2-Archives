<?php
session_start();
require '../authdatabase.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Configuration - Replace with your Gemini API key
$GEMINI_API_KEY = 'YOUR_GEMINI_API_KEY'; // User should replace this
$GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $GEMINI_API_KEY;

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Function to fetch document metadata from the database
function fetchDocumentContext($conn) {
    $context = [];
    
    // Fetch legislative records with versions
    $legQuery = $conn->query("
        SELECT lr.id, lr.title, lr.type, lr.version, lr.parent_version_id, lr.created_at, lr.author, lr.file_path
        FROM legislative_records lr
        ORDER BY lr.created_at DESC
        LIMIT 50
    ");
    if ($legQuery && $legQuery->num_rows > 0) {
        $context['legislative'] = [];
        while ($row = $legQuery->fetch_assoc()) {
            $context['legislative'][] = $row;
        }
    }
    
    // Fetch archive files with versions
    $archQuery = $conn->query("
        SELECT af.id, af.name, af.version, af.parent_version_id, af.created_at, af.author, af.file_path, af.folder_id, (SELECT name FROM archive_folders WHERE id = af.folder_id) as folder_name
        FROM archive_files af
        ORDER BY af.created_at DESC
        LIMIT 50
    ");
    if ($archQuery && $archQuery->num_rows > 0) {
        $context['archive'] = [];
        while ($row = $archQuery->fetch_assoc()) {
            $context['archive'][] = $row;
        }
    }
    
    return $context;
}

// Fetch the document context
$documentContext = fetchDocumentContext($conn);

// Build system prompt
$systemPrompt = "You are a helpful assistant for the SP Valenzuela Archiving System. Your only responsibilities are:
1. Smart Version Tracking: Answer questions about document versions (e.g., latest revision, changes between versions)
2. Smart Document Retrieval: Help users find archived documents by title, type, date, author, or keywords

You have access to the following document metadata (JSON):
" . json_encode($documentContext, JSON_PRETTY_PRINT) . "

When responding:
- Always refer to documents with their exact titles
- Mention versions clearly
- Provide helpful links when applicable (e.g., storage.php, folder_view.php, download.php)
- Keep responses friendly and professional
- Only answer questions related to version tracking and document retrieval";

// Prepare request to Gemini API
$requestData = [
    'contents' => [
        [
            'parts' => [
                ['text' => $systemPrompt],
                ['text' => "User query: " . $userMessage]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1000
    ]
];

// Send request to Gemini API
$ch = curl_init($GEMINI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle the response
if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? "I'm sorry, I couldn't process that request.";
    
    echo json_encode([
        'success' => true,
        'response' => $aiResponse
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get response from AI',
        'debug' => $response
    ]);
}
?>
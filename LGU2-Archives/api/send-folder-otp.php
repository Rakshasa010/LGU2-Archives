<?php
/**
 * Folder Access Password Verification API
 * Verifies the user's account password before allowing folder access.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$password = trim((string)($input['password'] ?? ''));

if ($password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter your password.']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$hash = '';
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $hash = $row['password'];
    }
    $stmt->close();
}

if ($hash === '' || !password_verify($password, $hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Incorrect password. Please try again.']);
    exit;
}

$_SESSION['folder_otp_verified'] = time();

echo json_encode(['success' => true, 'data' => ['verified' => true]]);

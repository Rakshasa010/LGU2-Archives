<?php
/**
 * Verify Folder Access (Session Check)
 * Checks if the user has a recent password verification in the session.
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

header('Content-Type: application/json');

if (!isset($_SESSION['folder_otp_verified'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No verification found. Please enter your password.']);
    exit;
}

$verified_at = (int)$_SESSION['folder_otp_verified'];
if (time() - $verified_at > 300) {
    unset($_SESSION['folder_otp_verified']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Verification expired. Please enter your password again.']);
    exit;
}

echo json_encode(['success' => true, 'data' => ['verified' => true]]);

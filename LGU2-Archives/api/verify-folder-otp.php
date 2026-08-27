<?php
/**
 * Verify Folder OTP API
 * Validates the 6-digit code the user entered against the session.
 * On success, the frontend may proceed to open the folder.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("=== VERIFY FOLDER OTP API CALLED ===");

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$code = trim((string)($input['otp'] ?? ''));

if (!isset($_SESSION['folder_otp_code']) || !isset($_SESSION['folder_otp_expires'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No OTP has been sent yet. Please request a new code.']);
    exit;
}

if (time() > (int)$_SESSION['folder_otp_expires']) {
    unset($_SESSION['folder_otp_code'], $_SESSION['folder_otp_expires'], $_SESSION['folder_otp_sent_at']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'OTP expired. Please request a new code.']);
    exit;
}

if ($code === '' || $code !== (string)$_SESSION['folder_otp_code']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid OTP code. Please try again.']);
    exit;
}

// Success: clear the OTP session data
unset($_SESSION['folder_otp_code'], $_SESSION['folder_otp_expires'], $_SESSION['folder_otp_sent_at']);

// Mark a fresh verification so server-side actions (e.g. downloads) can
// require an OTP that was just entered.
$_SESSION['folder_otp_verified'] = time();

echo json_encode(['success' => true, 'data' => ['verified' => true]]);

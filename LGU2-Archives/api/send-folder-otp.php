<?php
/**
 * Send Folder OTP API
 * Generates a 6-digit OTP, emails it to the current logged-in user,
 * and stores it in the session for verification before opening a folder.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("=== SEND FOLDER OTP API CALLED ===");

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../authdatabase.php';
header('Content-Type: application/json');

$uid = (int)$_SESSION['user_id'];
$email = '';
$full_name = '';
$stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $email = trim((string)($row['email'] ?? ''));
        $full_name = trim((string)($row['full_name'] ?? ''));
    }
    $stmt->close();
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("ERROR: No valid email for user #$uid");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid email address on file for the current user.']);
    exit;
}

// Generate and store OTP
$otp = random_int(100000, 999999);
$_SESSION['folder_otp_code'] = (string)$otp;
$_SESSION['folder_otp_expires'] = time() + 180;
$_SESSION['folder_otp_sent_at'] = time();

// Mask email for display
$at = strpos($email, '@');
$masked = $at !== false
    ? substr($email, 0, 2) . str_repeat('*', max(2, $at - 2)) . substr($email, $at)
    : 'your email';

$sent = false;
$fallback = null;
$emailError = null;

$cfgFile = __DIR__ . '/../mail_config.php';
if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
    $smtpUser = trim((string)($cfg['username'] ?? ''));
    $smtpPass = trim((string)($cfg['password'] ?? ''));
    if ($smtpUser !== '' && $smtpPass !== '') {
        try {
            require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
            require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
            require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $cfg['host'] ?? 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
            $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
            if ($enc === 'ssl') { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; $mailer->Port = (int)($cfg['port'] ?? 465); }
            else { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mailer->Port = (int)($cfg['port'] ?? 587); }
            if (!empty($cfg['smtp_options'])) { $mailer->SMTPOptions = $cfg['smtp_options']; }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Archives');
            $mailer->addAddress($email, $full_name ?: 'Archives User');
            $mailer->Subject = 'Your Folder Access Verification Code';
            $mailer->isHTML(true);
            $otpHtml = htmlspecialchars((string)$otp);
            $mailer->Body = '<div style="font-family:Arial,sans-serif;background:#f5f6f8;padding:24px;border-radius:12px;">
                <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;">
                    <div style="font-size:16px;color:#111827;margin-bottom:8px;">Your One-Time Password (OTP)</div>
                    <div style="font-size:28px;letter-spacing:8px;font-weight:700;color:#dc2626;background:#fff7ed;border:1px dashed #fca5a5;padding:14px 16px;text-align:center;border-radius:10px;margin:12px 0;">' . $otpHtml . '</div>
                    <div style="font-size:13px;color:#6b7280;">This code is required to open an archive folder and expires in <strong>3 minutes</strong>. If you did not request this, you can ignore this email.</div>
                </div>
            </div>';
            $mailer->AltBody = 'Your OTP code is ' . $otp . '. It expires in 3 minutes.';
            $mailer->send();
            $sent = true;
        } catch (Throwable $e) {
            $sent = false;
            $emailError = $e->getMessage();
            error_log("Folder OTP email error: " . $emailError);
        }
    } else {
        error_log("Folder OTP skipped: SMTP credentials empty");
    }
}

if (!$sent) {
    $fallback = (string)$otp;
}

error_log("Folder OTP generated for user #$uid sent=" . ($sent ? 'yes' : 'no'));

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'masked_email' => $masked,
    'fallback_otp' => $fallback,
    'expires_in' => 180,
    'email_error' => $emailError
]);

<?php
session_start();

header('Content-Type: application/json');
http_response_code(200);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Message received.']);
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || mb_strlen($name) > 150) {
    echo json_encode(['success' => false, 'message' => 'Please enter your name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if ($message === '' || mb_strlen($message) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Please enter your message.']);
    exit;
}
if (mb_strlen($department) > 200) {
    $department = mb_substr($department, 0, 200);
}

function contact_load_env($path)
{
    $vars = [];
    if (!file_exists($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value, " \t\n\r\0\"'");
    }
    return $vars;
}

$env = contact_load_env(__DIR__ . '/../.env');
$servername = $env['MYSQL_HOST'] ?? 'localhost';
$username = $env['MYSQL_USER'] ?? 'root';
$password = $env['MYSQL_PASSWORD'] ?? '';
$dbname = $env['MYSQL_DATABASE'] ?? 'las_lgu2_archives';

$conn = @new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.']);
    exit;
}
$conn->set_charset('utf8mb4');
$conn->select_db($dbname);

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(150) NOT NULL,
    sender_email VARCHAR(200) NOT NULL,
    department VARCHAR(200) NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO contact_messages (sender_name, sender_email, department, message, ip_address) VALUES (?, ?, ?, ?, ?)");
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$stmt->bind_param("sssss", $name, $email, $department, $message, $ip);
$stmt->execute();
$stmt->close();

$sent = false;
$cfgFile = __DIR__ . '/../mail_config.php';
if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
    $smtpUser = trim((string)($cfg['username'] ?? ''));
    $smtpPass = trim((string)($cfg['password'] ?? ''));
    if ($smtpUser !== '' && $smtpPass !== '') {
        require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
        require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $cfg['host'] ?? 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
            $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
            if ($enc === 'ssl') {
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mailer->Port = (int)($cfg['port'] ?? 465);
            } else {
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = (int)($cfg['port'] ?? 587);
            }
            if (!empty($cfg['smtp_options'])) {
                $mailer->SMTPOptions = $cfg['smtp_options'];
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Legislative Archive System');
            $mailer->addAddress($cfg['from_email'] ?? $smtpUser);
            $mailer->addReplyTo($email, $name);
            $mailer->Subject = 'New Contact Inquiry from ' . $name;
            $mailer->isHTML(true);
            $mailer->Body = '<div style="font-family:Arial,sans-serif;background:#f5f6f8;padding:24px;border-radius:12px;">
                <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;">
                    <div style="font-size:16px;color:#111827;margin-bottom:12px;font-weight:700;">New Contact Inquiry - Legislative Archive System</div>
                    <div style="font-size:14px;color:#374151;line-height:1.7;">
                        <p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Office/Department:</strong> ' . htmlspecialchars($department !== '' ? $department : 'N/A') . '</p>
                        <p><strong>Message:</strong></p>
                        <p style="background:#f9fafb;border-left:4px solid #dc2626;padding:12px 16px;border-radius:8px;">' . nl2br(htmlspecialchars($message)) . '</p>
                    </div>
                </div>
            </div>';
            $mailer->AltBody = 'New inquiry from ' . $name . ' (' . $email . ')' . ($department !== '' ? ' - ' . $department : '') . ":\n\n" . $message;
            $mailer->send();
            $sent = true;
        } catch (Throwable $e) {
            $sent = false;
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'emailSent' => $sent,
    'message' => $sent
        ? 'Thank you! Your message has been sent.'
        : 'Your message has been received. We will follow up with you.'
]);

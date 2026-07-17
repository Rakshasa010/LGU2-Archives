<?php
/**
 * Test script for email configuration
 * This will help debug why OTP emails might not be sending
 */

// Check if mail config exists
$cfgFile = __DIR__ . '/mail_config.php';
echo "<h2>Email Configuration Test</h2>";

if (!file_exists($cfgFile)) {
    echo "<div style='color: red;'>❌ mail_config.php file not found!</div>";
    exit();
}

echo "<div style='color: green;'>✓ mail_config.php file exists</div>";

// Load configuration
$cfg = require $cfgFile;
echo "<h3>Configuration:</h3>";
echo "<ul>";
echo "<li>Host: " . htmlspecialchars($cfg['host'] ?? 'NOT SET') . "</li>";
echo "<li>Port: " . htmlspecialchars($cfg['port'] ?? 'NOT SET') . "</li>";
echo "<li>Username: " . htmlspecialchars($cfg['username'] ?? 'NOT SET') . "</li>";
echo "<li>Password: " . (isset($cfg['password']) && !empty($cfg['password']) ? '[SET - ' . strlen($cfg['password']) . ' characters]' : 'NOT SET') . "</li>";
echo "<li>From Email: " . htmlspecialchars($cfg['from_email'] ?? 'NOT SET') . "</li>";
echo "<li>From Name: " . htmlspecialchars($cfg['from_name'] ?? 'NOT SET') . "</li>";
echo "<li>Encryption: " . htmlspecialchars($cfg['encryption'] ?? 'NOT SET') . "</li>";
echo "</ul>";

// Check PHPMailer files
$phpMailerPaths = [
    __DIR__ . '/PHPMailer-master/src/Exception.php',
    __DIR__ . '/PHPMailer-master/src/PHPMailer.php',
    __DIR__ . '/PHPMailer-master/src/SMTP.php'
];

echo "<h3>PHPMailer Files:</h3>";
$phpMailerOk = true;
foreach ($phpMailerPaths as $path) {
    $exists = file_exists($path);
    echo "<div style='color: " . ($exists ? 'green' : 'red') . ";'>";
    echo ($exists ? '✓' : '❌') . " " . basename($path);
    echo "</div>";
    if (!$exists) $phpMailerOk = false;
}

if (!$phpMailerOk) {
    echo "<div style='color: red; margin-top: 10px;'>❌ PHPMailer files are missing! Please ensure PHPMailer is installed in PHPMailer-master/ directory.</div>";
    exit();
}

// Test email sending if requested
if (isset($_POST['test_email'])) {
    $testEmail = trim($_POST['email'] ?? '');
    
    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "<div style='color: red; margin-top: 10px;'>❌ Please provide a valid email address</div>";
    } else {
        echo "<h3>Sending Test Email...</h3>";
        
        try {
            require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
            require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
            require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
            
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $cfg['host'] ?? 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $cfg['username'] ?? '';
            $mailer->Password = $cfg['password'] ?? '';
            
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
            $mailer->setFrom($cfg['from_email'] ?? $cfg['username'], $cfg['from_name'] ?? 'Archives Test');
            $mailer->addAddress($testEmail);
            $mailer->Subject = 'Test Email from Archives System';
            $mailer->isHTML(true);
            $mailer->Body = '<h2>Test Email</h2><p>If you receive this email, your mail configuration is working correctly!</p><p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>';
            $mailer->AltBody = 'Test Email - If you receive this email, your mail configuration is working correctly! Timestamp: ' . date('Y-m-d H:i:s');
            
            $mailer->send();
            echo "<div style='color: green; margin-top: 10px;'>✓ Test email sent successfully to " . htmlspecialchars($testEmail) . "</div>";
            
        } catch (Exception $e) {
            echo "<div style='color: red; margin-top: 10px;'>❌ Failed to send test email: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<form method="POST" style="margin-top: 20px; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
    <h3>Test Email Sending</h3>
    <p>Enter your email address to test if the system can send emails:</p>
    <input type="email" name="email" placeholder="your-email@example.com" required style="padding: 8px; width: 300px; margin-right: 10px;">
    <button type="submit" name="test_email" style="padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">Send Test Email</button>
</form>

<div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #007cba; border-radius: 5px;">
    <h4>Troubleshooting Tips:</h4>
    <ul>
        <li><strong>Gmail Users:</strong> You need to use an App Password, not your regular password</li>
        <li><strong>App Password Setup:</strong> Enable 2-Step Verification first, then create App Password at <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
        <li><strong>Less Secure Apps:</strong> Gmail no longer supports "Less Secure Apps" - you MUST use App Passwords</li>
        <li><strong>XAMPP/Local Development:</strong> Try uncommenting the smtp_options in mail_config.php</li>
    </ul>
</div>

<div style="margin-top: 10px;">
    <a href="login.php" style="color: #007cba;">← Back to Login</a>
</div>
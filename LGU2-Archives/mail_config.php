<?php
/**
 * SMTP mail configuration (PHPMailer).
 *
 * Gmail REQUIRES an App Password (not your normal account password):
 * 1. Enable 2-Step Verification: https://myaccount.google.com/security
 * 2. Create App Password: https://myaccount.google.com/apppasswords
 * 3. Use the 16-character password here (no spaces). Example: abcd efgh ijkl mnop → use abcdefghijklmnop
 *
 * Port 587 + TLS is used by default (works well on XAMPP/Windows). Alternative: port 465 + ssl.
 *
 * Credentials are loaded from the .env file in the project root.
 */

// Load .env if not already loaded
function mail_config_load_env($path) {
    $vars = [];
    if (!file_exists($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value, " \t\n\r\0\"'");
    }
    return $vars;
}

$envPath = __DIR__ . '/../.env';
$env = mail_config_load_env($envPath);

return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => $env['SMTP_USERNAME'] ?? '',
    'password'   => $env['SMTP_PASSWORD'] ?? '',
    'from_email' => $env['SMTP_FROM_EMAIL'] ?? ($env['SMTP_USERNAME'] ?? ''),
    'from_name'  => $env['SMTP_FROM_NAME'] ?? 'Archives',
    'encryption' => 'tls',
    'debug'      => 0,
    // If you get "Failed to connect", uncomment below (local dev only):
    // 'smtp_options' => ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
];

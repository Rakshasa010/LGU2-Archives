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
 */
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => 'johnpauldeluna06@gmail.com',
    'password'   => 'mftrjnhqmpmorozb',
    'from_email' => 'johnpauldeluna06@gmail.com',
    'from_name'  => 'Archives',
    'encryption' => 'tls',
    'debug'      => 0,
    // If you get "Failed to connect", uncomment below (local dev only):
    // 'smtp_options' => ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
];

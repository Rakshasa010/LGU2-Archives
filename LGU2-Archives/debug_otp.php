<?php
session_start();

echo "<h2>OTP Email Debug Information</h2>";

if (isset($_SESSION['email_error'])) {
    echo "<h3>Last Email Error:</h3>";
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #ffe6e6; margin: 10px 0;'>";
    echo htmlspecialchars($_SESSION['email_error']);
    echo "</div>";
}

if (isset($_SESSION['otp_email_status'])) {
    echo "<h3>Last Email Status:</h3>";
    echo "<div style='color: " . ($_SESSION['otp_email_status'] === 'sent' ? 'green' : 'red') . "; padding: 10px; border: 1px solid; margin: 10px 0;'>";
    echo "Status: " . htmlspecialchars($_SESSION['otp_email_status']);
    echo "</div>";
}

if (isset($_SESSION['otp_fallback'])) {
    echo "<h3>Fallback OTP:</h3>";
    echo "<div style='padding: 10px; border: 1px solid orange; background: #fff3cd; margin: 10px 0;'>";
    echo "OTP Code: " . htmlspecialchars($_SESSION['otp_fallback']);
    echo "</div>";
}

echo "<h3>Current OTP Session Data:</h3>";
$otpData = [];
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'otp') !== false) {
        if ($key === 'otp_code') {
            $otpData[$key] = '[HIDDEN FOR SECURITY]';
        } else {
            $otpData[$key] = $value;
        }
    }
}

if (!empty($otpData)) {
    echo "<pre>" . print_r($otpData, true) . "</pre>";
} else {
    echo "<p>No OTP session data found</p>";
}

// Check mail config quickly
$cfgFile = __DIR__ . '/mail_config.php';
echo "<h3>Mail Configuration Status:</h3>";
if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
    echo "<div style='color: green;'>✓ mail_config.php exists</div>";
    echo "<div>SMTP Username: " . (empty($cfg['username']) ? "❌ NOT SET" : "✓ SET") . "</div>";
    echo "<div>SMTP Password: " . (empty($cfg['password']) ? "❌ NOT SET" : "✓ SET") . "</div>";
} else {
    echo "<div style='color: red;'>❌ mail_config.php NOT FOUND</div>";
}

echo "<h3>Navigation:</h3>";
echo "<a href='login.php'>← Back to Login</a> | ";
echo "<a href='verify-otp.php'>Go to Verify OTP</a> | ";
echo "<a href='test_email.php'>Test Email Configuration</a>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
</style>
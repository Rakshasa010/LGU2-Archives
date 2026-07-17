<?php
session_start();

echo "<h2>OTP Flow Test</h2>";

echo "<h3>Current Session Variables:</h3>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'otp') !== false) {
        if ($key === 'otp_code') {
            echo "$key: [HIDDEN FOR SECURITY]\n";
        } else {
            echo "$key: " . var_export($value, true) . "\n";
        }
    }
}
echo "</pre>";

echo "<h3>Test Navigation:</h3>";
echo "<a href='login.php'>Go to Login</a><br>";
if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true) {
    echo "<a href='verify-otp.php'>Go to Verify OTP</a><br>";
}
echo "<a href='test_otp_flow.php?clear=1'>Clear OTP Session</a><br>";

if (isset($_GET['clear'])) {
    unset($_SESSION['otp_pending'], $_SESSION['otp_code'], $_SESSION['otp_expires'], 
          $_SESSION['otp_user_id'], $_SESSION['otp_must_change'], $_SESSION['otp_dark_mode']);
    echo "<p style='color: green;'>OTP session cleared!</p>";
    echo "<script>setTimeout(function(){ window.location.href = 'test_otp_flow.php'; }, 1000);</script>";
}

echo "<h3>Expected Flow:</h3>";
echo "<ol>";
echo "<li>User enters credentials in login.php</li>";
echo "<li>If credentials are valid, OTP is generated and user is redirected to verify-otp.php</li>";
echo "<li>In verify-otp.php, user enters OTP code</li>";
echo "<li>If OTP is correct, user is logged in and redirected to archives-landing.php</li>";
echo "<li>If user clicks 'Start Over', they are redirected back to login.php with session cleared</li>";
echo "<li>If OTP expires, user is automatically redirected to login.php</li>";
echo "</ol>";
?>
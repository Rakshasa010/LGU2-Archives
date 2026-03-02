<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gray-50 dark:bg-slate-900 transition-colors">
<div class="absolute top-6 right-6">
    <button id="theme-toggle" class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-100 flex items-center justify-center hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors" aria-label="Toggle theme">
        <svg id="theme-icon" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m6.364 1.636l-.707.707M21 12h-1m-1.636 6.364l-.707-.707M12 21v-1m-6.364-1.636l.707-.707M3 12h1m1.636-6.364l.707.707M12 6a6 6 0 100 12 6 6 0 000-12z"/></svg>
    </button>
</div>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'authdatabase.php';
    $step = $_POST['step'] ?? 'request';
    if ($step === 'request') {
        $identifier = trim($_POST['identifier'] ?? '');
        $stmt = $conn->prepare("SELECT id, email, full_name FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $u = $res->fetch_assoc();
            $userId = (int)$u['id'];
            $email = $u['email'];
            $fullName = $u['full_name'];
            $conn->query("CREATE TABLE IF NOT EXISTS password_reset_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $code = strval(random_int(100000, 999999));
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 10 * 60);
            $ins = $conn->prepare("INSERT INTO password_reset_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $userId, $codeHash, $expires);
            $ins->execute();

            $subject = 'Archives password reset code';
            $messagePlain = "Hello {$fullName},\n\nYour password reset code is: {$code}\nThis code expires in 10 minutes.\n\nIf you did not request this, ignore this email.";
            $sent = false;
            $cfgFile = __DIR__ . '/mail_config.php';
            if (file_exists($cfgFile)) {
                $cfg = require $cfgFile;
                $smtpUser = trim((string)($cfg['username'] ?? ''));
                $smtpPass = trim((string)($cfg['password'] ?? ''));
                if ($smtpUser !== '' && $smtpPass !== '') {
                    try {
                        require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                        require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                        require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mailer->isSMTP();
                        $mailer->Host = trim($cfg['host'] ?? 'smtp.gmail.com');
                        $mailer->SMTPAuth = true;
                        $mailer->Username = $smtpUser;
                        $mailer->Password = $smtpPass;
                        $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                        $mailer->SMTPSecure = ($enc === 'ssl')
                            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailer->Port = (int)($cfg['port'] ?? 587);
                        $mailer->CharSet = 'UTF-8';
                        $mailer->SMTPDebug = (int)($cfg['debug'] ?? 0);
                        if (!empty($cfg['smtp_options']) && is_array($cfg['smtp_options'])) {
                            $mailer->SMTPOptions = $cfg['smtp_options'];
                        }
                        $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Archives');
                        $mailer->addAddress($email, $fullName);
                        $mailer->Subject = $subject;
                        $mailer->isHTML(true);
                        $tzName = isset($cfg['timezone']) && is_string($cfg['timezone']) ? $cfg['timezone'] : 'Asia/Manila';
                        $nowLocal = new DateTime('now', new DateTimeZone($tzName));
                        $expiresLocal = (clone $nowLocal)->modify('+10 minutes');
                        $sentAt = $nowLocal->format('M j, Y h:i A');
                        $expiresAtTxt = $expiresLocal->format('M j, Y h:i A');
                        $otp = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
                        $nameSafe = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
                        $brand = htmlspecialchars($cfg['from_name'] ?? 'Archives', ENT_QUOTES, 'UTF-8');
                        $mailer->Body = '<div style="background:#f7f7f9;padding:24px;font-family:Segoe UI,Arial,sans-serif;color:#111111">'
                            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px">'
                            . '<div style="padding:20px 24px;border-bottom:1px solid #e5e7eb">'
                            . '<h1 style="margin:0;font-size:18px;color:#b91c1c">' . $brand . '</h1>'
                            . '<div style="margin-top:4px;font-size:12px;color:#6b7280">Password Reset Code</div>'
                            . '</div>'
                            . '<div style="padding:24px">'
                            . '<p style="margin:0 0 12px 0;font-size:14px;color:#111111">Hello ' . $nameSafe . ',</p>'
                            . '<p style="margin:0 0 16px 0;font-size:14px;color:#374151">Use the code below to reset your password. Do not share this code.</p>'
                            . '<div style="text-align:center;margin:16px 0">'
                            . '<div style="display:inline-block;font-size:28px;letter-spacing:6px;font-weight:700;color:#111111;background:#fff3f3;border:1px solid #fecaca;border-radius:10px;padding:12px 18px">' . $otp . '</div>'
                            . '</div>'
                            . '<table style="width:100%;font-size:12px;color:#6b7280;margin-top:8px;border-collapse:collapse">'
                            . '<tr><td style="padding:6px 0;width:120px">Sent</td><td style="padding:6px 0">' . $sentAt . ' (' . $tzName . ')</td></tr>'
                            . '<tr><td style="padding:6px 0;width:120px">Expires</td><td style="padding:6px 0">' . $expiresAtTxt . ' (' . $tzName . ')</td></tr>'
                            . '</table>'
                            . '<p style="margin:16px 0 0 0;font-size:12px;color:#6b7280">If you did not request this, you can ignore this email.</p>'
                            . '</div>'
                            . '<div style="padding:16px 24px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280">© ' . date('Y') . ' ' . $brand . '</div>'
                            . '</div>'
                            . '</div>';
                        $mailer->AltBody = $messagePlain;
                        $mailer->send();
                        $sent = true;
                    } catch (Throwable $e) {
                        $sent = false;
                    }
                }
            }
            $verifyFormEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-200 rounded-lg">If an account exists, a reset code has been sent to the registered email. Enter the code below.</div>';
            echo '<form action="forgot-password.php" method="POST" class="space-y-4">';
            echo '<input type="hidden" name="step" value="verify">';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label><input type="email" name="email" value="' . $verifyFormEmail . '" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"></div>';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reset Code</label><input type="text" name="code" required maxlength="6" autocomplete="one-time-code" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" placeholder="6-digit code"></div>';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label><input type="password" name="new_password" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"></div>';
            echo '<button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Update Password</button>';
            echo '<div class="mt-4 text-center text-sm"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request new code</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div>';
            echo '</form></div>';
            $stmt->close();
            $conn->close();
            exit;
        } else {
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-200 rounded-lg">If an account exists, a reset code has been sent to the registered email.</div>';
            echo '<form action="forgot-password.php" method="POST" class="space-y-4">';
            echo '<input type="hidden" name="step" value="verify">';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label><input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"></div>';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reset Code</label><input type="text" name="code" required maxlength="6" autocomplete="one-time-code" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" placeholder="6-digit code"></div>';
            echo '<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label><input type="password" name="new_password" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"></div>';
            echo '<button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Update Password</button>';
            echo '<div class="mt-4 text-center text-sm"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request new code</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div>';
            echo '</form></div>';
            $stmt->close();
            $conn->close();
            exit;
        }
    } elseif ($step === 'verify') {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        include 'authdatabase.php';
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows !== 1) {
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-200 rounded-lg">Invalid email.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request code again</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div></div>';
            $stmt->close();
            $conn->close();
            exit;
        }
        $u = $res->fetch_assoc();
        $userId = (int)$u['id'];
        $stmt->close();
        $conn->query("CREATE TABLE IF NOT EXISTS password_reset_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $sel = $conn->prepare("SELECT id, code_hash, expires_at, attempts FROM password_reset_codes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $sel->bind_param("i", $userId);
        $sel->execute();
        $codeRes = $sel->get_result();
        if (!$codeRes || $codeRes->num_rows !== 1) {
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-200 rounded-lg">No valid reset code. Request a new one.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request code again</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div></div>';
            $sel->close();
            $conn->close();
            exit;
        }
        $row = $codeRes->fetch_assoc();
        $resetId = (int)$row['id'];
        $codeHash = $row['code_hash'];
        $expiresAt = strtotime($row['expires_at']);
        $attempts = (int)$row['attempts'];
        if ($attempts >= 5) {
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-200 rounded-lg">Too many attempts. Request a new code.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request code again</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div></div>';
            $sel->close();
            $conn->close();
            exit;
        }
        if (time() > $expiresAt || !password_verify($code, $codeHash)) {
            $upd = $conn->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?");
            $upd->bind_param("i", $resetId);
            $upd->execute();
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-200 rounded-lg">Invalid or expired code.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Request code again</a> &nbsp;|&nbsp; <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Back to Login</a></div></div>';
            $sel->close();
            $conn->close();
            exit;
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $upd->bind_param("si", $newHash, $userId);
        $upd->execute();
        $del = $conn->prepare("DELETE FROM password_reset_codes WHERE user_id = ?");
        $del->bind_param("i", $userId);
        $del->execute();
        echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
        echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold text-red-600 dark:text-red-400">Password Reset</h1></div>';
        echo '<div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-200 rounded-lg">Password updated successfully. You can now log in with your new password.</div>';
        echo '<div class="text-center"><a href="login.php" class="inline-block w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all">Back to Login</a></div></div>';
        $sel->close();
        $conn->close();
        exit;
    }
}
?>
<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
    <div class="text-center mb-8">
        <img src="Images/Val-logo/valenzuela logo.webp" alt="" class="h-16 w-auto mx-auto mb-4">
        <h1 class="text-3xl font-bold text-red-600">Reset Password</h1>
        <div class="relative my-4">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t-2 border-gray-200 dark:border-slate-700"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="px-3 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gradient-to-br from-white to-gray-50 dark:from-slate-800 dark:to-slate-900">Or continue with</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-6">
            <a href="#" class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/10 hover:border-red-300 dark:hover:border-red-700 transition-all duration-200 font-semibold">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span class="text-sm">Microsoft</span>
            </a>
            <a href="#" class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/10 hover:border-red-300 dark:hover:border-red-700 transition-all duration-200 font-semibold">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                <span class="text-sm">Google</span>
            </a>
        </div>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Enter your email or username to receive a reset code</p>
    </div>
    <form action="forgot-password.php" method="POST" class="space-y-6">
        <input type="hidden" name="step" value="request">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email or Username</label>
            <input type="text" name="identifier" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
        </div>
        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Send Reset Code</button>
    </form>
    <div class="mt-6 text-center space-y-2">
        <p><a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Back to Login</a></p>
        <p class="text-xs text-gray-500 dark:text-gray-400"><a href="test_mail.php" class="hover:underline">Test email setup</a></p>
    </div>
</div>
<script>
    // Centralized dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    
    function getTheme() {
        return localStorage.getItem('theme') || 'light';
    }
    
    function setTheme(theme) {
        localStorage.setItem('theme', theme);
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        updateThemeIcon();
    }
    
    function updateThemeIcon() {
        const theme = getTheme();
        if (theme === 'dark') {
            themeIcon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
        } else {
            themeIcon.innerHTML = '<path d="M12 3v1m6.364 1.636l-.707.707M21 12h-1m-1.636 6.364l-.707-.707M12 21v-1m-6.364-1.636l.707-.707M3 12h1m1.636-6.364l.707.707M12 6a6 6 0 100 12 6 6 0 000-12z"/>';
        }
    }
    
    themeToggle?.addEventListener('click', () => {
        const theme = getTheme();
        setTheme(theme === 'dark' ? 'light' : 'dark');
    });
    
    // Initialize theme on load
    updateThemeIcon();
</script>
</body>
</html>

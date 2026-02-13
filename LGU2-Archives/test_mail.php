<?php
/**
 * One-click test script for PHPMailer + Gmail (mail_config.php).
 * Sends a single test email to confirm SMTP is working.
 * Remove or restrict access in production.
 */
$result = null;
$recipient = trim($_POST['to'] ?? $_GET['to'] ?? '');
$cfg = file_exists(__DIR__ . '/mail_config.php') ? (require __DIR__ . '/mail_config.php') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['send'])) {
    try {
        require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
        require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';

        if (empty($cfg)) {
            throw new RuntimeException('mail_config.php not found or invalid.');
        }

        $to = $recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)
            ? $recipient
            : (trim($cfg['username'] ?? ''));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('No valid recipient. Use config username or ?to=your@email.com');
        }

        $smtpUser = trim((string)($cfg['username'] ?? ''));
        $smtpPass = trim((string)($cfg['password'] ?? ''));
        if ($smtpUser === '' || $smtpPass === '') {
            throw new RuntimeException('mail_config.php: username and password are required.');
        }

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
        // Optional: on some Windows/XAMPP setups TLS verification fails; uncomment only if needed for local dev
        // $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Archives');
        $mailer->addAddress($to);
        $mailer->Subject = 'Archives – Test email';
        $mailer->Body = '<p>This is a test email from <strong>Archives</strong>.</p><p>If you received this, PHPMailer + Gmail are working.</p>';
        $mailer->AltBody = 'This is a test email from Archives. If you received this, PHPMailer + Gmail are working.';
        $mailer->send();

        $result = ['ok' => true, 'message' => 'Test email sent to ' . $to . '.'];
    } catch (Throwable $e) {
        $authError = (stripos($e->getMessage(), 'authenticate') !== false || stripos($e->getMessage(), 'authentication') !== false);
        $result = [
            'ok'       => false,
            'message'  => $e->getMessage(),
            'detail'   => isset($mailer) && $mailer->ErrorInfo !== '' ? $mailer->ErrorInfo : null,
            'auth_help' => $authError,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test email – Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-slate-100 dark:bg-slate-900">
    <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-200 dark:border-slate-700 p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Test email</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">PHPMailer + Gmail (mail_config.php)</p>
        </div>

        <?php if ($result !== null): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $result['ok'] ? 'bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-800 dark:text-red-200'; ?>">
                <p class="font-medium"><?php echo $result['ok'] ? 'Success' : 'Error'; ?></p>
                <p class="mt-1 text-sm"><?php echo htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($result['detail'])): ?>
                    <pre class="mt-2 text-xs overflow-auto max-h-24"><?php echo htmlspecialchars($result['detail'], ENT_QUOTES, 'UTF-8'); ?></pre>
                <?php endif; ?>
                <?php if (!empty($result['auth_help'])): ?>
                    <div class="mt-3 p-3 bg-amber-100 dark:bg-amber-900/40 border border-amber-500 rounded text-sm">
                        <p class="font-medium text-amber-900 dark:text-amber-200">Gmail: use an App Password</p>
                        <p class="mt-1 text-amber-800 dark:text-amber-300">Your normal Gmail password will not work. Create a 16-character <strong>App Password</strong> and put it in <code class="bg-amber-200 dark:bg-amber-800 px-1 rounded">mail_config.php</code> (no spaces).</p>
                        <p class="mt-2"><a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="text-red-600 dark:text-red-400 underline">Create App Password →</a></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="test_mail.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Send to (optional)</label>
                <input type="email" name="to" value="<?php echo htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="<?php echo htmlspecialchars($cfg['username'] ?? 'config default', ENT_QUOTES, 'UTF-8'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty to use the address in mail_config.php</p>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all">
                Send test email
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="forgot-password.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Forgot password</a>
            <span class="text-gray-400 mx-2">|</span>
            <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400">Login</a>
        </p>
    </div>
</body>
</html>

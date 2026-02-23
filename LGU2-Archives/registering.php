<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#dc2626',
                            light: '#f97316',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('Images/BG-login/backgroundlogin.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        [x-cloak] { display: none !important; }
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'authdatabase.php';
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $birthplace = trim($_POST['birthplace'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birthdateValid = ($birthdate !== '' && strtotime($birthdate) !== false);
    if ($full_name === '' || $username === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $birthplace === '' || !$birthdateValid || $address === '') {
        $error = 'Please provide valid information.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->bind_param('ss', $username, $email);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%+=';
            $len = 12;
            $tmp = '';
            for ($i = 0; $i < $len; $i++) {
                $idx = random_int(0, strlen($alphabet) - 1);
                $tmp .= $alphabet[$idx];
            }
            $hash = password_hash($tmp, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (username, password, email, full_name, role, status, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $role = 'user';
            $status = 'pending';
            $must = 1;
            $ins->bind_param('ssssssi', $username, $hash, $email, $full_name, $role, $status, $must);
            if ($ins->execute()) {
                $newId = $ins->insert_id;
                $cols = [];
                $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('nickname','age','birthplace','birthdate','address')");
                if ($colRes) {
                    while ($r = $colRes->fetch_assoc()) { $cols[] = $r['COLUMN_NAME']; }
                }
                $upd = [];
                if (in_array('nickname', $cols)) $upd[] = "nickname = '".$conn->real_escape_string($nickname)."'";
                if (in_array('birthplace', $cols)) $upd[] = "birthplace = '".$conn->real_escape_string($birthplace)."'";
                if (in_array('birthdate', $cols)) $upd[] = "birthdate = '".$conn->real_escape_string(date('Y-m-d', strtotime($birthdate)))."'";
                if (in_array('address', $cols)) $upd[] = "address = '".$conn->real_escape_string($address)."'";
                if (!empty($upd)) {
                    $conn->query("UPDATE users SET ".implode(', ', $upd)." WHERE id = ".(int)$newId);
                }
                $emailSent = false;
                $cfgFile = __DIR__ . '/mail_config.php';
                if (file_exists($cfgFile)) {
                    $cfg = require $cfgFile;
                    $smtpUser = trim((string)($cfg['username'] ?? ''));
                    $smtpPass = trim((string)($cfg['password'] ?? ''));
                    $isPlaceholder = (stripos($smtpUser, 'YOUR_GMAIL') !== false) || (stripos($smtpPass, 'YOUR_16_CHAR') !== false);
                    if ($smtpUser !== '' && $smtpPass !== '' && !$isPlaceholder) {
                        try {
                            require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                            require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                            require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                            $smtpHost = $cfg['host'] ?? 'smtp.gmail.com';
                            $smtpPort = (int)($cfg['port'] ?? 587);
                            $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                            $fromEmail = $cfg['from_email'] ?? $smtpUser;
                            $fromName = $cfg['from_name'] ?? 'Archives';
                            $mailer->isSMTP();
                            $mailer->Host = $smtpHost;
                            $mailer->SMTPAuth = true;
                            $mailer->Username = $smtpUser;
                            $mailer->Password = $smtpPass;
                            $mailer->SMTPSecure = ($enc === 'ssl')
                                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mailer->Port = $smtpPort;
                            $mailer->CharSet = 'UTF-8';
                            $mailer->SMTPDebug = 0;
                            $mailer->setFrom($fromEmail, $fromName);
                            $mailer->addAddress($email, $full_name);
                            $mailer->Subject = 'Your temporary password';
                            $mailer->isHTML(true);
                            $mailer->Body = '<p>Hello ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . ',</p>'
                                . '<p>Your account has been created. Use the temporary password below to sign in:</p>'
                                . '<p><strong>' . htmlspecialchars($tmp, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                                . '<p>After logging in, update your password in your profile settings.</p>'
                                . '<p>Archives</p>';
                            $mailer->AltBody = 'Hello ' . $full_name . ', Your temporary password: ' . $tmp . '. Update it after login.';
                            $mailer->send();
                            $emailSent = true;
                        } catch (Throwable $e) {
                            $emailSent = false;
                        }
                    }
                }
                $success = $emailSent
                    ? 'Requesting admin approval. Check your email for the temporary password.'
                    : 'Registered successfully. Use <a href="forgot-password.php" class="underline font-medium">Forgot password</a> to set your password and sign in.';

                // Ensure notifications table exists
                $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    time VARCHAR(20) NOT NULL,
                    date DATE NOT NULL,
                    content VARCHAR(255) NOT NULL,
                    about VARCHAR(100) NOT NULL,
                    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                // Add notification for admins
                $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
                if ($nt) {
                    $ntime = date('h:i A');
                    $ndate = date('Y-m-d');
                    $ncontent = 'New registration: ' . $full_name . ' (' . $username . ') — awaiting approval';
                    $nabout = 'User Registration';
                    $nstatus = 'unread';
                    $nt->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                    $nt->execute();
                    $nt->close();
                }
                // Email admins if SMTP configured
                if (file_exists($cfgFile)) {
                    $cfg = require $cfgFile;
                    $smtpUser = trim((string)($cfg['username'] ?? ''));
                    $smtpPass = trim((string)($cfg['password'] ?? ''));
                    $isPlaceholder = (stripos($smtpUser, 'YOUR_GMAIL') !== false) || (stripos($smtpPass, 'YOUR_16_CHAR') !== false);
                    if ($smtpUser !== '' && $smtpPass !== '' && !$isPlaceholder) {
                        try {
                            require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                            require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                            require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                            $mailer2 = new PHPMailer\PHPMailer\PHPMailer(true);
                            $smtpHost = $cfg['host'] ?? 'smtp.gmail.com';
                            $smtpPort = (int)($cfg['port'] ?? 587);
                            $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                            $fromEmail = $cfg['from_email'] ?? $smtpUser;
                            $fromName = $cfg['from_name'] ?? 'Archives';
                            $mailer2->isSMTP();
                            $mailer2->Host = $smtpHost;
                            $mailer2->SMTPAuth = true;
                            $mailer2->Username = $smtpUser;
                            $mailer2->Password = $smtpPass;
                            $mailer2->SMTPSecure = ($enc === 'ssl')
                                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mailer2->Port = $smtpPort;
                            $mailer2->CharSet = 'UTF-8';
                            $mailer2->SMTPDebug = 0;
                            $mailer2->setFrom($fromEmail, $fromName);
                            $adminsRes = $conn->query("SELECT email, full_name FROM users WHERE role = 'admin'");
                            if ($adminsRes) {
                                $hasRecipients = false;
                                while ($a = $adminsRes->fetch_assoc()) {
                                    if (!empty($a['email'])) {
                                        $mailer2->addAddress($a['email'], $a['full_name'] ?? 'Admin');
                                        $hasRecipients = true;
                                    }
                                }
                            }
                            if (!empty($hasRecipients)) {
                                $mailer2->Subject = 'New user registration pending approval';
                                $mailer2->isHTML(true);
                                $mailer2->Body = '<p>A new user has registered and is pending approval.</p>'
                                    . '<p><strong>Name:</strong> ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . '<br>'
                                    . '<strong>Username:</strong> ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '<br>'
                                    . '<strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
                                    . '<p>Approve or reject in the User Management page.</p>';
                                $mailer2->AltBody = 'New user registered and pending approval. Name: ' . $full_name . ', Username: ' . $username . ', Email: ' . $email;
                                $mailer2->send();
                            }
                        } catch (Throwable $e) {
                            // silently ignore admin email failures
                        }
                    }
                }
            } else {
                $error = 'Registration failed.';
            }
            $ins->close();
        }
        $check->close();
        $conn->close();
    }
}
?>
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
        <div class="text-center mb-8">
            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Archives</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Create your account</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-200 rounded-lg">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>


        <form action="registering.php" method="POST" class="space-y-6">
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
                <input type="text" id="full_name" name="full_name" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>

            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                <input type="text" id="username" name="username" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                <input type="email" id="email" name="email" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>
            <div>
                <label for="nickname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nickname (optional)</label>
                <input type="text" id="nickname" name="nickname"
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>
            <div>
                <label for="birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Birthdate</label>
                <input type="date" id="birthdate" name="birthdate" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>
            <div>
                <label for="birthplace" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Birthplace</label>
                <input type="text" id="birthplace" name="birthplace" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Complete Address</label>
                <input type="text" id="address" name="address" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400">After registering, you can sign in using the password sent to your email (if configured), or use <strong>Forgot password</strong> to set one.</p>

            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Register
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Already have an account? <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 font-medium">Sign in</a></p>
        </div>
    </div>

    <script>
        // Theme toggle if needed
    </script>
</body>
</html>

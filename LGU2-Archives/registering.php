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
                            $tzName = isset($cfg['timezone']) && is_string($cfg['timezone']) ? $cfg['timezone'] : 'Asia/Manila';
                            $nowLocal = new DateTime('now', new DateTimeZone($tzName));
                            $sentAt = $nowLocal->format('M j, Y h:i A');
                            $nameSafe = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
                            $tmpSafe = htmlspecialchars($tmp, ENT_QUOTES, 'UTF-8');
                            $brand = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
                            $mailer->Body = '<div style="background:#f7f7f9;padding:24px;font-family:Segoe UI,Arial,sans-serif;color:#111111">'
                                . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px">'
                                . '<div style="padding:20px 24px;border-bottom:1px solid #e5e7eb">'
                                . '<h1 style="margin:0;font-size:18px;color:#b91c1c">' . $brand . '</h1>'
                                . '<div style="margin-top:4px;font-size:12px;color:#6b7280">Welcome</div>'
                                . '</div>'
                                . '<div style="padding:24px">'
                                . '<p style="margin:0 0 12px 0;font-size:14px;color:#111111">Hello ' . $nameSafe . ',</p>'
                                . '<p style="margin:0 0 16px 0;font-size:14px;color:#374151">Your account has been created. Use the temporary password below to sign in:</p>'
                                . '<div style="text-align:center;margin:16px 0">'
                                . '<div style="display:inline-block;font-size:24px;font-weight:700;color:#111111;background:#fff3f3;border:1px solid #fecaca;border-radius:10px;padding:10px 16px">' . $tmpSafe . '</div>'
                                . '</div>'
                                . '<p style="margin:0 0 12px 0;font-size:13px;color:#374151">After logging in, go to Account Settings and update your password.</p>'
                                . '<table style="width:100%;font-size:12px;color:#6b7280;margin-top:8px;border-collapse:collapse">'
                                . '<tr><td style="padding:6px 0;width:120px">Sent</td><td style="padding:6px 0">' . $sentAt . ' (' . $tzName . ')</td></tr>'
                                . '</table>'
                                . '</div>'
                                . '<div style="padding:16px 24px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280">© ' . date('Y') . ' ' . $brand . '</div>'
                                . '</div>'
                                . '</div>';
                            $mailer->AltBody = 'Hello ' . $full_name . ', Your temporary password: ' . $tmp . '. Update it after login. Sent: ' . $sentAt . ' (' . $tzName . ').';
                            $mailer->send();
                            $emailSent = true;
                        } catch (Throwable $e) {
                            $emailSent = false;
                        }
                    }
                }
                $success = $emailSent
                    ? 'Requesting admin approval. Check your email for the temporary password.'
                    : 'Registered successfully. (Email failed to send). Your temporary password is: <strong>' . htmlspecialchars($tmp) . '</strong> (Save this!). <a href="login.php" class="underline font-medium">Go to Sign in</a>.';

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
                
                // Success stays on page to display message or temporary password smoothly
                // No redirect so user can copy password if fallback is used.
                $error = 'Registration failed.';
            }
            $ins->close();
        }
        $check->close();
        $conn->close();
    }
}
?>
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
        [x-cloak] { display: none !important; }

        @keyframes fade-in{from{opacity:0}to{opacity:1}}
        @keyframes fade-in-up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes bounce-in{0%{opacity:0;transform:scale(0.3)}50%{opacity:1;transform:scale(1.05)}70%{opacity:1;transform:scale(0.9)}100%{opacity:1;transform:scale(1)}}
        @keyframes shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}
        .animate-fade-in{animation:fade-in .6s ease-out forwards}
        .animate-fade-in-up{animation:fade-in-up .6s ease-out forwards}
        .animate-bounce-in{animation:bounce-in .6s cubic-bezier(.68,-.55,.265,1.55) forwards}
        .animate-shake{animation:shake .5s ease-in-out}
    </style>
    <link rel="stylesheet" href="assets/css/skeletons.css">
    <script src="assets/js/ui-enhancements.js"></script>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8">        
        <div class="text-center mb-8">
            <div class="mx-auto w-24 h-24 rounded-2xl shadow-xl bg-white dark:bg-slate-100 flex items-center justify-center -mt-16 mb-6 ring-4 ring-white dark:ring-slate-900 transform hover:scale-110 transition-transform duration-300">
                <img src="Images/Val-logo/valenzuela logo.webp" alt="City Government of Valenzuela" class="w-16 h-16 object-contain">
            </div>
            <div class="text-4xl font-extrabold tracking-tight text-red-600 mb-2">LAS</div>
            <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Join Our Archive System</div>
            <div class="text-sm text-red-600 dark:text-red-400">City Government of Valenzuela</div>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border-2 border-red-400 dark:border-red-700/60 text-red-700 dark:text-red-300 rounded-xl font-semibold animate-shake flex items-start gap-3">
                <i class="bi bi-exclamation-circle-fill text-xl flex-shrink-0"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border-2 border-green-400 dark:border-green-700/60 text-green-700 dark:text-green-300 rounded-xl font-semibold flex items-start gap-3">
                <i class="bi bi-check-circle-fill text-xl flex-shrink-0"></i>
                <span><?php echo strip_tags($success); ?></span>
            </div>
        <?php endif; ?>

        <form action="registering.php" method="POST" class="space-y-5">
            <!-- Grid 2x2 for main fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="full_name" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Full Name</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-person text-lg"></i>
                        </span>
                        <input type="text" id="full_name" name="full_name" required placeholder="Juan Dela Cruz"
                               class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                    </div>
                </div>

                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Username</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-at text-lg"></i>
                        </span>
                        <input type="text" id="username" name="username" required placeholder="juan.delacruz"
                               class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Email Address</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-envelope text-lg"></i>
                        </span>
                        <input type="email" id="email" name="email" required placeholder="juan@lgu.gov.ph"
                               class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                    </div>
                </div>

                <div>
                    <label for="nickname" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Nickname <span class="text-xs text-gray-500">(optional)</span></label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-tag text-lg"></i>
                        </span>
                        <input type="text" id="nickname" name="nickname" placeholder="JDC"
                               class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                    </div>
                </div>
            </div>

            <!-- Personal Info Section -->
            <div class="pt-6 border-t border-gray-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                    <i class="bi bi-person-badge text-red-600"></i>
                    Personal Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="birthdate" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Birthdate</label>
                        <div class="relative group">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                                <i class="bi bi-calendar-event text-lg"></i>
                            </span>
                            <input type="date" id="birthdate" name="birthdate" required
                                   class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                        </div>
                    </div>

                    <div>
                        <label for="birthplace" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Birthplace</label>
                        <div class="relative group">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                                <i class="bi bi-geo-alt text-lg"></i>
                            </span>
                            <input type="text" id="birthplace" name="birthplace" required placeholder="City, Province"
                                   class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <label for="address" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Complete Address</label>
                    <div class="relative group">
                        <span class="absolute top-4 left-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-map text-lg"></i>
                        </span>
                        <input type="text" id="address" name="address" required placeholder="House/Building, Street, Barangay, City"
                               class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all duration-200 hover:border-red-300 dark:hover:border-red-600">
                    </div>
                </div>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-200 dark:border-blue-800">
                <i class="bi bi-info-circle text-blue-600 dark:text-blue-400 mr-2"></i>
                After registration, a temporary password will be sent to your email. Use <strong>Forgot Password</strong> to set a new one.
            </p>

            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-xl font-bold text-lg transition-all duration-200 shadow-lg hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 flex items-center justify-center gap-2 group">
                <span>Create Account</span>
                <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-slate-700 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Already have an account? <a href="login.php" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-bold hover:underline">Sign In Here</a></p>
        </div>
    </div>

    <script>
        // Theme toggle if needed
    </script>
</body>
</html>

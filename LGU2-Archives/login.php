<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/login-head.js"></script>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        
        @keyframes fade-in{from{opacity:0}to{opacity:1}}
        @keyframes fade-in-up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes bounce-in{0%{opacity:0;transform:scale(0.3)}50%{opacity:1;transform:scale(1.05)}70%{opacity:1;transform:scale(0.9)}100%{opacity:1;transform:scale(1)}}
        @keyframes shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}
        .animate-fade-in{animation:fade-in .6s ease-out forwards}
        .animate-fade-in-up{animation:fade-in-up .6s ease-out forwards}
        .animate-bounce-in{animation:bounce-in .6s cubic-bezier(.68,-.55,.265,1.55) forwards}
        .animate-shake{animation:shake .5s ease-in-out}
        .animation-delay-100{animation-delay:100ms}
        .animation-delay-200{animation-delay:200ms}
        .animation-delay-300{animation-delay:300ms}
        .animation-delay-400{animation-delay:400ms}
        @media screen and (max-width:767px){input,select,textarea{font-size:16px !important}}
        .input-field:focus{box-shadow:0 0 0 3px rgba(220,38,38,.1)}
        .spinner{border:2px solid rgba(255,255,255,.3);border-top:2px solid #fff;border-radius:50%;width:20px;height:20px;animation:spin .8s linear infinite}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-anim">
        <div class="layer1"></div>
        <div class="layer2"></div>
        <div class="grid"></div>
        <div class="blob b1"></div>
        <div class="blob b2"></div>
    </div>
    <?php
    session_start();
    
    // If OTP is pending, redirect to verify-otp.php
    if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true) {
        header("Location: verify-otp.php");
        exit();
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['google_sso'])) {
        include 'authdatabase.php';
        $google_email = trim($_POST['google_email']);
        
        $stmt = $conn->prepare("SELECT id, password, must_change_password, status, role, email, full_name, username, dark_mode FROM users WHERE email = ?");
        $stmt->bind_param("s", $google_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (isset($user['status']) && $user['status'] !== 'active') {
                $error = "Your account is not active. Status: " . $user['status'];
            }
        } else {
            // Register new Google user
            $temp_username = 'google_' . substr(md5(uniqid()), 0, 8);
            $temp_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $role = 'user';
            $status = 'active'; 
            $full_name = explode('@', $google_email)[0];
            
            $insert = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param("ssssss", $temp_username, $temp_password, $google_email, $full_name, $role, $status);
            if ($insert->execute()) {
                $user = [
                    'id' => $conn->insert_id,
                    'email' => $google_email,
                    'username' => $temp_username,
                    'full_name' => $full_name,
                    'must_change_password' => 0,
                    'dark_mode' => 0
                ];
            } else {
                $error = "Failed to register new Google account.";
            }
        }
        
        if (!isset($error)) {
            $otp = random_int(100000, 999999);
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_expires'] = time() + 180;
            $_SESSION['otp_user_id'] = (int)$user['id'];
            $_SESSION['otp_must_change'] = (int)($user['must_change_password'] ?? 0);
            $_SESSION['otp_dark_mode'] = (int)($user['dark_mode'] ?? 0);
            $_SESSION['otp_pending'] = true;
            
            $toEmail = $google_email;
            $cfgFile = __DIR__ . '/mail_config.php';
            $sent = false;
            
            if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) && file_exists($cfgFile)) {
                $cfg = require $cfgFile;
                $smtpUser = trim((string)($cfg['username'] ?? ''));
                $smtpPass = trim((string)($cfg['password'] ?? ''));
                if ($smtpUser !== '' && $smtpPass !== '') {
                    require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                    require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                    require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mailer->isSMTP();
                    $mailer->Host = $cfg['host'] ?? 'smtp.gmail.com';
                    $mailer->SMTPAuth = true;
                    $mailer->Username = $smtpUser;
                    $mailer->Password = $smtpPass;
                    $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                    if ($enc === 'ssl') { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; $mailer->Port = (int)($cfg['port'] ?? 465); }
                    else { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mailer->Port = (int)($cfg['port'] ?? 587); }
                    if (!empty($cfg['smtp_options'])) { $mailer->SMTPOptions = $cfg['smtp_options']; }
                    $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Archives');
                    $mailer->addAddress($toEmail, ($user['full_name'] ?? '') ?: 'Google User');
                    $mailer->Subject = 'Your Verification Code';
                    $mailer->isHTML(true);
                    $otpHtml = htmlspecialchars((string)$otp);
                    $mailer->Body = '<div style="font-family:Arial,sans-serif;background:#f5f6f8;padding:24px;border-radius:12px;">
                        <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;">
                            <div style="font-size:16px;color:#111827;margin-bottom:8px;">Your One-Time Password (OTP)</div>
                            <div style="font-size:28px;letter-spacing:8px;font-weight:700;color:#dc2626;background:#fff7ed;border:1px dashed #fca5a5;padding:14px 16px;text-align:center;border-radius:10px;margin:12px 0;">' . $otpHtml . '</div>
                            <div style="font-size:13px;color:#6b7280;">This code expires in <strong>3 minutes</strong>. If you did not request this, you can ignore this email.</div>
                        </div>
                    </div>';
                    $mailer->AltBody = 'Your OTP code is ' . $otp . '. It expires in 3 minutes.';
                    try { 
                        $mailer->send(); 
                        $sent = true; 
                    } catch (Throwable $e) { 
                        $sent = false;
                        // Store the error message for debugging
                        $_SESSION['email_error'] = $e->getMessage();
                    }
                }
            }
            // Store email send status in session for display on verify-otp page
            $_SESSION['otp_email_status'] = $sent ? 'sent' : 'failed';
            $_SESSION['otp_fallback'] = $sent ? null : $otp; // Show OTP if email failed
            // Redirect to verify-otp.php
            header("Location: verify-otp.php");
            exit();
        }

    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
        include 'authdatabase.php';

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Check for lockout
        $stmt = $conn->prepare("SELECT id, password, must_change_password, status, role, failed_attempts, lockout_until, email, full_name, dark_mode FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
                $wait = ceil((strtotime($user['lockout_until']) - time()) / 60);
                $error = "Account locked due to too many failed attempts. Please try again in " . $wait . " minutes.";
            } else {
                if (password_verify($password, $user['password'])) {
                    // Reset failed attempts
                    $conn->query("UPDATE users SET failed_attempts = 0, lockout_until = NULL, last_activity = NOW() WHERE id = " . $user['id']);
                    
                    if (isset($user['status']) && $user['status'] !== 'active') {
                        if ($user['status'] === 'pending') {
                            $error = "Your account is pending approval by an administrator.";
                        } elseif ($user['status'] === 'rejected') {
                            $error = "Your account was rejected. Please contact support.";
                        } else {
                            $error = "Your account is not active.";
                        }
                    } else {
                        $otp = random_int(100000, 999999);
                        $_SESSION['otp_code'] = $otp;
                        $_SESSION['otp_expires'] = time() + 60;
                        $_SESSION['otp_user_id'] = (int)$user['id'];
                        $_SESSION['otp_must_change'] = (int)($user['must_change_password'] ?? 0);
                        $_SESSION['otp_dark_mode'] = (int)($user['dark_mode'] ?? 0);
                        $_SESSION['otp_pending'] = true;
                        $toEmail = trim((string)($user['email'] ?? $username));
                        $cfgFile = __DIR__ . '/mail_config.php';
                        $sent = false;
                        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) && file_exists($cfgFile)) {
                            $cfg = require $cfgFile;
                            $smtpUser = trim((string)($cfg['username'] ?? ''));
                            $smtpPass = trim((string)($cfg['password'] ?? ''));
                            if ($smtpUser !== '' && $smtpPass !== '') {
                                require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                                require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                                require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                                $mailer->isSMTP();
                                $mailer->Host = $cfg['host'] ?? 'smtp.gmail.com';
                                $mailer->SMTPAuth = true;
                                $mailer->Username = $smtpUser;
                                $mailer->Password = $smtpPass;
                                $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                                if ($enc === 'ssl') { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; $mailer->Port = (int)($cfg['port'] ?? 465); }
                                else { $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mailer->Port = (int)($cfg['port'] ?? 587); }
                                if (!empty($cfg['smtp_options'])) { $mailer->SMTPOptions = $cfg['smtp_options']; }
                                $mailer->CharSet = 'UTF-8';
                                $mailer->setFrom($cfg['from_email'] ?? $smtpUser, $cfg['from_name'] ?? 'Archives');
                                $mailer->addAddress($toEmail, ($user['full_name'] ?? '') ?: $username);
                                $mailer->Subject = 'Your One-Time Password';
                                $mailer->isHTML(true);
                                $otpHtml = htmlspecialchars((string)$otp);
                                $mailer->Body = '<div style="font-family:Arial,sans-serif;background:#f5f6f8;padding:24px;border-radius:12px;">
                                    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;">
                                        <div style="font-size:16px;color:#111827;margin-bottom:8px;">Your One-Time Password (OTP)</div>
                                        <div style="font-size:28px;letter-spacing:8px;font-weight:700;color:#dc2626;background:#fff7ed;border:1px dashed #fca5a5;padding:14px 16px;text-align:center;border-radius:10px;margin:12px 0;">' . $otpHtml . '</div>
                                        <div style="font-size:13px;color:#6b7280;">This code expires in <strong>1 minute</strong>. If you did not request this, you can ignore this email.</div>
                                    </div>
                                </div>';
                                $mailer->AltBody = 'Your OTP code is ' . $otp . '. It expires in 1 minute.';
                                try { 
                                    $mailer->send(); 
                                    $sent = true; 
                                } catch (Throwable $e) { 
                                    $sent = false;
                                    // Store the error message for debugging
                                    $_SESSION['email_error'] = $e->getMessage();
                                }
                            }
                        }
                        // Store email send status in session for display on verify-otp page
                        $_SESSION['otp_email_status'] = $sent ? 'sent' : 'failed';
                        $_SESSION['otp_fallback'] = $sent ? null : $otp; // Show OTP if email failed
                        // Redirect to verify-otp.php
                        header("Location: verify-otp.php");
                        exit();
                    }
                } else {
                    // Increment failed attempts
                    $new_attempts = $user['failed_attempts'] + 1;
                    if ($new_attempts >= 5) {
                        $lockout_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        $stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?");
                        $stmt->bind_param("isi", $new_attempts, $lockout_time, $user['id']);
                        $stmt->execute();
                        $error = "Account locked for 5 minutes due to multiple failed login attempts.";
                        
                        // Log security alert
                        $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            time VARCHAR(20) NOT NULL,
                            date DATE NOT NULL,
                            content VARCHAR(255) NOT NULL,
                            about VARCHAR(100) NOT NULL,
                            status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
                        $ntime = date('h:i A');
                        $ndate = date('Y-m-d');
                        $ncontent = "Security Alert: Multiple failed login attempts for user " . $username;
                        $nabout = "Security";
                        $nstatus = "unread";
                        $nt->bind_param("sssss", $ntime, $ndate, $ncontent, $nabout, $nstatus);
                        $nt->execute();
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                        $stmt->bind_param("ii", $new_attempts, $user['id']);
                        $stmt->execute();
                        $remaining = 5 - $new_attempts;
                        $error = "Invalid password. " . $remaining . " attempt(s) remaining.";
                    }
                }
            }
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
        $conn->close();
    }
    ?>
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8 relative animate-fade-in-up">
        <div class="text-center mb-8">
            <div class="mx-auto w-20 h-20 rounded-full shadow-lg bg-white flex items-center justify-center -mt-12 mb-4 ring-4 ring-white dark:ring-slate-900">
                <img src="Images/Val-logo/valenzuela logo.webp" alt="City Government of Valenzuela" class="w-14 h-14 object-contain">
            </div>
            <div class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">LAS</div>
            <div class="text-sm text-gray-700 dark:text-gray-300">Legislative Archive System</div>
            <div class="text-sm font-semibold text-red-600 dark:text-red-400">City Government of Valenzuela</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Metropolitan Manila</div>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                <?php 
                if ($_GET['registered'] === 'email') {
                    echo "Registered successfully. Check your email for the temporary password.";
                } else {
                    echo "Registered successfully. Use Forgot password to set your password and sign in.";
                }
?>
  </div>
        <?php endif; ?>

        <div class="mb-6">
            <div class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Welcome Back</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Sign in to access your archives</div>
        </div>
            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Email Address</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-envelope text-lg"></i>
                        </span>
                        <input type="text" id="username" name="username" placeholder="your.email@lgu.gov.ph" required
                            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Password</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-red-500 transition-colors">
                            <i class="bi bi-lock text-lg"></i>
                        </span>
                        <input type="password" id="password" name="password" required class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors" />
                        <button type="button" id="togglePassword" class="absolute right-0 top-0 h-full flex items-center px-4 text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 focus:outline-none transition-colors" aria-label="Toggle password visibility">
                            <i id="eyeOpen" class="bi bi-eye text-lg"></i>
                            <i id="eyeClosed" class="bi bi-eye-slash text-lg hidden"></i>
                        </button>
                    </div>
                </div>
        <div class="mt-5 flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
        <input id="agreeTerms" name="agreeTerms" type="checkbox" required class="mt-1.5 h-5 w-5 rounded border-2 border-blue-300 dark:border-blue-600 text-red-600 focus:ring-red-500 cursor-pointer">
        <label for="agreeTerms" class="text-sm text-gray-700 dark:text-gray-300">I agree to the <button type="button" onclick="openTerms()" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-semibold underline">Terms & Conditions</button></label>
    </div>

                <button id="login-btn" type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition duration-200 ease-in-out flex items-center justify-center gap-2">
                    <span id="login-btn-text">Sign In</span>
                    <i id="login-btn-icon" class="bi bi-arrow-right text-xl group-hover:translate-x-1 transition-transform"></i>
                    <span id="login-btn-spinner" class="hidden spinner"></span>
                </button>
                <div class="relative my-4">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t-2 border-gray-200 dark:border-slate-700"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="px-3 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gradient-to-br from-white to-gray-50 dark:from-slate-800 dark:to-slate-900">Or continue with</span>
                    </div>
                </div>
                <a href="forgot-password.php" class="block text-center text-sm font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 py-2 rounded-lg transition-colors">Forgot password?</a>
                <div class="grid grid-cols-2 gap-3">
                    <a href="#" id="btn-ms-sso" onclick="initiateSSO('microsoft', this)" class="relative flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-blue-500 transition-all duration-200 font-semibold overflow-hidden group">
                        <div class="absolute inset-0 bg-blue-500/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                        <svg class="w-5 h-5 relative z-10" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                            <rect x="12" y="1" width="10" height="10" fill="#7FBA00"/>
                            <rect x="1" y="12" width="10" height="10" fill="#00A4EF"/>
                            <rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
                        </svg>
                        <span class="text-sm relative z-10">Microsoft</span>
                        <span class="sso-spinner hidden absolute inset-0 bg-white/90 dark:bg-slate-800/90 flex items-center justify-center z-20">
                            <i class="bi bi-arrow-repeat animate-spin text-xl text-blue-600"></i>
                        </span>
                    </a>
                    <a href="#" id="btn-google-sso" onclick="initiateSSO('google', this)" class="relative flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-red-500 transition-all duration-200 font-semibold overflow-hidden group">
                        <div class="absolute inset-0 bg-red-500/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                        <svg class="w-5 h-5 relative z-10" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <span class="text-sm relative z-10">Google</span>
                        <span class="sso-spinner hidden absolute inset-0 bg-white/90 dark:bg-slate-800/90 flex items-center justify-center z-20">
                            <i class="bi bi-arrow-repeat animate-spin text-xl text-red-600"></i>
                        </span>
                    </a>
                </div>
                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-slate-700 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Don't have an account? <a href="registering.php" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-bold hover:underline">Create Account</a></p>
                </div>

            </form>
        </div>

    <script>
    (function(){
        var toggleBtn = document.getElementById('togglePassword');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(){
                var p = document.getElementById('password');
                if (!p) return;
                var open = document.getElementById('eyeOpen');
                var closed = document.getElementById('eyeClosed');
                var show = p.type === 'password';
                p.type = show ? 'text' : 'password';
                if (open && closed){ open.classList.toggle('hidden', show); closed.classList.toggle('hidden', !show); }
            });
        }
        var form = document.querySelector('form[action="login.php"]');
        var btn = document.getElementById('login-btn');
        var btnText = document.getElementById('login-btn-text');
        var btnIcon = document.getElementById('login-btn-icon');
        if (form) {
            form.addEventListener('submit', function(){
                if (btn && btnText && btnIcon){
                    btn.disabled = true;
                    btnText.textContent = 'Signing in...';
                    btnIcon.classList.remove('hidden');
                }
            });
        }
    })();
    
    // SSO Initialization Logic
    function initiateSSO(provider, element) {
        event.preventDefault();

        if (provider === 'google') {
            document.getElementById('googleSsoModal').classList.remove('hidden');
            document.getElementById('googleSsoModal').classList.add('flex');
            return;
        }
        
        // Disable other buttons to prevent multiple clicks
        const allSsoBtns = document.querySelectorAll('#btn-ms-sso, #btn-google-sso, #login-btn');
        allSsoBtns.forEach(b => {
            b.style.pointerEvents = 'none';
            b.style.opacity = '0.7';
        });

        // Show loading spinner on the clicked button
        const spinner = element.querySelector('.sso-spinner');
        if (spinner) {
            spinner.classList.remove('hidden');
            spinner.classList.add('flex');
        }
        
        // Show Toast info optionally if UI_ENH exists
        if (typeof UI_ENH !== 'undefined' && UI_ENH.toast) {
            UI_ENH.toast(`Connecting to ${provider.charAt(0).toUpperCase() + provider.slice(1)}...`, {background: 'linear-gradient(90deg, #3b82f6, #8b5cf6)'});
        }

        // Redirect to the SSO handler script after a slight delay to allow the animation
        setTimeout(() => {
            window.location.href = `sso.php?provider=${provider}`;
        }, 800);
    }
    </script>

    <!-- Google SSO Modal -->
    <div id="googleSsoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm p-6 relative animate-bounce-in mx-4">
            <button type="button" onclick="closeGoogleModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                ✕
            </button>
            <div class="text-center mb-6 mt-2">
                <div class="w-16 h-16 bg-white rounded-full shadow-md flex items-center justify-center mx-auto mb-4 border border-gray-100">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Sign in with Google</h3>
                <p class="text-sm text-gray-500 mt-2">Enter your Google email to receive an OTP.</p>
            </div>
            <form action="login.php" method="POST" class="space-y-4">
                <input type="hidden" name="google_sso" value="1">
                <div>
                    <input type="email" name="google_email" required placeholder="name@gmail.com" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                </div>
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2">
                    Send OTP to Email
                </button>
            </form>
        </div>
    </div>

    <!-- Terms & Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="max-w-3xl w-full mx-4 bg-white dark:bg-slate-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-start justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Terms & Conditions</h3>
                <button type="button" onclick="closeTerms()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-200">✕</button>
            </div>
            <div class="px-6 py-4 max-h-[60vh] overflow-auto text-sm text-gray-700 dark:text-gray-300 space-y-4">
                <div>
                    <strong>1. Acceptance of Terms</strong>
                    <p>By accessing and using the Legislative Services Committee Management System, you accept and agree to be bound by the terms and provision of this agreement.</p>
                </div>
                <div>
                    <strong>2. Use License</strong>
                    <p>Permission is granted to temporarily download one copy of the materials (information or software) on the Legislative Services Committee Management System for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                    <ul class="list-disc pl-5">
                        <li>Modify or copy the materials</li>
                        <li>Use the materials for any commercial purpose or for any public display</li>
                        <li>Attempt to decompile or reverse engineer any software contained on the system</li>
                        <li>Remove any copyright or other proprietary notations from the materials</li>
                        <li>Transfer the materials to another person or "mirror" the materials on any other server</li>
                    </ul>
                </div>
                <div>
                    <strong>3. Disclaimer</strong>
                    <p>The materials on the Legislative Services Committee Management System are provided on an 'as is' basis. We make no warranties, expressed or implied, and hereby disclaim and negate all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex items-center justify-end gap-3">
                <button type="button" onclick="closeTerms()" class="px-4 py-2 rounded-md bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200">Close</button>
                <button type="button" id="acceptTermsBtn" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Accept</button>
            </div>
        </div>
    </div>

    <script>
        function openTerms(){
            var m = document.getElementById('termsModal');
            if (m){ m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow = 'hidden'; }
        }
        function closeTerms(){
            var m = document.getElementById('termsModal');
            if (m){ m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow = ''; }
        }
        function closeGoogleModal() {
            var m = document.getElementById('googleSsoModal');
            if (m){ m.classList.add('hidden'); m.classList.remove('flex'); }
        }
        document.getElementById('acceptTermsBtn')?.addEventListener('click', function(){
            var chk = document.getElementById('agreeTerms');
            if (chk){ chk.checked = true; }
            closeTerms();
        });
    </script>




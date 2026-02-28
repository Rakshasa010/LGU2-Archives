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
        .bg-anim{position:fixed;inset:0;z-index:-1;overflow:hidden}
        .bg-anim .layer1{position:absolute;inset:-20%;background:radial-gradient(1200px 800px at 20% 30%, rgba(220,38,38,.25), transparent 60%),radial-gradient(1000px 700px at 80% 70%, rgba(249,115,22,.25), transparent 60%);filter:blur(40px);animation:drift 18s linear infinite alternate}
        .bg-anim .layer2{position:absolute;inset:0;background:linear-gradient(135deg, rgba(220,38,38,.15), rgba(249,115,22,.1) 50%, rgba(239,68,68,.15));animation:hue 20s linear infinite alternate}
        .bg-anim .grid{position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);background-size:24px 24px;mix-blend-mode:overlay;opacity:.6}
        .bg-anim .blob{position:absolute;width:40vmax;height:40vmax;border-radius:50%;filter:blur(60px);opacity:.2}
        .bg-anim .b1{background:#dc2626;top:-10vmax;left:-10vmax;animation:move1 24s ease-in-out infinite alternate}
        .bg-anim .b2{background:#f97316;bottom:-12vmax;right:-8vmax;animation:move2 26s ease-in-out infinite alternate}
        @keyframes drift{from{transform:translate3d(0,0,0)}to{transform:translate3d(2%,-2%,0)}}
        @keyframes hue{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(20deg)}}
        @keyframes move1{from{transform:translate(0,0) scale(1)}to{transform:translate(6vmax,4vmax) scale(1.1)}}
        @keyframes move2{from{transform:translate(0,0) scale(1)}to{transform:translate(-5vmax,-3vmax) scale(1.08)}}
        @media (prefers-color-scheme: dark){.bg-anim .grid{background-image:radial-gradient(rgba(148,163,184,.08) 1px, transparent 1px)}}
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
    $otp_step = isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true;
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
        include 'authdatabase.php';
        $code = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_expires']) || !isset($_SESSION['otp_user_id'])) {
            $error = "OTP verification not initialized.";
        } elseif (time() > (int)$_SESSION['otp_expires']) {
            $error = "OTP expired. Please sign in again.";
            $_SESSION['otp_pending'] = false;
            unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_user_id'], $_SESSION['otp_must_change']);
        } elseif ($code !== (string)$_SESSION['otp_code']) {
            $error = "Invalid OTP code.";
            $otp_step = true;
        } else {
            $uid = (int)$_SESSION['otp_user_id'];
            $_SESSION['user_id'] = $uid;
            $_SESSION['last_activity'] = time();
            $conn->query("UPDATE users SET last_activity = NOW(), failed_attempts = 0, lockout_until = NULL WHERE id = " . $uid);
            $must = (int)($_SESSION['otp_must_change'] ?? 0);
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, time VARCHAR(20) NOT NULL, date DATE NOT NULL, content VARCHAR(255) NOT NULL, about VARCHAR(100) NOT NULL, status ENUM('unread','read') NOT NULL DEFAULT 'unread', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
            if ($nt) {
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = "User login verified via OTP";
                $nabout = "Login";
                $nstatus = "unread";
                $nt->bind_param("sssss", $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $nt->execute();
                $nt->close();
            }
            $_SESSION['otp_pending'] = false;
            unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_user_id']);
            if ($must === 1) {
                header("Location: profile.php?force=1");
            } else {
                header("Location: archives-landing.php");
            }
            exit();
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
        include 'authdatabase.php';

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Check for lockout
        $stmt = $conn->prepare("SELECT id, password, must_change_password, status, role, failed_attempts, lockout_until, email, full_name FROM users WHERE username = ?");
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
                                $mailer->Body = '<p>Your OTP code is <strong>' . htmlspecialchars((string)$otp) . '</strong>.</p><p>It expires in 1 minute.</p>';
                                $mailer->AltBody = 'Your OTP code is ' . $otp . '. It expires in 1 minute.';
                                try { $mailer->send(); $sent = true; } catch (Throwable $e) { $sent = false; }
                            }
                        }
                        $otp_step = true;
                        $error = $sent ? "An OTP was sent to your email." : "Unable to send OTP via Email. Testing fallback OTP is: " . $otp;
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

        <?php if (!$otp_step): ?>
            <div class="mb-4">
                <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">Welcome Back</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Sign in to access your account</div>
            </div>
            <form action="login.php" method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H8m0 0l-4 4m4-4l-4-4m8 4h8" /></svg></span>
                        <input type="text" id="username" name="username" placeholder="your.email@lgu.gov.ph" required
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required class="w-full pl-10 px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors" />
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 1.105-.672 2-1.5 2S9 12.105 9 11s.672-2 1.5-2S12 9.895 12 11z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></span>
                        <button type="button" id="togglePassword" class="absolute right-0 top-0 h-full flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none" aria-label="Show password">
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5 c4.478 0 8.268 2.943 9.542 7 -1.274 4.057-5.064 7-9.542 7 -4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" /><path stroke-linecap="round" stroke-linejoin="round" d="M10.585 10.585A2 2 0 0012 14a2 2 0 001.414-.586" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.223 6.223A9.956 9.956 0 0112 5 c4.478 0 8.268 2.943 9.543 7 a9.97 9.97 0 01-4.216 5.568" /></svg>
                        </button>
                    </div>
                </div>
    <div class="mt-4 flex items-start gap-3">
        <input id="agreeTerms" name="agreeTerms" type="checkbox" required class="mt-1 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
        <label for="agreeTerms" class="text-sm text-gray-700 dark:text-gray-300">I agree to the <button type="button" onclick="openTerms()" class="text-red-600 hover:underline">Terms & Conditions</button></label>
    </div>

                <button id="login-btn" type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 flex items-center justify-center gap-2">
                    <span id="login-btn-text">Sign In</span>
                    <span id="login-btn-icon" class="hidden spinner"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13.293 4.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 11-1.414-1.414L17.586 12l-4.293-4.293a1 1 0 010-1.414z"/><path d="M3 12a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg>
                </button>
                <div class="relative my-2">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-200 dark:border-slate-700"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="px-3 text-xs text-gray-500 bg-white dark:bg-slate-800">Or continue with</span>
                    </div>
                </div>
                <a href="forgot-password.php" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Forgot password?</a>
                <div class="grid grid-cols-2 gap-3">
                    <a href="#" class="flex items-center justify-center gap-2 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600">
                        <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/microsoft.svg" alt="Microsoft" class="w-5 h-5 opacity-80">
                        <span class="text-sm">Microsoft</span>
                    </a>
                    <a href="#" class="flex items-center justify-center gap-2 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600">
                        <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/google.svg" alt="Google" class="w-5 h-5 opacity-80">
                        <span class="text-sm">Google</span>
                    </a>
                </div>
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Don't have an account? <a href="registering.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 font-medium">Create Account</a></p>
                </div>

            </form>
        <?php else: ?>
            <div class="mb-4">
                <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">Verify OTP</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Enter the 6-digit code sent to your email. Expires in 1 minute.</div>
            </div>
            <form action="login.php" method="POST" class="space-y-6">
                <input type="hidden" name="verify_otp" value="1">
                <div>
                    <label for="otp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">OTP Code</label>
                    <input type="text" id="otp" name="otp" minlength="6" maxlength="6" pattern="[0-9]{6}" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors" placeholder="123456">
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400" id="otp-timer">Expires in: <span id="timer">60</span>s</div>
                    <a href="login.php" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Sign in again</a>
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Verify</button>
            </form>
            <script>
                (function(){
                    var end = <?php echo isset($_SESSION['otp_expires']) ? (int)$_SESSION['otp_expires'] : '0'; ?>;
                    function tick(){
                        var now = Math.floor(Date.now()/1000);
                        var remain = Math.max(0, end - now);
                        var el = document.getElementById('timer');
                        if (el) el.textContent = String(remain);
                        if (remain > 0) setTimeout(tick, 1000);
                    }
                    tick();
                })();
            </script>
        <?php endif; ?>
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
        var ms = document.querySelectorAll('.grid a[href="#"]');
        ms.forEach(function(a){
            a.addEventListener('click', function(e){
                e.preventDefault();
                alert('Single Sign-On coming soon.');
            });
        });
    })();
    </script>

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
        document.getElementById('acceptTermsBtn')?.addEventListener('click', function(){
            var chk = document.getElementById('agreeTerms');
            if (chk){ chk.checked = true; }
            closeTerms();
        });
    </script>




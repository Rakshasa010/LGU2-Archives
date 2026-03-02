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
                    <a href="#" class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/10 hover:border-red-300 dark:hover:border-red-700 transition-all duration-200 font-semibold">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span class="text-sm">Microsoft</span>
                    </a>
                    <a href="#" class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/10 hover:border-red-300 dark:hover:border-red-700 transition-all duration-200 font-semibold">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        <span class="text-sm">Google</span>
                    </a>
                </div>
                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-slate-700 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Don't have an account? <a href="registering.php" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-bold hover:underline">Create Account</a></p>
                </div>

            </form>
        <?php else: ?>
            <div class="mb-6">
                <div class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verify OTP</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Enter the 6-digit code sent to your email</div>
                <div class="text-xs text-amber-600 dark:text-amber-400 mt-2">⏱️ Expires in <span id="timer" class="font-bold">60</span>s</div>
            </div>
            <form action="login.php" method="POST" class="space-y-5">
                <input type="hidden" name="verify_otp" value="1">
                <div>
                    <label for="otp" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">OTP Code</label>
                    <input type="text" id="otp" name="otp" minlength="6" maxlength="6" pattern="[0-9]{6}" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 text-center text-2xl font-bold tracking-widest transition-colors" placeholder="000000">
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-xl font-bold text-lg transition-all duration-200 shadow-lg hover:shadow-2xl">Verify Code</button>
                <a href="login.php" class="block text-center text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-semibold">← Start Over</a>
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
                UI_ENH.toast('Single Sign-On coming soon.', {background:'linear-gradient(90deg,#fbbf24,#f59e0b)'});
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




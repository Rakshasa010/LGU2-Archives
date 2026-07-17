<?php
session_start();

// Check if OTP session is valid
if (!isset($_SESSION['otp_pending']) || $_SESSION['otp_pending'] !== true || 
    !isset($_SESSION['otp_code']) || !isset($_SESSION['otp_expires']) || 
    !isset($_SESSION['otp_user_id'])) {
    // Clear any partial OTP session data
    unset($_SESSION['otp_pending'], $_SESSION['otp_code'], $_SESSION['otp_expires'], 
          $_SESSION['otp_user_id'], $_SESSION['otp_must_change'], $_SESSION['otp_dark_mode']);
    header("Location: login.php");
    exit();
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    include 'authdatabase.php';
    $code = trim($_POST['otp'] ?? '');
    
    if (time() > (int)$_SESSION['otp_expires']) {
        $error = "OTP expired. Please sign in again.";
        // Clear OTP session
        unset($_SESSION['otp_pending'], $_SESSION['otp_code'], $_SESSION['otp_expires'], 
              $_SESSION['otp_user_id'], $_SESSION['otp_must_change'], $_SESSION['otp_dark_mode']);
        
        // Redirect to login after 2 seconds
        echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>";
    } elseif ($code !== (string)$_SESSION['otp_code']) {
        $error = "Invalid OTP code.";
    } else {
        $uid = (int)$_SESSION['otp_user_id'];
        $_SESSION['user_id'] = $uid;
        $_SESSION['last_activity'] = time();
        
        $conn->query("UPDATE users SET last_activity = NOW(), failed_attempts = 0, lockout_until = NULL WHERE id = " . $uid);
        $must = (int)($_SESSION['otp_must_change'] ?? 0);
        
        // Create notifications table if not exists and log successful login
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
        if ($nt) {
            $ntime = date('h:i A');
            $ndate = date('Y-m-d');
            $label = "User ID #" . $uid;
            
            if ($userStmt = $conn->prepare("SELECT full_name, username FROM users WHERE id = ?")) {
                $userStmt->bind_param("i", $uid);
                $userStmt->execute();
                if ($userRes = $userStmt->get_result()) {
                    if ($urow = $userRes->fetch_assoc()) {
                        $parts = [];
                        if (!empty($urow['full_name'])) $parts[] = $urow['full_name'];
                        if (!empty($urow['username'])) $parts[] = '@' . $urow['username'];
                        if (!empty($parts)) $label = implode(' ', $parts);
                    }
                }
                $userStmt->close();
            }
            
            $ncontent = "User login verified via OTP: " . $label;
            $nabout = "Login";
            $nstatus = "unread";
            $nt->bind_param("sssss", $ntime, $ndate, $ncontent, $nabout, $nstatus);
            $nt->execute();
            $nt->close();
        }
        
        $_SESSION['otp_pending'] = false;
        $_SESSION['dark_mode'] = (int)($_SESSION['otp_dark_mode'] ?? 0);
        
        $themeScript = $_SESSION['dark_mode'] === 1 ? 
            "localStorage.setItem('theme', 'dark'); localStorage.setItem('archive-theme', 'dark');" : 
            "localStorage.setItem('theme', 'light'); localStorage.setItem('archive-theme', 'light');";
        
        // Clear OTP session data
        unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_user_id'], 
              $_SESSION['otp_dark_mode'], $_SESSION['otp_must_change']);
        
        if ($must === 1) {
            echo "<script>$themeScript window.location.href = 'profile.php?force=1';</script>";
        } else {
            echo "<script>$themeScript window.location.href = 'archives-landing.php';</script>";
        }
        exit();
    }
}

// Handle start over
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_over'])) {
    // Clear OTP session
    unset($_SESSION['otp_pending'], $_SESSION['otp_code'], $_SESSION['otp_expires'], 
          $_SESSION['otp_user_id'], $_SESSION['otp_must_change'], $_SESSION['otp_dark_mode']);
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Archives</title>
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
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['otp_email_status'])): ?>
            <?php if ($_SESSION['otp_email_status'] === 'sent'): ?>
                <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    ✓ OTP has been sent to your email address
                </div>
            <?php elseif ($_SESSION['otp_email_status'] === 'failed'): ?>
                <div class="mb-4 p-3 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg">
                    ⚠️ Unable to send OTP via email.
                    <?php if (isset($_SESSION['email_error'])): ?>
                        <br><small>Error: <?php echo htmlspecialchars($_SESSION['email_error']); ?></small>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['otp_fallback'])): ?>
                        <br><strong>Your OTP code is: <span class="font-mono text-lg bg-yellow-200 px-2 py-1 rounded"><?php echo htmlspecialchars($_SESSION['otp_fallback']); ?></span></strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php 
            // Clear the status after showing it once
            unset($_SESSION['otp_email_status'], $_SESSION['otp_fallback'], $_SESSION['email_error']); 
            ?>
        <?php endif; ?>

        <div class="mb-6">
            <div class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verify OTP</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Enter the 6-digit code sent to your email</div>
            <div class="text-xs text-amber-600 dark:text-amber-400 mt-2">⏱️ Expires in <span id="timer" class="font-bold">--</span>s</div>
        </div>

        <form action="verify-otp.php" method="POST" class="space-y-5">
            <input type="hidden" name="verify_otp" value="1">
            <div>
                <label for="otp-1" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">OTP Code</label>
                <div class="flex items-center justify-between gap-2 sm:gap-3">
                    <input type="text" id="otp-1" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 1" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="OTP digit 2" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="OTP digit 3" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="OTP digit 4" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="OTP digit 5" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="OTP digit 6" class="otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors" placeholder="0">
                </div>
                <input type="hidden" id="otp" name="otp" minlength="6" maxlength="6" pattern="[0-9]{6}" required>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tip: paste the full code, it will fill automatically.</div>
            </div>
            
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-xl font-bold text-lg transition-all duration-200 shadow-lg hover:shadow-2xl">
                Verify Code
            </button>
        </form>
        
        <form action="verify-otp.php" method="POST" class="mt-4">
            <input type="hidden" name="start_over" value="1">
            <button type="submit" class="block w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-semibold py-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
                ← Start Over
            </button>
        </form>
        
        <!-- Debug link (remove in production) -->
        <div class="mt-2 text-center">
            <a href="test_email.php" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                Test Email Configuration
            </a>
        </div>
    </div>

    <script>
        // Timer functionality
        (function(){
            var end = <?php echo isset($_SESSION['otp_expires']) ? (int)$_SESSION['otp_expires'] : 'Math.floor(Date.now()/1000) + 60'; ?>;
            function tick(){
                var now = Math.floor(Date.now()/1000);
                var remain = Math.max(0, end - now);
                var el = document.getElementById('timer');
                if (el) el.textContent = String(remain);
                if (remain > 0) {
                    setTimeout(tick, 1000);
                } else {
                    // Auto redirect when expired
                    setTimeout(function(){
                        window.location.href = 'login.php';
                    }, 2000);
                }
            }
            tick();
        })();

        // OTP input handling
        (function(){
            var digits = Array.prototype.slice.call(document.querySelectorAll('.otp-digit'));
            var hidden = document.getElementById('otp');
            if (!digits.length || !hidden) return;

            function syncHidden() {
                hidden.value = digits.map(function (d) { return d.value.replace(/[^0-9]/g, ''); }).join('');
            }

            digits.forEach(function (input, idx) {
                input.addEventListener('input', function () {
                    var val = input.value.replace(/[^0-9]/g, '');
                    input.value = val.slice(0, 1);
                    if (val.length > 1) {
                        var chars = val.split('');
                        for (var i = 0; i < chars.length && (idx + i) < digits.length; i++) {
                            digits[idx + i].value = chars[i];
                        }
                        var nextIdx = Math.min(idx + chars.length, digits.length - 1);
                        digits[nextIdx].focus();
                    } else if (val && digits[idx + 1]) {
                        digits[idx + 1].focus();
                    }
                    syncHidden();
                });
                
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !input.value && digits[idx - 1]) {
                        digits[idx - 1].focus();
                    }
                });
                
                input.addEventListener('paste', function (e) {
                    var text = (e.clipboardData || window.clipboardData).getData('text');
                    if (!text) return;
                    var cleaned = text.replace(/[^0-9]/g, '').slice(0, digits.length);
                    if (!cleaned) return;
                    e.preventDefault();
                    cleaned.split('').forEach(function (ch, i) {
                        if (digits[i]) digits[i].value = ch;
                    });
                    digits[Math.min(cleaned.length, digits.length) - 1].focus();
                    syncHidden();
                });
            });
            
            syncHidden();
            
            // Focus first input on load
            if (digits[0]) {
                digits[0].focus();
            }
        })();
    </script>
</body>
</html>
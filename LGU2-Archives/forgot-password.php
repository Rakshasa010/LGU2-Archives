<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PLV Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
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
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $from = 'no-reply@' . $host;
            $subject = 'PLV Archives password reset code';
            $message = "Hello {$fullName},\n\nYour password reset code is: {$code}\nThis code expires in 10 minutes.\n\nIf you did not request this, ignore this email.";
            $headers = "From: PLV Archives <{$from}>\r\n";
            $headers .= "Reply-To: {$from}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            @mail($email, $subject, $message, $headers);
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">If an account exists, a reset code has been sent to the registered email.</div>';
            echo '<form action="forgot-password.php" method="POST" class="space-y-4">';
            echo '<input type="hidden" name="step" value="verify">';
            echo '<div><label class="block text-sm font-medium mb-2">Email</label><input type="email" name="email" required class="w-full px-4 py-3 border rounded-lg"></div>';
            echo '<div><label class="block text-sm font-medium mb-2">Reset Code</label><input type="text" name="code" required class="w-full px-4 py-3 border rounded-lg" placeholder="6-digit code"></div>';
            echo '<div><label class="block text-sm font-medium mb-2">New Password</label><input type="password" name="new_password" required class="w-full px-4 py-3 border rounded-lg"></div>';
            echo '<button type="submit" class="w-full bg-red-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Update Password</button>';
            echo '<div class="mt-4 text-center"><a href="login.php" class="text-red-600 hover:text-red-500">Back to Login</a></div>';
            echo '</form></div>';
            $stmt->close();
            $conn->close();
            exit;
        } else {
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="text-center mb-6"><img src="Images/Val-logo/valenzuela logo.webp" class="h-16 w-auto mx-auto mb-4" alt=""><h1 class="text-2xl font-bold">Password Reset</h1></div>';
            echo '<div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">If an account exists, a reset code has been sent to the registered email.</div>';
            echo '<form action="forgot-password.php" method="POST" class="space-y-4">';
            echo '<input type="hidden" name="step" value="verify">';
            echo '<div><label class="block text-sm font-medium mb-2">Email</label><input type="email" name="email" required class="w-full px-4 py-3 border rounded-lg"></div>';
            echo '<div><label class="block text-sm font-medium mb-2">Reset Code</label><input type="text" name="code" required class="w-full px-4 py-3 border rounded-lg" placeholder="6-digit code"></div>';
            echo '<div><label class="block text-sm font-medium mb-2">New Password</label><input type="password" name="new_password" required class="w-full px-4 py-3 border rounded-lg"></div>';
            echo '<button type="submit" class="w-full bg-red-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Update Password</button>';
            echo '<div class="mt-4 text-center"><a href="login.php" class="text-red-600 hover:text-red-500">Back to Login</a></div>';
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
            echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">Invalid email.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500">Try Again</a></div></div>';
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
            echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">No valid reset code. Request a new one.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500">Request Code</a></div></div>';
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
            echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">Too many attempts. Request a new code.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500">Request Code</a></div></div>';
            $sel->close();
            $conn->close();
            exit;
        }
        if (time() > $expiresAt || !password_verify($code, $codeHash)) {
            $upd = $conn->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?");
            $upd->bind_param("i", $resetId);
            $upd->execute();
            echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
            echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">Invalid or expired code.</div>';
            echo '<div class="text-center"><a href="forgot-password.php" class="text-red-600 hover:text-red-500">Try Again</a></div></div>';
            $sel->close();
            $conn->close();
            exit;
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $newHash, $userId);
        $upd->execute();
        $del = $conn->prepare("DELETE FROM password_reset_codes WHERE user_id = ?");
        $del->bind_param("i", $userId);
        $del->execute();
        echo '<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">';
        echo '<div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">Password updated successfully.</div>';
        echo '<div class="text-center"><a href="login.php" class="text-red-600 hover:text-red-500">Back to Login</a></div></div>';
        $sel->close();
        $conn->close();
        exit;
    }
}
?>
<div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
    <div class="text-center mb-8">
        <img src="Images/Val-logo/valenzuela logo.webp" alt="" class="h-16 w-auto mx-auto mb-4">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Reset Password</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Enter your email or username to receive a reset code</p>
    </div>
    <form action="forgot-password.php" method="POST" class="space-y-6">
        <input type="hidden" name="step" value="request">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email or Username</label>
            <input type="text" name="identifier" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Send Reset Code</button>
    </form>
    <div class="mt-6 text-center">
        <a href="login.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Back to Login</a>
    </div>
</div>
<script>
</script>
</body>
</html>

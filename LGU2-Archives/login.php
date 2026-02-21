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
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        include 'authdatabase.php';

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Check for lockout
        $stmt = $conn->prepare("SELECT id, password, must_change_password, status, role, failed_attempts, lockout_until FROM users WHERE username = ?");
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
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['last_activity'] = time();
                        if (isset($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
                            header("Location: profile.php?force=1");
                        } else {
                            header("Location: archives-landing.php");
                        }
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
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8 relative">
        <div class="text-center mb-8">
            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Archives</h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                <input type="text" id="username" name="username" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>

    <div class="mb-4">
  <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
    Password
  </label>

  <div class="relative">
    <input
      type="password"
      id="password"
      name="password"
      required
      class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 rounded-lg
             focus:ring-2 focus:ring-red-500 focus:border-transparent
             bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100
             placeholder-gray-500 dark:placeholder-gray-400 transition-colors"
    />

    <button
      type="button"
      id="togglePassword"
      class="absolute right-0 top-0 h-full flex items-center px-3
             text-gray-500 dark:text-gray-400
             hover:text-gray-700 dark:hover:text-gray-200
             focus:outline-none"
      aria-label="Show password"
    >
      <!-- Eye Open -->
      <svg
        id="eyeOpen"
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M2.458 12C3.732 7.943 7.523 5 12 5
             c4.478 0 8.268 2.943 9.542 7
             -1.274 4.057-5.064 7-9.542 7
             -4.477 0-8.268-2.943-9.542-7z" />
      </svg>

      <!-- Eye Closed -->
      <svg
        id="eyeClosed"
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 hidden"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M3 3l18 18" />
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M10.585 10.585A2 2 0 0012 14a2 2 0 001.414-.586" />
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M6.223 6.223A9.956 9.956 0 0112 5
             c4.478 0 8.268 2.943 9.543 7
             a9.97 9.97 0 01-4.216 5.568" />
      </svg>
    </button>
  </div>
</div>



  <button
    type="submit"
    class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
  >
    Login
  </button>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-slate-600 text-red-600 focus:ring-red-500 dark:bg-slate-700">
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                </label>
                <a href="forgot-password.php" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Forgot password?</a>
            </div>

            
        </form>


        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Don't have an account? <a href="registering.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 font-medium">Register here</a></p>
        </div>


    <script src="assets/js/login.js"></script>
</body>
</html>

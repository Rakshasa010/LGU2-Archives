<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PLV Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/login-head.js"></script>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <?php
    session_start();
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        include 'authdatabase.php';

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $sql = "SELECT id, password FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: archives-landing.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
        $conn->close();
    }
    ?>
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
        <div class="text-center mb-8">
            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">PLV Archives</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Sign in to your account</p>
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
    class="w-full rounded-md bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700"
  >
    Login
  </button>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-slate-600 text-red-600 focus:ring-red-500 dark:bg-slate-700">
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                </label>
                <a href="#" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Forgot password?</a>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Don't have an account? <a href="registering.php" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 font-medium">Register here</a></p>
        </div>
    </div>


    <script src="assets/js/login.js"></script>
</body>
</html>

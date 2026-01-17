<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PLV Archives</title>
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

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">
            </div>

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

    <script>
        // Theme toggle if needed, but for login page, maybe not necessary
    </script>
</body>
</html>
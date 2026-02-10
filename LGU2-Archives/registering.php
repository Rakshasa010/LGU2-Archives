<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PLV Archives</title>
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
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
        <div class="text-center mb-8">
            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">PLV Archives</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Create your account</p>
        </div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            include 'authdatabase.php';

            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $plainPassword = bin2hex(random_bytes(8));
            $password = password_hash($plainPassword, PASSWORD_DEFAULT);

            // Check if username or email already exists
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">Username or email already exists.</div>';
            } else {
                // Insert new user
                $insert_sql = "INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssss", $username, $password, $email, $full_name);

                if ($stmt->execute()) {
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $from = 'no-reply@' . $host;
                    $subject = 'Your PLV Archives account credentials';
                    $message = "Hello {$full_name},\n\nYour account has been created.\n\nUsername: {$username}\nTemporary Password: {$plainPassword}\n\nSign in: http://{$host}/LGU2-Archives/LGU2-Archives/login.php\n\nPlease change your password after signing in.";
                    $headers = "From: PLV Archives <{$from}>\r\n";
                    $headers .= "Reply-To: {$from}\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $sent = @mail($email, $subject, $message, $headers);
                    if ($sent) {
                        echo '<div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">Registration successful! We sent your temporary password to your email. <a href="login.php" class="underline">Login here</a></div>';
                    } else {
                        echo '<div class="mb-4 p-3 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">Registration successful, but email could not be sent. Your temporary password is: <strong>' . htmlspecialchars($plainPassword) . '</strong>. <a href="login.php" class="underline">Login here</a></div>';
                    }
                } else {
                    echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">Registration failed. Please try again.</div>';
                }
            }

            $stmt->close();
            $conn->close();
        }
        ?>

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

            <p class="text-sm text-gray-600 dark:text-gray-400">A secure temporary password will be generated and sent to your email after registration.</p>

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

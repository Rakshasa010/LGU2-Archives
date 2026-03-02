<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Archives</title>
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
        body { background-size: cover; background-position: center; background-repeat: no-repeat; }
        [x-cloak] { display: none !important; }
        
        @keyframes drift{from{transform:translate3d(0,0,0)}to{transform:translate3d(2%,-2%,0)}}
        @keyframes hue{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(20deg)}}
        @keyframes move1{from{transform:translate(0,0) scale(1)}to{transform:translate(6vmax,4vmax) scale(1.1)}}
        @keyframes move2{from{transform:translate(0,0) scale(1)}to{transform:translate(-5vmax,-3vmax) scale(1.08)}}
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <?php
    session_start();
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    ?>
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-800/90 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">
        <div class="text-center mb-8">
            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Archives</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Are you sure you want to logout?</p>
        </div>

        <form action="logout.php" method="POST" class="space-y-6">
            <div class="flex space-x-4">
                <button type="submit" name="confirm" value="yes" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Yes, Logout
                </button>
                <a href="archives-landing.php" class="flex-1 bg-gray-300 dark:bg-slate-600 text-gray-700 dark:text-gray-300 py-3 px-4 rounded-lg font-semibold hover:bg-gray-400 dark:hover:bg-slate-500 transition-all shadow-md hover:shadow-lg text-center leading-3">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>

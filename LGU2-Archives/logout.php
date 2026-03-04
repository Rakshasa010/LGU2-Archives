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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-red-50 to-orange-50 dark:from-slate-900 dark:to-slate-800">
    <?php
    session_start();
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    ?>
    <div class="w-full max-w-md bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/50 dark:border-slate-700/50 p-8 fade-in">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-red-500 to-orange-500 rounded-full mb-6 shadow-lg">
                <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="w-16 h-16 object-contain rounded-full">
            </div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Logout Confirmation</h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Are you sure you want to logout from your account?</p>
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-700 dark:text-red-300">
                    <i class="bi bi-info-circle mr-2"></i>
                    You will be redirected to the login page and your session will end.
                </p>
            </div>
        </div>

        <form action="logout.php" method="POST" class="space-y-6">
            <div class="flex space-x-4">
                <button type="submit" name="confirm" value="yes" class="flex-1 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transform hover:scale-105">
                    <i class="bi bi-check-circle mr-2"></i>Yes, Logout
                </button>
                <a href="archives-landing.php" class="flex-1 bg-gray-300 dark:bg-slate-600 text-gray-700 dark:text-gray-300 py-3 px-4 rounded-lg font-semibold hover:bg-gray-400 dark:hover:bg-slate-500 transition-all shadow-md hover:shadow-lg text-center leading-3 flex items-center justify-center transform hover:scale-105">
                    <i class="bi bi-x-circle mr-2"></i>Cancel
                </a>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                City of Valenzuela - Legislative Archives Management System
            </p>
        </div>
    </div>
    
    <script>
        // Add fade-in animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.fade-in');
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            }
        });
    </script>
</body>
</html>

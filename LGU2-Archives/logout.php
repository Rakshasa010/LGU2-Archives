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
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
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
                <button type="submit" name="confirm" value="yes" class="flex-1 bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
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

<?php
session_start();

$provider = $_GET['provider'] ?? '';
$allowed_providers = ['google', 'microsoft'];

if (!in_array($provider, $allowed_providers)) {
    die("Invalid provider specified.");
}

$providerName = ucfirst($provider);
$redirectTime = 2; // seconds

// MOCK: Auto-login a demo user if database exists, otherwise just redirect back to archives-landing.php
// This is a placeholder for actual OAuth2 flow
$mock_user_id = 1; // Assuming admin user is ID 1
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $providerName; ?> Single Sign-On</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .loader {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: inline-block;
            position: relative;
            background: linear-gradient(0deg, rgba(255, 61, 0, 0.2) 33%, #ff3d00 100%);
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        .loader::after {
            content: '';  
            box-sizing: border-box;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
        }
        @keyframes rotation {
            0% { transform: rotate(0deg) }
            100% { transform: rotate(360deg) }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center relative overflow-hidden">
        <!-- Connecting Line Animation -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gray-100">
            <div class="h-full bg-blue-500 w-1/3 animate-[pulse_2s_ease-in-out_infinite]" style="animation: slide 1.5s ease-in-out infinite alternate;"></div>
        </div>

        <div class="mb-8 mt-4 flex items-center justify-center gap-6">
            <div class="w-16 h-16 bg-white rounded-full shadow-md flex items-center justify-center border border-gray-100">
                <img src="Images/Val-logo/valenzuela logo.webp" alt="LGU" class="w-10 h-10 object-contain">
            </div>
            <div class="flex space-x-2">
                <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
            <div class="w-16 h-16 bg-white rounded-full shadow-md flex items-center justify-center border border-gray-100">
                <?php if ($provider === 'google'): ?>
                <svg class="w-8 h-8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                <?php else: ?>
                <svg class="w-8 h-8" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                    <rect x="12" y="1" width="10" height="10" fill="#7FBA00"/>
                    <rect x="1" y="12" width="10" height="10" fill="#00A4EF"/>
                    <rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
                </svg>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mb-2">Connecting to <?php echo $providerName; ?></h2>
        <p class="text-sm text-gray-500 mb-8">Please wait while we securely authenticate your account...</p>

        <div class="flex justify-center mb-6">
            <span class="loader"></span>
        </div>

        <p class="text-xs text-gray-400">Taking too long? <a href="login.php" class="text-blue-500 hover:underline">Cancel and go back</a>.</p>
    </div>

    <script>
        // Simulate SSO Auth Processing Delay
        setTimeout(() => {
            // Usually this happens server-side, so we will submit a form or fetch
            // For now, we simulate a successful login by making a POST to a hypothetical handler
            // or we just inject mock session via PHP and redirect
            handleSSOSuccess();
        }, <?php echo $redirectTime * 1000; ?>);

        function handleSSOSuccess() {
            // A realistic approach for mock: Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'sso.php?provider=<?php echo htmlspecialchars($provider); ?>';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'sso_completed';
            input.value = '1';
            form.appendChild(input);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    <style>
        @keyframes slide {
            0% { transform: translateX(0); }
            100% { transform: translateX(200%); }
        }
    </style>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sso_completed'])) {
        // Authenticate as a top-level user for demonstration
        // Note: Real implementation uses valid OAuth tokens
        include 'authdatabase.php';
        
        // Find the first active user to simulate login, or user id 1
        $result = $conn->query("SELECT id, dark_mode FROM users WHERE status = 'active' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['dark_mode'] = (int)$user['dark_mode'];
        } else {
            $_SESSION['user_id'] = $mock_user_id; // fallback
            $_SESSION['dark_mode'] = 0;
        }
        
        $_SESSION['last_activity'] = time();

        // Redirect to archives-landing
        $themeScript = ($_SESSION['dark_mode'] ?? 0) === 1 ? "localStorage.setItem('theme', 'dark'); localStorage.setItem('archive-theme', 'dark');" : "localStorage.setItem('theme', 'light'); localStorage.setItem('archive-theme', 'light');";
        echo "<script>$themeScript window.location.href = 'archives-landing.php';</script>";
        exit;
    }
    ?>
</body>
</html>

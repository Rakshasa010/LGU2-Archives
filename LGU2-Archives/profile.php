<?php
// profile.php - renamed from user_management.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'authdatabase.php';

$user_id = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch current user data initially (include must_change_password for reset prompt)
$sql = "SELECT username, email, full_name, profile_picture, must_change_password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$user['must_change_password'] = (int)($user['must_change_password'] ?? 0);

// Handle reset password form (current password + new password)
$reset_msg = '';
$reset_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $new_confirm = $_POST['new_password_confirm'] ?? '';
    if ($current === '' || $new_pass === '' || $new_confirm === '') {
        $reset_msg = 'Please fill in all password fields.';
    } elseif (strlen($new_pass) < 8) {
        $reset_msg = 'New password must be at least 8 characters.';
    } elseif ($new_pass !== $new_confirm) {
        $reset_msg = 'New password and confirmation do not match.';
    } else {
        $check = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows === 1 && password_verify($current, $res->fetch_assoc()['password'])) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $upd->bind_param("si", $hash, $user_id);
            if ($upd->execute()) {
                $reset_success = true;
                $user['must_change_password'] = 0;
                $reset_msg = 'Password updated successfully. You can now use your new password to sign in.';
            } else {
                $reset_msg = 'Failed to update password. Please try again.';
            }
        } else {
            $reset_msg = 'Current password is incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LAS</title>
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
    <script src="assets/js/theme-head.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        /* Hide scrollbar but maintain scroll functionality */
        .hide-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="h-screen overflow-hidden flex flex-col dark:bg-slate-900 text-slate-900 dark:text-white">
    <!-- Main Container -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-slate-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                    <a href="archives-landing.php" class="hidden sm:inline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">← Dashboard</a>
                </div>
                <div class="flex items-center space-x-3">
                    <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Notifications">
                        <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                    </button>
                    <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                        <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto hide-scrollbar">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="space-y-6">
            <!-- Profile Header -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center space-x-4">
                    <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="w-16 h-16 rounded-full object-cover border-2 border-gray-200 dark:border-slate-600">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-gradient-to-r from-red-600 to-orange-500 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                        <p class="text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Rest of profile content goes here (same as previous user_management.php) -->

        </div>
        </div>
    </div>
    </div>
    </div>

    <script src="assets/js/archives-landing.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

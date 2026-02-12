<?php
// user_management.php placeholder page
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-white min-h-screen flex items-center justify-center">
    <div class="max-w-xl w-full p-8 bg-white dark:bg-slate-800 rounded-lg shadow">
        <h1 class="text-2xl font-semibold mb-4">User Management</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300">This is a placeholder page. The full profile page is available at <a href="profile.php" class="text-red-600">profile.php</a>.</p>
    </div>
</body>
</html>

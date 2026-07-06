<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'authdatabase.php';
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role, full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc();
$stmt->close();
$display_name = $me['full_name'] ?? 'User';
$profile_picture = $me['profile_picture'] ?? null;
$is_admin = isset($me['role']) && strtolower($me['role']) === 'admin';
if (!$is_admin) {
    header("Location: archives-landing.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($target > 0 && in_array($action, ['approve','reject','delete'], true)) {
        if ($action === 'delete') {
            // Delete user
            $del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del->bind_param("i", $target);
            if ($del->execute()) {
                $message = 'User deleted.';
            } else {
                $message = 'Failed to delete user.';
            }
            $del->close();
        } else {
            $info = null;
            $gi = $conn->prepare("SELECT full_name, username, email FROM users WHERE id = ?");
        if ($gi) { $gi->bind_param("i", $target); $gi->execute(); $r = $gi->get_result(); if ($r) { $info = $r->fetch_assoc(); } $gi->close(); }
        $newStatus = $action === 'approve' ? 'active' : 'rejected';
        $up = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $up->bind_param("si", $newStatus, $target);
        if ($up->execute()) {
            $message = $action === 'approve' ? 'User approved.' : 'User rejected.';
            // Ensure notifications table exists
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                time VARCHAR(20) NOT NULL,
                date DATE NOT NULL,
                content VARCHAR(255) NOT NULL,
                about VARCHAR(100) NOT NULL,
                status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            // Add notification entry
            $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
            if ($nt) {
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = ($action === 'approve' ? 'User approved: ' : 'User rejected: ') . ($info['full_name'] ?? 'User') . ' (' . ($info['username'] ?? '') . ')';
                $nabout = 'User Management';
                $nstatus = 'unread';
                $nt->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $nt->execute();
                $nt->close();
            }
            // Send email to user on approval only
            if ($action === 'approve' && !empty($info['email'])) {
                $cfgFile = __DIR__ . '/mail_config.php';
                if (file_exists($cfgFile)) {
                    $cfg = require $cfgFile;
                    $smtpUser = trim((string)($cfg['username'] ?? ''));
                    $smtpPass = trim((string)($cfg['password'] ?? ''));
                    $isPlaceholder = (stripos($smtpUser, 'YOUR_GMAIL') !== false) || (stripos($smtpPass, 'YOUR_16_CHAR') !== false);
                    if ($smtpUser !== '' && $smtpPass !== '' && !$isPlaceholder) {
                        try {
                            require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
                            require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
                            require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
                            $mailer3 = new PHPMailer\PHPMailer\PHPMailer(true);
                            $smtpHost = $cfg['host'] ?? 'smtp.gmail.com';
                            $smtpPort = (int)($cfg['port'] ?? 587);
                            $enc = strtolower(trim($cfg['encryption'] ?? 'tls'));
                            $fromEmail = $cfg['from_email'] ?? $smtpUser;
                            $fromName = $cfg['from_name'] ?? 'Archives';
                            $mailer3->isSMTP();
                            $mailer3->Host = $smtpHost;
                            $mailer3->SMTPAuth = true;
                            $mailer3->Username = $smtpUser;
                            $mailer3->Password = $smtpPass;
                            $mailer3->SMTPSecure = ($enc === 'ssl')
                                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mailer3->Port = $smtpPort;
                            $mailer3->CharSet = 'UTF-8';
                            $mailer3->SMTPDebug = 0;
                            $mailer3->setFrom($fromEmail, $fromName);
                            $mailer3->addAddress($info['email'], $info['full_name'] ?? $info['username'] ?? '');
                            $mailer3->Subject = 'Your Archives account has been approved';
                            $mailer3->isHTML(true);
                            $mailer3->Body = '<p>Hello ' . htmlspecialchars($info['full_name'] ?? $info['username'] ?? 'User', ENT_QUOTES, 'UTF-8') . ',</p>'
                                . '<p>Your account has been <strong>approved</strong>. You can now sign in.</p>'
                                . '<p><a href="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : ''), ENT_QUOTES, 'UTF-8') . '/LGU2-Archives/login.php">Login</a></p>'
                                . '<p>Thank you.</p>';
                            $mailer3->AltBody = 'Your account has been approved. You can now sign in.';
                            $mailer3->send();
                        } catch (Throwable $e) {
                            // Ignore email failures silently
                        }
                    }
                }
            }
        } else {
            $message = 'Failed to update user.';
        }
        $up->close();
        }
    }
}

$pending = [];
if ($q = $conn->query("SELECT id, username, email, full_name, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC")) {
    while ($row = $q->fetch_assoc()) { $pending[] = $row; }
}

$all_users = [];
$qStr = "SELECT id, username, email, full_name, status, last_activity, role";
// Add extra columns if they exist
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'nickname'");
if ($colCheck && $colCheck->num_rows > 0) {
    $qStr .= ", nickname, birthplace, birthdate, address";
}
$qStr .= " FROM users ORDER BY full_name ASC";
if ($q = $conn->query($qStr)) {
    while ($row = $q->fetch_assoc()) { $all_users[] = $row; }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/archives-landing-head.js"></script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="Images/Val-logo/valenzuela logo.webp">
        
</head>
<body class="min-h-screen bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <?php
    $sidebar_active_page = 'user-management';
    $sidebar_include_overlay = true;
    require_once 'includes/sidebar-centralized.php';
    ?>

    <div class="flex min-h-screen">
        <div class="flex-1 min-h-0">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side: Toggle buttons and Logo -->
                        <div class="flex items-center">
                            <!-- Mobile Menu Button -->
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">User Management</h2>
                            </div>
                        </div>
                        
                        <!-- Right Side Actions -->
                        <div class="flex items-center space-x-1 md:space-x-4">
                            <!-- Dark Mode Toggle (Centralized) -->
                            <button data-theme-toggle class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle dark mode">
                                <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </button>
                        
                            <!-- Notification Dropdown (placed beside theme toggle) -->
                            <div class="relative">
                                <button id="notification-btn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors relative" title="Notifications">
                                    <i class="bi bi-bell-fill text-xl text-gray-700 dark:text-gray-300"></i>
                                    <span id="notif-count" class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-red-600 bg-red-100 rounded-full">3</span>
                                </button>

                                <div id="notification-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                    <div class="p-4">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Notifications</div>
                                        <div id="notif-list" class="space-y-2">
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Loading notifications...</div>
                                        </div>
                                    </div>

                                    <div class="px-4 py-2 border-t border-gray-200 dark:border-slate-700">
                                        <a href="audit-logs.php" class="block text-center text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            View All Notifications
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- User Profile Dropdown (moved right of notification) -->
                             <div class="relative">
                                <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                <?php if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php elseif ($profile_picture && file_exists($profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php else: ?>
                                    <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden sm:block text-left">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($display_name); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $is_admin ? 'Administrator' : 'User'; ?></p>
                                </div>
                                <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                            </button>
                            
                                <!-- Profile Dropdown -->
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <i class="bi bi-gear mr-2"></i>Account Settings
                                        </a>
                                        <button type="button" id="open-logout-modal" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer w-full text-left">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Container -->
            <?php if ($message !== ''): ?>
            <div class="mb-4 p-3 rounded-lg border <?php echo strpos($message, 'Failed') === false ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Database Backup & Restore Panel -->
        <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-xl shadow-lg border border-red-700 p-6 mb-8 flex flex-col md:flex-row items-center justify-between text-white">
            <div class="flex items-center space-x-4 mb-4 md:mb-0">
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="bi bi-database text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">System Database Management</h3>
                    <p class="text-red-100 text-sm">Download or restore a complete .sql snapshot of the system structure and data.</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row w-full sm:w-auto gap-3">
                <a id="downloadBackupBtn" href="backup_database.php" target="_blank" class="w-full sm:w-auto px-5 py-2.5 bg-white text-red-900 font-semibold rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center shadow-md">
                    <i class="bi bi-download mr-2"></i> Download Backup
                </a>
                <button onclick="openRestoreModal()" class="w-full sm:w-auto px-5 py-2.5 bg-red-700 text-white font-semibold rounded-lg hover:bg-red-600 transition-colors flex items-center justify-center shadow-md border border-white/20">
                    <i class="bi bi-upload mr-2"></i> Restore Database
                </button>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-slate-700">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div class="font-semibold">Pending Approvals</div>
                <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo count($pending); ?> pending</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if (empty($pending)): ?>
                            <tr>
                                <td colspan="5" class="px-4 sm:px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">No pending users</td>
                            </tr>
                        <?php else: foreach ($pending as $u): ?>
                            <tr>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700">Approve</button>
                                    </form>
                                    <form method="post" class="inline-block ml-2">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Active/Inactive Users List -->
        <div class="mt-8 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-slate-700">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div class="font-semibold text-gray-800 dark:text-gray-200">User List</div>
                <div class="flex flex-col sm:flex-row w-full sm:w-auto items-stretch sm:items-center gap-2 sm:gap-4">
                    <span class="text-sm text-gray-600 dark:text-gray-400" id="userCount"><?php echo count($all_users); ?> users</span>
                    <select id="roleFilter" class="w-full sm:w-auto text-sm border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-gray-200 p-2">
                        <option value="all">All Roles</option>
                        <option value="admin">Admins</option>
                        <option value="user">Users</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full" id="userTable">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Name</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Email</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Active</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if (empty($all_users)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">No users found</td>
                            </tr>
                        <?php else: foreach ($all_users as $u): ?>
                            <tr class="user-row hover:bg-gray-50 dark:hover:bg-slate-700/50 cursor-pointer" data-role="<?php echo isset($u['role']) ? strtolower($u['role']) : 'user'; ?>" onclick="toggleUserDetails(<?php echo (int)$u['id']; ?>)">
                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <div class="flex items-center gap-3">
                                        <i id="icon-<?php echo (int)$u['id']; ?>" class="bi bi-chevron-right text-gray-400 text-sm transition-transform duration-200"></i>
                                        <?php
                                            $name = trim($u['full_name'] ?? '');
                                            $initials = '';
                                            if ($name !== '') {
                                                $parts = preg_split('/\s+/', $name);
                                                $initials = strtoupper(substr($parts[0] ?? '',0,1) . substr($parts[count($parts)-1] ?? '',0,1));
                                            } else {
                                                $initials = strtoupper(substr($u['username'] ?? 'U',0,1));
                                            }
                                        ?>
                                        <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300 text-xs font-bold">
                                            <?php echo htmlspecialchars($initials); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate"><?php echo htmlspecialchars($u['role'] ?? 'user'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm">
                                    <?php if (!empty($u['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo htmlspecialchars($u['email']); ?></a>
                                    <?php else: ?>
                                        <span class="text-gray-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $u['status']==='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-700 dark:text-gray-300">
                                    <?php 
                                    if (!empty($u['last_activity'])) {
                                        $last_activity = strtotime($u['last_activity']);
                                        $diff = time() - $last_activity;
                                        
                                        // If active within last 5 minutes (300 seconds), show "Active Now"
                                        if ($diff < 300) {
                                            echo '<span class="flex items-center text-green-600 dark:text-green-400 font-medium"><span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>Active Now</span>';
                                        } else {
                                            // Show time elapsed since last activity
                                            if ($diff < 3600) echo floor($diff/60) . ' mins ago';
                                            elseif ($diff < 86400) echo floor($diff/3600) . ' hours ago';
                                            else echo floor($diff/86400) . ' days ago';
                                        }
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php if ((int)$u['id'] !== $user_id): // Prevent deleting self ?>
                                    <form method="post" class="inline-block" onsubmit="event.stopPropagation(); return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:hover:text-red-400" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr id="details-<?php echo (int)$u['id']; ?>" class="hidden bg-gray-50 dark:bg-slate-800/80">
                                <td colspan="6" class="px-8 py-5 border-t border-gray-100 dark:border-slate-700">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Nickname</div>
                                            <div class="font-medium text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($u['nickname'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Birthdate</div>
                                            <div class="font-medium text-gray-800 dark:text-gray-200"><?php echo !empty($u['birthdate']) ? date('F j, Y', strtotime($u['birthdate'])) : 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Birthplace</div>
                                            <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($u['birthplace'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Address</div>
                                            <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($u['address'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        document.getElementById('roleFilter').addEventListener('change', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const role = row.getAttribute('data-role');
                // If filter is 'all', show everything.
                // If filter is 'admin', show only 'admin'.
                // If filter is 'user', show anything NOT 'admin' (assuming default is user).
                let show = false;
                if (filter === 'all') {
                    show = true;
                } else if (filter === 'admin') {
                    show = (role === 'admin');
                } else {
                    // filter is 'user'
                    show = (role !== 'admin');
                }

                if (show) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update the counter text
            document.getElementById('userCount').textContent = visibleCount + ' users';
        });

        function toggleUserDetails(id) {
            const detailsRow = document.getElementById('details-' + id);
            const icon = document.getElementById('icon-' + id);
            if (detailsRow.classList.contains('hidden')) {
                detailsRow.classList.remove('hidden');
                icon.classList.add('rotate-90');
            } else {
                detailsRow.classList.add('hidden');
                icon.classList.remove('rotate-90');
            }
        }

        // automatically click the backup button at most once per hour
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('downloadBackupBtn');
            if (!btn) return;
            const last = localStorage.getItem('lastBackupClick') || 0;
            const now = Date.now();
            if (now - last > 3600 * 1000) { // 1 hour
                btn.click();
                localStorage.setItem('lastBackupClick', now);
            }
        });
        </script>

    <script>
    (function(){
        // Initialize DataTable for user list (MVP)
        try{
            const userTable = $('#userTable').DataTable({
                pageLength: 25,
                autoWidth: false,
                columnDefs: [{ targets: -1, orderable: false }]
            });

            // Role filter integration: simple client-side filter
            document.getElementById('roleFilter')?.addEventListener('change', function(){
                const v = this.value;
                if (v === 'all') userTable.column(3).search('').draw();
                else if (v === 'admin') userTable.column(3).search('admin', true, false).draw();
                else userTable.column(3).search('^(?!.*admin).*$','regex',false).draw();
            });
        }catch(e){ console.warn('user table datatable init', e); }
    })();
    </script>

            <div class="mt-6">
                <a href="archives-landing.php" class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700">
                    <span class="mr-2">←</span> Back to Archives
                </a>
            </div>
        </div>
    </div>
</div>

    <!-- Restore Database Modal -->
    <div id="restoreModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRestoreModal()"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <i class="bi bi-database-up text-red-600"></i>
                        Restore Database
                    </h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeRestoreModal()">&times;</button>
                </div>
                
                <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg text-sm text-yellow-800 dark:text-yellow-300">
                    <i class="bi bi-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> This will replace your entire database. Ensure you have a backup first.
                </div>

                <form id="restoreForm" class="space-y-4">
                    <!-- Drag and Drop Area -->
                    <div 
                        id="dropZone" 
                        class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-8 text-center cursor-pointer hover:border-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-all"
                        ondrop="handleDrop(event)"
                        ondragover="handleDragOver(event)"
                        ondragleave="handleDragLeave(event)"
                        onclick="document.getElementById('fileInput').click()">
                        
                        <i class="bi bi-cloud-upload text-4xl text-gray-400 dark:text-gray-500 mb-3 block"></i>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Drag and drop your .sql file here</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">or click to browse</p>
                        
                        <input 
                            type="file" 
                            id="fileInput" 
                            name="backupFile" 
                            accept=".sql,.zip,.gz" 
                            class="hidden"
                            onchange="handleFileSelect(this.files)">
                    </div>

                    <!-- Selected File Info -->
                    <div id="fileInfo" class="hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-file-earmark-check text-green-600 dark:text-green-400 text-lg"></i>
                                <div>
                                    <p id="fileName" class="text-sm font-semibold text-gray-800 dark:text-gray-200"></p>
                                    <p id="fileSize" class="text-xs text-gray-600 dark:text-gray-400"></p>
                                </div>
                            </div>
                            <button type="button" onclick="clearFileInput()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <i class="bi bi-x-circle text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div id="progressContainer" class="hidden space-y-2">
                        <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span>Uploading and restoring...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                            <div id="progressBar" class="bg-red-600 h-full rounded-full transition-all duration-300" style="width: 0%;"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeRestoreModal()" class="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="restoreBtn" class="flex-1 px-4 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" disabled>
                            <i class="bi bi-lightning-charge"></i> Restore Now
                        </button>
                    </div>

                    <!-- Info Text -->
                    <p class="text-xs text-gray-600 dark:text-gray-400 text-center mt-4">
                        Supported formats: .sql, .zip, .gz
                    </p>
                </form>
            </div>
        </div>
    </div>
<script src="assets/js/archives.js"></script>
    <script src="assets/js/archives-landing.js"></script>

    <script>
    let selectedFile = null;

    function openRestoreModal() {
        document.getElementById('restoreModal').classList.remove('hidden');
        selectedFile = null;
        document.getElementById('fileInfo').classList.add('hidden');
        document.getElementById('progressContainer').classList.add('hidden');
        document.getElementById('restoreBtn').disabled = true;
    }

    function closeRestoreModal() {
        document.getElementById('restoreModal').classList.add('hidden');
        selectedFile = null;
        document.getElementById('fileInput').value = '';
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/10');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/10');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/10');
        
        const files = e.dataTransfer.files;
        handleFileSelect(files);
    }

    function handleFileSelect(files) {
        if (!files || files.length === 0) return;
        
        const file = files[0];
        const validExtensions = ['sql', 'zip', 'gz'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!validExtensions.includes(fileExtension)) {
            try { UI_ENH.toast('Invalid file type. Please upload a .sql, .zip, or .gz file.', {background:'linear-gradient(90deg,#f87171,#ef4444)'}); } catch(e) {}
            return;
        }
        
        selectedFile = file;
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
        document.getElementById('fileInfo').classList.remove('hidden');
        document.getElementById('restoreBtn').disabled = false;
    }

    function clearFileInput() {
        selectedFile = null;
        document.getElementById('fileInput').value = '';
        document.getElementById('fileInfo').classList.add('hidden');
        document.getElementById('restoreBtn').disabled = true;
    }

    document.getElementById('restoreForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!selectedFile) {
            try { UI_ENH.toast('Please select a file to restore.', {background:'#dc2626'}); } catch(e) {}
            return;
        }
        
        // Confirm before restoring
        if (!confirm('Are you absolutely sure? This will replace your entire database with the contents of the selected file.\n\nThis action CANNOT be undone. Make sure you have a backup first.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'restore');
        formData.append('backupFile', selectedFile);
        
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const restoreBtn = document.getElementById('restoreBtn');
        
        progressContainer.classList.remove('hidden');
        restoreBtn.disabled = true;
        
        try {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                    progressPercent.textContent = Math.round(percentComplete) + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            try { UI_ENH.toast('Database restored successfully — reloading…', {background:'#10b981'}); } catch(e) {}
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            try { UI_ENH.toast('Restoration failed: ' + (response.message || 'Unknown error'), {background:'#dc2626'}); } catch(e) {}
                            progressContainer.classList.add('hidden');
                            restoreBtn.disabled = false;
                        }
                    } catch (err) {
                        try { UI_ENH.toast('Invalid response from server.', {background:'#dc2626'}); } catch(e) {}
                        progressContainer.classList.add('hidden');
                        restoreBtn.disabled = false;
                    }
                } else {
                    try { UI_ENH.toast('Server error: ' + xhr.status, {background:'#dc2626'}); } catch(e) {}
                    progressContainer.classList.add('hidden');
                    restoreBtn.disabled = false;
                }
            });
            
            xhr.addEventListener('error', function() {
                try { UI_ENH.toast('Network error occurred.', {background:'#dc2626'}); } catch(e) {}
                progressContainer.classList.add('hidden');
                restoreBtn.disabled = false;
            });
            
            xhr.open('POST', 'archives_api.php');
            xhr.send(formData);
        } catch (error) {
            try { UI_ENH.toast('Error: ' + error.message, {background:'#dc2626'}); } catch(e) {}
            progressContainer.classList.add('hidden');
            restoreBtn.disabled = false;
        }
    });
    </script>

    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="hidden fixed inset-0 z-50">
        <div id="logout-modal-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
                <div class="text-center mb-6">
                    <div class="bg-red-100 dark:bg-red-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-box-arrow-right text-red-600 dark:text-red-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Logout Confirmation</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Are you sure you want to logout from your account?</p>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button id="logout-cancel" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                    <form action="logout.php" method="POST" class="inline">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Yes, Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            var logoutModal = document.getElementById('logout-modal');
            var openLogoutModalBtn = document.getElementById('open-logout-modal');
            var logoutCancelBtn = document.getElementById('logout-cancel');
            var logoutModalBackdrop = document.getElementById('logout-modal-backdrop');
            
            function openLogoutModal() {
                logoutModal && logoutModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            
            function closeLogoutModal() {
                logoutModal && logoutModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            
            openLogoutModalBtn?.addEventListener('click', openLogoutModal);
            logoutCancelBtn?.addEventListener('click', closeLogoutModal);
            logoutModalBackdrop?.addEventListener('click', closeLogoutModal);
            
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !logoutModal?.classList.contains('hidden') === false) {
                    closeLogoutModal();
                }
            });
        })();
    </script>
</body>
</html>

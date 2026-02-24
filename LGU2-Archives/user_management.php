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
if ($q = $conn->query("SELECT id, username, email, full_name, status, last_activity, role FROM users ORDER BY full_name ASC")) {
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
<body class="bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-white min-h-screen">
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">
       
    
    <!-- Mobile sidebar header -->
        <div class="p-4 border-b border-red-700/50 sidebar-header">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3 sidebar-logo">
                    <div class="bg-white rounded-full p-1.5 shadow-lg">
                        <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="w-9 h-9 object-contain">
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight">LAS</h1>
                        <p class="text-xs text-red-200">City of Valenzuela</p>
                    </div>
                </div>
                <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Navigation Menu -->
        <nav class="flex-1 py-4 px-3 overflow-y-auto">
            <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-speedometer2 mr-3 text-lg"></i>
                <span>Dashboard Archives</span>
            </a>
            
            <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-folder mr-3 text-lg"></i>
                <span>Main Storage Archives</span>
            </a>
            
            <?php if (isset($is_admin) && $is_admin): ?>
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>
            <?php endif; ?>

            <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-cloud-upload mr-3 text-lg"></i>
                <span>Export</span>
            </a>

           
            <!-- ANALYTICS Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
            
            <!-- ADMINISTRATION Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                <?php if ($is_admin): ?>
                <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
                <?php endif; ?>
                <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-shield-check mr-3 text-lg"></i>
                    <span>Audit Logs</span>
                </a>
            </div>
            
            <!-- Storage Bar -->
            <div class="mt-6 pt-4 border-t border-red-700/50 px-2">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-red-100">Storage Usage</span>
                        <span class="text-xs font-bold text-white">2%</span>
                    </div>
                    <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                        <div class="bg-white h-full rounded-full" style="width: 2%;"></div>
                    </div>
                    <div class="text-xs text-red-100">1.0 GB of 50.0 GB</div>
                </div>
            </div>
        </nav>
    </div>
    
    <div class="flex h-screen overflow-hidden">
        <!-- Desktop Sidebar -->
        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
            <!-- Logo Section -->
            <div class="p-6 border-b border-red-700 sidebar-logo">
                <a href="archives-landing.php" class="flex items-center space-x-3 hover:opacity-80 transition-all duration-300 transform hover:scale-105 group">
                    <div class="bg-white rounded-full shadow-md flex items-center justify-center overflow-hidden transform transition-all duration-300 group-hover:scale-110 group-hover:rotate-6" style="width: 70px; height: 70px;">
                        <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" style="width: 100%; height: 100%;" class="object-contain">
                    </div>
                    <div class="transform transition-all duration-300 group-hover:translate-x-1 sidebar-text">
                        <h1 class="text-lg font-bold">LAS</h1>
                        <p class="text-xs text-red-200">City of Valenzuela</p>
                    </div>
                </a>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 space-y-1">
                    <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-speedometer2 mr-3"></i>
                        <span class="sidebar-text">Dashboard Archives</span>
                    </a>
                    
                    <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-folder mr-3"></i>
                        <span class="sidebar-text">Main Storage Archives</span>
                    </a>

                    <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-cloud-upload mr-3"></i>
                        <span class="sidebar-text">Export</span>
                    </a>

                    <?php if (isset($is_admin) && $is_admin): ?>
                    <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-trash mr-3"></i>
                        <span class="sidebar-text">Recently Deleted</span>
                    </a>
                    <?php endif; ?>

                    <a href="version_tracking.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-book mr-3"></i>
                        <span class="sidebar-text">Version Tracking</span>
                    </a>
                </div>
                
                
                <!-- ANALYTICS Section -->
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                    <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-graph-up mr-3"></i>
                        <span class="sidebar-text">Reports & Analytics</span>
                    </a>
                </div>
                
                <!-- ADMINISTRATION Section -->
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                    <?php if ($is_admin): ?>
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <?php endif; ?>


                    <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-shield-check mr-3"></i>
                        <span class="sidebar-text">Audit Logs</span>
                    </a>
                </div>
                
                <!-- Storage Bar -->
                <div class="mt-6 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                    <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-red-100">Storage Usage</span>
                            <span class="text-xs font-bold text-white">2%</span>
                        </div>
                        <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-white h-full rounded-full" style="width: 2%;"></div>
                        </div>
                        <div class="text-xs text-red-100">1.0 GB of 50.0 GB</div>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col md:ml-64 overflow-y-auto">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side: Toggle buttons and Logo -->
                        <div class="flex items-center">
                            <!-- Sidebar Toggle Button (Desktop) -->

                            <!-- Mobile Menu Button -->
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            
                            <!-- Logo (Mobile) -->
                            <div class="mobile-only flex items-center ml-2">
                                <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                            </div>
                        </div>
                        
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">User Management</h2>
                            </div>
                        </div>
                        
                        <!-- Right Side Actions -->
                        <div class="flex items-center space-x-1 md:space-x-4">
                            <!-- Dark Mode Toggle -->
                            <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
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
                                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>


    <div class="max-w-5xl mx-auto p-6"> 
        <?php if ($message !== ''): ?>
            <div class="mb-4 p-3 rounded-lg border <?php echo strpos($message, 'Failed') === false ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <div class="font-semibold">Pending Approvals</div>
                <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo count($pending); ?> pending</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if (empty($pending)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">No pending users</td>
                            </tr>
                        <?php else: foreach ($pending as $u): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td class="px-6 py-4">
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
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <div class="font-semibold text-gray-800 dark:text-gray-200">User List</div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 dark:text-gray-400" id="userCount"><?php echo count($all_users); ?> users</span>
                    <select id="roleFilter" class="text-sm border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-gray-200 p-2">
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Active</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if (empty($all_users)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">No users found</td>
                            </tr>
                        <?php else: foreach ($all_users as $u): ?>
                            <tr class="user-row" data-role="<?php echo isset($u['role']) ? strtolower($u['role']) : 'user'; ?>">
                                <td class="px-6 py-4 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                    <?php if(isset($u['role']) && $u['role']==='admin') echo '<span class="ml-2 text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full">Admin</span>'; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $u['status']==='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
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
                                <td class="px-6 py-4">
                                    <?php if ((int)$u['id'] !== $user_id): // Prevent deleting self ?>
                                    <form method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:hover:text-red-400" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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
        </script>

        <div class="mt-6">
            <a href="archives-landing.php" class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700">
                <span class="mr-2">←</span> Back to Archives
            </a>
        </div>
    </div>
    <script src="assets/js/archives-landing.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

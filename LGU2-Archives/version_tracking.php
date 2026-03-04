<?php
include 'authdatabase.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$display_name = 'User';
$profile_picture = null;
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $ud = $res->fetch_assoc();
        $display_name = $ud['full_name'] ?? $display_name;
        $profile_picture = $ud['profile_picture'] ?? $profile_picture;
    }
    $stmt->close();
}

$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare("SELECT role FROM users WHERE id = ?");
    if ($st) {
        $st->bind_param("i", $uid);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && $rs->num_rows === 1) {
            $r = $rs->fetch_assoc();
            $is_admin = isset($r['role']) && strtolower($r['role']) === 'admin';
        }
        $st->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#dc2626', light: '#f97316' }
                    }
                }
            }
        }
    </script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        html, body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        .toggle-pill { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .625rem; border-radius: 9999px; border: 1px solid rgba(203,213,225,.6); }
        .toggle-track { position: relative; width: 40px; height: 20px; border-radius: 9999px; background-color: rgba(203,213,225,.6); }
        .dark .toggle-track { background-color: rgba(30,41,59,.6); }
        .toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 9999px; background: white; transition: transform .2s ease; }
        .dark .toggle-thumb { transform: translateX(20px); }
    </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
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
            <a href="recent_deleted.php" class="hidden"></a>
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
                <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
                <?php endif; ?>
                <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-shield-check mr-3 text-lg"></i>
                    <span>Audit Logs</span>
                </a>
            </div>
            
            <!-- Centralized Storage Overview (Mobile) -->
            <div class="mt-6 pt-4 border-t border-red-700/50 px-2">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2 uppercase tracking-wide">Centralized Storage Overview</div>
                <div class="bg-gradient-to-br from-red-900/50 to-red-800/30 backdrop-blur-lg rounded-xl p-4 border border-red-700/50 hover:border-red-600/70 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs text-red-100 font-medium">Capacity Used</span>
                        <span class="text-sm font-bold text-white rounded-full px-2 py-0.5 bg-red-600/40" id="mobile-storage-pct">0%</span>
                    </div>
                    <div class="w-full bg-red-900/60 rounded-full h-2.5 overflow-hidden mb-3 shadow-inner">
                        <div class="bg-gradient-to-r from-red-400 to-orange-500 h-2.5 rounded-full transition-all duration-500" id="mobile-storage-bar" style="width: 0%;"></div>
                    </div>
                    <div class="text-xs text-red-100/80" id="mobile-storage-text">0 B of 50 GB</div>
                    <div class="mt-2 text-xs text-red-100/60" id="mobile-storage-files">0 files tracked</div>
                    <div class="mt-2 text-[11px] text-red-100/75">Combined view for legislative and archive files.</div>
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
                    <a href="recent_deleted.php" class="hidden"></a>
                    <?php endif; ?>

                    <a href="version_tracking.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <?php endif; ?>


                    <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-shield-check mr-3"></i>
                        <span class="sidebar-text">Audit Logs</span>
                    </a>
                </div>
                
                <!-- Centralized Storage Overview (Desktop) -->
                <div class="mt-6 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2 uppercase tracking-wide">Centralized Storage Overview</div>
                    <div class="bg-gradient-to-br from-red-900/50 to-red-800/30 backdrop-blur-lg rounded-xl p-4 border border-red-700/50 hover:border-red-600/70 transition-all cursor-pointer" title="Click to view storage details">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-red-100 font-medium">Capacity Used</span>
                            <span class="text-sm font-bold text-white rounded-full px-2 py-0.5 bg-red-600/40" id="desktop-storage-pct">0%</span>
                        </div>
                        <div class="w-full bg-red-900/60 rounded-full h-2.5 overflow-hidden mb-3 shadow-inner">
                            <div class="bg-gradient-to-r from-red-400 to-orange-500 h-2.5 rounded-full transition-all duration-500" id="desktop-storage-bar" style="width: 0%;"></div>
                        </div>
                        <div class="text-xs text-red-100/80" id="desktop-storage-text">0 B of 50 GB</div>
                        <div class="mt-2 text-xs text-red-100/60" id="desktop-storage-files">0 files tracked</div>
                        <div class="mt-2 text-[11px] text-red-100/75">Combined view for legislative and archive files.</div>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side: Toggle buttons and Logo -->
                        <div class="flex items-center">
                            <!-- Sidebar Toggle Button (Desktop) -->

                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Version Tracking</h2>
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

            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="mb-8 pb-6 border-b border-gray-200 dark:border-slate-700">
                        <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Version Tracking</h1>
                        <p class="text-gray-600 dark:text-gray-400">Browse folders and view mock version history of files</p>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <div class="lg:col-span-1">
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-4">
                                <div class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Folders</div>
                                <div id="vt-folders-list" class="space-y-2">
                                    <button type="button" onclick="viewFolder('ordRes','Ordinances & Resolutions')" class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">
                                        <span class="flex items-center gap-2"><i class="bi bi-folder text-orange-600"></i><span>Ordinances & Resolutions</span></span>
                                        <i class="bi bi-chevron-right text-gray-400"></i>
                                    </button>
                                    <button type="button" onclick="viewFolder('billing','Billing')" class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">
                                        <span class="flex items-center gap-2"><i class="bi bi-folder2-open text-emerald-600"></i><span>Billing</span></span>
                                        <i class="bi bi-chevron-right text-gray-400"></i>
                                    </button>
                                    <button type="button" onclick="viewFolder('publicHearing','Public Hearings')" class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">
                                        <span class="flex items-center gap-2"><i class="bi bi-folder2 text-blue-600"></i><span>Public Hearings</span></span>
                                        <i class="bi bi-chevron-right text-gray-400"></i>
                                    </button>
                                    <button type="button" onclick="viewFolder('meeting','Meeting/Sessions Records')" class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">
                                        <span class="flex items-center gap-2"><i class="bi bi-journal-text text-purple-600"></i><span>Meeting/Sessions Records</span></span>
                                        <i class="bi bi-chevron-right text-gray-400"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-3">
                            <div id="filesPanel" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <div id="filesPanelTitle" class="font-semibold text-base md:text-lg tracking-tight text-gray-800 dark:text-gray-200">Files</div>
                                        <div id="filesPanelMeta" class="text-xs md:text-sm text-gray-500 dark:text-gray-400"></div>
                                    </div>
                                    <button type="button" onclick="clearFolder()" class="px-3 py-1.5 text-xs font-semibold bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 rounded hover:bg-red-100 dark:hover:bg-red-800/40">Close</button>
                                </div>
                                <div id="filesPanelList" class="space-y-3">
                                    <div class="text-sm text-gray-500">Select a folder on the left</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="versionModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeVersionModal()"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="vm-title" class="text-2xl font-bold text-gray-800 dark:text-gray-200">Version History</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeVersionModal()">&times;</button>
                </div>
                <div id="vm-list" class="space-y-3"></div>
            </div>
        </div>
    </div>

    <script>
        function viewFolder(key, label) {
            var panel = document.getElementById('filesPanel');
            var title = document.getElementById('filesPanelTitle');
            var meta = document.getElementById('filesPanelMeta');
            var list = document.getElementById('filesPanelList');
            title.textContent = label;
            list.innerHTML = '<div class="text-center py-4 text-gray-500">Loading files...</div>';
            panel.classList.remove('hidden');

            var typesToFetch = [];
            if (key === 'ordRes') typesToFetch = ['Ordinance', 'Resolution'];
            else if (key === 'billing') typesToFetch = ['Billing'];
            else if (key === 'publicHearing') typesToFetch = ['Public Hearing'];
            else if (key === 'meeting') typesToFetch = ['Meeting'];

            var promises = typesToFetch.map(function(t) {
                return fetch('legislative_api.php?action=get_files&type=' + encodeURIComponent(t))
                    .then(function(r){ return r.json(); })
                    .then(function(d){ return d.success ? d.files : []; });
            });

            Promise.all(promises).then(function(results) {
                var allFiles = results.flat().sort(function(a, b) {
                    return new Date(b.created_at) - new Date(a.created_at);
                });

                meta.textContent = (allFiles.length ? allFiles.length + ' files' : 'No files');
                list.innerHTML = '';
                
                if (!allFiles.length) {
                    var empty = document.createElement('div');
                    empty.className = 'text-sm text-gray-500 dark:text-gray-400 text-center py-4';
                    empty.textContent = 'No files found';
                    list.appendChild(empty);
                } else {
                    allFiles.forEach(function(record){
                        var row = document.createElement('div');
                        row.className = 'flex items-start justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-slate-600';
                        var left = document.createElement('div');
                        left.className = 'min-w-0';
                        var titleEl = document.createElement('div');
                        titleEl.className = 'font-medium text-gray-800 dark:text-white truncate';
                        titleEl.textContent = record.title;
                        var metaEl = document.createElement('div');
                        metaEl.className = 'text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5';
                        var badge = document.createElement('span');
                        badge.className = 'px-2 py-0.5 rounded text-[11px]';
                        var type = String(record.type||'');
                        var cls = type === 'Billing' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300' :
                                  type === 'Public Hearing' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' :
                                  type === 'Meeting' ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-800 dark:text-violet-300' :
                                  'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300';
                        badge.className += ' ' + cls;
                        badge.textContent = type;
                        var sep1 = document.createElement('span'); sep1.className = 'mx-1.5'; sep1.textContent = '•';
                        var dateEl = document.createElement('span'); dateEl.textContent = String(record.month||'') + ' ' + String(record.year||'');
                        var sep2 = document.createElement('span'); sep2.className = 'mx-1.5'; sep2.textContent = '•';
                        var authEl = document.createElement('span'); authEl.textContent = record.author || 'Unknown';
                        metaEl.appendChild(badge); metaEl.appendChild(sep1); metaEl.appendChild(dateEl); metaEl.appendChild(sep2); metaEl.appendChild(authEl);
                        left.appendChild(titleEl); left.appendChild(metaEl);
                        var btn = document.createElement('button');
                        btn.className = 'ml-4 px-3 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600 flex items-center space-x-1';
                        var countSpan = document.createElement('span');
                        countSpan.className = 'ml-1 bg-gray-200 dark:bg-gray-600 px-1.5 rounded-full';
                        countSpan.textContent = '…';
                        btn.innerHTML = '<i class="bi bi-clock-history"></i><span>History</span>';
                        btn.appendChild(countSpan);
                        btn.addEventListener('click', function(){
                            openVersionHistory(record);
                        });
                        row.appendChild(left);
                        row.appendChild(btn);
                        list.appendChild(row);
                        fetch('legislative_api.php?action=get_versions&id=' + encodeURIComponent(record.id))
                            .then(function(r){ return r.json(); })
                            .then(function(d){
                                if (d && d.success && Array.isArray(d.versions)) {
                                    countSpan.textContent = String(d.versions.length);
                                } else {
                                    countSpan.textContent = '0';
                                }
                            })
                            .catch(function(){ countSpan.textContent = '0'; });
                    });
                }
            });
        }
        (function(){
            var list = document.getElementById('vt-folders-list');
            if (!list) return;
            fetch('archives_api.php?action=list_folders').then(function(r){ return r.json(); }).then(function(d){
                var folders = (d && d.success) ? (d.folders || []) : [];
                var html = folders.map(function(f){
                    return '<button type="button" data-id="'+String(f.id)+'" data-name="'+String(f.name)+'" class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">'
                         + '<span class="flex items-center gap-2"><i class="bi bi-folder text-red-600"></i><span>'+String(f.name)+'</span></span>'
                         + '<i class="bi bi-chevron-right text-gray-400"></i></button>';
                }).join('');
                list.insertAdjacentHTML('beforeend', html);
                Array.prototype.forEach.call(list.querySelectorAll('button[data-id]'), function(btn){
                    btn.addEventListener('click', function(){
                        var folder = { id: parseInt(btn.getAttribute('data-id'),10), name: btn.getAttribute('data-name') };
                        viewArchiveFolder(folder);
                    });
                });
            }).catch(function(){});
        })();
        function clearFolder() {
            var panel = document.getElementById('filesPanel');
            var list = document.getElementById('filesPanelList');
            list.innerHTML = '';
            panel.classList.add('hidden');
        }
        function viewArchiveFolder(folder) {
            var panel = document.getElementById('filesPanel');
            var title = document.getElementById('filesPanelTitle');
            var meta = document.getElementById('filesPanelMeta');
            var list = document.getElementById('filesPanelList');
            title.textContent = folder.name;
            list.innerHTML = '<div class="text-center py-4 text-gray-500">Loading files...</div>';
            panel.classList.remove('hidden');
            fetch('archives_api.php?action=get_files&folder_id=' + encodeURIComponent(folder.id))
            .then(function(r){ return r.json(); })
            .then(function(d){
                var files = (d && d.success) ? (d.files || []) : [];
                meta.textContent = files.length ? (files.length + ' files') : 'No files';
                list.innerHTML = '';
                if (!files.length) {
                    var empty = document.createElement('div');
                    empty.className = 'text-sm text-gray-500 dark:text-gray-400 text-center py-4';
                    empty.textContent = 'No files found';
                    list.appendChild(empty);
                } else {
                    files.forEach(function(f){
                        var row = document.createElement('div');
                        row.className = 'flex items-start justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-slate-600';
                        var left = document.createElement('div');
                        left.className = 'min-w-0';
                        var titleEl = document.createElement('div');
                        titleEl.className = 'font-medium text-gray-800 dark:text-white truncate';
                        titleEl.textContent = f.title;
                        var metaEl = document.createElement('div');
                        metaEl.className = 'text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5';
                        var badge = document.createElement('span');
                        badge.className = 'px-2 py-0.5 rounded text-[11px] bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                        badge.textContent = 'Archive';
                        var sep1 = document.createElement('span'); sep1.className = 'mx-1.5'; sep1.textContent = '•';
                        var dateEl = document.createElement('span'); dateEl.textContent = (f.created_at || '');
                        var sep2 = document.createElement('span'); sep2.className = 'mx-1.5'; sep2.textContent = '•';
                        var verEl = document.createElement('span'); verEl.textContent = 'v' + String(f.version || 1);
                        metaEl.appendChild(badge); metaEl.appendChild(sep1); metaEl.appendChild(dateEl); metaEl.appendChild(sep2); metaEl.appendChild(verEl);
                        left.appendChild(titleEl); left.appendChild(metaEl);
                        var btn = document.createElement('button');
                        btn.className = 'ml-4 px-3 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600 flex items-center space-x-1';
                        var countSpan = document.createElement('span');
                        countSpan.className = 'ml-1 bg-gray-200 dark:bg-gray-600 px-1.5 rounded-full';
                        countSpan.textContent = '…';
                        btn.innerHTML = '<i class="bi bi-clock-history"></i><span>History</span>';
                        btn.appendChild(countSpan);
                        btn.addEventListener('click', function(){
                            openArchiveVersionHistory(f);
                        });
                        row.appendChild(left);
                        row.appendChild(btn);
                        list.appendChild(row);
                        fetch('archives_api.php?action=get_versions&id=' + encodeURIComponent(f.id))
                            .then(function(r){ return r.json(); })
                            .then(function(d){ countSpan.textContent = (d && d.success && d.versions) ? String(d.versions.length) : '0'; })
                            .catch(function(){ countSpan.textContent = '0'; });
                    });
                }
            });
        }
        function openArchiveVersionHistory(file) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (file && file.title ? file.title : 'File');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            fetch('archives_api.php?action=get_versions&id=' + (file && file.id ? file.id : ''))
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.success) {
                    if (!d.versions || d.versions.length === 0) {
                        list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                    } else {
                        list.innerHTML = d.versions.map(function(v){
                            return '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">'
                                 + '<div><div class="font-medium text-gray-800 dark:text-white">Version ' + String(v.version) + '</div>'
                                 + '<div class="text-xs text-gray-500 dark:text-gray-400">' + (v.created_at || '') + '</div></div>'
                                 + '<div class="flex space-x-2"><a href="#" onclick="window.open(\'' + encodeURI(file.file_path) + '\', \'_blank\')" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Open</a></div>'
                                 + '</div>';
                        }).join('');
                    }
                } else {
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                }
            });
            document.getElementById('versionModal').classList.remove('hidden');
        }
        function openVersionHistory(record) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (record && record.title ? record.title : 'File');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            
            fetch('legislative_api.php?action=get_versions&id=' + (record && record.id ? record.id : ''))
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    if(d.versions.length === 0) {
                        list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                    } else {
                        list.innerHTML = d.versions.map(v => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">
                                <div>
                                    <div class="font-medium text-gray-800 dark:text-white">Version ${v.version}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ${v.created_at} • ${v.author}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="download.php?${new URLSearchParams({
                                        id: v.id,
                                        title: (record && record.title) ? record.title : (v.title || 'Document'),
                                        type: (record && record.type) ? record.type : '',
                                        month: (record && record.month) ? record.month : '',
                                        year: (record && record.year) ? record.year : '',
                                        author: (record && record.author) ? record.author : ''
                                    }).toString()}" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                }
            });

            document.getElementById('versionModal').classList.remove('hidden');
        }
        function closeVersionModal(){
            document.getElementById('versionModal').classList.add('hidden');
        }
    </script>
    <script>
        (function(){
            var root = document.documentElement;
            var btn = document.getElementById('themeToggle');
            var sun = document.getElementById('sunIcon');
            var moon = document.getElementById('moonIcon');
            function apply(mode){
                var isDark = mode === 'dark';
                root.classList.toggle('dark', isDark);
                try { localStorage.setItem('theme', mode); } catch(e){}
                if (sun && moon) {
                    sun.classList.toggle('hidden', isDark);
                    moon.classList.toggle('hidden', !isDark);
                }
                root.dispatchEvent(new CustomEvent('themechange', { detail: { mode: mode } }));
            }
            var saved = 'light';
            try {
                saved = localStorage.getItem('theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch(e){}
            apply(saved);
            if (btn) {
                btn.addEventListener('click', function(){
                    apply(root.classList.contains('dark') ? 'light' : 'dark');
                });
            }
        })();
    </script>
    <script>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('sidebar-collapsed');
        }
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.remove('-translate-x-full');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
                    sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
                }
            });
        }
        if (closeMobileSidebar) {
            closeMobileSidebar.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.add('-translate-x-full');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                    sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
                }
            });
        }
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            });
        }
        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notification-btn');
        const notifDropdown = document.getElementById('notification-dropdown');
        const notifCount = document.getElementById('notif-count');

        profileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown?.classList.add('hidden');
            profileDropdown?.classList.toggle('hidden');
        });

        notifBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.toggle('hidden');
            try {
                var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
                if (ids.length > 0) {
                    fetch('notifications_log.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
                    }).then(function(){});
                }
            } catch(e){}
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
                profileDropdown?.classList.add('hidden');
            }
            if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
                notifDropdown?.classList.add('hidden');
            }
        });

        (function(){
            function renderNotifList(items){
                var container = document.getElementById('notif-list');
                if (!container) return;
                if (!items || items.length === 0) {
                    container.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No notifications</div>';
                    return;
                }
                var html = items.map(function(n){
                    var href = n.link ? n.link : ('audit-logs.php?id='+encodeURIComponent(n.id));
                    var badge = '';
                    var textWeight = (n.status === 'unread') ? 'font-semibold' : 'font-medium';
                    if (n.status === 'unread') badge = ' ring-2 ring-red-200';
                    return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
                           '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
                           '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
                           '<div class="flex-1 min-w-0">'+
                           '<p class="text-sm '+textWeight+' text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
                           '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
                           '</div></a>';
                }).join('');
                container.innerHTML = html;
            }
            function escapeHtml(s){
                if (typeof s !== 'string') return '';
                return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); });
            }
            function fetchLatest(){
                fetch('notifications_fetch.php?page_size=5&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (d && d.success) renderNotifList(d.items||[]);
                }).catch(function(){});
            }
            function fetchUnread(){
                fetch('notifications_fetch.php?status=unread&page_size=1&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (!notifCount) return;
                    var total = (d && d.success) ? (d.total||0) : 0;
                    notifCount.textContent = String(total);
                    notifCount.style.display = total > 0 ? 'inline-flex' : 'none';
                }).catch(function(){});
            }
            function refresh(){
                fetchLatest();
                fetchUnread();
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', refresh);
            } else {
                refresh();
            }
            window.addEventListener('focus', refresh);
        })();
    </script>
    <script src="assets/js/storage-status.js"></script>

    <script>
        (function() {
            function fetchAndUpdateStorage() {
                fetch('archives-landing.php?action=get_storage_data')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const pct = data.percentage;
                            const usedText = data.usedText;
                            const totalText = data.totalText;
                            const fileCount = data.fileCount;

                            ['mobile', 'desktop'].forEach(prefix => {
                                const bar = document.getElementById(prefix + '-storage-bar');
                                const pctEl = document.getElementById(prefix + '-storage-pct');
                                const textEl = document.getElementById(prefix + '-storage-text');
                                const filesEl = document.getElementById(prefix + '-storage-files');
                                
                                if (bar) bar.style.width = pct + '%';
                                if (pctEl) pctEl.textContent = pct + '%';
                                if (textEl) textEl.textContent = usedText + ' of ' + totalText;
                                if (filesEl) filesEl.textContent = fileCount + ' files tracked';
                            });
                        }
                    }).catch(err => console.warn('Storage fetch error:', err));
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fetchAndUpdateStorage);
            } else {
                fetchAndUpdateStorage();
            }
            setInterval(fetchAndUpdateStorage, 60000);
        })();
    </script>
\n</body>
</html>

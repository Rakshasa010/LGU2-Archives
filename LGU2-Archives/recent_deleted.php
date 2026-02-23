<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recently Deleted - Document Management</title>
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Prevent dark mode flicker -->
    <script src="assets/js/theme-head.js"></script>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    // Get user information
    require 'authdatabase.php';
    $user_id = $_SESSION['user_id'];
    $user_data = null;
    
    $stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
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
    $stmt->close();
    $conn->close();
    
    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;
    ?>
    
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    
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
            
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>
            
             <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Export</span>
            </a>
            
            <!-- ANALYTICS Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
            
            <!-- ADMINISTRATION Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                <a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
                <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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
                        <span class="text-xs font-bold text-white" id="mobile-storage-percent">2%</span>
                    </div>
                    <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                        <div class="bg-white h-full rounded-full" id="mobile-storage-bar" style="width: 2%;"></div>
                    </div>
                    <div class="text-xs text-red-100"><span id="mobile-storage-used">1.0 GB</span> of <span id="mobile-storage-total">50.0 GB</span></div>
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
            <nav class="flex-1 overflow-y-hidden py-4">
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

                    <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-trash mr-3"></i>
                        <span class="sidebar-text">Recently Deleted</span>
                    </a>
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
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-person mr-3"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                    <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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
                            <span class="text-xs font-bold text-white" id="desktop-storage-percent">2%</span>
                        </div>
                        <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-white h-full rounded-full" id="desktop-storage-bar" style="width:2%;"></div>
                        </div>
                        <div class="text-xs text-red-100"><span id="desktop-storage-used">1.0 GB</span> of <span id="desktop-storage-total">50.0 GB</span></div>
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
                        <!-- Left Side -->
                        <div class="flex items-center">
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        <!-- Page Title -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Recently Deleted</h2>
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
                        
                            <!-- User Profile Dropdown -->
                            <div class="relative">
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                                </div>
                                <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                            </button>
                                <!-- Profile Dropdown -->
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <i class="bi bi-gear mr-2"></i>Settings
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

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
            <div class="mb-6">
                <h2 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Recently Deleted</h2>
                <p class="text-gray-600 dark:text-gray-400">Restore files removed in the past few days</p>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 pb-6 border-b border-gray-200 dark:border-slate-700">
                <div class="flex items-center gap-4">
                    <div class="px-4 py-2 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-lg border border-red-200 dark:border-red-800">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Items</span>
                        <span class="ml-2 font-bold text-red-600 dark:text-red-400" id="statCount">0</span>
                    </div>
                    <div class="px-4 py-2 bg-gray-100 dark:bg-slate-700 rounded-lg">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Retention</span>
                        <span class="ml-2 font-semibold text-gray-800 dark:text-gray-200">30 Days</span>
                    </div>
                </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
                        <div class="relative flex-1 sm:flex-initial">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input id="rdSearch" type="text" placeholder="Search deleted files..." 
                                   class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                    <select id="rdFilter" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        <option value="all">All types</option>
                        <option value="PDF">PDF</option>
                        <option value="DOCX">DOCX</option>
                        <option value="XLSX">XLSX</option>
                        <option value="TXT">TXT</option>
                    </select>
                </div>
            </div>

            <div id="deletedList" class="space-y-6 hidden">
                <!-- Files will be rendered dynamically -->
            </div>
            <div id="deletedEmpty" class="text-center py-16">
                <svg class="w-20 h-20 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No recently deleted files</div>
                <div class="text-gray-600 dark:text-gray-400">Files you delete will appear here for quick restore.</div>
            </div>
        </div>
                </div>
            </main>
        </div>
    </div>

    <div id="toast" class="fixed right-6 bottom-6 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold" role="status" aria-live="polite"></div>

    <div id="restoreModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('restoreModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700 transform transition-all scale-100 opacity-100 duration-300">
                <div class="mb-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-4 animate-bounce">
                        <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Restore File?</h3>
                    <p class="text-gray-500 dark:text-gray-400">Are you sure you want to restore <span id="restoreFileName" class="font-semibold text-gray-800 dark:text-gray-200"></span>?</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">This will return the file to <span id="restoreFileCategory" class="text-green-500 font-medium">its original location</span>.</p>
                </div>
                <div class="flex justify-center space-x-4">
                    <button type="button" onclick="closeModal('restoreModal')" class="px-5 py-2.5 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-all font-medium">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmRestore()" class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl shadow-lg hover:shadow-green-500/30 transition-all transform hover:-translate-y-0.5 font-medium flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Restore File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/archives.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
    <script>
        // Load deleted files from localStorage and prune expired entries
        function loadDeletedFiles() {
            const key = 'deletedFiles';
            try {
                const stored = localStorage.getItem(key);
                let arr = stored ? JSON.parse(stored) : [];
                const now = Date.now();
                // keep items that have no expireAt or expireAt in the future
                arr = arr.filter(it => {
                    if (!it) return false;
                    if (!it.expireAt) return true;
                    try {
                        return new Date(it.expireAt).getTime() > now;
                    } catch (e) {
                        return true;
                    }
                });
                // persist pruned list
                localStorage.setItem(key, JSON.stringify(arr));
                return arr;
            } catch (e) {
                console.error('Could not load deleted files', e);
                return [];
            }
        }

        function saveDeletedFiles(files) {
            localStorage.setItem('deletedFiles', JSON.stringify(files));
        }

        let deletedFiles = loadDeletedFiles();

        const deletedListEl = document.getElementById('deletedList');
        const emptyStateEl = document.getElementById('deletedEmpty');
        const toastEl = document.getElementById('toast');
        const searchInput = document.getElementById('rdSearch');
        const filterSelect = document.getElementById('rdFilter');
        const statCount = document.getElementById('statCount');

        function getFiltered() {
            const term = (searchInput.value || '').toLowerCase();
            const typeFilter = filterSelect.value;
            return deletedFiles.filter(file => {
                const matchType = typeFilter === 'all' || file.type === typeFilter;
                const matchTerm = !term || file.name.toLowerCase().includes(term) || 
                                 (file.originalPath && file.originalPath.toLowerCase().includes(term)) ||
                                 (file.category && file.category.toLowerCase().includes(term));
                return matchType && matchTerm;
            });
        }

        function groupFilesByCategory(files) {
            const grouped = {};
            files.forEach(file => {
                const category = file.category || 'Other';
                if (!grouped[category]) {
                    grouped[category] = [];
                }
                grouped[category].push(file);
            });
            return grouped;
        }

        function renderDeleted() {
            const items = getFiltered();
            statCount.textContent = deletedFiles.length;

            if (!items.length) {
                deletedListEl.classList.add('hidden');
                emptyStateEl.classList.remove('hidden');
                return;
            }

            deletedListEl.classList.remove('hidden');
            emptyStateEl.classList.add('hidden');

            const grouped = groupFilesByCategory(items);
            const categories = Object.keys(grouped).sort();

            let html = '';
            categories.forEach(category => {
                const categoryFiles = grouped[category];
                const categoryIcon = getCategoryIcon(category);
                
                html += `
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 p-6">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-slate-600">
                            <div class="flex items-center space-x-3">
                                ${categoryIcon}
                                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200">${category}</h3>
                            </div>
                            <span class="px-3 py-1 bg-gray-200 dark:bg-slate-600 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-300">${categoryFiles.length} file${categoryFiles.length !== 1 ? 's' : ''}</span>
                        </div>
                        <div class="space-y-3">
                            ${categoryFiles.map(file => `
                                <div class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 hover:shadow-md transition-all">
                                    <div class="flex items-center space-x-4 flex-1">
                                        ${getTypeIcon(file.type)}
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">${file.name}</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Original: ${file.originalPath || category}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-500">Deleted: ${file.deletedAt}</div>
                                            <span class="inline-block mt-2 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded text-xs font-semibold">${file.type}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="retention-countdown px-3 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 text-xs font-semibold text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-slate-600" data-expire-at="${file.expireAt || ''}">
                                            --
                                        </div>
                                        <button class="restore-btn px-6 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-lg font-semibold hover:from-green-600 hover:to-emerald-600 transition-all shadow-md hover:shadow-lg flex items-center space-x-2" data-id="${file.id}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                            <span>Restore</span>
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });

            deletedListEl.innerHTML = html;

            deletedListEl.querySelectorAll('.restore-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    handleRestore(id);
                });
            });
        }

        function getCategoryIcon(category) {
            const icons = {
                'Billing': '<svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'Meeting/Sessions Records': '<svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                'Ordinances & Resolutions': '<svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                'Public Hearings': '<svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>',
                'Other': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>'
            };
            return icons[category] || '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>';
        }

        let restoreTargetId = null;

        function handleRestore(id) {
            const idx = deletedFiles.findIndex(f => f.id === id);
            if (idx === -1) return;
            const file = deletedFiles[idx];

            restoreTargetId = id;
            document.getElementById('restoreFileName').textContent = `"${file.name}"`;
            document.getElementById('restoreFileCategory').textContent = file.category || 'its original location';
            
            document.getElementById('restoreModal').classList.remove('hidden');
        }

        function confirmRestore() {
            if (!restoreTargetId) return;
            
            const idx = deletedFiles.findIndex(f => f.id === restoreTargetId);
            if (idx === -1) {
                closeModal('restoreModal');
                return;
            }
            
            const [restored] = deletedFiles.splice(idx, 1);
            saveDeletedFiles(deletedFiles);

            showToast(`"${restored.name}" restored successfully`);
            renderDeleted();
            closeModal('restoreModal');
            restoreTargetId = null;
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function getTypeIcon(type) {
            const map = { 
                PDF: '<svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                DOCX: '<svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                XLSX: '<svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                TXT: '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>'
            };
            return map[type] || '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>';
        }

        function showToast(message) {
            toastEl.textContent = message;
            toastEl.classList.remove('opacity-0', 'translate-y-4');
            toastEl.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toastEl.classList.remove('opacity-100', 'translate-y-0');
                toastEl.classList.add('opacity-0', 'translate-y-4');
            }, 2200);
        }

        searchInput.addEventListener('input', () => renderDeleted());
        filterSelect.addEventListener('change', () => renderDeleted());

        renderDeleted();

        // Countdown beside Restore (updates every second)
        let countdownTimer = null;
        function formatRemaining(ms) {
            if (!Number.isFinite(ms) || ms <= 0) return 'Expired';
            const totalSeconds = Math.floor(ms / 1000);
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            if (days > 0) return `${days}d ${String(hours).padStart(2,'0')}h ${String(minutes).padStart(2,'0')}m`;
            return `${String(hours).padStart(2,'0')}h ${String(minutes).padStart(2,'0')}m ${String(seconds).padStart(2,'0')}s`;
        }

        function updateCountdowns() {
            const els = document.querySelectorAll('.retention-countdown');
            const now = Date.now();
            let needsRerender = false;
            els.forEach(el => {
                const exp = el.getAttribute('data-expire-at') || '';
                const expMs = Date.parse(exp);
                if (!Number.isFinite(expMs)) {
                    el.textContent = '30d';
                    return;
                }
                const remaining = expMs - now;
                el.textContent = formatRemaining(remaining);
                if (remaining <= 0) needsRerender = true;
            });

            if (needsRerender) {
                // prune expired and rerender
                deletedFiles = loadDeletedFiles();
                renderDeleted();
            }
        }

        if (countdownTimer) clearInterval(countdownTimer);
        countdownTimer = setInterval(updateCountdowns, 1000);
        updateCountdowns();

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');
        
        // Desktop sidebar toggle
        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('sidebar-collapsed'));
        });
        
        // Mobile sidebar toggle
        mobileMenuBtn?.addEventListener('click', () => {
            mobileSidebar?.classList.remove('-translate-x-full');
            sidebarOverlay?.classList.remove('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.add('opacity-100', 'pointer-events-auto');
        });
        
        closeMobileSidebar?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });
        
        sidebarOverlay?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });
        
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
        
        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar?.classList.add('sidebar-collapsed');
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#dc2626">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title>Archives - Document Management | City of Valenzuela</title>
    <meta name="description" content="Legislative Records Management System - City Government of Valenzuela, Metropolitan Manila">
    <meta name="keywords" content="Archives, Valenzuela, Legislative Records, Document Management">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/archives-landing-head.js"></script>
    <script src="assets/js/theme-head.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
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
    $stmt->close();
    $conn->close();
    
    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;

    // Normalize profile picture (DB may store either filename or a relative path).
    $profile_picture_url = null;
    if (is_string($profile_picture) && $profile_picture !== '') {
        $candidatePath = $profile_picture;
        $candidateUrl = $profile_picture;

        // If DB stores only the filename, prepend the uploads folder.
        if (strpos($profile_picture, 'uploads/') !== 0) {
            $candidatePath = 'uploads/profile_pictures/' . $profile_picture;
            $candidateUrl = 'uploads/profile_pictures/' . $profile_picture;
        }

        if (file_exists($candidatePath)) {
            $profile_picture_url = $candidateUrl;
        }
    }
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
            <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                <i class="bi bi-speedometer2 mr-3 text-lg"></i>
                <span>Dashboard Archives</span>
            </a>
            
            <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-folder mr-3 text-lg"></i>
                <span>Main Storage Archives</span>
            </a>
            
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>

            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
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
                <a href="burgersettings.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
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
                    <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-speedometer2 mr-3"></i>
                        <span class="sidebar-text">Dashboard Archives</span>
                    </a>
                    
                    <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-folder mr-3"></i>
                        <span class="sidebar-text">Main Storage Archives</span>
                    </a>

                    <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-cloud-upload mr-3"></i>
                        <span class="sidebar-text">Export</span>
                    </a>

                    <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-trash mr-3"></i>
                        <span class="sidebar-text">Recently Deleted</span>
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
                    <a href="burgersettings.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
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
        <div class="flex-1 flex flex-col overflow-hidden">
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
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Dashboard</h2>
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

                                <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
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
                                    <?php if ($profile_picture_url): ?>
                                        <img
                                            src="<?php echo htmlspecialchars($profile_picture_url, ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="Profile"
                                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover border border-gray-300 dark:border-gray-600 flex-shrink-0"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    <?php else: ?>
                                        <div class="bg-red-600 rounded-full w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center text-white flex-shrink-0">
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
                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="burgersettings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
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

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <!-- Content Header -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between">
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Document Archives</h1>
                    </div>
                </div>

                <!-- Document Archives Search Section -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <h2 class="text-xl font-bold mb-2 text-gray-800 dark:text-gray-200">Archives</h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">Advanced Search</p>
                            
                            <!-- Search Bar -->
                            <div class="flex flex-col sm:flex-row gap-3 mb-3">
                                <div class="flex-1 relative">
                                    <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <input type="text" id="legislativeSearchInput" 
                                           class="w-full pl-12 pr-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                                           placeholder="Search for ordinances, resolutions, billing, public hearings, meetings, sessions..." 
                                           autocomplete="off">
                                </div>
                                <button id="legislativeSearchBtn" class="px-6 py-3 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg">Search</button>
                            </div>

                            <!-- Display of current search term -->
                            <div id="searchTermDisplay" class="mb-6 text-sm text-gray-600 dark:text-gray-400 hidden">
                                Showing results for: <span id="searchTermText" class="font-semibold text-gray-900 dark:text-gray-100"></span>
                            </div>

                            <!-- Search Results Container -->
                            <div id="legislativeSearchResults" class="hidden">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 id="searchResultsCount" class="text-lg font-semibold text-gray-800 dark:text-gray-200">0 results found</h3>
                                    <button id="clearSearchBtn" class="px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">Clear</button>
                                </div>
                                <div id="searchResultsList" class="space-y-3">
                                    <!-- Results will be dynamically inserted here -->
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div id="legislativeEmptyState" class="text-center py-12">
                                <svg class="w-20 h-20 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Search Document Archives</div>
                                <div class="text-gray-600 dark:text-gray-400">Enter keywords to search for ordinances, resolutions, billing documents, public hearings, meetings, and legislative sessions</div>
                            </div>
                        </div>

                        <!-- Recent Archives Section -->
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Recent Archives Folders</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <a href="ordinances-resolution.php" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                                    <div class="mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-12 h-12 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Ordinances & Resolutions</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Last modified: 2 days ago</div>
                                </a>
                                <a href="billing.php" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                                    <div class="mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-12 h-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Billing</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Last modified: 1 week ago</div>
                                </a>
                                <a href="public-hearings.php" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                                    <div class="mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-12 h-12 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Public Hearings</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Last modified: 2 weeks ago</div>
                                </a>
                                <a href="meeting-records.php" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                                    <div class="mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-12 h-12 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Meeting/Sessions Records</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Last modified: 3 weeks ago</div>
                                </a>
                            </div>
                        </div>

                        <!-- Latest Archives Section (dynamic: shows recent files visited) -->
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Latest Archive Files</h2>
                            <div id="latestFilesList" class="space-y-3">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Loading recent files...</div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Toast container: same style as recent_deleted.php, stacked; only unseen notifications shown once -->
    <div id="toast-container" class="fixed right-6 bottom-6 z-50 space-y-2 flex flex-col items-end pointer-events-none"></div>

    <?php
    require 'authdatabase.php';
    $notif_data = [];
    if ($r = $conn->query("SELECT id, content, about, status, time, date FROM notifications WHERE status='unread' ORDER BY date DESC, id DESC LIMIT 5")) {
        while ($row = $r->fetch_assoc()) {
            $notif_data[] = $row;
        }
    }
    $conn->close();
    ?>
    <script>
        (function() {
            const STORAGE_KEY = 'archives_shown_notif_ids';
            const MAX_STORED_IDS = 100;
            const initialNotifs = <?php echo json_encode($notif_data); ?> || [];

            function getShownIds() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY);
                    return raw ? JSON.parse(raw) : [];
                } catch (e) {
                    return [];
                }
            }
            function markAsShown(id) {
                const ids = getShownIds();
                if (ids.indexOf(id) !== -1) return;
                ids.push(id);
                if (ids.length > MAX_STORED_IDS) ids.splice(0, ids.length - MAX_STORED_IDS);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
            }

            const notifsToShow = initialNotifs.filter(function(n) {
                return n.id && getShownIds().indexOf(String(n.id)) === -1;
            });

            const ctn = document.getElementById('toast-container');
            function showToast(n) {
                const el = document.createElement('div');
                el.setAttribute('role', 'status');
                el.setAttribute('aria-live', 'polite');
                el.className = 'pointer-events-auto bg-gradient-to-r from-green-500 to-emerald-500 text-white px-5 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all duration-300 ease-out font-semibold max-w-sm';
                const text = (n.about || 'Notification') + (n.content ? ': ' + (n.content.length > 60 ? n.content.slice(0, 60) + '…' : n.content) : '');
                el.textContent = text;
                ctn.appendChild(el);
                requestAnimationFrame(function() {
                    el.classList.remove('opacity-0', 'translate-y-4');
                    el.classList.add('opacity-100', 'translate-y-0');
                });
                markAsShown(n.id);
                setTimeout(function() {
                    el.classList.remove('opacity-100', 'translate-y-0');
                    el.classList.add('opacity-0', 'translate-y-4');
                    setTimeout(function() { el.remove(); }, 300);
                }, 2800);
            }

            var delay = 0;
            notifsToShow.forEach(function(n) {
                setTimeout(function() { showToast(n); }, delay);
                delay += 1400;
            });
        })();
    </script>
    <!-- Modal -->
    <div id="createFolderModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Create New Folder</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal()">&times;</button>
                </div>
                <div class="mb-6">
                    <label for="folderName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Folder Name:</label>
                    <input type="text" id="folderName" 
                           class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                           placeholder="Enter folder name" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button onclick="closeModal()" class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                    <button id="createBtn" class="px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all">Create</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/recent-views.js"></script>
    <script src="assets/js/archives.js"></script>
    <script src="assets/js/archives-landing.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

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
    <?php include 'includes/header_scripts.php'; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <style>
        .compact .p-6 { padding: 0.75rem; }
        .compact .text-xl { font-size: 1.1rem; }
        .compact .text-3xl { font-size: 1.5rem; }
    </style>
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
    
    $stmt = $conn->prepare("SELECT full_name, profile_picture, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
    $archive_folders = [];
    $folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
    if ($folders_result && $folders_result->num_rows > 0) {
        while ($row = $folders_result->fetch_assoc()) {
            $archive_folders[] = $row;
        }
    }
    $conn->close();
    
    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;
    $is_admin = isset($user_data['role']) && strtolower($user_data['role']) === 'admin';

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
            
            <?php if (isset($user_data['role']) && strtolower($user_data['role']) === 'admin'): ?>
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

                    <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-cloud-upload mr-3"></i>
                        <span class="sidebar-text">Export</span>
                    </a>

                    <?php if (isset($user_data['role']) && strtolower($user_data['role']) === 'admin'): ?>
                    <a href="recent_deleted.php" class="hidden"></a>
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
                                <div id="searchRelated" class="mb-3 hidden">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Related topics</div>
                                    <div id="searchRelatedChips" class="flex flex-wrap gap-2"></div>
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
                            <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Storage Overview</h2>
                            <?php
                            require 'authdatabase.php';
                            $totalBytes = 0;
                            $fileCount = 0;
                            $storageTop = [];
                            if ($r = $conn->query("SELECT file_path FROM legislative_records WHERE file_path IS NOT NULL AND file_path <> ''")) {
                                while ($row = $r->fetch_assoc()) {
                                    $p = $row['file_path'];
                                    if ($p && file_exists($p)) {
                                        $fileCount++;
                                        $size = @filesize($p);
                                        if ($size !== false) {
                                            $totalBytes += (int)$size;
                                            $storageTop[] = ['path'=>$p,'size'=>(int)$size,'src'=>'Records'];
                                        }
                                    }
                                }
                            }
                            if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0) {
                                if ($r = $conn->query("SELECT name, file_path FROM archive_files WHERE file_path IS NOT NULL AND file_path <> ''")) {
                                    while ($row = $r->fetch_assoc()) {
                                        $p = $row['file_path'];
                                        if ($p && file_exists($p)) {
                                            $fileCount++;
                                            $size = @filesize($p);
                                            if ($size !== false) {
                                                $totalBytes += (int)$size;
                                                $storageTop[] = ['path'=>$p,'name'=>$row['name'],'size'=>(int)$size,'src'=>'Archive'];
                                            }
                                        }
                                    }
                                }
                            }
                            $capacityBytes = 50 * 1024 * 1024 * 1024;
                            $pct = $capacityBytes > 0 ? min(100, round(($totalBytes / $capacityBytes) * 100, 1)) : 0;
                            function fmt_bytes($b){ if($b<=0) return '0 B'; $u=['B','KB','MB','GB','TB']; $e=floor(log($b,1024)); return round($b/pow(1024,$e),2).' '.$u[$e]; }
                            usort($storageTop, function($a,$b){ return ($b['size']??0) <=> ($a['size']??0); });
                            $storageTop = array_slice($storageTop, 0, 15);
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Used Storage</div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo fmt_bytes($totalBytes); ?></div>
                                </div>
                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Capacity</div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">50 GB</div>
                                </div>
                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Files Tracked</div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo (int)$fileCount; ?></div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Usage</span>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?php echo $pct; ?>%</span>
                                </div>
                                <div id="storage-usage-bar" class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-3 overflow-hidden cursor-pointer">
                                    <div class="bg-gradient-to-r from-red-600 to-orange-500 h-3 rounded-full" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo fmt_bytes($totalBytes); ?> of 50 GB</div>
                            </div>
                        </div>
                        <div id="storageDetailsModal" class="hidden fixed inset-0 z-50">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                                <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 border border-gray-200 dark:border-slate-700">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="text-lg font-semibold text-gray-800 dark:text-gray-100">Storage Details</div>
                                        <button id="storageDetailsClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl">&times;</button>
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">Largest files</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-xs text-gray-500">
                                                <tr><th class="py-2 pr-3">File</th><th class="py-2 pr-3">Source</th><th class="py-2 pr-3">Size</th></tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                                <?php foreach ($storageTop as $it): ?>
                                                <tr>
                                                    <td class="py-2 pr-3 break-all"><?php echo htmlspecialchars($it['name'] ?? basename($it['path'])); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($it['src'] ?? ''); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo fmt_bytes($it['size'] ?? 0); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($storageTop)): ?>
                                                <tr><td colspan="3" class="py-3 text-gray-500">No files found</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Analytics -->
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <?php
                            require 'authdatabase.php';
                            $fa_start = isset($_GET['start']) ? $_GET['start'] : null;
                            $fa_end = isset($_GET['end']) ? $_GET['end'] : null;
                            $fa_type = isset($_GET['type']) ? $_GET['type'] : null;
                            $f_from = null;
                            $f_to = null;
                            if ($fa_start) { $d = DateTime::createFromFormat('Y-m-d', $fa_start); if ($d) $f_from = $d->format('Y-m-d'); }
                            if ($fa_end) { $d = DateTime::createFromFormat('Y-m-d', $fa_end); if ($d) $f_to = $d->format('Y-m-d'); }
                            $where_rec = "1=1";
                            if ($f_from) $where_rec .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                            if ($f_to) $where_rec .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                            if ($fa_type) $where_rec .= " AND type = '".$conn->real_escape_string($fa_type)."'";
                            $where_dl = "event_type='download'";
                            if ($f_from) $where_dl .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                            if ($f_to) $where_dl .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                            if ($fa_type) $where_dl .= " AND record_type = '".$conn->real_escape_string($fa_type)."'";
                            $types_list = [];
                            if ($r = $conn->query("SELECT DISTINCT type FROM legislative_records ORDER BY type")) {
                                while ($row = $r->fetch_assoc()) { if ($row['type'] !== null && $row['type'] !== '') $types_list[] = $row['type']; }
                            }
                            $qa_total_records = 0;
                            $qa_downloads = 0;
                            $qa_by_type = [];
                            if ($res = $conn->query("SELECT COUNT(*) AS t FROM legislative_records WHERE $where_rec")) {
                                if ($row = $res->fetch_assoc()) $qa_total_records = (int)$row['t'];
                            }
                            if ($conn->query("SHOW TABLES LIKE 'analytics_events'")->num_rows > 0) {
                                if ($res = $conn->query("SELECT COUNT(*) AS c FROM analytics_events WHERE $where_dl")) {
                                    if ($row = $res->fetch_assoc()) $qa_downloads = (int)$row['c'];
                                }
                            } else {
                                $where_legacy = "last_accessed IS NOT NULL";
                                if ($f_from) $where_legacy .= " AND last_accessed >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                                if ($f_to) $where_legacy .= " AND last_accessed <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                                if ($fa_type) $where_legacy .= " AND type = '".$conn->real_escape_string($fa_type)."'";
                                if ($res = $conn->query("SELECT COUNT(*) AS c FROM legislative_records WHERE $where_legacy")) {
                                    if ($row = $res->fetch_assoc()) $qa_downloads = (int)$row['c'];
                                }
                            }
                            if ($res = $conn->query("SELECT type, COUNT(*) AS c FROM legislative_records WHERE $where_rec GROUP BY type")) {
                                while ($row = $res->fetch_assoc()) $qa_by_type[$row['type']] = (int)$row['c'];
                            }
                            // Merge newest folders uploaded into record type counts
                            if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0 && $conn->query("SHOW TABLES LIKE 'archive_folders'")->num_rows > 0) {
                                $af_where = "1=1";
                                if ($f_from) $af_where .= " AND f.created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                                if ($f_to) $af_where .= " AND f.created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                                $q = "SELECT fo.name AS folder_name, COUNT(f.id) AS cnt
                                      FROM archive_files f
                                      JOIN archive_folders fo ON fo.id = f.folder_id
                                      WHERE $af_where
                                      GROUP BY fo.id, fo.name";
                                if ($r = $conn->query($q)) {
                                    while ($row = $r->fetch_assoc()) {
                                        $folder = strtolower($row['folder_name'] ?? '');
                                        $count = (int)$row['cnt'];
                                        $mapped = null;
                                        if (strpos($folder, 'resolution') !== false) $mapped = 'Resolution';
                                        elseif (strpos($folder, 'ordinance') !== false) $mapped = 'Ordinance';
                                        elseif (strpos($folder, 'billing') !== false) $mapped = 'Billing';
                                        elseif (strpos($folder, 'public hearing') !== false || strpos($folder, 'hearing') !== false) $mapped = 'Public Hearing';
                                        elseif (strpos($folder, 'meeting') !== false || strpos($folder, 'session') !== false) $mapped = 'Meeting';
                                        if ($mapped !== null) {
                                            if ($fa_type && $fa_type !== $mapped) continue;
                                            $qa_by_type[$mapped] = ($qa_by_type[$mapped] ?? 0) + $count;
                                        }
                                    }
                                }
                            }
                            $days = [];
                            for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $days[$d] = 0; }
                            $series_downloads = $days;
                            $series_records = $days;
                            $series_folders = $days;
                            $dl_limit_clause = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)";
                            $rec_limit_clause = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)";
                            if ($conn->query("SHOW TABLES LIKE 'analytics_events'")->num_rows > 0) {
                                $q = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM analytics_events WHERE $where_dl $dl_limit_clause GROUP BY DATE(created_at) ORDER BY d";
                                if ($r = $conn->query($q)) {
                                    while ($row = $r->fetch_assoc()) { $k = $row['d']; if (isset($series_downloads[$k])) $series_downloads[$k] = (int)$row['c']; }
                                }
                            } else {
                                $w = "last_accessed IS NOT NULL";
                                if ($f_from) $w .= " AND last_accessed >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                                if ($f_to) $w .= " AND last_accessed <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                                if ($fa_type) $w .= " AND type = '".$conn->real_escape_string($fa_type)."'";
                                $q = "SELECT DATE(last_accessed) AS d, COUNT(*) AS c FROM legislative_records WHERE $w $rec_limit_clause GROUP BY DATE(last_accessed) ORDER BY d";
                                if ($r = $conn->query($q)) {
                                    while ($row = $r->fetch_assoc()) { $k = $row['d']; if (isset($series_downloads[$k])) $series_downloads[$k] = (int)$row['c']; }
                                }
                            }
                            $q = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM legislative_records WHERE $where_rec $rec_limit_clause GROUP BY DATE(created_at) ORDER BY d";
                            if ($r = $conn->query($q)) {
                                while ($row = $r->fetch_assoc()) { $k = $row['d']; if (isset($series_records[$k])) $series_records[$k] = (int)$row['c']; }
                            }
                            if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0) {
                                $wf = "1=1";
                                if ($f_from) $wf .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                                if ($f_to) $wf .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                                $qf = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM archive_files WHERE $wf $rec_limit_clause GROUP BY DATE(created_at) ORDER BY d";
                                if ($r = $conn->query($qf)) {
                                    while ($row = $r->fetch_assoc()) { $k = $row['d']; if (isset($series_folders[$k])) $series_folders[$k] = (int)$row['c']; }
                                }
                            }
                            $series_records_merged = $series_records;
                            foreach ($series_records_merged as $k => $v) {
                                $series_records_merged[$k] = $v + ($series_folders[$k] ?? 0);
                            }
                            $qa_series_labels = array_keys($days);
                            $qa_series_downloads = array_values($series_downloads);
                            $qa_series_records = array_values($series_records);
                            $qa_series_records_merged = array_values($series_records_merged);
                            ?>
                            <div class="flex items-end justify-between gap-3 flex-wrap">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Quick Reports & Analytics</h2>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php
                                        $range = ($f_from ? $f_from : 'Start') . ' — ' . ($f_to ? $f_to : 'End');
                                        echo htmlspecialchars($range);
                                        echo $fa_type ? ' • '.htmlspecialchars($fa_type) : '';
                                        ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input id="qa-from" type="date" value="<?php echo htmlspecialchars($f_from ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <input id="qa-to" type="date" value="<?php echo htmlspecialchars($f_to ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <select id="qa-type" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                        <option value="">All Types</option>
                                        <?php foreach ($types_list as $t): ?>
                                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($fa_type === $t ? 'selected' : ''); ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button id="qa-apply" class="px-3 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Apply</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
                                <div class="lg:col-span-2 p-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="text-sm opacity-80">Downloads</div>
                                        <div class="text-xs opacity-80">Last 14 days</div>
                                    </div>
                                    <div class="text-2xl font-bold mb-2"><?php echo $qa_downloads; ?></div>
                                    <canvas id="qaDownloadsBar" height="120"></canvas>
                                </div>
                                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Records</div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2"><?php echo $qa_total_records; ?></div>
                                    <canvas id="qaRecordsMini" height="80"></canvas>
                                </div>
                                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Downloads</div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2"><?php echo $qa_downloads; ?></div>
                                    <canvas id="qaDownloadsMini" height="80"></canvas>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                                <div class="lg:col-span-2 p-4 rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">Records Trend</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Last 14 days</div>
                                    </div>
                                    <canvas id="qaRecordsLine" height="140"></canvas>
                                </div>
                                <div class="lg:col-span-2 p-4 rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-semibold text-gray-800 dark:text-gray-100">Records by Type</div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-600 dark:text-gray-400"><?php echo count($qa_by_type); ?> types</span>
                                        <button id="rbt-toggle" class="text-[11px] px-2 py-1 rounded border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600" title="Toggle absolute/percentage">ABS</button>
                                    </div>
                                </div>
                                    <canvas id="qaRecordsByType" height="180"></canvas>
                                </div>
                            </div>
                        </div>


                        <?php
                        $uploads_by_folder = [];
                        $uploads_labels = [];
                        $uploads_last7 = [];
                        $uploads_prev7 = [];
                        $uploads_earlier = [];
                        if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0 && $conn->query("SHOW TABLES LIKE 'archive_folders'")->num_rows > 0) {
                            $q = "
                                SELECT fo.name AS folder,
                                       SUM(CASE WHEN f.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last7,
                                       SUM(CASE WHEN f.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND f.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS prev7,
                                       SUM(CASE WHEN f.created_at < DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND f.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS earlier
                                FROM archive_folders fo
                                LEFT JOIN archive_files f
                                  ON f.folder_id = fo.id
                                 AND f.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                GROUP BY fo.id, fo.name
                                ORDER BY fo.name
                            ";
                            if ($r = $conn->query($q)) {
                                while ($row = $r->fetch_assoc()) {
                                    $uploads_labels[] = $row['folder'];
                                    $uploads_last7[] = (int)$row['last7'];
                                    $uploads_prev7[] = (int)$row['prev7'];
                                    $uploads_earlier[] = (int)$row['earlier'];
                                }
                            }
                        }
                        $recent_uploads = [];
                        if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0 && $conn->query("SHOW TABLES LIKE 'archive_folders'")->num_rows > 0) {
                            $q = "SELECT f.id, f.name, fo.name AS folder_name, f.created_at FROM archive_files f JOIN archive_folders fo ON fo.id=f.folder_id ORDER BY f.created_at DESC LIMIT 12";
                            if ($r = $conn->query($q)) {
                                while ($row = $r->fetch_assoc()) $recent_uploads[] = $row;
                            }
                        }
                        ?>
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Folders & Uploads</h2>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <div class="lg:col-span-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">Recent Uploads</div>
                                        <a href="storage.php" class="text-sm text-red-600 dark:text-red-400 hover:underline">View All</a>
                                    </div>
                                    <div class="mb-2">
                                        <select id="fu-filter" class="w-full px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                            <option value="">All Folders</option>
                                            <?php foreach ($uploads_labels as $lab): ?>
                                                <option value="<?php echo htmlspecialchars($lab); ?>"><?php echo htmlspecialchars($lab); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-xs text-gray-500">
                                                <tr><th class="py-2 pr-3">File</th><th class="py-2 pr-3">Folder</th><th class="py-2 pr-3">Date</th></tr>
                                            </thead>
                                            <tbody id="fu-table" class="divide-y divide-gray-100 dark:divide-slate-700">
                                                <?php foreach ($recent_uploads as $u): ?>
                                                <tr data-folder="<?php echo htmlspecialchars($u['folder_name']); ?>">
                                                    <td class="py-2 pr-3 truncate"><?php echo htmlspecialchars($u['name']); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($u['folder_name']); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recent_uploads)): ?>
                                                <tr><td colspan="3" class="py-3 text-gray-500">No uploads yet.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="lg:col-span-2">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">Uploads by Folder</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Last 30 days</div>
                                    </div>
                                    <canvas id="uploadsByFolderChart" height="180"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <?php
                            $warn_count = 0;
                            if ($r = $conn->query("SELECT COUNT(*) AS c FROM legislative_records WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND (author='' OR author IS NULL OR file_path IS NULL OR file_path='')")) {
                                if ($row = $r->fetch_assoc()) $warn_count = (int)$row['c'];
                            }
                            ?>
                           

                        <!-- Latest Archives Section (dynamic: shows recent files visited) -->
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Latest Archive Files Visit </h2>
                            <div id="latestFilesList" class="space-y-3">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Loading recent files...</div>
                            </div>
                            <div class="mt-4 p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600">
                                <div class="text-sm text-gray-700 dark:text-gray-200"><?php echo $is_admin ? 'Tip: Use Reports & Analytics to audit user downloads.' : 'Tip: You can preview documents before downloading.'; ?></div>
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
    // Normalized duplicate detection (case-insensitive, trimmed)
    $dup_leg_labels = []; $dup_leg_counts = [];
    if ($conn->query("SHOW TABLES LIKE 'legislative_records'")->num_rows > 0) {
        $q = "SELECT LOWER(TRIM(title)) AS k, MIN(title) AS label, COUNT(*) AS c
              FROM legislative_records
              GROUP BY k
              HAVING c > 1
              ORDER BY c DESC, label ASC
              LIMIT 10";
        if ($r = $conn->query($q)) {
            while ($row = $r->fetch_assoc()) { $dup_leg_labels[] = $row['label']; $dup_leg_counts[] = (int)$row['c']; }
        }
    }
    $dup_file_labels = []; $dup_file_counts = [];
    if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0) {
        $q = "SELECT LOWER(TRIM(name)) AS k, MIN(name) AS label, COUNT(*) AS c
              FROM archive_files
              GROUP BY k
              HAVING c > 1
              ORDER BY c DESC, label ASC
              LIMIT 10";
        if ($r = $conn->query($q)) {
            while ($row = $r->fetch_assoc()) { $dup_file_labels[] = $row['label']; $dup_file_counts[] = (int)$row['c']; }
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
    <script>
        (function(){
            var uploads = <?php echo json_encode($recent_uploads ?? []); ?> || [];
            var ctn = document.getElementById('toast-container');
            var KEY = 'archives_shown_upload_ids';
            function getShown(){
                try{ var raw = localStorage.getItem(KEY); return raw ? JSON.parse(raw) : []; }catch(e){ return []; }
            }
            function mark(id){
                try{
                    var ids = getShown();
                    if (ids.indexOf(String(id)) === -1) {
                        ids.push(String(id));
                        if (ids.length > 120) ids.splice(0, ids.length - 120);
                        localStorage.setItem(KEY, JSON.stringify(ids));
                    }
                }catch(e){}
            }
            function showUploadToast(u){
                if (!ctn) return;
                var el = document.createElement('div');
                el.setAttribute('role','status');
                el.setAttribute('aria-live','polite');
                el.className = 'pointer-events-auto bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-5 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all duration-300 ease-out font-semibold max-w-sm';
                var txt = 'New upload: ' + String(u.name || u.title || 'File') + (u.folder_name ? ' • ' + String(u.folder_name) : '');
                el.textContent = txt;
                ctn.appendChild(el);
                requestAnimationFrame(function(){ el.classList.remove('opacity-0','translate-y-4'); el.classList.add('opacity-100','translate-y-0'); });
                mark(u.id || (u.name || ''));
                setTimeout(function(){
                    el.classList.remove('opacity-100','translate-y-0');
                    el.classList.add('opacity-0','translate-y-4');
                    setTimeout(function(){ el.remove(); }, 300);
                }, 2600);
            }
            var shown = getShown();
            var now = Date.now();
            uploads.filter(function(u){
                if (!u || !u.id) return false;
                if (shown.indexOf(String(u.id)) !== -1) return false;
                var t = Date.parse(u.created_at || u.raw_date || '');
                if (isNaN(t)) return true;
                return (now - t) <= 3*24*60*60*1000;
            }).slice(0, 6).forEach(function(u, idx){
                setTimeout(function(){ showUploadToast(u); }, idx * 1000);
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
    <script src="assets/js/highlight-record.js"></script>
    <?php include 'includes/footer_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function(){
            var byType = <?php echo json_encode($qa_by_type ?? []); ?>;
            var seriesLabels = <?php echo json_encode($qa_series_labels ?? []); ?>;
            var seriesDownloads = <?php echo json_encode($qa_series_downloads ?? []); ?>;
            var seriesRecords = <?php echo json_encode($qa_series_records ?? []); ?>;
            var seriesRecordsMerged = <?php echo json_encode($qa_series_records_merged ?? []); ?>;
            var fuLabels = <?php echo json_encode($uploads_labels ?? []); ?>;
            var fuLast7 = <?php echo json_encode($uploads_last7 ?? []); ?>;
            var fuPrev7 = <?php echo json_encode($uploads_prev7 ?? []); ?>;
            var fuEarlier = <?php echo json_encode($uploads_earlier ?? []); ?>;
            var catLabels = <?php echo json_encode($cat_labels ?? []); ?>;
            var catLast7 = <?php echo json_encode($cat_last7 ?? []); ?>;
            var catPrev7 = <?php echo json_encode($cat_prev7 ?? []); ?>;
            var catEarlier = <?php echo json_encode($cat_earlier ?? []); ?>;
            var ffLabels = <?php echo json_encode($folder_counts_labels ?? []); ?>;
            var ffValues = <?php echo json_encode($folder_counts_values ?? []); ?>;
            var dupLegLabels = <?php echo json_encode($dup_leg_labels ?? []); ?>;
            var dupLegCounts = <?php echo json_encode($dup_leg_counts ?? []); ?>;
            var dupFileLabels = <?php echo json_encode($dup_file_labels ?? []); ?>;
            var dupFileCounts = <?php echo json_encode($dup_file_counts ?? []); ?>;
            var typeCtx = document.getElementById('qaRecordsByType');
            var rbtToggle = document.getElementById('rbt-toggle');
            var rbtChart = null;
            var rbtMode = localStorage.getItem('rbtMode') || 'abs';
            function renderRbt() {
                if (!typeCtx) return;
                var labels = Object.keys(byType);
                var values = Object.values(byType);
                var total = values.reduce(function(a,b){return a+b;},0) || 1;
                var data = (rbtMode === 'pct') ? values.map(function(v){ return +(v*100/total).toFixed(2); }) : values;
                if (rbtChart) { rbtChart.destroy(); }
                rbtChart = new Chart(typeCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{ data: data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444'] }]
                    },
                    options: { 
                        responsive: true, 
                        plugins: { 
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { label: function(ctx){
                                var idx = ctx.dataIndex;
                                var raw = values[idx];
                                var pct = (raw*100/total).toFixed(2)+'%';
                                return labels[idx]+': '+ (rbtMode==='pct' ? pct : raw);
                            }}}
                        }
                    }
                });
                if (rbtToggle) rbtToggle.textContent = (rbtMode==='pct' ? '%' : 'ABS');
            }
            renderRbt();
            if (rbtToggle) {
                rbtToggle.addEventListener('click', function(){
                    rbtMode = (rbtMode === 'abs') ? 'pct' : 'abs';
                    localStorage.setItem('rbtMode', rbtMode);
                    renderRbt();
                });
            }
            var dlBar = document.getElementById('qaDownloadsBar');
            if (dlBar) {
                new Chart(dlBar.getContext('2d'), {
                    type: 'bar',
                    data: { labels: seriesLabels, datasets: [{ data: seriesDownloads, backgroundColor: 'rgba(255,255,255,0.9)' }] },
                    options: { responsive: true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#fff'} , grid:{ display:false } }, y:{ ticks:{ display:false }, grid:{ display:false } } } }
                });
            }
            var recMini = document.getElementById('qaRecordsMini');
            if (recMini) {
                new Chart(recMini.getContext('2d'), {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ data: seriesRecordsMerged, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.15)', fill: true, tension: 0.35, pointRadius: 0 }] },
                    options: { responsive: true, plugins:{ legend:{ display:false } }, scales:{ x:{ display:false }, y:{ display:false } } }
                });
            }
            var dlMini = document.getElementById('qaDownloadsMini');
            if (dlMini) {
                new Chart(dlMini.getContext('2d'), {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ data: seriesDownloads, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', fill: true, tension: 0.35, pointRadius: 0 }] },
                    options: { responsive: true, plugins:{ legend:{ display:false } }, scales:{ x:{ display:false }, y:{ display:false } } }
                });
            }
            var recLine = document.getElementById('qaRecordsLine');
            if (recLine) {
                new Chart(recLine.getContext('2d'), {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ label: 'Records', data: seriesRecords, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.2)', fill: true, tension: 0.3 }] },
                    options: { responsive: true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ maxRotation: 0, autoSkip: true } }, y:{ beginAtZero:true, precision:0 } } }
                });
            }
            var fu = document.getElementById('uploadsByFolderChart');
            if (fu) {
                new Chart(fu.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: fuLabels,
                        datasets: [
                            { label: 'Last 7d', data: fuLast7, backgroundColor: '#2563eb' },
                            { label: 'Prev 7d', data: fuPrev7, backgroundColor: '#f97316' },
                            { label: '8-30d', data: fuEarlier, backgroundColor: '#6b7280' }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom' } },
                        scales: { x: { stacked: false }, y: { beginAtZero: true, precision: 0 } }
                    }
                });
            }
            var rbc = document.getElementById('recordsByCategoryChart');
            if (rbc) {
                new Chart(rbc.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: catLabels,
                        datasets: [
                            { label: 'Last 7d', data: catLast7, backgroundColor: '#dc2626' },
                            { label: 'Prev 7d', data: catPrev7, backgroundColor: '#f97316' },
                            { label: '8-30d', data: catEarlier, backgroundColor: '#6b7280' }
                        ]
                    },
                    options: { responsive: true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true, precision:0 } } }
                });
            }
            var ffd = document.getElementById('filesByFolderDonut');
            if (ffd) {
                new Chart(ffd.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ffLabels,
                        datasets: [{ data: ffValues, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444','#06b6d4','#84cc16'] }]
                    },
                    options: { responsive: true, plugins:{ legend:{ position:'bottom' } } }
                });
            }
            var d1 = document.getElementById('dupLegBar');
            if (d1) {
                new Chart(d1.getContext('2d'), {
                    type: 'bar',
                    data: { labels: dupLegLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'…' : s; }), datasets: [{ label:'Count', data: dupLegCounts, backgroundColor:'#dc2626' }] },
                    options: { indexAxis:'y', responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ beginAtZero:true, precision:0 } } }
                });
            }
            var d2 = document.getElementById('dupFileBar');
            if (d2) {
                new Chart(d2.getContext('2d'), {
                    type: 'bar',
                    data: { labels: dupFileLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'…' : s; }), datasets: [{ label:'Count', data: dupFileCounts, backgroundColor:'#2563eb' }] },
                    options: { indexAxis:'y', responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ beginAtZero:true, precision:0 } } }
                });
            }
            var fuFilter = document.getElementById('fu-filter');
            if (fuFilter) {
                fuFilter.addEventListener('change', function(){
                    var val = this.value || '';
                    var rows = document.querySelectorAll('#fu-table tr[data-folder]');
                    rows.forEach(function(tr){
                        var fld = tr.getAttribute('data-folder') || '';
                        tr.style.display = (!val || fld === val) ? '' : 'none';
                    });
                });
            }
        })();
    </script>
    <script>
        (function(){
            var bar = document.getElementById('storage-usage-bar');
            var modal = document.getElementById('storageDetailsModal');
            var closeBtn = document.getElementById('storageDetailsClose');
            bar && bar.addEventListener('click', function(){ modal && modal.classList.remove('hidden'); });
            closeBtn && closeBtn.addEventListener('click', function(){ modal && modal.classList.add('hidden'); });
            modal && modal.addEventListener('click', function(e){ if (e.target === modal) modal.classList.add('hidden'); });
        })();
    </script>
    <script>
        (function(){
            var applyBtn = document.getElementById('qa-apply');
            function applyFilters(){
                var p = new URLSearchParams(window.location.search);
                var from = document.getElementById('qa-from')?.value || '';
                var to = document.getElementById('qa-to')?.value || '';
                var type = document.getElementById('qa-type')?.value || '';
                ['start','end','type'].forEach(function(k){ p.delete(k); });
                if (from) p.set('start', from);
                if (to) p.set('end', to);
                if (type) p.set('type', type);
                var url = window.location.pathname + (p.toString() ? ('?'+p.toString()) : '');
                window.location.assign(url);
            }
            applyBtn && applyBtn.addEventListener('click', applyFilters);
        })();
    </script>
</body>
</html>

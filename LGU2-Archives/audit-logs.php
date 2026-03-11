<?php
// audit-logs.php
// Lists recent notifications with time, date, content, and about; supports ?id= to highlight.
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php
    // audit-logs.php
    // Lists recent notifications with time, date, content, and about; supports ?id= to highlight.

    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    require 'authdatabase.php';
    date_default_timezone_set('Asia/Manila');
    $conn->query("SET time_zone = '+08:00'");
    $user_id = $_SESSION['user_id'];
    $user_data = null;
    $stmt = $conn->prepare("SELECT full_name, profile_picture, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
    // Ensure notifications table exists (safety in case database.sql not yet applied)
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time VARCHAR(20) NOT NULL,
        date DATE NOT NULL,
        content VARCHAR(255) NOT NULL,
        about VARCHAR(100) NOT NULL,
        status ENUM('unread','read') NOT NULL DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $notif_cols = [
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'link' => "VARCHAR(255) DEFAULT NULL",
        'file_name' => "VARCHAR(255) DEFAULT NULL",
        'file_version' => "VARCHAR(60) DEFAULT NULL",
        'needed_date' => "DATE DEFAULT NULL",
        'request_note' => "TEXT",
        'purpose' => "VARCHAR(255) DEFAULT NULL"
    ];
    foreach ($notif_cols as $col => $def) {
        $exists = $conn->query("SHOW COLUMNS FROM notifications LIKE '$col'");
        if ($exists && $exists->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN $col $def");
        }
    }

    // Load notifications from DB
    $notifications = [];
    if ($res = $conn->query("SELECT id, time, date, content, about, status, created_at, link FROM notifications ORDER BY date DESC, id DESC")) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    $conn->close();

    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;
    $is_admin = isset($user_data['role']) && strtolower($user_data['role']) === 'admin';

    $selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Audit Logs - Document Management | City of Valenzuela</title>
        <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
        <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="assets/js/archives-landing-head.js"></script>
        <script src="assets/js/theme-head.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/archives-landing.css">
        <link rel="stylesheet" href="assets/css/audit-logs.css">
    </head>
    <body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200 min-h-screen">
        <!-- Mobile Sidebar Overlay -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    
        <!-- Mobile Sidebar -->
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">
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
            <nav class="flex-1 py-4 px-3 overflow-y-auto">
                <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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
                <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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
                <!-- Navigation Menu (same as archives dashboard) -->
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
                    

                        <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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

                            <!-- Mobile Menu Button -->
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            
                            <!-- Logo (Mobile) -->
                            <div class="mobile-only flex items-center ml-2">
                                <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                            </div>
                        </div>
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Audit Logs</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">View and manage system audit logs</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-1 md:space-x-4">
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
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div class="space-y-6">
                            <div class="bg-white dark:bg-slate-800/95 rounded-xl shadow-lg border border-gray-200 dark:border-slate-600/80 ring-1 ring-gray-200 dark:ring-slate-700 transition-all p-4 sm:p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center space-x-3">
                                        <h1 class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400">Audit Logs</h1>
                                    </div>
                                </div>

                                <div class="bg-gray-50/50 dark:bg-slate-800/80 rounded-xl border border-gray-200 dark:border-slate-600/80 shadow-inner dark:shadow-none backdrop-blur-sm ring-1 ring-gray-200/50 dark:ring-slate-700/50 p-4">
                                    <div class="flex flex-col gap-4 mb-4">
                                        <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2">
                                            <button id="filter-all" class="w-full sm:w-auto px-3 py-2 rounded-lg bg-gray-200 dark:bg-slate-600 text-gray-800 dark:text-slate-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">All</button>
                                            <button id="filter-unread" class="w-full sm:w-auto px-3 py-2 rounded-lg bg-red-50 dark:bg-slate-700 text-red-700 dark:text-red-300 text-sm font-semibold hover:bg-red-100 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">Unread</button>
                                            <select id="filter-status" class="w-full sm:w-auto px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">Status</option>
                                                <option value="unread">Unread</option>
                                                <option value="read">Read</option>
                                            </select>
                                            <select id="filter-about" class="w-full sm:w-auto px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">About</option>
                                            </select>
                                            <div class="w-full sm:w-auto flex items-center gap-1 border border-gray-300 dark:border-slate-500 rounded-lg bg-white dark:bg-slate-700 p-1">
                                                <button type="button" id="date-preset-today" class="flex-1 sm:flex-none px-2 py-1.5 rounded text-xs font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">Today</button>
                                                <button type="button" id="date-preset-week" class="flex-1 sm:flex-none px-2 py-1.5 rounded text-xs font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">This Week</button>
                                                <button type="button" id="date-preset-month" class="flex-1 sm:flex-none px-2 py-1.5 rounded text-xs font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">This Month</button>
                                            </div>
                                            <input id="filter-from" type="date" class="w-full sm:w-auto px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                            <input id="filter-to" type="date" class="w-full sm:w-auto px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                            <select id="page-size" class="w-full sm:w-auto px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>
                                            <span id="unread-count" class="w-full sm:w-auto sm:ml-2 text-sm text-gray-600 dark:text-slate-400"></span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2">
                                            <input id="searchInput" type="search" placeholder="Search notifications" class="w-full sm:flex-1 min-w-[140px] px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 placeholder-gray-500 dark:placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                            <a href="?" class="w-full sm:w-auto text-center text-sm text-red-600 dark:text-red-400 hover:underline font-medium">Reset</a>
                                            <div id="paginationControls" class="w-full sm:w-auto flex items-center gap-2">
                                                <button id="page-prev" class="flex-1 sm:flex-none px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 text-sm hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">Prev</button>
                                                <span id="page-info" class="text-sm text-gray-600 dark:text-slate-400 px-2">1</span>
                                                <button id="page-next" class="flex-1 sm:flex-none px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 text-sm hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">Next</button>
                                            </div>
                                            <button id="mark-all-read" class="w-full sm:w-auto sm:ml-auto px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-red-500">Mark all as read</button>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-slate-600/80">
                                    <table id="auditTable" class="w-full min-w-[720px] text-left table-auto">
                                        <thead>
                                            <tr class="text-sm text-gray-600 dark:text-slate-400 bg-gray-100 dark:bg-slate-700/80 border-b border-gray-200 dark:border-slate-600">
                                                <th class="px-3 py-3 font-semibold">#</th>
                                                <th class="px-3 py-3 font-semibold">Time</th>
                                                <th class="px-3 py-3 font-semibold">Date</th>
                                                <th class="px-3 py-3 font-semibold">Content</th>
                                                <th class="px-3 py-3 font-semibold">About</th>
                                                <th class="px-3 py-3 font-semibold">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="notesBody" class="divide-y divide-gray-200 dark:divide-slate-600">
                                        <?php foreach ($notifications as $note): ?>
                                            <?php $isSelected = ($selectedId !== null && $selectedId === (int)$note['id']); ?>
                                            <tr id="note-<?php echo (int)$note['id']; ?>" data-id="<?php echo (int)$note['id']; ?>" data-status="<?php echo htmlspecialchars($note['status']); ?>" class="<?php echo $isSelected ? 'highlight' : ''; ?> bg-white dark:bg-slate-800/50 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-slate-200"><?php echo (int)$note['id']; ?></td>
                                                <td class="px-3 py-3 text-sm">
                                                    <?php
                                                    $createdTs = isset($note['created_at']) ? strtotime($note['created_at']) : null;
                                                    $dispTime = $createdTs ? date('h:i A', $createdTs) : ($note['time'] ?? '');
                                                    $dispDate = $createdTs ? date('Y-m-d', $createdTs) : ($note['date'] ?? '');
                                                    $ms = $createdTs ? ($createdTs * 1000) : 0;
                                                    ?>
                                                    <span class="note-time" data-ts="<?php echo (int)$ms; ?>" data-base="<?php echo htmlspecialchars($dispTime); ?>" title="Created: <?php echo htmlspecialchars($note['created_at'] ?? $dispDate); ?> • Status: <?php echo htmlspecialchars($note['status'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($dispTime); ?>
                                                        <span class="text-gray-500"></span>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-slate-200"><?php echo htmlspecialchars($dispDate); ?></td>
                                                <td class="px-3 py-3 text-sm">
                                                    <?php if (!empty($note['link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($note['link']); ?>" class="text-gray-800 dark:text-slate-100 hover:underline block"><?php echo htmlspecialchars($note['content']); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-gray-800 dark:text-slate-100 block"><?php echo htmlspecialchars($note['content']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-3 text-sm text-gray-600 dark:text-slate-400"><?php echo htmlspecialchars($note['about']); ?></td>
                                                <td class="px-3 py-3 text-sm">
                                                    <?php $isReadBtn = strtolower($note['status']) === 'read'; ?>
                                                    <button class="mark-read-btn px-3 py-2 text-xs font-semibold rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 <?php echo $isReadBtn ? 'bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 border-gray-200 dark:border-slate-600 highlight-mark-read' : 'bg-red-600 hover:bg-red-700 text-white border-red-700'; ?>" type="button" data-status="<?php echo htmlspecialchars($note['status']); ?>"><?php echo $isReadBtn ? 'Read' : 'Mark Read'; ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <script src="assets/js/archives-landing.js"></script>
        <script src="assets/js/audit-logs.js"></script>
        <script src="assets/js/theme-toggle.js"></script>
        <script>
        (function(){
            // Initialize DataTable for audit logs
            try{
                const table = $('#auditTable').DataTable({
                    pageLength: parseInt(document.getElementById('page-size')?.value || 10,10),
                    lengthChange: false,
                    ordering: true,
                    autoWidth: false,
                    columnDefs: [{ targets: -1, orderable: false }]
                });

                // Wire search input
                $('#searchInput').on('input', function(){ table.search(this.value).draw(); });

                // Wire page size control
                document.getElementById('page-size')?.addEventListener('change', function(){ table.page.len(parseInt(this.value,10)).draw(); });

                // Populate 'About' filter from table data
                const aboutSet = new Set();
                $('#auditTable tbody tr').each(function(){ aboutSet.add($(this).find('td').eq(4).text().trim()); });
                const sel = document.getElementById('filter-about');
                if (sel){ aboutSet.forEach(v=>{ if(v){ const o = document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); } }); sel.addEventListener('change', function(){ table.column(4).search(this.value).draw(); }); }

                // Wire preset date buttons to filter via search (simple)
                document.getElementById('date-preset-today')?.addEventListener('click', function(){ table.column(2).search('<?php echo date('Y-m-d'); ?>').draw(); });
                document.getElementById('date-preset-week')?.addEventListener('click', function(){ table.column(2).search('<?php echo date('Y-m-d', strtotime('-7 days')); ?>').draw(); });
                document.getElementById('date-preset-month')?.addEventListener('click', function(){ table.column(2).search('<?php echo date('Y-m-d', strtotime('-30 days')); ?>').draw(); });

            }catch(e){ console.warn('DataTable init failed', e); }
        })();
        </script>
    
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

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
    $total_logs = 0;
    $unread_count = 0;
    $logins_today = 0;
    $registrations_all = 0;
    $today_str = date('Y-m-d');
    
    if ($res = $conn->query("SELECT id, time, date, content, about, status, created_at, link FROM notifications ORDER BY date DESC, id DESC")) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
            $total_logs++;
            if (strtolower($row['status']) === 'unread') $unread_count++;
            if (stripos($row['about'], 'Login') !== false && $row['date'] === $today_str) $logins_today++;
            if (stripos($row['about'], 'Register') !== false || stripos($row['about'], 'Registration') !== false) $registrations_all++;
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

        <div class="flex h-screen overflow-hidden">
            <?php
            $sidebar_active_page = 'audit-logs';
            $sidebar_include_overlay = true;
            require_once 'includes/sidebar-centralized.php';
            ?>

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

                <!-- Main Content Area -->
                <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div class="space-y-6">
                            <!-- Stats Bar -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                                    <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300"><i class="bi bi-file-earmark-text text-xl"></i></div>
                                    <div><div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo $total_logs; ?></div><div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Logs</div></div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-red-200 dark:border-red-900/50 shadow-sm flex items-center gap-4 relative overflow-hidden group">
                                    <div class="absolute inset-0 bg-red-50 dark:bg-red-900/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/40 rounded-lg flex items-center justify-center text-red-600 dark:text-red-400 relative z-10"><i class="bi bi-bell-fill text-xl"></i></div>
                                    <div class="relative z-10"><div class="text-2xl font-bold text-red-600 dark:text-red-400"><?php echo $unread_count; ?></div><div class="text-xs text-red-500 dark:text-red-400/80 font-semibold">Unread Alerts</div></div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center text-blue-600 dark:bg-blue-400"><i class="bi bi-door-open-fill text-xl"></i></div>
                                    <div><div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo $logins_today; ?></div><div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Logins Today</div></div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/40 rounded-lg flex items-center justify-center text-emerald-600 dark:text-emerald-400"><i class="bi bi-person-plus-fill text-xl"></i></div>
                                    <div><div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo $registrations_all; ?></div><div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Registrations (All-time)</div></div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-slate-800/95 rounded-xl shadow-lg border border-gray-200 dark:border-slate-600/80 ring-1 ring-gray-200 dark:ring-slate-700 transition-all p-4 sm:p-6 pb-2">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <h1 class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400">Audit Logs</h1>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button id="mark-all-read" class="px-3 py-1.5 rounded-lg bg-red-50 dark:bg-slate-700 text-red-600 dark:text-red-400 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 border border-red-200 dark:border-slate-600 shadow-sm hover:bg-red-100 dark:hover:bg-slate-600">Mark all read</button>
                                    </div>
                                </div>

                                <!-- Smarter filter bar -->
                                <div class="bg-gray-50 dark:bg-slate-900/50 rounded-lg border border-gray-200 dark:border-slate-700 shadow-sm p-3 mb-6 flex flex-col lg:flex-row gap-3 lg:items-center">
                                    <div class="relative flex-1">
                                        <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        <input id="searchInput" type="search" placeholder="Search audit logs by content, user, or date..." class="w-full pl-9 pr-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 focus:border-red-500 dark:focus:border-red-500 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-1 focus:ring-red-500 transition-colors shadow-sm">
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <select id="filter-about" class="px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 text-sm focus:outline-none focus:border-red-400 shadow-sm">
                                            <option value="">All Categories</option>
                                        </select>
                                        <div class="h-6 w-px bg-gray-300 dark:bg-slate-600 hidden md:block mx-1"></div>
                                        <div class="flex items-center gap-1 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 p-0.5 shadow-sm">
                                            <button type="button" id="date-preset-today" class="px-2.5 py-1.5 rounded text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">Today</button>
                                            <div class="w-px h-3 bg-gray-300 dark:bg-slate-600"></div>
                                            <button type="button" id="date-preset-week" class="px-2.5 py-1.5 rounded text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">7 Days</button>
                                        </div>
                                        <div class="h-6 w-px bg-gray-300 dark:bg-slate-600 hidden lg:block mx-1"></div>
                                        <select id="page-size" class="px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 text-sm focus:outline-none focus:border-red-400 shadow-sm">
                                            <option value="10">10 / page</option>
                                            <option value="25">25 / page</option>
                                            <option value="50">50 / page</option>
                                        </select>
                                        <div id="paginationControls" class="flex items-center bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm overflow-hidden">
                                            <button id="page-prev" class="px-3 py-1.5 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors border-r border-gray-200 dark:border-slate-600"><i class="bi bi-chevron-left text-xs"></i></button>
                                            <span id="page-info" class="text-xs font-bold text-gray-700 dark:text-gray-300 px-3 py-1.5">1</span>
                                            <button id="page-next" class="px-3 py-1.5 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors border-l border-gray-200 dark:border-slate-600"><i class="bi bi-chevron-right text-xs"></i></button>
                                        </div>
                                    </div>
                                </div>

                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-slate-600 shadow-sm">
                                    <table id="auditTable" class="w-full min-w-[720px] text-left table-auto">
                                        <thead>
                                            <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400 bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-600">
                                                <th class="px-4 py-3 font-bold w-12 text-center">#</th>
                                                <th class="px-4 py-3 font-bold w-36">Time & Date</th>
                                                <th class="px-4 py-3 font-bold">Content</th>
                                                <th class="px-4 py-3 font-bold w-48">Category</th>
                                                <th class="px-4 py-3 font-bold w-16 text-center"><i class="bi bi-check2-all text-lg"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody id="notesBody" class="divide-y divide-gray-100 dark:divide-slate-700/50 block md:table-row-group">
                                        <?php 
                                        if (!function_exists('time_elapsed_string')) {
                                            function time_elapsed_string($datetime, $full = false) {
                                                $now = new DateTime();
                                                try { $ago = new DateTime($datetime); } catch (Exception $e) { return ''; }
                                                $diff = $now->diff($ago);
                                                $diff->w = floor($diff->d / 7);
                                                $diff->d -= $diff->w * 7;
                                                $string = array('y' => 'yr','m' => 'mo','w' => 'wk','d' => 'd','h' => 'h','i' => 'm','s' => 's');
                                                foreach ($string as $k => &$v) {
                                                    if ($diff->$k) { $v = $diff->$k . $v; } else { unset($string[$k]); }
                                                }
                                                if (!$full) $string = array_slice($string, 0, 1);
                                                return $string ? implode(', ', $string) . ' ago' : 'just now';
                                            }
                                        }
                                        ?>
                                        <?php foreach ($notifications as $note): ?>
                                            <?php 
                                            $isSelected = ($selectedId !== null && $selectedId === (int)$note['id']); 
                                            $isUnread = strtolower($note['status']) === 'unread';
                                            
                                            // Handle Time Calculation
                                            $createdTs = isset($note['created_at']) ? strtotime($note['created_at']) : null;
                                            $dispTime = $createdTs ? date('h:i A', $createdTs) : ($note['time'] ?? '');
                                            $dispDate = $createdTs ? date('Y-m-d', $createdTs) : ($note['date'] ?? '');
                                            
                                            $relativeTime = $createdTs ? time_elapsed_string('@'.$createdTs) : '';
                                            
                                            // Decorate Username in Content
                                            $safeContent = htmlspecialchars($note['content']);
                                            $safeContent = preg_replace('/\bUser\s+([a-zA-Z0-9\s\.\-]+?)(?=\s+(logged|requested|failed|registered|updated|created|deleted|viewed|unlocked|action|is)|\b)/i', 'User <span class="font-bold text-blue-600 dark:text-blue-400">$1</span>', $safeContent);
                                            
                                            // Decorate Badges
                                            $about = $note['about'];
                                            $badgeColor = 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600';
                                            if (stripos($about, 'Login') !== false) {
                                                $badgeColor = 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800/50';
                                            } elseif (stripos($about, 'Register') !== false || stripos($about, 'Registration') !== false) {
                                                $badgeColor = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50';
                                            }
                                            ?>
                                            <tr id="note-<?php echo (int)$note['id']; ?>" data-id="<?php echo (int)$note['id']; ?>" data-status="<?php echo htmlspecialchars($note['status']); ?>" class="<?php echo $isUnread ? 'bg-[#fff5f5] dark:bg-red-900/20 border-l-[3px] border-l-red-500' : 'bg-white dark:bg-slate-800 border-l-[3px] border-l-transparent text-gray-700 dark:text-slate-300'; ?> hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors md:table-row flex flex-col md:flex-row mb-2 md:mb-0 border border-gray-100 dark:border-slate-700/50 md:border-0 rounded-lg md:rounded-none shadow-sm md:shadow-none">
                                                <td class="px-4 py-3 text-sm font-medium opacity-70 text-center"><span class="md:hidden font-bold inline-block w-20 text-left">ID</span><?php echo (int)$note['id']; ?></td>
                                                <td class="px-4 py-3">
                                                    <span class="md:hidden font-bold inline-block w-20 text-left mb-1">Time</span>
                                                    <div class="inline-block align-top">
                                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100" title="<?php echo htmlspecialchars($dispDate); ?>"><?php echo htmlspecialchars($dispTime); ?></div>
                                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 font-medium" data-search="<?php echo htmlspecialchars($dispDate); ?>"><?php echo $relativeTime; ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm leading-relaxed">
                                                    <span class="md:hidden font-bold block w-20 text-left mb-1">Content</span>
                                                    <?php if (!empty($note['link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($note['link']); ?>" class="text-gray-900 dark:text-slate-100 hover:text-red-600 dark:hover:text-red-400 block"><?php echo $safeContent; ?></a>
                                                    <?php else: ?>
                                                        <span class="block"><?php echo $safeContent; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="md:hidden font-bold inline-block w-20 text-left">Cat.</span>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?php echo $badgeColor; ?>"><?php echo htmlspecialchars($about); ?></span>
                                                    <span class="hidden" data-search="<?php echo htmlspecialchars($about); ?>"></span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-center">
                                                    <?php $isReadBtn = strtolower($note['status']) === 'read'; ?>
                                                    <button class="mark-read-btn inline-flex items-center justify-center w-8 h-8 rounded-full border transition-all focus:outline-none focus:ring-2 focus:ring-red-500 <?php echo $isReadBtn ? 'bg-white dark:bg-slate-700 text-gray-400 dark:text-slate-400 border-gray-200 dark:border-slate-600 highlight-mark-read cursor-default' : 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800/50 hover:bg-red-600 hover:text-white dark:hover:bg-red-600'; ?>" type="button" data-status="<?php echo htmlspecialchars($note['status']); ?>" title="<?php echo $isReadBtn ? 'Read' : 'Mark as Read'; ?>">
                                                        <i class="<?php echo $isReadBtn ? 'bi bi-check2-all' : 'bi bi-check2'; ?> text-lg"></i>
                                                    </button>
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

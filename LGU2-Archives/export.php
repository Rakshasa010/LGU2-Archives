<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit();
}

require 'authdatabase.php';
$user_id = $_SESSION['user_id'];
$user_data = null;
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
	$user_data = $res->fetch_assoc();
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

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;
// Centralized notifications: load recent Export Request items
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    content TEXT NOT NULL,
    about VARCHAR(80) NOT NULL,
    status ENUM('unread','read') NOT NULL DEFAULT 'unread'
)");
$mock_notifications = [];
$unread_count = 0;
$resN = $conn->query("SELECT time, date, content, about, status FROM notifications WHERE about = 'Export Request' ORDER BY date DESC, id DESC LIMIT 50");
if ($resN) {
    while ($rowN = $resN->fetch_assoc()) {
        $mock_notifications[] = $rowN;
        if (isset($rowN['status']) && $rowN['status'] === 'unread') $unread_count++;
    }
}
if (count($mock_notifications) < 10) {
    $base = [
        ['Ordinance No. 12-2025 (PDF)', '08:15 AM', 'Export Request', 'unread'],
        ['Resolution 34 Series 2024 (DOCX)', '09:40 AM', 'Export Request', 'read'],
        ['Billing Report Q1 (XLSX)', '10:05 AM', 'Export Request', 'unread'],
        ['Public Hearing Minutes Jan (PDF)', '11:22 AM', 'Export Request', 'unread'],
        ['Meeting Attendance List (CSV)', '01:10 PM', 'Export Request', 'read'],
        ['Annual Summary 2025 (PDF)', '02:55 PM', 'Export Request', 'unread'],
        ['Session Agenda 03-12 (DOC)', '03:30 PM', 'Export Request', 'read'],
        ['Records Index Update (TXT)', '04:05 PM', 'Export Request', 'unread'],
        ['Audit Findings Draft (PDF)', '04:45 PM', 'Export Request', 'unread'],
        ['Metadata Export Batch #7 (JSON)', '05:20 PM', 'Export Request', 'read'],
        ['Supplemental Report (PDF)', '06:05 PM', 'Export Request', 'unread'],
    ];
    $today = date('Y-m-d');
    $needed = 10 - count($mock_notifications);
    for ($i = 0; $i < $needed; $i++) {
        $pick = $base[$i % count($base)];
        $mock_notifications[] = [
            'time' => $pick[1],
            'date' => $today,
            'content' => $pick[0],
            'about' => $pick[2],
            'status' => $pick[3],
        ];
        if ($pick[3] === 'unread') $unread_count++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Export - Document Management | City of Valenzuela</title>
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

            <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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
                    <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-speedometer2 mr-3"></i>
                        <span class="sidebar-text">Dashboard Archives</span>
                    </a>
                    
                    <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-folder mr-3"></i>
                        <span class="sidebar-text">Main Storage Archives</span>
                    </a>

                    <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-cloud-upload mr-3"></i>
                        <span class="sidebar-text">Export</span>
                    </a>

                        <?php if (isset($is_admin) && $is_admin): ?>
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
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Export</h2>
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
				<div class="max-w-5xl mx-auto space-y-6">
					<div class="bg-white dark:bg-slate-800/95 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
						<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
							<div>
								<h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Export Requests</h3>
								<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Minimal, focused list of recent export activity.</p>
							</div>
							<div class="flex items-center gap-2">
								<div class="relative">
									<input id="export-search" type="text" class="peer w-56 sm:w-64 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-gray-800 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Search requests">
									<i class="bi bi-search absolute right-3 top-2.5 text-gray-400 dark:text-gray-300"></i>
								</div>
								<button id="filter-unread" class="px-3 py-2 text-xs font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white border border-transparent dark:bg-red-700 dark:hover:bg-red-800 dark:text-white" aria-pressed="false">Unread</button>
								<button id="filter-today" class="px-3 py-2 text-xs font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white border border-transparent dark:bg-red-700 dark:hover:bg-red-800 dark:text-white" aria-pressed="false">Today</button>
								<button id="filter-week" class="px-3 py-2 text-xs font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white border border-transparent dark:bg-red-700 dark:hover:bg-red-800 dark:text-white" aria-pressed="false">This Week</button>
							</div>
						</div>
						<div class="mt-6 divide-y divide-gray-200 dark:divide-slate-700" id="export-list">
							<?php foreach ($mock_notifications as $n): ?>
								<div class="export-item flex items-start gap-4 py-4 first:pt-0 last:pb-0" data-status="<?php echo htmlspecialchars($n['status']); ?>" data-content="<?php echo htmlspecialchars($n['content']); ?>" data-date="<?php echo htmlspecialchars($n['date']); ?>">
									<div class="flex-shrink-0 mt-0.5">
										<div class="w-10 h-10 rounded-md bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
											<i class="bi bi-cloud-arrow-up-fill text-lg"></i>
										</div>
									</div>
									<div class="flex-1 min-w-0">
										<div class="flex items-center justify-between">
											<div class="truncate text-sm text-gray-800 dark:text-gray-200 font-medium"><?php echo htmlspecialchars($n['content']); ?></div>
											<div class="flex items-center gap-3">
												<span class="hidden sm:inline-flex px-2 py-0.5 text-[11px] rounded-md border <?php echo $n['status'] === 'unread' ? 'border-red-300 text-red-700 bg-red-50 dark:border-red-700 dark:text-red-300 dark:bg-red-900/20' : 'border-gray-300 text-gray-700 bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:bg-slate-700'; ?>"><?php echo htmlspecialchars(ucfirst($n['status'])); ?></span>
												<div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($n['time']); ?></div>
											</div>
										</div>
										<div class="mt-1.5 flex items-center justify-between">
											<div class="text-xs text-gray-500 dark:text-gray-400 truncate"><?php echo htmlspecialchars($n['about']); ?> • <?php echo htmlspecialchars($n['date']); ?></div>
											<div class="flex items-center gap-2">
												<button class="view-btn px-3 py-1.5 text-xs rounded-md bg-red-600 hover:bg-red-700 text-white border border-transparent">View</button>
												<button class="send-to-btn px-3 py-1.5 text-xs rounded-md bg-red-600 hover:bg-red-700 text-white border border-transparent flex items-center gap-1" data-content="<?php echo htmlspecialchars($n['content']); ?>">
													<i class="bi bi-send"></i>
													<span>Send To</span>
												</button>
											</div>
										</div>
										<div class="export-details hidden mt-3 rounded-lg border border-gray-200 dark:border-slate-700 p-3 bg-gray-50 dark:bg-slate-700/40">
											<div class="text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($n['content']); ?></div>
											<div class="mt-2 text-xs text-gray-600 dark:text-gray-400">Status: <?php echo htmlspecialchars(ucfirst($n['status'])); ?> • Time: <?php echo htmlspecialchars($n['time']); ?> • Date: <?php echo htmlspecialchars($n['date']); ?></div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<div id="export-empty" class="hidden mt-6 rounded-lg border border-dashed border-gray-300 dark:border-slate-600 p-6 text-center">
							<div class="mx-auto w-10 h-10 rounded-md bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-gray-300">
								<i class="bi bi-inbox"></i>
							</div>
							<p class="mt-3 text-sm text-gray-600 dark:text-gray-400">No matching export requests.</p>
						</div>
					</div>
				</div>
			</main>
		</div>
		</div>

		<div id="send-to-modal" class="fixed inset-0 z-50 hidden">
			<div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
			<div class="relative max-w-md mx-auto mt-24 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6">
				<div class="flex items-center gap-3 mb-4">
					<div class="w-10 h-10 rounded-md bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
						<i class="bi bi-send"></i>
					</div>
					<div>
						<div class="text-base font-semibold text-gray-900 dark:text-gray-100">Send To</div>
						<div id="send-to-file" class="text-xs text-gray-500 dark:text-gray-400"></div>
					</div>
				</div>
				<div class="space-y-2">
					<label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
						<input type="radio" name="sendToDest" value="Sessions" class="accent-red-600">
						<span class="text-sm text-gray-800 dark:text-gray-200">Sessions</span>
					</label>
					<label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
						<input type="radio" name="sendToDest" value="Records" class="accent-red-600">
						<span class="text-sm text-gray-800 dark:text-gray-200">Records</span>
					</label>
					<label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
						<input type="radio" name="sendToDest" value="Meetings" class="accent-red-600">
						<span class="text-sm text-gray-800 dark:text-gray-200">Meetings</span>
					</label>
					<label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
						<input type="radio" name="sendToDest" value="Others" class="accent-red-600">
						<span class="text-sm text-gray-800 dark:text-gray-200">Others</span>
					</label>
				</div>
				<div class="mt-6 flex justify-end gap-2">
					<button id="send-to-cancel" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600">Cancel</button>
					<button id="send-to-confirm" class="px-4 py-2 text-sm rounded-lg bg-red-700 hover:bg-red-800 text-white">Send</button>
				</div>
			</div>
		</div>

	<script src="assets/js/archives-landing.js"></script>
	<script src="assets/js/theme-toggle.js"></script>
	<script>
		(function () {
			const list = document.getElementById('export-list');
			const sendToModal = document.getElementById('send-to-modal');
			const sendToFile = document.getElementById('send-to-file');
			const sendToCancel = document.getElementById('send-to-cancel');
			const sendToConfirm = document.getElementById('send-to-confirm');
			let sendFile = '';
			const search = document.getElementById('export-search');
			const unreadBtn = document.getElementById('filter-unread');
			const todayBtn = document.getElementById('filter-today');
			const weekBtn = document.getElementById('filter-week');
			const empty = document.getElementById('export-empty');
			let onlyUnread = false;
			let onlyToday = false;
			let onlyWeek = false;
			let q = '';
			function isDark() {
				return document.documentElement.classList.contains('dark');
			}
			function updateUnreadBtnStyle() {
				unreadBtn.classList.remove('bg-red-600','bg-red-700');
				unreadBtn.classList.add(onlyUnread ? 'bg-red-700' : 'bg-red-600');
			}
			function isToday(dateStr) {
				const d = new Date(dateStr + 'T00:00:00');
				const now = new Date();
				return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
			}
			function isThisWeek(dateStr) {
				const d = new Date(dateStr + 'T00:00:00');
				const now = new Date();
				const diff = now.getTime() - d.getTime();
				const days = diff / (1000 * 60 * 60 * 24);
				return days >= 0 && days <= 6;
			}
			function apply() {
				const items = list.querySelectorAll('.export-item');
				let shown = 0;
				items.forEach(el => {
					const status = el.getAttribute('data-status') || '';
					const content = el.getAttribute('data-content') || '';
					const dateStr = el.getAttribute('data-date') || '';
					const matchText = content.toLowerCase().includes(q);
					const matchUnread = !onlyUnread || status === 'unread';
					const matchDate = (!onlyToday && !onlyWeek) || (onlyToday && isToday(dateStr)) || (onlyWeek && isThisWeek(dateStr));
					const visible = matchText && matchUnread && matchDate;
					el.style.display = visible ? '' : 'none';
					if (visible) shown++;
				});
				empty.classList.toggle('hidden', shown !== 0);
				updateUnreadBtnStyle();
			}
			search.addEventListener('input', function () {
				q = this.value.trim().toLowerCase();
				apply();
			});
			unreadBtn.addEventListener('click', function () {
				onlyUnread = !onlyUnread;
				this.setAttribute('aria-pressed', String(onlyUnread));
				apply();
			});
			function updateToggle(btn, active) {
				btn.classList.remove('bg-red-600','bg-red-700');
				btn.classList.add(active ? 'bg-red-700' : 'bg-red-600');
			}
			todayBtn.addEventListener('click', function () {
				onlyToday = !onlyToday;
				this.setAttribute('aria-pressed', String(onlyToday));
				updateToggle(this, onlyToday);
				apply();
			});
			weekBtn.addEventListener('click', function () {
				onlyWeek = !onlyWeek;
				this.setAttribute('aria-pressed', String(onlyWeek));
				updateToggle(this, onlyWeek);
				apply();
			});
			const mo = new MutationObserver(() => updateUnreadBtnStyle());
			mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
			apply();
			list.addEventListener('click', function (e) {
				const vb = e.target.closest('.view-btn');
				if (vb) {
					const item = vb.closest('.export-item');
					if (!item) return;
					const det = item.querySelector('.export-details');
					if (!det) return;
					const hidden = det.classList.contains('hidden');
					det.classList.toggle('hidden');
					vb.textContent = hidden ? 'Hide' : 'View';
					return;
				}
				const btn = e.target.closest('.send-to-btn');
				if (!btn) return;
				sendFile = btn.getAttribute('data-content') || '';
				sendToFile.textContent = sendFile;
				sendToModal.classList.remove('hidden');
				document.body.style.overflow = 'hidden';
			});
			sendToCancel.addEventListener('click', function () {
				sendToModal.classList.add('hidden');
				document.body.style.overflow = '';
			});
			sendToConfirm.addEventListener('click', function () {
				const chosen = document.querySelector('input[name="sendToDest"]:checked');
				if (!chosen) return;
				sendToModal.classList.add('hidden');
				document.body.style.overflow = '';
				alert('Sent "' + sendFile + '" to ' + chosen.value);
			});
		})();
	</script>
</body>
</html>

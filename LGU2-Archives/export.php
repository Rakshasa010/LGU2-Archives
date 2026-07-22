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
function map_export_link($title, $location = '') {
    $loc = strtolower(trim((string)$location));
    $t = strtolower(trim((string)$title));
    if ($loc === 'billing') return 'billing.php';
    if ($loc === 'meeting records') return 'meeting-records.php';
    if ($loc === 'ordinances & resolutions') return 'ordinances-resolution.php';
    if ($loc === 'public hearings') return 'public-hearings.php';
    if (strpos($t, 'ordinance') !== false || strpos($t, 'resolution') !== false) return 'ordinances-resolution.php';
    if (strpos($t, 'meeting') !== false || strpos($t, 'session') !== false) return 'meeting-records.php';
    if (strpos($t, 'public hearing') !== false || strpos($t, 'hearing') !== false) return 'public-hearings.php';
    if (strpos($t, 'billing') !== false) return 'billing.php';
    return 'storage.php';
}

// Centralized notifications: load recent Request Copy items
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    content TEXT NOT NULL,
    about VARCHAR(80) NOT NULL,
    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
    link VARCHAR(255) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_version VARCHAR(60) DEFAULT NULL,
    needed_date DATE DEFAULT NULL,
    request_note TEXT,
    purpose VARCHAR(255) DEFAULT NULL,
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

$export_notice = null;
$export_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_request'])) {
    $file_name = trim((string)($_POST['file_name'] ?? ''));
    $file_version = trim((string)($_POST['file_version'] ?? 'Latest'));
    $needed_date = trim((string)($_POST['needed_date'] ?? ''));
    $request_note = trim((string)($_POST['request_note'] ?? ''));
    $purpose = trim((string)($_POST['purpose'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));

    if ($file_name === '') {
        $export_error = "File name is required.";
    } else {
        $link = map_export_link($file_name, $location);
        $ntime = date('h:i A');
        $ndate = date('Y-m-d');
        $about = 'Request Copy';
        $status = 'unread';
        $needed_date = $needed_date !== '' ? $needed_date : null;
        $request_note = $request_note !== '' ? $request_note : null;
        $purpose = $purpose !== '' ? $purpose : null;

        if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status, file_name, file_version, needed_date, request_note, purpose, link) VALUES (?,?,?,?,?,?,?,?,?,?,?)")) {
            $ins->bind_param("sssssssssss", $ntime, $ndate, $file_name, $about, $status, $file_name, $file_version, $needed_date, $request_note, $purpose, $link);
            if ($ins->execute()) {
                $export_notice = "Request Copy created for " . $file_name . ".";
            } else {
                $export_error = "Failed to save Request Copy.";
            }
            $ins->close();
        } else {
            $export_error = "Unable to prepare Request Copy.";
        }
    }
}

// Fetch archive folders for sidebar
$archive_folders = [];
$folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
if ($folders_result && $folders_result->num_rows > 0) {
    while ($row = $folders_result->fetch_assoc()) {
        $archive_folders[] = $row;
    }
}

// Fetch requests from requests table
$requestsStmt = $conn->prepare("SELECT * FROM requests ORDER BY date_requested DESC");
$requestsStmt->execute();
$mock_notifications = $requestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unread_count = 0;
$totalreqs = count($mock_notifications);
$readreqs = 0;
$duetodayreqs = 0;
$todayStr = date('Y-m-d');
foreach ($mock_notifications as $req) {
    // For now, let's treat Pending as unread, Approved/Released/Denied as read
    if ($req['status'] === 'Pending') {
        $unread_count++;
    } else {
        $readreqs++;
    }
    // Check if due today - let's use date_requested as due date for now
    $reqDate = date('Y-m-d', strtotime($req['date_requested']));
    if ($reqDate === $todayStr) {
        $duetodayreqs++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Request Copy - Document Management | City of Valenzuela</title>
	<link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
	<link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="assets/js/archives-landing-head.js"></script>
	<script src="assets/js/theme-head.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
	<link rel="stylesheet" href="assets/css/archives-landing.css">
	<link rel="stylesheet" href="assets/css/audit-logs.css">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <div>
        <?php
        $sidebar_active_page = 'export';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col min-h-screen md:ml-64">
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
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Request Copy</h2>
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

                                <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 z-50">
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
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <i class="bi bi-gear mr-2"></i>Account Settings
                                        </a>
                                        <form action="logout.php" method="POST" class="block w-full">
                                            <button type="submit" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer w-full text-left">
                                                <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

			<style>
				@keyframes fadeInUp {
					from { opacity: 0; transform: translateY(12px); }
					to { opacity: 1; transform: translateY(0); }
				}
			</style>
			<main class="flex-1 overflow-y-auto bg-[#fafafa] dark:bg-[#0f1117]">
				<div class="w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6 max-w-[1600px] mx-auto">
                    <?php
                    $totalreqs = count($mock_notifications);
                    $unreadreqs = 0;
                    $readreqs = 0;
                    $duetodayreqs = 0;
                    $todayStr = date('Y-m-d');
                    foreach($mock_notifications as $nn) {
                        if ($nn['status'] === 'unread') $unreadreqs++;
                        else $readreqs++;
                        if (($nn['needed_date'] ?? '') === $todayStr) $duetodayreqs++;
                    }
                    ?>
					<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-2">
						<div>
							<h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">Request Copy Requests</h3>
							<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimal, focused list of recent requests.</p>
						</div>
						<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <div class="relative w-full sm:w-auto">
                                <input id="request-search" type="text" class="peer w-full sm:w-64 pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 shadow-sm transition-colors" placeholder="Search requests...">
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 dark:text-gray-500 text-sm"></i>
                            </div>
						</div>
					</div>

                    <!-- STATS BAR -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm transition-colors">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2"><i class="bi bi-inbox text-gray-400"></i> Total requests</div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalreqs; ?></div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm transition-colors">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2"><i class="bi bi-envelope-exclamation text-red-500"></i> Unread</div>
                            <div class="text-3xl font-bold text-red-500"><?php echo $unreadreqs; ?></div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm transition-colors">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2"><i class="bi bi-envelope-check text-blue-500"></i> Read</div>
                            <div class="text-3xl font-bold text-blue-500"><?php echo $readreqs; ?></div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm transition-colors">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2"><i class="bi bi-calendar-event text-amber-500"></i> Due today</div>
                            <div class="text-3xl font-bold text-amber-500"><?php echo $duetodayreqs; ?></div>
                        </div>
                    </div>

                    <!-- ANALYTICS SECTION -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <i class="bi bi-bar-chart text-red-600"></i>
                            Reports & Analytics
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Line Chart: Requests Over Time -->
                            <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Requests Over Time</h4>
                                <canvas id="requestsOverTimeChart"></canvas>
                            </div>
                            <!-- Pie Chart: Request Status -->
                            <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Request Status</h4>
                                <canvas id="statusPieChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- FILTER + SORT BAR -->
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Type Filter -->
                            <div class="relative">
                                <button id="filter-type-btn" class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-file-earmark"></i>
                                    Type
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </button>
                                <div id="filter-type-menu" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-30">
                                    <div class="py-1">
                                        <button class="type-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-type="all">All Types</button>
                                        <button class="type-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-type="pdf">PDF</button>
                                        <button class="type-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-type="doc">DOC/DOCX</button>
                                        <button class="type-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-type="xls">XLS/XLSX/CSV</button>
                                        <button class="type-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-type="other">Other</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Filter -->
                            <div class="relative">
                                <button id="filter-status-btn" class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-circle"></i>
                                    Status
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </button>
                                <div id="filter-status-menu" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-30">
                                    <div class="py-1">
                                        <button class="status-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-status="all">All</button>
                                        <button class="status-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-status="unread">Unread</button>
                                        <button class="status-filter-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-status="read">Read</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Grouping -->
                            <div class="relative">
                                <button id="group-date-btn" class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-calendar3"></i>
                                    <span id="group-date-label">Daily</span>
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </button>
                                <div id="group-date-menu" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-30">
                                    <div class="py-1">
                                        <button class="group-date-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-group="daily">Daily</button>
                                        <button class="group-date-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-group="monthly">Monthly</button>
                                        <button class="group-date-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-group="yearly">Yearly</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <button id="sort-btn" class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-sort-alpha-down"></i>
                                    <span id="sort-label">Name</span>
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </button>
                                <div id="sort-menu" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-30">
                                    <div class="py-1">
                                        <button class="sort-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-sort="name">Name</button>
                                        <button class="sort-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-sort="date">Date</button>
                                        <button class="sort-option block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-sort="status">Status</button>
                                    </div>
                                </div>
                            </div>

                            <button id="sort-direction-btn" class="p-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" title="Toggle sort direction">
                                <i id="sort-direction-icon" class="bi bi-arrow-down-up text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- REQUEST GRID -->
                    <div id="request-grid-container">
                        <div id="request-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($mock_notifications as $index => $n): ?>
                                <?php
                                $fname = htmlspecialchars("Request #{$n['id']} - {$n['requester_name']}");
                                $typeIcon = 'bi-person';
                                $typeBg = 'bg-gray-100 dark:bg-slate-700/50 text-gray-600 dark:text-gray-400';
                                $fileType = 'other';

                                $isUnread = $n['status'] === 'Pending';
                                $submittedDate = date('Y-m-d', strtotime($n['date_requested']));
                                $submittedTime = date('H:i A', strtotime($n['date_requested']));
                                ?>
                                <div class="request-item relative bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 hover:shadow-sm transition-all duration-200 cursor-pointer group"
                                     data-id="<?php echo htmlspecialchars($n['id'] ?? ''); ?>"
                                     data-status="<?php echo htmlspecialchars($n['status']); ?>"
                                     data-content="<?php echo $fname; ?>"
                                     data-date="<?php echo htmlspecialchars($submittedDate); ?>"
                                     data-type="<?php echo $fileType; ?>"
                                     data-requester-name="<?php echo htmlspecialchars($n['requester_name']); ?>"
                                     data-department="<?php echo htmlspecialchars($n['department']); ?>"
                                     data-purpose="<?php echo htmlspecialchars($n['purpose']); ?>"
                                     data-contact-info="<?php echo htmlspecialchars($n['contact_info']); ?>"
                                     data-submitted-date="<?php echo htmlspecialchars($submittedDate); ?>"
                                     data-submitted-time="<?php echo htmlspecialchars($submittedTime); ?>"
                                     aria-label="<?php echo $fname; ?> - <?php echo $isUnread ? 'Unread' : 'Read'; ?>"
                                     tabindex="0"
                                     style="animation: fadeInUp 0.3s ease-out forwards; animation-delay: <?php echo $index * 0.03; ?>s; opacity: 0;"
                                >
                                    <!-- Status Dot -->
                                    <div class="absolute top-3 left-3 z-10">
                                        <span class="w-3 h-3 rounded-full <?php echo $isUnread ? 'bg-red-500' : 'bg-gray-400'; ?> shadow-sm"></span>
                                    </div>

                                    <!-- Three Dot Menu -->
                                    <div class="absolute top-3 right-3 z-10">
                                        <button class="item-menu-btn p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors" aria-label="File options">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                    </div>

                                    <!-- File Icon Area -->
                                    <div class="flex items-center justify-center p-6">
                                        <div class="w-16 h-16 rounded-lg <?php echo $typeBg; ?> flex items-center justify-center transition-colors group-hover:scale-105 duration-200">
                                            <i class="bi <?php echo $typeIcon; ?> text-3xl"></i>
                                        </div>
                                    </div>

                                    <!-- File Name -->
                                    <div class="px-3 pb-4 text-center">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" title="<?php echo $fname; ?>">
                                            <?php echo htmlspecialchars($n['requester_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <?php echo htmlspecialchars($submittedDate); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="request-empty" class="hidden mt-10 rounded-xl border border-dashed border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800/50 p-10 text-center shadow-sm">
                            <div class="mx-auto w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-500 dark:text-red-400 border border-red-100 dark:border-red-900/50">
                                <i class="bi bi-inbox text-xl"></i>
                            </div>
                            <h4 class="mt-4 text-base font-bold text-gray-900 dark:text-gray-100">No requests found</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No matching Request Copy requests fit this filter.</p>
                        </div>
                    </div>
				</div>
			</main>
		<?php include 'includes/footer.php'; ?>
		</div>
	</div>
	</div>

        <!-- Floating Action Button -->
        <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2 group">
            <div class="flex-col items-end gap-2 hidden group-hover:flex transition-all duration-300 transform translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 mb-2">
                <button id="open-request-modal" type="button" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow-md border border-gray-200 dark:border-slate-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    <i class="bi bi-file-earmark-plus text-red-600 dark:text-red-400"></i>New Request
                </button>
            </div>
            <button class="w-14 h-14 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg flex items-center justify-center focus:outline-none transition-transform hover:scale-105">
                <i class="bi bi-plus-lg text-2xl transition-transform duration-300 group-hover:rotate-45"></i>
            </button>
        </div>

        <!-- New Request Modal -->
        <div id="request-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative max-w-2xl mx-auto mt-16 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-md bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    <div>
                        <div class="text-base font-semibold text-gray-900 dark:text-gray-100">New Request Copy</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Provide the exact file and request details.</div>
                    </div>
                </div>
                <?php if (!empty($export_error)): ?>
                    <div class="mb-3 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/30 rounded-lg px-3 py-2">
                        <?php echo htmlspecialchars($export_error); ?>
                    </div>
                <?php endif; ?>
                <form action="export.php" method="POST" class="space-y-4">
                    <input type="hidden" name="export_request" value="1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">File Name</label>
                            <input type="text" name="file_name" required value="<?php echo htmlspecialchars($_POST['file_name'] ?? ''); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Version</label>
                            <input type="text" name="file_version" value="<?php echo htmlspecialchars($_POST['file_version'] ?? 'Latest'); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Needed Date</label>
                            <input type="date" name="needed_date" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Location</label>
                            <select name="location" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500">
                                <option value="">Auto-detect</option>
                                <option value="Billing" <?php echo (($_POST['location'] ?? '') === 'Billing') ? 'selected' : ''; ?>>Billing</option>
                                <option value="Meeting Records" <?php echo (($_POST['location'] ?? '') === 'Meeting Records') ? 'selected' : ''; ?>>Meeting Records</option>
                                <option value="Ordinances & Resolutions" <?php echo (($_POST['location'] ?? '') === 'Ordinances & Resolutions') ? 'selected' : ''; ?>>Ordinances & Resolutions</option>
                                <option value="Public Hearings" <?php echo (($_POST['location'] ?? '') === 'Public Hearings') ? 'selected' : ''; ?>>Public Hearings</option>
                                <option value="Storage" <?php echo (($_POST['location'] ?? '') === 'Storage') ? 'selected' : ''; ?>>Storage</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Request Note</label>
                        <textarea name="request_note" rows="3" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500"><?php echo htmlspecialchars($_POST['request_note'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Purpose</label>
                        <input type="text" name="purpose" value="<?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500">
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button id="request-cancel" type="button" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-red-700 hover:bg-red-800 text-white">Create Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detail Modal (Modal #1) -->
        <div id="detail-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative max-w-2xl mx-auto mt-12 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div id="detail-icon-container" class="w-12 h-12 rounded-md bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
                            <i id="detail-icon" class="bi bi-file-earmark text-2xl"></i>
                        </div>
                        <div>
                            <div id="detail-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100"></div>
                            <div id="detail-status" class="text-xs text-gray-500 dark:text-gray-400"></div>
                        </div>
                    </div>
                    <button id="detail-close" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Requester</label>
                            <p id="detail-requester" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Department</label>
                            <p id="detail-department" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Version</label>
                            <p id="detail-version" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Needed By</label>
                            <p id="detail-needed" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Submitted Date</label>
                            <p id="detail-submitted-date" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Submitted Time</label>
                            <p id="detail-submitted-time" class="text-sm text-gray-900 dark:text-gray-100"></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Purpose</label>
                        <p id="detail-purpose" class="text-sm text-gray-900 dark:text-gray-100"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Request Note</label>
                        <p id="detail-note" class="text-sm text-gray-800 dark:text-gray-300 bg-gray-50 dark:bg-slate-900 rounded-lg p-3 border border-gray-100 dark:border-slate-700/50"></p>
                    </div>

                    <!-- Staged Attachment Status -->
                    <div id="staged-attachment-container" class="hidden bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-900/50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-emerald-900 dark:text-emerald-100">Staged Attachment: <span id="staged-file-name" class="font-semibold"></span></p>
                                <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-1"><span id="staged-file-size"></span> · Ready for export</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-slate-700">
                        <button id="detail-open-storage" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class="bi bi-folder-open"></i>Open Storage
                        </button>
                        <div class="flex gap-2">
                            <button id="detail-export-btn" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Export Package
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Storage Browser Modal (Modal #2) -->
        <div id="storage-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative max-w-4xl mx-auto mt-12 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6 max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-slate-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Storage Browser</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select a file to create a copy for export</p>
                    </div>
                    <button id="storage-close" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>

                <!-- Search Bar -->
                <div class="mb-4">
                    <input type="text" id="storage-search" placeholder="Search files by name..." class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto min-h-0">
                    <!-- Folder Tabs -->
                    <div id="storage-folders" class="mb-4 flex flex-wrap gap-2"></div>

                    <!-- Files List -->
                    <div class="space-y-2">
                        <div id="storage-files-container" class="space-y-2">
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <i class="bi bi-hourglass-split text-3xl mb-2"></i>
                                <p class="text-sm">Loading files...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700 flex justify-end gap-2">
                    <button id="storage-cancel" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

	<script src="assets/js/archives-landing.js"></script>
	<script src="assets/js/theme-toggle.js"></script>
	<script src="assets/js/export-fulfillment.js"></script>
	<script>
		// Chart initialization
		(function () {
            const requestItems = document.querySelectorAll('.request-item');
            const requestModal = document.getElementById('request-modal');
            const openRequestBtn = document.getElementById('open-request-modal');
            const requestCancel = document.getElementById('request-cancel');

            // Open new request modal
            function openRequestModal() {
                if (!requestModal) return;
                requestModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            function closeRequestModal() {
                if (!requestModal) return;
                requestModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            if (openRequestBtn) openRequestBtn.addEventListener('click', openRequestModal);
            if (requestCancel) requestCancel.addEventListener('click', closeRequestModal);
            <?php if (!empty($export_error) || !empty($export_notice)): ?>
            openRequestModal();
            <?php endif; ?>
            <?php if (!empty($export_notice)): ?>
            try { UI_ENH.toast('<?php echo htmlspecialchars($export_notice, ENT_QUOTES); ?>', {background:'linear-gradient(90deg,#4ade80,#10b981)'}); } catch(e) {}
            <?php endif; ?>            // Initialize Charts
            const requestsData = <?php echo json_encode($mock_notifications); ?>;

            // Requests Over Time (Line Chart)
            const requestsByDate = {};
            requestsData.forEach(req => {
                const date = req.date_requested.split(' ')[0];
                requestsByDate[date] = (requestsByDate[date] || 0) + 1;
            });
            const dates = Object.keys(requestsByDate).sort();
            const counts = dates.map(d => requestsByDate[d]);

            const lineCtx = document.getElementById('requestsOverTimeChart');
            if (lineCtx) {
                new Chart(lineCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Requests',
                            data: counts,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // Status Pie Chart
            const statusCounts = {
                Pending: 0,
                Approved: 0,
                Released: 0,
                Denied: 0
            };
            requestsData.forEach(req => {
                statusCounts[req.status] = (statusCounts[req.status] || 0) + 1;
            });
            const statusLabels = Object.keys(statusCounts);
            const statusData = Object.values(statusCounts);
            const statusColors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'];

            const pieCtx = document.getElementById('statusPieChart');
            if (pieCtx) {
                new Chart(pieCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusData,
                            backgroundColor: statusColors
                        }]
                    },
                    options: { responsive: true }
                });
            }
		})();
	</script>
</body>
</html>
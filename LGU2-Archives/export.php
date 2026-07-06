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

// Centralized notifications: load recent Export Request items
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
        $about = 'Export Request';
        $status = 'unread';
        $needed_date = $needed_date !== '' ? $needed_date : null;
        $request_note = $request_note !== '' ? $request_note : null;
        $purpose = $purpose !== '' ? $purpose : null;

        if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status, file_name, file_version, needed_date, request_note, purpose, link) VALUES (?,?,?,?,?,?,?,?,?,?,?)")) {
            $ins->bind_param("sssssssssss", $ntime, $ndate, $file_name, $about, $status, $file_name, $file_version, $needed_date, $request_note, $purpose, $link);
            if ($ins->execute()) {
                $export_notice = "Export request created for " . $file_name . ".";
            } else {
                $export_error = "Failed to save export request.";
            }
            $ins->close();
        } else {
            $export_error = "Unable to prepare export request.";
        }
    }
}

$mock_notifications = [];
$unread_count = 0;
$resN = $conn->query("SELECT id, time, date, content, about, status, file_name, file_version, needed_date, request_note, purpose, link FROM notifications WHERE about = 'Export Request' ORDER BY date DESC, id DESC LIMIT 50");
if ($resN) {
    while ($rowN = $resN->fetch_assoc()) {
        $rowN['file_name'] = $rowN['file_name'] ?? $rowN['content'];
        $rowN['file_version'] = $rowN['file_version'] ?? 'Latest';
        $rowN['needed_date'] = $rowN['needed_date'] ?? $rowN['date'];
        $rowN['request_note'] = $rowN['request_note'] ?? 'No additional notes provided.';
        $rowN['purpose'] = $rowN['purpose'] ?? 'General reference';
        if (empty($rowN['link'])) $rowN['link'] = map_export_link($rowN['file_name'] ?? $rowN['content']);
        $mock_notifications[] = $rowN;
        if (isset($rowN['status']) && $rowN['status'] === 'unread') $unread_count++;
    }
}
if (count($mock_notifications) < 10) {
    $today = date('Y-m-d');
    $base = [
        [
            'content' => 'Ordinance No. 12-2025 (PDF)',
            'time' => '08:15 AM',
            'status' => 'unread',
            'file_version' => 'v2',
            'needed_date' => date('Y-m-d', strtotime('+2 days')),
            'request_note' => 'Need final signed copy for committee review.',
            'purpose' => 'Council packet',
            'link' => 'ordinances-resolution.php'
        ],
        [
            'content' => 'Resolution 34 Series 2024 (DOCX)',
            'time' => '09:40 AM',
            'status' => 'read',
            'file_version' => 'v1',
            'needed_date' => date('Y-m-d', strtotime('+3 days')),
            'request_note' => 'Include attachments and appendices.',
            'purpose' => 'Legal validation',
            'link' => 'ordinances-resolution.php'
        ],
        [
            'content' => 'Billing Report Q1 (XLSX)',
            'time' => '10:05 AM',
            'status' => 'unread',
            'file_version' => 'Latest',
            'needed_date' => date('Y-m-d', strtotime('+1 day')),
            'request_note' => 'Please export with summaries tab included.',
            'purpose' => 'Finance review',
            'link' => 'billing.php'
        ],
        [
            'content' => 'Public Hearing Minutes Jan (PDF)',
            'time' => '11:22 AM',
            'status' => 'unread',
            'file_version' => 'v3',
            'needed_date' => date('Y-m-d', strtotime('+4 days')),
            'request_note' => 'For public disclosure request.',
            'purpose' => 'Public access',
            'link' => 'public-hearings.php'
        ],
        [
            'content' => 'Meeting Attendance List (CSV)',
            'time' => '01:10 PM',
            'status' => 'read',
            'file_version' => 'v5',
            'needed_date' => date('Y-m-d', strtotime('+2 days')),
            'request_note' => 'Verify participants list before sending.',
            'purpose' => 'Compliance',
            'link' => 'meeting-records.php'
        ],
        [
            'content' => 'Annual Summary 2025 (PDF)',
            'time' => '02:55 PM',
            'status' => 'unread',
            'file_version' => 'Final',
            'needed_date' => date('Y-m-d', strtotime('+5 days')),
            'request_note' => 'Latest approved version only.',
            'purpose' => 'Executive briefing',
            'link' => 'storage.php'
        ],
        [
            'content' => 'Session Agenda 03-12 (DOC)',
            'time' => '03:30 PM',
            'status' => 'read',
            'file_version' => 'v1',
            'needed_date' => date('Y-m-d', strtotime('+1 day')),
            'request_note' => 'Include agenda revisions.',
            'purpose' => 'Meeting prep',
            'link' => 'meeting-records.php'
        ],
        [
            'content' => 'Records Index Update (TXT)',
            'time' => '04:05 PM',
            'status' => 'unread',
            'file_version' => 'Latest',
            'needed_date' => date('Y-m-d', strtotime('+3 days')),
            'request_note' => 'Include filenames and dates.',
            'purpose' => 'Indexing',
            'link' => 'storage.php'
        ],
        [
            'content' => 'Audit Findings Draft (PDF)',
            'time' => '04:45 PM',
            'status' => 'unread',
            'file_version' => 'Draft',
            'needed_date' => date('Y-m-d', strtotime('+6 days')),
            'request_note' => 'Redact sensitive sections.',
            'purpose' => 'Audit review',
            'link' => 'storage.php'
        ],
        [
            'content' => 'Metadata Export Batch #7 (JSON)',
            'time' => '05:20 PM',
            'status' => 'read',
            'file_version' => 'Latest',
            'needed_date' => date('Y-m-d', strtotime('+2 days')),
            'request_note' => 'Ensure JSON schema v2.',
            'purpose' => 'System sync',
            'link' => 'storage.php'
        ],
        [
            'content' => 'Supplemental Report (PDF)',
            'time' => '06:05 PM',
            'status' => 'unread',
            'file_version' => 'v2',
            'needed_date' => date('Y-m-d', strtotime('+4 days')),
            'request_note' => 'Include annex pages.',
            'purpose' => 'Council packet',
            'link' => 'storage.php'
        ],
    ];
    $today = date('Y-m-d');
    $needed = 10 - count($mock_notifications);
    for ($i = 0; $i < $needed; $i++) {
        $pick = $base[$i % count($base)];
        $mock_notifications[] = [
            'id' => null,
            'time' => $pick['time'],
            'date' => $today,
            'content' => $pick['content'],
            'about' => 'Export Request',
            'status' => $pick['status'],
            'file_name' => $pick['content'],
            'file_version' => $pick['file_version'] ?? 'Latest',
            'needed_date' => $pick['needed_date'] ?? $today,
            'request_note' => $pick['request_note'] ?? 'No additional notes provided.',
            'purpose' => $pick['purpose'] ?? 'General reference',
            'link' => $pick['link'] ?? map_export_link($pick['content']),
        ];
        if ($pick['status'] === 'unread') $unread_count++;
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
<body class="min-h-screen bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <div class="flex min-h-screen">
    <?php
    $sidebar_active_page = 'export';
    $sidebar_include_overlay = true;
    require_once 'includes/sidebar-centralized.php';
    ?>

        <div class="flex-1 min-h-0">
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

			<style>
				@keyframes fadeInUp {
					from { opacity: 0; transform: translateY(12px); }
					to { opacity: 1; transform: translateY(0); }
				}
			</style>
			<main class="flex-1 overflow-y-auto bg-[#fafafa] dark:bg-[#0f1117]">
				<div class="w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6 max-w-[1400px] mx-auto">
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
							<h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">Export Requests</h3>
							<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimal, focused list of recent export activity.</p>
						</div>
						<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <div class="relative w-full sm:w-auto">
                                <input id="export-search" type="text" class="peer w-full sm:w-64 pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 shadow-sm transition-colors" placeholder="Search requests...">
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 dark:text-gray-500 text-sm"></i>
                            </div>
							<div class="flex bg-gray-200/50 dark:bg-slate-800/80 rounded-lg p-1 border border-gray-200 dark:border-slate-700/50 shadow-sm w-full sm:w-auto overflow-x-auto">
								<button id="filter-all" class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md text-gray-600 dark:text-gray-400 filter-btn transition-colors hover:text-gray-900 dark:hover:text-gray-100 whitespace-nowrap" aria-pressed="false">All</button>
								<button id="filter-unread" class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md text-gray-600 dark:text-gray-400 filter-btn transition-colors hover:text-gray-900 dark:hover:text-gray-100 whitespace-nowrap" aria-pressed="false">Unread</button>
								<button id="filter-today" class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md text-gray-600 dark:text-gray-400 filter-btn transition-colors hover:text-gray-900 dark:hover:text-gray-100 whitespace-nowrap" aria-pressed="false">Today</button>
								<button id="filter-week" class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md text-gray-600 dark:text-gray-400 filter-btn transition-colors hover:text-gray-900 dark:hover:text-gray-100 whitespace-nowrap" aria-pressed="false">This Week</button>
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" id="export-list">
                        <?php foreach ($mock_notifications as $index => $n): ?>
                            <?php
                            $fname = htmlspecialchars($n['file_name'] ?? $n['content']);
                            $typeIcon = 'bi-file-earmark-text';
                            $typeBg = 'bg-gray-100 dark:bg-slate-700/50 text-gray-600 dark:text-gray-400';
                            $fup = strtoupper($fname);
                            if (strpos($fup, '.PDF') !== false || strpos($fup, '(PDF)') !== false) {
                                $typeIcon = 'bi-file-earmark-pdf';
                                $typeBg = 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
                            } elseif (strpos($fup, '.DOC') !== false || strpos($fup, '(DOC') !== false) {
                                $typeIcon = 'bi-file-earmark-word';
                                $typeBg = 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400';
                            } elseif (strpos($fup, '.XLS') !== false || strpos($fup, '(XLS') !== false || strpos($fup, '.CSV') !== false || strpos($fup, '(CSV)') !== false) {
                                $typeIcon = 'bi-file-earmark-spreadsheet';
                                $typeBg = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400';
                            }

                            $isUnread = $n['status'] === 'unread';
                            ?>
                            <div class="export-item flex flex-col justify-between rounded-xl border <?php echo $isUnread ? 'border-l-[3px] border-l-red-500 border-y-gray-200 border-r-gray-200 dark:border-y-slate-700 dark:border-r-slate-700' : 'border-gray-200 dark:border-slate-700'; ?> bg-white dark:bg-slate-800 p-5 shadow-sm transition-all hover:shadow-md relative cursor-pointer group" style="animation: fadeInUp 0.4s ease-out forwards; animation-delay: <?php echo $index * 0.05; ?>s; opacity: 0;" tabindex="0" data-id="<?php echo htmlspecialchars($n['id'] ?? ''); ?>" data-link="<?php echo htmlspecialchars($n['link'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($n['status']); ?>" data-content="<?php echo $fname; ?>" data-date="<?php echo htmlspecialchars($n['date']); ?>">
                                
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg <?php echo $typeBg; ?> flex items-center justify-center transition-colors">
                                                <i class="bi <?php echo $typeIcon; ?> text-lg"></i>
                                            </div>
                                            <?php if ($isUnread): ?>
                                                <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 text-[10px] font-bold uppercase tracking-wider text-red-600 dark:text-red-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Unread
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600/50 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Read
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="relative">
                                            <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors" onclick="document.querySelectorAll('.export-menu').forEach(i => {if(i !== this.nextElementSibling) i.classList.add('hidden');}); this.nextElementSibling.classList.toggle('hidden'); event.stopPropagation();">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <div class="hidden absolute right-0 top-8 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 shadow-xl rounded-lg z-10 w-36 py-1 export-menu">
                                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 view-btn font-medium transition-colors">View Details</button>
                                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 send-to-btn font-medium transition-colors" data-content="<?php echo $fname; ?>">Send To</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-[15px] font-bold text-gray-900 dark:text-white leading-tight mb-1 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors" title="<?php echo $fname; ?>">
                                        <?php echo $fname; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                        <?php echo htmlspecialchars($n['about']); ?>
                                    </div>

                                    <div class="grid grid-cols-2 gap-y-3 gap-x-3 text-[11px] mb-4">
                                        <div class="flex flex-col"><span class="text-gray-500 dark:text-gray-400 mb-0.5">Version</span> <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($n['file_version']); ?></span></div>
                                        <div class="flex flex-col"><span class="text-gray-500 dark:text-gray-400 mb-0.5">Needed By</span> <span class="font-semibold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($n['needed_date']); ?></span></div>
                                    </div>

                                    <div class="bg-gray-50 dark:bg-slate-900/60 rounded-lg p-3 border border-gray-100 dark:border-slate-700/50 mb-4">
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500/80 uppercase tracking-widest font-semibold mb-1">Request Note</div>
                                        <div class="text-xs text-gray-800 dark:text-gray-300 font-medium leading-relaxed max-h-16 overflow-y-auto custom-scrollbar"><?php echo htmlspecialchars($n['request_note']); ?></div>
                                    </div>

                                    <div class="flex items-center justify-between mb-4 mt-auto">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600/50">
                                            <i class="bi bi-tag-fill text-slate-400 dark:text-slate-500 text-[10px]"></i> <?php echo htmlspecialchars($n['purpose']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <a href="<?php echo htmlspecialchars($n['link'] ?? 'storage.php'); ?>" class="open-link inline-flex items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors">
                                            <i class="bi bi-box-arrow-right"></i> Open Location
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mt-auto pt-3 border-t border-gray-100 dark:border-slate-700/60 flex justify-between items-center text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                    <span><?php echo htmlspecialchars($n['date']); ?></span>
                                    <span><?php echo htmlspecialchars($n['time']); ?></span>
                                </div>
                                
                                <div class="export-details hidden mt-2 bg-gray-50 dark:bg-slate-900 rounded p-2 text-[11px] border border-gray-200 dark:border-slate-600 space-y-1">
                                    <div class="text-gray-800 dark:text-gray-200">Status: <?php echo htmlspecialchars(ucfirst($n['status'])); ?></div>
                                    <div class="text-gray-600 dark:text-gray-400">File: <?php echo htmlspecialchars($n['file_name'] ?? $n['content']); ?></div>
                                    <div class="text-gray-600 dark:text-gray-400">Version: <?php echo htmlspecialchars($n['file_version']); ?></div>
                                    <div class="text-gray-600 dark:text-gray-400">Needed By: <?php echo htmlspecialchars($n['needed_date']); ?></div>
                                    <div class="text-gray-600 dark:text-gray-400">Request Note: <?php echo htmlspecialchars($n['request_note']); ?></div>
                                    <div class="text-gray-600 dark:text-gray-400">Purpose: <?php echo htmlspecialchars($n['purpose']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="export-empty" class="hidden mt-6 rounded-xl border border-dashed border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800/50 p-10 text-center shadow-sm">
                        <div class="mx-auto w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-500 dark:text-red-400 border border-red-100 dark:border-red-900/50">
                            <i class="bi bi-inbox text-xl"></i>
                        </div>
                        <h4 class="mt-4 text-base font-bold text-gray-900 dark:text-gray-100">No requests found</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No matching export requests fit this filter.</p>
                    </div>
				</div>
			</main>
		</div>
		</div>
	</div>
	</div>

        <!-- Floating Action Button -->
        <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2 group">
            <div class="flex-col items-end gap-2 hidden group-hover:flex transition-all duration-300 transform translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 mb-2">
                <button class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow-md border border-gray-200 dark:border-slate-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    <i class="bi bi-cloud-arrow-up text-red-600 dark:text-red-400"></i> Upload Archive
                </button>
                <button class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow-md border border-gray-200 dark:border-slate-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    <i class="bi bi-collection text-red-600 dark:text-red-400"></i> Bulk Export
                </button>
                <button id="open-export-request" type="button" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow-md border border-gray-200 dark:border-slate-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    <i class="bi bi-file-earmark-plus text-red-600 dark:text-red-400"></i> New Export
                </button>
            </div>
            <button class="w-14 h-14 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg flex items-center justify-center focus:outline-none transition-transform hover:scale-105">
                <i class="bi bi-plus-lg text-2xl transition-transform duration-300 group-hover:rotate-45"></i>
            </button>
        </div>

        <div id="export-request-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative max-w-2xl mx-auto mt-16 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-md bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    <div>
                        <div class="text-base font-semibold text-gray-900 dark:text-gray-100">New Export Request</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Provide the exact file and export details.</div>
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
                        <button id="export-request-cancel" type="button" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-red-700 hover:bg-red-800 text-white">Create Request</button>
                    </div>
                </form>
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
			const exportModal = document.getElementById('export-request-modal');
			const openExportBtn = document.getElementById('open-export-request');
			const exportCancel = document.getElementById('export-request-cancel');
			const sendToModal = document.getElementById('send-to-modal');
			const sendToFile = document.getElementById('send-to-file');
			const sendToCancel = document.getElementById('send-to-cancel');
			const sendToConfirm = document.getElementById('send-to-confirm');
			let sendFile = '';
			const search = document.getElementById('export-search');
			const allBtn = document.getElementById('filter-all');
			const unreadBtn = document.getElementById('filter-unread');
			const todayBtn = document.getElementById('filter-today');
			const weekBtn = document.getElementById('filter-week');
			const empty = document.getElementById('export-empty');
			let filterMode = 'all'; // all, unread, today, week
			let q = '';

			document.addEventListener('click', function(){
				document.querySelectorAll('.export-menu').forEach(el => el.classList.add('hidden'));
			});
			function openExportModal() {
				if (!exportModal) return;
				exportModal.classList.remove('hidden');
				document.body.style.overflow = 'hidden';
			}
			function closeExportModal() {
				if (!exportModal) return;
				exportModal.classList.add('hidden');
				document.body.style.overflow = '';
			}
			if (openExportBtn) openExportBtn.addEventListener('click', openExportModal);
			if (exportCancel) exportCancel.addEventListener('click', closeExportModal);
			<?php if (!empty($export_error)): ?>
			openExportModal();
			<?php endif; ?>
			<?php if (!empty($export_notice)): ?>
			try { UI_ENH.toast('<?php echo htmlspecialchars($export_notice, ENT_QUOTES); ?>', {background:'linear-gradient(90deg,#4ade80,#10b981)'}); } catch(e) {}
			<?php endif; ?>

			function updateBtnsStyle() {
			    [allBtn, unreadBtn, todayBtn, weekBtn].forEach(b => {
			        b.classList.remove('bg-red-700','dark:bg-red-800');
			        b.classList.add('bg-red-600','dark:bg-red-700');
			        b.setAttribute('aria-pressed', 'false');
			    });
			    let active;
			    if(filterMode==='all') active = allBtn;
			    if(filterMode==='unread') active = unreadBtn;
			    if(filterMode==='today') active = todayBtn;
			    if(filterMode==='week') active = weekBtn;
			    
			    if(active) {
			        active.classList.add('bg-red-700','dark:bg-red-800');
			        active.classList.remove('bg-red-600','dark:bg-red-700');
			        active.setAttribute('aria-pressed', 'true');
			    }
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
					let matchCond = true;
					if(filterMode === 'unread') matchCond = (status === 'unread');
					if(filterMode === 'today') matchCond = isToday(dateStr);
					if(filterMode === 'week') matchCond = isThisWeek(dateStr);
					
					const visible = matchText && matchCond;
					el.style.display = visible ? '' : 'none';
					if (visible) shown++;
				});
				empty.classList.toggle('hidden', shown !== 0);
				updateBtnsStyle();
			}
			search.addEventListener('input', function () {
				q = this.value.trim().toLowerCase();
				apply();
			});
			allBtn.addEventListener('click', function () {
				filterMode = 'all'; apply();
			});
			unreadBtn.addEventListener('click', function () {
				filterMode = 'unread'; apply();
			});
			todayBtn.addEventListener('click', function () {
				filterMode = 'today'; apply();
			});
			weekBtn.addEventListener('click', function () {
				filterMode = 'week'; apply();
			});
			const mo = new MutationObserver(() => updateUnreadBtnStyle());
			mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
			apply();
			function setItemStatus(item, status) {
				if (!item) return;
				status = (status === 'read') ? 'read' : 'unread';
				item.setAttribute('data-status', status);
				const dot = item.querySelector('.status-dot');
				const text = item.querySelector('.status-text');
				if (dot) {
					dot.classList.toggle('bg-red-500', status === 'unread');
					dot.classList.toggle('bg-gray-400', status === 'read');
				}
				if (text) {
					text.textContent = status.charAt(0).toUpperCase() + status.slice(1);
					text.classList.toggle('text-red-600', status === 'unread');
					text.classList.toggle('dark:text-red-400', status === 'unread');
					text.classList.toggle('text-gray-500', status === 'read');
					text.classList.toggle('dark:text-gray-400', status === 'read');
				}
			}
			function markRead(item) {
				if (!item) return;
				const cur = item.getAttribute('data-status') || 'unread';
				if (cur === 'read') return;
				setItemStatus(item, 'read');
				const id = item.getAttribute('data-id');
				if (id) {
					try {
						fetch('notifications_update.php', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'id=' + encodeURIComponent(id) + '&status=read'
						}).then(function () { });
					} catch (e) { }
				}
			}
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
					markRead(item);
					return;
				}
				const btn = e.target.closest('.send-to-btn');
				if (btn) {
					const item = btn.closest('.export-item');
					if (item) markRead(item);
					sendFile = btn.getAttribute('data-content') || '';
					sendToFile.textContent = sendFile;
					sendToModal.classList.remove('hidden');
					document.body.style.overflow = 'hidden';
					return;
				}
				const openLink = e.target.closest('.open-link');
				if (openLink) {
					const item = openLink.closest('.export-item');
					if (item) markRead(item);
					return;
				}
				if (e.target.closest('button') || e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea')) return;
				const item = e.target.closest('.export-item');
				if (!item) return;
				markRead(item);
				const link = item.getAttribute('data-link');
				if (link) window.location.href = link;
			});
			list.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter') return;
				const item = e.target.closest('.export-item');
				if (!item) return;
				markRead(item);
				const link = item.getAttribute('data-link');
				if (link) window.location.href = link;
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
                try { UI_ENH.toast('Sent "' + sendFile + '" to ' + chosen.value, {background:'linear-gradient(90deg,#4ade80,#10b981)'}); } catch(e) {}
			});
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

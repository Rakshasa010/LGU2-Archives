<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
require __DIR__ . '/authdatabase.php';
require_once __DIR__ . '/includes/storage_shared.php';
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

function fmt_bytes($bytes) {
    return storage_format_bytes($bytes);
}

function calculateStorageMetrics($conn, $uploadsMetrics = null) {
    $capacityBytes = 50 * 1024 * 1024 * 1024; // 50 GB
    $totalBytes = 0;
    $fileCount = 0;
    $storageTop = [];

    $legResult = $conn->query("SELECT file_path FROM legislative_records WHERE file_path IS NOT NULL AND file_path <> ''");
    if ($legResult) {
        while ($row = $legResult->fetch_assoc()) {
            if (@file_exists($row['file_path'])) {
                $size = @filesize($row['file_path']);
                $totalBytes += $size;
                $fileCount++;
                $storageTop[] = ['name' => basename($row['file_path']), 'path' => $row['file_path'], 'src' => 'Legislative', 'size' => $size];
            }
        }
    }

    $archResult = $conn->query("SELECT name, file_path FROM archive_files WHERE file_path IS NOT NULL AND file_path <> ''");
    if ($archResult) {
        while ($row = $archResult->fetch_assoc()) {
            if (@file_exists($row['file_path'])) {
                $size = @filesize($row['file_path']);
                $totalBytes += $size;
                $fileCount++;
                $storageTop[] = ['name' => $row['name'], 'path' => $row['file_path'], 'src' => 'Archive', 'size' => $size];
            }
        }
    }

    usort($storageTop, function($a, $b) { return $b['size'] - $a['size']; });
    $storageTop = array_slice($storageTop, 0, 15);

    if (is_array($uploadsMetrics)) {
        if (isset($uploadsMetrics['capacityBytes'])) $capacityBytes = (int)$uploadsMetrics['capacityBytes'];
        if (isset($uploadsMetrics['bytes'])) $totalBytes = (int)$uploadsMetrics['bytes'];
        if (isset($uploadsMetrics['fileCount'])) $fileCount = (int)$uploadsMetrics['fileCount'];
    }

    $pct = ($capacityBytes > 0) ? min(100, round(($totalBytes / $capacityBytes) * 100, 1)) : 0;

    return [
        'pct' => $pct,
        'totalBytes' => $totalBytes,
        'capacityBytes' => $capacityBytes,
        'fileCount' => $fileCount,
        'storageTop' => $storageTop,
        'usedText' => fmt_bytes($totalBytes),
        'totalText' => fmt_bytes($capacityBytes)
    ];
}

$uploads_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$uploads_metrics = storage_dir_metrics($uploads_path);

if (isset($_GET['action']) && $_GET['action'] === 'get_storage_data') {
    $storage = calculateStorageMetrics($conn, $uploads_metrics);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'percentage' => $storage['pct'],
        'usedText' => $storage['usedText'],
        'totalText' => $storage['totalText'],
        'fileCount' => $storage['fileCount'],
        'bytes' => $storage['totalBytes'],
        'capacityBytes' => $storage['capacityBytes']
    ]);
    $conn->close();
    exit();
}

// Calculate storage for initial page load
$storage = calculateStorageMetrics($conn, $uploads_metrics);
$pct = $storage['pct'];
$totalBytes = $storage['totalBytes'];
$capacityBytes = $storage['capacityBytes'];
$fileCount = $storage['fileCount'];
$storageTop = $storage['storageTop'];

$dashboard_date = date('l, F d, Y');

$archive_folders = [];
$folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
if ($folders_result && $folders_result->num_rows > 0) {
    while ($row = $folders_result->fetch_assoc()) {
        $archive_folders[] = $row;
    }
}

// Calculate data for dashboard charts
$dashboard_chart_data = [
    'storage_last7' => [],
    'files_by_source' => []
];
// Get last 7 days dates
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dashboard_chart_data['storage_last7'][] = [
        'date' => $date,
        'value' => rand(10000000, 50000000) // Mock data for now
    ];
}
// Count files by source
$leg_count = 0;
$arch_count = 0;
$leg_res = $conn->query("SELECT COUNT(*) AS c FROM legislative_records");
if ($leg_res && $row = $leg_res->fetch_assoc()) $leg_count = (int)$row['c'];
$arch_res = $conn->query("SELECT COUNT(*) AS c FROM archive_files");
if ($arch_res && $row = $arch_res->fetch_assoc()) $arch_count = (int)$row['c'];
$dashboard_chart_data['files_by_source'] = [
    'labels' => ['Legislative', 'Archives'],
    'data' => [$leg_count, $arch_count]
];

$dashboard_buckets = count($archive_folders);
$dashboard_objects = $leg_count + $arch_count;
$dashboard_names = 0;
if ($users_count_res = $conn->query("SELECT COUNT(*) AS c FROM users")) {
    if ($users_count_row = $users_count_res->fetch_assoc()) {
        $dashboard_names = (int)$users_count_row['c'];
    }
}
$dashboard_gateways = 1;
$storage_text = fmt_bytes($totalBytes);
$bandwidth_bytes = 0;
if ($conn->query("SHOW TABLES LIKE 'analytics_events'")->num_rows > 0) {
    if ($bw_res = $conn->query("SELECT COALESCE(SUM(bytes), 0) AS total_bytes FROM analytics_events")) {
        if ($bw_row = $bw_res->fetch_assoc()) {
            $bandwidth_bytes = (int)$bw_row['total_bytes'];
        }
    }
}
$bandwidth_text = fmt_bytes($bandwidth_bytes);

$uploads_labels = [];
$uploads_last7 = [];
$uploads_prev7 = [];
$uploads_earlier = [];
$recent_uploads = [];

$cat_labels = $cat_labels ?? [];
$cat_last7 = $cat_last7 ?? [];
$cat_prev7 = $cat_prev7 ?? [];
$cat_earlier = $cat_earlier ?? [];
$folder_counts_labels = $folder_counts_labels ?? [];
$folder_counts_values = $folder_counts_values ?? [];

$has_archive = $conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0;
$has_leg = $conn->query("SHOW TABLES LIKE 'legislative_records'")->num_rows > 0;
$all_files_union = [];
if ($has_archive) {
    $all_files_union[] = "SELECT f.id, f.name COLLATE utf8mb4_unicode_ci AS name, f.folder_id, f.created_at, fo.name COLLATE utf8mb4_unicode_ci AS folder_name, 'archive' COLLATE utf8mb4_unicode_ci AS src FROM archive_files f JOIN archive_folders fo ON fo.id = f.folder_id WHERE f.created_at IS NOT NULL";
}
if ($has_leg) {
    $all_files_union[] = "SELECT lr.id, lr.title COLLATE utf8mb4_unicode_ci AS name, lr.folder_id, lr.created_at, lf.name COLLATE utf8mb4_unicode_ci AS folder_name, 'legislative' COLLATE utf8mb4_unicode_ci AS src FROM legislative_records lr JOIN legislative_folders lf ON lf.id = lr.folder_id WHERE lr.created_at IS NOT NULL";
}

if (!empty($all_files_union)) {
    $all_files_sql = implode(" UNION ALL ", $all_files_union);
    $q = "
        SELECT folder_name AS folder,
               SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last7,
               SUM(CASE WHEN created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS prev7,
               SUM(CASE WHEN created_at < DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS earlier
        FROM ($all_files_sql) all_files
        GROUP BY folder_name
        ORDER BY folder_name
    ";
    if ($r = $conn->query($q)) {
        while ($row = $r->fetch_assoc()) {
            $uploads_labels[] = $row['folder'];
            $uploads_last7[] = (int)$row['last7'];
            $uploads_prev7[] = (int)$row['prev7'];
            $uploads_earlier[] = (int)$row['earlier'];
        }
    }

    $recent_sql = "SELECT id, name, folder_id, folder_name, src, created_at FROM ($all_files_sql) all_files ORDER BY created_at DESC LIMIT 12";
    if ($r = $conn->query($recent_sql)) {
        while ($row = $r->fetch_assoc()) $recent_uploads[] = $row;
    }
}

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;
$is_admin = isset($user_data['role']) && strtolower($user_data['role']) === 'admin';

$profile_picture_url = null;
if (is_string($profile_picture) && $profile_picture !== '') {
    $candidatePath = $profile_picture;
    $candidateUrl = $profile_picture;
    if (strpos($profile_picture, 'uploads/') !== 0) {
        $candidatePath = 'uploads/profile_pictures/' . $profile_picture;
        $candidateUrl = 'uploads/profile_pictures/' . $profile_picture;
    }
    if (file_exists($candidatePath)) {
        $profile_picture_url = $candidateUrl;
    }
}

?>
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
<body class="bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.16),_transparent_38%),linear-gradient(135deg,_#fef2f2_0%,_#f8fafc_50%,_#fef2f2_100%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.14),_transparent_35%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f172a_100%)] font-sans antialiased transition-colors duration-200">
    <div>
        <?php
        // include centralized sidebar after session/auth and user data are ready
        $sidebar_active_page = 'dashboard';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>
        <div class="flex flex-col min-h-screen md:ml-72">
            <!-- Header / Navbar -->
            <nav class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl border-b border-white/70 dark:border-slate-700/70 shadow-[0_10px_35px_rgba(15,23,42,0.08)] sticky top-0 z-40 transition-colors duration-200">
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

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <!-- 
                  Removed 'max-w-7xl mx-auto' to allow full screen scaling, 
                  and replaced with 'w-full' plus expanded responsive padding to maximize screen usage while keeping content breathing room.
                -->
                <div class="w-full px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                    <!-- Enhanced Header Section -->
                    <div class="mb-8">
                        <div class="space-y-6">
                            <div class="text-center lg:text-left">
                                <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($dashboard_date); ?></p>
                                <h1 class="text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white tracking-tight mt-2">
                                    Hello, <?php echo htmlspecialchars($display_name); ?>!
                                </h1>
                                <p class="max-w-2xl text-lg text-gray-600 dark:text-gray-300 mx-auto lg:mx-0 mt-3">
                                    Welcome to your dashboard. Here's an overview of your account and recent activity.
                                </p>
                            </div>
                        </div>
                    </div>
<!-- Quick Reports & Analytics Section -->
                    <!-- Quick Reports & Analytics (matches report_analytics.php lines 651-791 outer structure exactly) -->
                    <div class="card bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl transition-all duration-300 p-4 sm:p-6 mb-6">
                        <?php
                        $fa_start = isset($_GET['start']) ? $_GET['start'] : null;
                        $fa_end = isset($_GET['end']) ? $_GET['end'] : null;
                        $fa_type = isset($_GET['type']) ? $_GET['type'] : null;
                        // event filter (download / upload)
                        $q_event = isset($_GET['event']) ? $_GET['event'] : null;
                        $safe_event = $q_event ? $conn->real_escape_string(strtolower($q_event)) : null;
                        $f_from = null;
                        $f_to = null;
                        if ($fa_start) { $d = DateTime::createFromFormat('Y-m-d', $fa_start); if ($d) $f_from = $d->format('Y-m-d'); }
                        if ($fa_end) { $d = DateTime::createFromFormat('Y-m-d', $fa_end); if ($d) $f_to = $d->format('Y-m-d'); }
                        $where_rec = "1=1";
                        if ($f_from) $where_rec .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                        if ($f_to) $where_rec .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                        if ($fa_type) $where_rec .= " AND type = '".$conn->real_escape_string($fa_type)."'";
                        $where_dl = "event_type='download'";
                        if ($fa_type && $fa_type !== '') {
                            // type param may refer to record_type or folder name, but we still apply directly
                        }
                        if ($safe_event) {
                            if ($safe_event === 'download') {
                                // do nothing
                            } elseif ($safe_event === 'upload') {
                                $where_dl = "event_type='upload'";
                            } else {
                                $where_dl = "0";
                            }
                        }
                        if ($f_from) $where_dl .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                        if ($f_to) $where_dl .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                        if ($fa_type) $where_dl .= " AND record_type = '".$conn->real_escape_string($fa_type)."'";
                        $types_list = [];
                        if ($r = $conn->query("SELECT DISTINCT type FROM legislative_records ORDER BY type")) {
                            while ($row = $r->fetch_assoc()) { if ($row['type'] !== null && $row['type'] !== '') $types_list[] = $row['type']; }
                        }
                        $qa_total_records = 0;
                        $qa_downloads = 0;
                        $qa_uploads = 0;
                        $qa_by_type = [];
                        if ($res = $conn->query("SELECT COUNT(*) AS t FROM legislative_records WHERE $where_rec")) {
                            if ($row = $res->fetch_assoc()) $qa_total_records = (int)$row['t'];
                        }
                        if ($conn->query("SHOW TABLES LIKE 'analytics_events'")->num_rows > 0) {
                            if ($res = $conn->query("SELECT COUNT(*) AS c FROM analytics_events WHERE $where_dl")) {
                                if ($row = $res->fetch_assoc()) $qa_downloads = (int)$row['c'];
                            }
                            // uploads count (respect event filter)
                            $qa_uploads = 0;
                            $up_where = "event_type='upload'";
                            if ($safe_event && $safe_event !== 'upload') {
                                $up_where = "0"; // no rows
                            }
                            if ($f_from) $up_where .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                            if ($f_to) $up_where .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                            if ($fa_type) $up_where .= " AND record_type = '".$conn->real_escape_string($fa_type)."'";
                            if ($up_where !== "0" && $res2 = $conn->query("SELECT COUNT(*) AS c FROM analytics_events WHERE $up_where")) {
                                if ($row2 = $res2->fetch_assoc()) $qa_uploads = (int)$row2['c'];
                            }
                        } else {
                            $where_legacy = "last_accessed IS NOT NULL";
                            if ($f_from) $where_legacy .= " AND last_accessed >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                            if ($f_to) $where_legacy .= " AND last_accessed <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                            if ($fa_type) $where_legacy .= " AND type = '".$conn->real_escape_string($fa_type)."'";
                            if ($res = $conn->query("SELECT COUNT(*) AS c FROM legislative_records WHERE $where_legacy")) {
                                if ($row = $res->fetch_assoc()) $qa_downloads = (int)$row['c'];
                            }
                            // fallback uploads using archive_files count
                            $w2 = "1=1";
                            if ($f_from) $w2 .= " AND created_at >= '".$conn->real_escape_string($f_from)." 00:00:00'";
                            if ($f_to) $w2 .= " AND created_at <= '".$conn->real_escape_string($f_to)." 23:59:59'";
                            if ($res3 = $conn->query("SELECT COUNT(*) AS c FROM archive_files WHERE $w2")) {
                                if ($row3 = $res3->fetch_assoc()) $qa_uploads = (int)$row3['c'];
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
                                    $mapped = trim($row['folder_name']);
                                    if ($mapped === '') $mapped = 'Unknown Folder';
                                    if ($fa_type && $fa_type !== $mapped) continue;
                                    $qa_by_type[$mapped] = ($qa_by_type[$mapped] ?? 0) + $count;
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
                        $qa_series_uploads = array_values($series_folders);
                        $qa_series_records = array_values($series_records);
                        $qa_series_records_merged = array_values($series_records_merged);
                        ?>
                        <!-- Filter bar: exact structure matches report_analytics.php lines 763-788 -->
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="text-xs text-gray-600 dark:text-gray-400">From</label>
                                <input id="qa-from" type="date" value="<?php echo htmlspecialchars($f_from ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <label class="text-xs text-gray-600 dark:text-gray-400 ml-2">To</label>
                                <input id="qa-to" type="date" value="<?php echo htmlspecialchars($f_to ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <select id="qa-type" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <option value="">All Types</option>
                                    <?php foreach ($types_list as $t): ?>
                                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($fa_type === $t ? 'selected' : ''); ?>><?php echo htmlspecialchars($t); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="qa-event" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <option value="">All Events</option>
                                    <option value="download" <?php echo ($safe_event === 'download' ? 'selected' : ''); ?>>Download</option>
                                    <option value="upload" <?php echo ($safe_event === 'upload' ? 'selected' : ''); ?>>Upload</option>
                                </select>
                                <div class="flex md:justify-end ml-auto">
                                    <button id="qa-apply" class="px-3 py-1.5 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Apply</button>
                                </div>
                            </div>
                        </div>
                        <!-- KPI Stat cards: exact wrapper structure matches report_analytics.php lines 679-717 -->
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300 flex items-center justify-center"><i class="bi bi-file-earmark-text"></i></div>
                                    <div class="flex-1">
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Records</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5"><?php echo $qa_total_records; ?></div>
                                    </div>
                                </div>
                                <div class="relative w-full h-10 mt-3">
                                    <canvas id="qaRecordsMini"></canvas>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 flex items-center justify-center"><i class="bi bi-download"></i></div>
                                    <div class="flex-1">
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Downloads</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5"><?php echo $qa_downloads; ?></div>
                                    </div>
                                </div>
                                <div class="relative w-full h-10 mt-3">
                                    <canvas id="qaDownloadsMini"></canvas>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-300 flex items-center justify-center"><i class="bi bi-cloud-upload"></i></div>
                                    <div class="flex-1">
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Uploads</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5"><?php echo $qa_uploads; ?></div>
                                    </div>
                                </div>
                                <div class="relative w-full h-10 mt-3">
                                    <canvas id="qaUploadsMini"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6 items-start">
                        <!-- Main Search Section -->
                        <div class="w-full xl:order-2">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden">
                            <!-- Search Header -->
                            <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/10 dark:to-orange-900/10 px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                                        <i class="bi bi-search text-red-600 dark:text-red-400 text-lg"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Advanced Search</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Find documents across all archives</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Search Content -->
                            <div class="p-6">
                                <!-- Search Input -->
                                <div class="relative mb-6">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" id="legislativeSearchInput" 
                                           class="w-full pl-12 pr-4 py-4 text-lg border-2 border-gray-200 dark:border-slate-600 rounded-xl bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200" 
                                           placeholder="Search archives, ordinances, hearings, sessions, authors..."
                                           autocomplete="off">
                                    <button id="legislativeSearchBtn" 
                                            class="absolute right-2 top-2 bottom-2 px-6 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all duration-200 shadow-md hover:shadow-lg">
                                        Search
                                    </button>
                                </div>

                                <!-- Filter Chips -->
                                <div class="mb-6">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Filters:</span>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" data-filter="legislative" class="search-filter-chip px-4 py-2 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/30 dark:hover:text-red-300 transition-all duration-200 border border-gray-200 dark:border-slate-600">
                                                <i class="bi bi-file-text mr-2"></i>Legislative
                                            </button>
                                            <button type="button" data-filter="archive_files" class="search-filter-chip px-4 py-2 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/30 dark:hover:text-red-300 transition-all duration-200 border border-gray-200 dark:border-slate-600">
                                                <i class="bi bi-file-earmark mr-2"></i>Archive Files
                                            </button>
                                            <button type="button" data-filter="folders" class="search-filter-chip px-4 py-2 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/30 dark:hover:text-red-300 transition-all duration-200 border border-gray-200 dark:border-slate-600">
                                                <i class="bi bi-folder mr-2"></i>Folders
                                            </button>
                                            <button type="button" data-filter="authors" class="search-filter-chip px-4 py-2 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/30 dark:hover:text-red-300 transition-all duration-200 border border-gray-200 dark:border-slate-600">
                                                <i class="bi bi-person mr-2"></i>Authors
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Sort Options -->
                                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                                        <div class="flex items-center gap-3">
                                            <label for="searchSortSelect" class="text-sm font-medium text-gray-700 dark:text-gray-300">Sort by:</label>
                                            <select id="searchSortSelect" class="px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                                <option value="relevance">Relevance</option>
                                                <option value="newest">Newest first</option>
                                                <option value="date">Date</option>
                                            </select>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <i class="bi bi-info-circle mr-1"></i>
                                            <span id="searchResultCount">Ready to search</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search Results Section -->
                                <div class="relative">
                                    <div id="searchPopup" class="hidden mt-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 overflow-hidden">
                                        <div class="p-6">
                                            <!-- Recent Searches -->
                                            <div class="mb-6">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                                        <i class="bi bi-clock-history text-gray-500"></i>
                                                        Recent searches
                                                    </h3>
                                                    <button id="clearRecentBtn" type="button" class="text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-medium">
                                                        Clear all
                                                    </button>
                                                </div>
                                                <div id="recentSearchesList" class="flex flex-wrap gap-2"></div>
                                            </div>

                                            <!-- Search Results -->
                                            <div id="searchResultsPanel" class="hidden">
                                                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 dark:border-slate-600">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="searchResultsCount">0 results found</h3>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400" id="searchResultsSubtitle">Showing filtered archive matches</p>
                                                    </div>
                                                    <button id="clearSearchBtn" type="button" class="px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                                        Clear search
                                                    </button>
                                                </div>
                                                <div id="searchResultsList" class="space-y-3 max-h-96 overflow-y-auto"></div>
                                                
                                                <!-- Related Topics -->
                                                <div id="searchRelated" class="hidden mt-6 pt-4 border-t border-gray-200 dark:border-slate-600">
                                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Related topics</h4>
                                                    <div id="searchRelatedChips" class="flex flex-wrap gap-2"></div>
                                                </div>
                                            </div>

                                            <!-- Empty State -->
                                            <div id="searchEmptyState" class="text-center py-8">
                                                <div class="w-16 h-16 bg-gray-200 dark:bg-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                                    <i class="bi bi-search text-2xl text-gray-500 dark:text-gray-400"></i>
                                                </div>
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Search Document Archives</h3>
                                                <p class="text-gray-600 dark:text-gray-400 max-w-sm mx-auto">Start typing to find ordinances, folders, archive files, or authors. Results will appear here.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden xl:order-1">
                        <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/10 dark:to-orange-900/10 px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                                        <i class="bi bi-hdd-stack text-red-600 dark:text-red-400 text-lg"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Storage Overview</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Real-time analytics</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" id="storage-details-btn" title="Details">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                    <button class="p-2 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors" id="storage-refresh-btn" title="Refresh">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div id="storage-skeleton" class="hidden">
                                <div class="animate-pulse">
                                    <div class="w-48 h-48 bg-gray-200 dark:bg-slate-700 rounded-full mx-auto mb-6"></div>
                                    <div class="space-y-3">
                                        <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded"></div>
                                        <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded w-3/4"></div>
                                        <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded w-1/2"></div>
                                    </div>
                                </div>
                            </div>

                            <div id="storage-content" class="text-center">
                                <div class="relative w-48 h-48 mx-auto mb-6">
                                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 200 200">
                                        <circle cx="100" cy="100" r="80" fill="none" stroke="currentColor" stroke-width="12" class="text-gray-200 dark:text-slate-700" opacity="0.3"/>
                                        <circle id="donutProgress" cx="100" cy="100" r="80" fill="none" stroke-width="12"
                                                class="text-red-500" stroke-linecap="round"
                                                stroke-dasharray="502.65" stroke-dashoffset="502.65"
                                                style="transition: stroke-dashoffset 1s ease-in-out"/>
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="text-center">
                                            <div class="text-3xl font-bold bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent" id="storagePercentage"><?php echo $pct; ?>%</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Used</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Used Space</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo (int)$fileCount; ?> files</span>
                                        </div>
                                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 mb-2" id="storageUsed"><?php echo fmt_bytes($totalBytes); ?></div>
                                        <div class="w-full bg-gray-200 dark:bg-slate-600 rounded-full h-2">
                                            <div class="bg-gradient-to-r from-red-500 to-red-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%;"></div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Available Space</span>
                                            <span class="text-sm text-green-600 dark:text-green-400">
                                                <i class="bi bi-check-circle mr-1"></i><?php echo $pct < 80 ? 'Optimal' : ($pct < 90 ? 'Good' : 'Critical'); ?>
                                            </span>
                                        </div>
                                        <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo fmt_bytes(53687091200 - $totalBytes); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">of 50 GB total</div>
                                    </div>

                                    <div class="pt-4 border-t border-gray-100 dark:border-slate-700">
                                        <div class="grid grid-cols-2 gap-3">
                                            <a href="storage.php" class="flex items-center justify-center gap-2 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors text-sm font-medium">
                                                <i class="bi bi-folder"></i>
                                                Browse
                                            </a>
                                            <a href="export.php" class="flex items-center justify-center gap-2 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors text-sm font-medium">
                                                <i class="bi bi-download"></i>
                                                Export
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>


                    <!-- Analytics Overview Section: 3 charts side by side in one row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Storage Usage (Last 7 Days)</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200">Trend</span>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="storageUsageChart"></canvas>
                            </div>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Files by Source</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo $leg_count + $arch_count; ?> total</span>
                            </div>
                            <div class="relative w-full h-64 md:h-72 flex justify-center">
                                <canvas id="filesBySourceChart"></canvas>
                            </div>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Uploads by Folder</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200">Last 30 days</span>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="uploadsByFolderChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Latest Archive Files Visit</h3>
                            <button id="latest-files-toggle" type="button"
                                class="text-xs px-2 py-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                                aria-expanded="true" aria-controls="latestFilesList">
                                <span id="latest-files-toggle-text">Hide</span>
                                <i id="latest-files-toggle-icon" class="bi bi-chevron-up text-[10px]"></i>
                            </button>
                        </div>
                        <div id="latestFilesList" class="space-y-2">
                            <div id="latest-files-loading" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="bi bi-arrow-clockwise text-lg block mb-1 opacity-60"></i>
                                Loading recent files…
                            </div>
                        </div>
                    </div>
<!-- Storage Details Modal -->
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
    
            </main>
        <?php include 'includes/footer.php'; ?>
        </div>
    </div>



    <!-- Toast container: same style as recent_deleted.php, stacked; only unseen notifications shown once -->
    <div id="toast-container" class="fixed right-6 bottom-6 z-50 space-y-2 flex flex-col items-end pointer-events-none"></div>

    <?php
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
    ?>

    <script>
        // #region debug-point A:chart-debug-helper
        window.__dbgCharts = function(hypothesisId, msg, data) {
            fetch("http://127.0.0.1:7777/event", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    sessionId: "charts-blank-card",
                    runId: "pre-fix",
                    hypothesisId: hypothesisId,
                    location: "archives-landing.php",
                    msg: msg,
                    data: data || {},
                    ts: Date.now()
                })
            }).catch(function(){});
        };
        window.addEventListener('error', function(e) {
            window.__dbgCharts('B', '[DEBUG] window.error', {
                message: e.message || null,
                source: e.filename || null,
                line: e.lineno || null,
                column: e.colno || null
            });
        });
        window.addEventListener('unhandledrejection', function(e) {
            var reason = e && e.reason;
            window.__dbgCharts('B', '[DEBUG] window.unhandledrejection', {
                message: reason && reason.message ? reason.message : String(reason)
            });
        });
        window.__dbgCharts('A', '[DEBUG] chart helper ready', {
            chartType: typeof window.Chart,
            readyState: document.readyState
        });
        // #endregion
    </script>
    <script>
        // Analytics Overview Charts (uses same pattern as report_analytics.php)
        // #region debug-point A:analytics-overview-init
        const dashboardData = <?php echo json_encode($dashboard_chart_data); ?>;
        const storageLabels = dashboardData.storage_last7.map(d => d.date);
        const storageValues = dashboardData.storage_last7.map(d => d.value);
        const sourceLabels = dashboardData.files_by_source.labels;
        const sourceValues = dashboardData.files_by_source.data;
        window.__dbgCharts('A', '[DEBUG] analytics init start', {
            chartType: typeof window.Chart,
            storageCanvas: !!document.getElementById('storageUsageChart'),
            sourceCanvas: !!document.getElementById('filesBySourceChart'),
            storagePoints: storageValues.length,
            sourcePoints: sourceValues.length
        });

        const storageUsageCtx = document.getElementById('storageUsageChart')?.getContext('2d');
        if (storageUsageCtx) {
            try {
                new Chart(storageUsageCtx, {
                    type: 'line',
                    data: {
                        labels: storageLabels,
                        datasets: [{
                            label: 'Storage Used (Bytes)',
                            data: storageValues,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.2)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { maxRotation: 0, autoSkip: true } },
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
                window.__dbgCharts('A', '[DEBUG] storage chart created', {
                    width: document.getElementById('storageUsageChart')?.width || null,
                    height: document.getElementById('storageUsageChart')?.height || null
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] storage chart failed', {
                    message: err && err.message ? err.message : String(err)
                });
                throw err;
            }
        } else {
            window.__dbgCharts('C', '[DEBUG] storage chart context missing', {});
        }
        const filesBySourceCtx = document.getElementById('filesBySourceChart')?.getContext('2d');
        if (filesBySourceCtx) {
            try {
                new Chart(filesBySourceCtx, {
                    type: 'bar',
                    data: {
                        labels: sourceLabels,
                        datasets: [{
                            label: 'Files',
                            data: sourceValues,
                            backgroundColor: ['#dc2626', '#3b82f6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, precision: 0 } }
                    }
                });
                window.__dbgCharts('A', '[DEBUG] source chart created', {
                    width: document.getElementById('filesBySourceChart')?.width || null,
                    height: document.getElementById('filesBySourceChart')?.height || null
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] source chart failed', {
                    message: err && err.message ? err.message : String(err)
                });
                throw err;
            }
        } else {
            window.__dbgCharts('C', '[DEBUG] source chart context missing', {});
        }
        // #endregion
    </script>
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

    <!-- Image Preview Modal -->
    <div id="imagePreviewModal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeImagePreview()"></div>
            <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle max-w-4xl w-full border border-gray-200 dark:border-slate-700">
                <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none bg-white/50 dark:bg-slate-800/50 rounded-full p-1 backdrop-blur-sm" onclick="closeImagePreview()">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="bg-gray-100 dark:bg-slate-900 flex items-center justify-center min-h-[50vh] max-h-[85vh] p-4 relative" id="previewImageContainer">
                    <img id="previewModalImage" src="" alt="Preview" class="max-w-full max-h-[80vh] object-contain shadow-lg rounded" />
                    <div id="previewLoading" class="absolute inset-0 flex items-center justify-center bg-gray-100/80 dark:bg-slate-900/80">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 px-4 py-3 sm:px-6 flex items-center justify-between border-t border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 truncate pr-4" id="previewModalTitle">Image Preview</h3>
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeImagePreview()">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function openImagePreview(url, title) {
            const modal = document.getElementById('imagePreviewModal');
            const img = document.getElementById('previewModalImage');
            const tit = document.getElementById('previewModalTitle');
            const loader = document.getElementById('previewLoading');
            if(modal && img) {
                tit.textContent = title || 'Image Preview';
                img.classList.add('hidden');
                loader.classList.remove('hidden');
                img.onload = function() {
                    loader.classList.add('hidden');
                    img.classList.remove('hidden');
                };
                img.src = url;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeImagePreview() {
            const modal = document.getElementById('imagePreviewModal');
            if(modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                document.getElementById('previewModalImage').src = '';
            }
        }
    </script>
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
    <script>
        // Quick Reports & Analytics Charts (uses exact same pattern as report_analytics.php lines 1195-1217)
        // #region debug-point C:quick-reports-init
        const byType = <?php echo json_encode($qa_by_type ?? []); ?>;
        const seriesLabels = <?php echo json_encode($qa_series_labels ?? []); ?>;
        const seriesDownloads = <?php echo json_encode($qa_series_downloads ?? []); ?>;
        const seriesUploads = <?php echo json_encode($qa_series_uploads ?? []); ?>;
        const seriesRecords = <?php echo json_encode($qa_series_records ?? []); ?>;
        const seriesRecordsMerged = <?php echo json_encode($qa_series_records_merged ?? []); ?>;
        const fuLabels = <?php echo json_encode($uploads_labels ?? []); ?>;
        const fuLast7 = <?php echo json_encode($uploads_last7 ?? []); ?>;
        const fuPrev7 = <?php echo json_encode($uploads_prev7 ?? []); ?>;
        const fuEarlier = <?php echo json_encode($uploads_earlier ?? []); ?>;
        const catLabels = <?php echo json_encode($cat_labels ?? []); ?>;
        const catLast7 = <?php echo json_encode($cat_last7 ?? []); ?>;
        const catPrev7 = <?php echo json_encode($cat_prev7 ?? []); ?>;
        const catEarlier = <?php echo json_encode($cat_earlier ?? []); ?>;
        const ffLabels = <?php echo json_encode($folder_counts_labels ?? []); ?>;
        const ffValues = <?php echo json_encode($folder_counts_values ?? []); ?>;
        const dupLegLabels = <?php echo json_encode($dup_leg_labels ?? []); ?>;
        const dupLegCounts = <?php echo json_encode($dup_leg_counts ?? []); ?>;
        const dupFileLabels = <?php echo json_encode($dup_file_labels ?? []); ?>;
        const dupFileCounts = <?php echo json_encode($dup_file_counts ?? []); ?>;

        const labelsAndData = (obj) => { const labels = Object.keys(obj); const data = Object.values(obj); return { labels, data }; };
        const hideSk = (id) => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); };
        const rbt = labelsAndData(byType);
        window.__dbgCharts('C', '[DEBUG] quick reports init start', {
            chartType: typeof window.Chart,
            recordsMini: !!document.getElementById('qaRecordsMini'),
            downloadsMini: !!document.getElementById('qaDownloadsMini'),
            uploadsMini: !!document.getElementById('qaUploadsMini'),
            recordsLine: !!document.getElementById('qaRecordsLine'),
            recordsByType: !!document.getElementById('qaRecordsByType'),
            uploadsByFolder: !!document.getElementById('uploadsByFolderChart'),
            seriesLabels: seriesLabels.length,
            seriesDownloads: seriesDownloads.length,
            seriesUploads: seriesUploads.length,
            seriesRecords: seriesRecords.length,
            byTypeLabels: rbt.labels.length,
            folderLabels: fuLabels.length
        });

        // Mini sparkline: Records
        const qaRecordsMiniCtx = document.getElementById('qaRecordsMini')?.getContext('2d');
        if (qaRecordsMiniCtx) {
            try {
                new Chart(qaRecordsMiniCtx, {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ data: seriesRecordsMerged, borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, 0.2)', fill: true, tension: 0.3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] qaRecordsMini failed', { message: err && err.message ? err.message : String(err) });
                throw err;
            }
        }

        // Mini sparkline: Downloads
        const qaDownloadsMiniCtx = document.getElementById('qaDownloadsMini')?.getContext('2d');
        if (qaDownloadsMiniCtx) {
            new Chart(qaDownloadsMiniCtx, {
                type: 'line',
                data: { labels: seriesLabels, datasets: [{ data: seriesDownloads, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.2)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
        }

        // Mini sparkline: Uploads
        const qaUploadsMiniCtx = document.getElementById('qaUploadsMini')?.getContext('2d');
        if (qaUploadsMiniCtx) {
            new Chart(qaUploadsMiniCtx, {
                type: 'line',
                data: { labels: seriesLabels, datasets: [{ data: seriesUploads, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.2)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
        }

        // Records Trend Line
        const qaRecordsLineCtx = document.getElementById('qaRecordsLine')?.getContext('2d');
        if (qaRecordsLineCtx) {
            try {
                new Chart(qaRecordsLineCtx, {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ label: 'Records', data: seriesRecords, tension: 0.3, borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, 0.2)', fill: true }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { maxRotation: 0, autoSkip: true } },
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] qaRecordsLine failed', { message: err && err.message ? err.message : String(err) });
                throw err;
            }
        }

        // Records by Type Doughnut (with ABS/% toggle - special pattern)
        let rbtChart = null;
        let rbtMode = localStorage.getItem('rbtMode') || 'abs';
        function renderRbt() {
            const qaRecordsByTypeCtx = document.getElementById('qaRecordsByType')?.getContext('2d');
            if (!qaRecordsByTypeCtx) return;
            const labels = rbt.labels;
            const values = rbt.data;
            const total = values.reduce((a, b) => a + b, 0) || 1;
            const data = (rbtMode === 'pct') ? values.map(v => +(v * 100 / total).toFixed(2)) : values;
            if (rbtChart) rbtChart.destroy();
            try {
                rbtChart = new Chart(qaRecordsByTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{ data: data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444','#06b6d4','#84cc16'] }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const idx = ctx.dataIndex;
                                        const raw = values[idx];
                                        const pct = (raw * 100 / total).toFixed(2) + '%';
                                        return labels[idx] + ': ' + (rbtMode === 'pct' ? pct : raw);
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] qaRecordsByType failed', { message: err && err.message ? err.message : String(err) });
                throw err;
            }
            const toggle = document.getElementById('rbt-toggle');
            if (toggle) toggle.textContent = (rbtMode === 'pct' ? '%' : 'ABS');
        }
        renderRbt();
        const rbtToggle = document.getElementById('rbt-toggle');
        if (rbtToggle) {
            rbtToggle.addEventListener('click', function() {
                rbtMode = (rbtMode === 'abs') ? 'pct' : 'abs';
                localStorage.setItem('rbtMode', rbtMode);
                renderRbt();
            });
        }

        // Uploads by Folder grouped bar
        const uploadsByFolderChartCtx = document.getElementById('uploadsByFolderChart')?.getContext('2d');
        if (uploadsByFolderChartCtx) {
            try {
                new Chart(uploadsByFolderChartCtx, {
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
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } },
                        scales: { x: { stacked: false }, y: { beginAtZero: true, precision: 0 } }
                    }
                });
            } catch (err) {
                window.__dbgCharts('C', '[DEBUG] uploadsByFolderChart failed', { message: err && err.message ? err.message : String(err) });
                throw err;
            }
        }

        // Optional canvases (safe guards)
        const recordsByCategoryChartCtx = document.getElementById('recordsByCategoryChart')?.getContext('2d');
        if (recordsByCategoryChartCtx) {
            new Chart(recordsByCategoryChartCtx, {
                type: 'bar',
                data: {
                    labels: catLabels,
                    datasets: [
                        { label: 'Last 7d', data: catLast7, backgroundColor: '#dc2626' },
                        { label: 'Prev 7d', data: catPrev7, backgroundColor: '#f97316' },
                        { label: '8-30d', data: catEarlier, backgroundColor: '#6b7280' }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, precision: 0 } } }
            });
        }

        const filesByFolderDonutCtx = document.getElementById('filesByFolderDonut')?.getContext('2d');
        if (filesByFolderDonutCtx) {
            new Chart(filesByFolderDonutCtx, {
                type: 'doughnut',
                data: { labels: ffLabels, datasets: [{ data: ffValues, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444','#06b6d4','#84cc16'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        }

        const dupLegBarCtx = document.getElementById('dupLegBar')?.getContext('2d');
        if (dupLegBarCtx) {
            new Chart(dupLegBarCtx, {
                type: 'bar',
                data: { labels: dupLegLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'…' : s; }), datasets: [{ label: 'Count', data: dupLegCounts, backgroundColor: '#dc2626' }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, precision: 0 } } }
            });
        }

        const dupFileBarCtx = document.getElementById('dupFileBar')?.getContext('2d');
        if (dupFileBarCtx) {
            new Chart(dupFileBarCtx, {
                type: 'bar',
                data: { labels: dupFileLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'…' : s; }), datasets: [{ label: 'Count', data: dupFileCounts, backgroundColor: '#2563eb' }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, precision: 0 } } }
            });
        }

        window.__dbgCharts('D', '[DEBUG] quick reports init complete', {
            storageCardVisible: !!document.getElementById('storageUsageChart')?.offsetParent,
            recordsCardVisible: !!document.getElementById('qaRecordsLine')?.offsetParent,
            uploadsCardVisible: !!document.getElementById('uploadsByFolderChart')?.offsetParent
        });
        // #endregion
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
                var event = document.getElementById('qa-event')?.value || '';
                ['start','end','type','event'].forEach(function(k){ p.delete(k); });
                if (from) p.set('start', from);
                if (to) p.set('end', to);
                if (type) p.set('type', type);
                if (event) p.set('event', event);
                var url = window.location.pathname + (p.toString() ? ('?'+p.toString()) : '');
                window.location.assign(url);
            }
            applyBtn && applyBtn.addEventListener('click', applyFilters);
        })();
    </script>
    <script>
        // Shared storage API + unified updates (sidebar + dashboard)
        (function initStorageSync() {
            function fmtBytes(bytes) {
                if (!isFinite(bytes) || bytes < 0) return '0 B';
                const units = ['B','KB','MB','GB','TB'];
                let idx = 0;
                let val = bytes;
                while (val >= 1024 && idx < units.length - 1) {
                    val /= 1024;
                    idx++;
                }
                return (Math.round(val * 10) / 10) + ' ' + units[idx];
            }
            function applyStorageData(data) {
                if (!data || !data.success) return;
                const pct = data.percentage ?? 0;
                const usedText = data.usedText ?? '0 B';
                const totalText = data.totalText ?? '50 GB';
                const fileCount = data.fileCount ?? 0;
                const usedBytes = data.bytes ?? 0;
                const capacityBytes = data.capacityBytes ?? (50 * 1024 * 1024 * 1024);
                const availableBytes = Math.max(0, capacityBytes - usedBytes);
                const availableText = fmtBytes(availableBytes);

                // Sidebar (mobile + desktop)
                const mobileBar = document.getElementById('mobile-storage-bar');
                const mobilePct = document.getElementById('mobile-storage-pct');
                const mobileText = document.getElementById('mobile-storage-text');
                const mobileFiles = document.getElementById('mobile-storage-files');
                if (mobileBar) mobileBar.style.width = pct + '%';
                if (mobilePct) mobilePct.textContent = pct + '%';
                if (mobileText) mobileText.textContent = usedText + ' of ' + totalText;
                if (mobileFiles) mobileFiles.textContent = fileCount + ' files tracked';

                const desktopBar = document.getElementById('desktop-storage-bar');
                const desktopPct = document.getElementById('desktop-storage-pct');
                const desktopText = document.getElementById('desktop-storage-text');
                const desktopFiles = document.getElementById('desktop-storage-files');
                if (desktopBar) desktopBar.style.width = pct + '%';
                if (desktopPct) desktopPct.textContent = pct + '%';
                if (desktopText) desktopText.textContent = usedText + ' of ' + totalText;
                if (desktopFiles) desktopFiles.textContent = fileCount + ' files tracked';

                // Dashboard overview
                const pctEl = document.getElementById('storagePercentage');
                const usedEl = document.getElementById('storageUsed');
                const totalEl = document.getElementById('storageTotal');
                const statusEl = document.getElementById('storageStatus');
                const detailUsed = document.getElementById('detailUsed');
                const detailAvailable = document.getElementById('detailAvailable');
                const detailTotal = document.getElementById('detailTotal');
                const usedBar = document.getElementById('usedSpaceBar');
                const availBar = document.getElementById('availableSpaceBar');
                const donutProgress = document.getElementById('donutProgress');
                const lastUpdate = document.getElementById('lastUpdateTime');

                if (pctEl) pctEl.textContent = pct + '%';
                if (usedEl) usedEl.textContent = usedText;
                if (totalEl) totalEl.textContent = 'of ' + totalText;
                if (detailUsed) detailUsed.textContent = usedText;
                if (detailAvailable) detailAvailable.textContent = availableText;
                if (detailTotal) detailTotal.textContent = totalText;
                if (usedBar) usedBar.style.width = pct + '%';
                if (availBar) availBar.style.width = Math.max(0, 100 - pct) + '%';
                if (donutProgress) {
                    const circumference = 565.48;
                    const offset = circumference - (pct / 100) * circumference;
                    donutProgress.style.strokeDashoffset = offset;
                }
                if (lastUpdate) lastUpdate.textContent = 'just now';

                if (statusEl) {
                    let status = 'Optimal';
                    if (pct >= 90) status = 'Critical';
                    else if (pct >= 75) status = 'Warning';
                    statusEl.textContent = status;
                }
            }
            function fetchStorageData() {
                return fetch('archives-landing.php?action=get_storage_data')
                    .then(res => res.json())
                    .then(data => { applyStorageData(data); return data; })
                    .catch(err => { console.warn('Storage update error:', err); });
            }

            window.fetchStorageData = fetchStorageData;
            fetchStorageData();
            setInterval(fetchStorageData, 60000);
        })();

        // Storage Overview Button Handlers & Skeleton Loading
        (function initStorageOverview() {
            const detailsBtn = document.getElementById('storage-details-btn');
            const refreshBtn = document.getElementById('storage-refresh-btn');
            const cleanupBtn = document.getElementById('storage-cleanup-btn');
            const exportBtn = document.getElementById('storage-export-btn');
            
            // Details Button - Show storage breakdown toast
            if (detailsBtn) {
                detailsBtn.addEventListener('click', function() {
                    const pct = document.getElementById('storagePercentage')?.textContent || '0%';
                    const used = document.getElementById('storageUsed')?.textContent || '0 B';
                    const status = document.getElementById('storageStatus')?.textContent || 'Optimal';
                    
                    // Create and show toast
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-6 right-6 z-50 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 rounded-lg shadow-xl max-w-sm transform transition-all duration-300 animate-pulse';
                    toast.innerHTML = `
                        <div class="font-semibold mb-2">Storage Details</div>
                        <div class="text-sm space-y-1">
                            <div>Usage: <span class="font-bold">${pct}</span></div>
                            <div>Space Used: <span class="font-bold">${used}</span></div>
                            <div>Status: <span class="font-bold">${status}</span></div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.classList.remove('animate-pulse');
                        toast.classList.add('opacity-0', 'translate-y-4');
                        setTimeout(() => toast.remove(), 300);
                    }, 4000);
                });
            }
            
            // Refresh Button - Reload storage data with spinner
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const originalHTML = refreshBtn.innerHTML;
                    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise mr-1 animate-spin"></i>Refreshing...';
                    refreshBtn.disabled = true;
                    Promise.resolve(window.fetchStorageData && window.fetchStorageData())
                        .then(function(){
                            const successToast = document.createElement('div');
                            successToast.className = 'fixed bottom-6 right-6 z-50 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-lg shadow-xl';
                            successToast.textContent = '✓ Storage data refreshed';
                            document.body.appendChild(successToast);
                            setTimeout(() => successToast.remove(), 3000);
                        })
                        .finally(function(){
                            refreshBtn.innerHTML = originalHTML;
                            refreshBtn.disabled = false;
                        });
                });
            }
            
            // Cleanup Button - Show cleanup notification
            if (cleanupBtn) {
                cleanupBtn.addEventListener('click', function() {
                    const btn = this;
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat mr-2 animate-spin"></i>Cleaning...';
                    btn.disabled = true;
                    
                    // Simulate cleanup process
                    setTimeout(() => {
                        const cleanupToast = document.createElement('div');
                        cleanupToast.className = 'fixed bottom-6 right-6 z-50 bg-gradient-to-r from-orange-600 to-red-600 text-white px-6 py-4 rounded-lg shadow-xl max-w-sm';
                        cleanupToast.innerHTML = `
                            <div class="font-semibold mb-2">🧹 Cleanup Complete</div>
                            <div class="text-sm">Temporary files cleaned. Refresh to see updated storage.</div>
                        `;
                        document.body.appendChild(cleanupToast);
                        
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        
                        setTimeout(() => cleanupToast.remove(), 4000);
                    }, 1500);
                });
            }
            
            // Export Button - Generate and download report
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    const btn = this;
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-cloud-download mr-2 animate-bounce"></i>Generating...';
                    btn.disabled = true;
                    
                    const pct = document.getElementById('storagePercentage')?.textContent || '0%';
                    const used = document.getElementById('storageUsed')?.textContent || '0 B';
                    const timestamp = new Date().toLocaleString();
                    
                    // Generate CSV report
                    const reportContent = `Storage Analysis Report
Generated: ${timestamp}

SUMMARY
Storage Percentage: ${pct}
Storage Used: ${used}
Total Capacity: 50 GB
Status: ${document.getElementById('storageStatus')?.textContent}

This is an automatically generated storage analysis report.
For detailed information, visit the Storage Overview dashboard.`;
                    
                    // Create download
                    setTimeout(() => {
                        const blob = new Blob([reportContent], { type: 'text/plain' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `storage-report-${new Date().toISOString().split('T')[0]}.txt`;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(url);
                        
                        const exportToast = document.createElement('div');
                        exportToast.className = 'fixed bottom-6 right-6 z-50 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-lg shadow-xl';
                        exportToast.innerHTML = '✓ Report downloaded successfully';
                        document.body.appendChild(exportToast);
                        
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        
                        setTimeout(() => exportToast.remove(), 3000);
                    }, 1200);
                });
            }
        })();
    </script>
    <script>
        (function() {
            var input = document.getElementById('legislativeSearchInput');
            var button = document.getElementById('legislativeSearchBtn');
            var clearBtn = document.getElementById('clearSearchBtn');
            var searchPopup = document.getElementById('searchPopup');
            var recentSearchesList = document.getElementById('recentSearchesList');
            var clearRecentBtn = document.getElementById('clearRecentBtn');
            var searchResultsPanel = document.getElementById('searchResultsPanel');
            var searchEmptyState = document.getElementById('searchEmptyState');
            var searchResultsCount = document.getElementById('searchResultsCount');
            var searchResultsList = document.getElementById('searchResultsList');
            var searchRelated = document.getElementById('searchRelated');
            var searchRelatedChips = document.getElementById('searchRelatedChips');
            var filterButtons = document.querySelectorAll('.search-filter-chip');
            var sortSelect = document.getElementById('searchSortSelect');
            var activeFilters = [];
            var recentStorageKey = 'archivesRecentSearches';

            function getRecentSearches() {
                try {
                    var raw = localStorage.getItem(recentStorageKey) || '[]';
                    var parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }

            function saveRecentSearch(term) {
                if (!term) return;
                var searches = getRecentSearches().filter(function(item) { return item !== term; });
                searches.unshift(term);
                searches = searches.slice(0, 5);
                localStorage.setItem(recentStorageKey, JSON.stringify(searches));
                renderRecentSearches();
            }

            function clearRecentSearches() {
                localStorage.removeItem(recentStorageKey);
                renderRecentSearches();
            }

            function renderRecentSearches() {
                if (!recentSearchesList) return;
                var searches = getRecentSearches();
                recentSearchesList.innerHTML = '';
                if (searches.length === 0) {
                    recentSearchesList.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">No recent searches yet.</span>';
                    return;
                }
                searches.forEach(function(term) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 px-3 py-1 text-xs font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors';
                    btn.textContent = term;
                    btn.addEventListener('click', function() {
                        if (input) {
                            input.value = term;
                            performSearch(term);
                        }
                    });
                    recentSearchesList.appendChild(btn);
                });
            }

            function getSelectedFilters() {
                return activeFilters.slice();
            }

            function updateFilterUI() {
                filterButtons.forEach(function(button) {
                    var filter = button.getAttribute('data-filter');
                    if (activeFilters.indexOf(filter) >= 0) {
                        button.classList.add('bg-red-600', 'text-white');
                        button.classList.remove('bg-gray-100', 'dark:bg-slate-700', 'text-gray-700', 'dark:text-gray-200');
                    } else {
                        button.classList.remove('bg-red-600', 'text-white');
                        button.classList.add('bg-gray-100', 'dark:bg-slate-700', 'text-gray-700', 'dark:text-gray-200');
                    }
                });
            }

            function toggleFilter(filter) {
                var index = activeFilters.indexOf(filter);
                if (index >= 0) {
                    activeFilters.splice(index, 1);
                } else {
                    activeFilters.push(filter);
                }
                updateFilterUI();
            }

            function showPopup() {
                if (searchPopup) searchPopup.classList.remove('hidden');
            }

            function hidePopup() {
                if (searchPopup) searchPopup.classList.add('hidden');
            }

            function toggleResultState(showResults) {
                if (!searchResultsPanel || !searchEmptyState) return;
                if (showResults) {
                    searchResultsPanel.classList.remove('hidden');
                    searchEmptyState.classList.add('hidden');
                } else {
                    searchResultsPanel.classList.add('hidden');
                    searchEmptyState.classList.remove('hidden');
                }
            }

            function renderRelated(items) {
                if (!searchRelated || !searchRelatedChips) return;
                searchRelatedChips.innerHTML = '';
                if (!Array.isArray(items) || items.length === 0) {
                    searchRelated.classList.add('hidden');
                    return;
                }
                searchRelated.classList.remove('hidden');
                items.slice(0, 6).forEach(function(item) {
                    var chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'rounded-full bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 px-3 py-1 text-xs font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors';
                    chip.textContent = item.label;
                    chip.addEventListener('click', function() {
                        if (input) {
                            input.value = item.query || item.label;
                            performSearch(input.value.trim());
                        }
                    });
                    searchRelatedChips.appendChild(chip);
                });
            }

            function normalizeDate(item) {
                if (item.created_at) {
                    return new Date(item.created_at).getTime() || 0;
                }
                if (item.year) {
                    var month = item.month ? new Date(item.month + ' 1, ' + item.year).getTime() : 0;
                    return month || (item.year * 1000);
                }
                return 0;
            }

            function sortEntries(entries, term) {
                var mode = sortSelect ? sortSelect.value : 'relevance';
                if (mode === 'newest' || mode === 'date') {
                    return entries.slice().sort(function(a, b) {
                        return normalizeDate(b) - normalizeDate(a);
                    });
                }
                return entries.slice().sort(function(a, b) {
                    var titleA = (a.title || '').toLowerCase();
                    var titleB = (b.title || '').toLowerCase();
                    var termLower = term.toLowerCase();
                    var score = function(item) {
                        var value = (item.title || '').toLowerCase();
                        if (value === termLower) return 3;
                        if (value.startsWith(termLower)) return 2;
                        if (value.includes(termLower)) return 1;
                        return 0;
                    };
                    return score(b) - score(a);
                });
            }

            function buildResultLink(item) {
                if (item.source === 'legislative' && item.folder_id) {
                    return 'folder_view.php?id=' + encodeURIComponent(item.folder_id) + '&legislative=true&highlight=' + encodeURIComponent(item.id);
                }
                if (item.kind === 'folder') {
                    return 'folder_view.php?id=' + encodeURIComponent(item.id);
                }
                if (item.kind === 'file' && item.folder_id) {
                    return 'folder_view.php?id=' + encodeURIComponent(item.folder_id) + '&highlight=' + encodeURIComponent(item.id);
                }
                return '#';
            }

            function renderSearchResults(data, term) {
                if (!searchResultsList || !searchResultsCount) return;
                var entries = Array.isArray(data.results) ? data.results : [];
                entries = sortEntries(entries, term);
                searchResultsList.innerHTML = '';
                if (entries.length === 0) {
                    searchResultsCount.textContent = 'No results found';
                    toggleResultState(false);
                    renderRelated(data.related || []);
                    return;
                }
                searchResultsCount.textContent = entries.length + ' results found';
                toggleResultState(true);
                entries.slice(0, 15).forEach(function(item) {
                    var meta = [];
                    if (item.source === 'legislative') {
                        if (item.author) meta.push(item.author);
                        if (item.month && item.year) meta.push(item.month + ' ' + item.year);
                        else if (item.year) meta.push(item.year);
                    } else if (item.source === 'archive') {
                        if (item.type) meta.push(item.type);
                        if (item.folder_name) meta.push('Folder: ' + item.folder_name);
                    }
                    var link = buildResultLink(item);
                    var anchor = document.createElement('a');
                    anchor.href = link;
                    anchor.className = 'block rounded-2xl bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 p-3 transition hover:shadow-md';
                    anchor.innerHTML = '<div class="flex items-start justify-between gap-3">' +
                                       '<div class="min-w-0">' +
                                       '<p class="text-sm font-semibold text-gray-900 dark:text-white truncate">' + (item.title || 'Untitled') + '</p>' +
                                       '<p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">' + (meta.filter(Boolean).join(' • ') || item.type || item.source || '') + '</p>' +
                                       '</div>' +
                                       '<span class="whitespace-nowrap rounded-full bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-200 px-2 py-1 text-[10px] uppercase tracking-[0.18em] font-semibold">' + (item.type || item.source || '').toUpperCase() + '</span>' +
                                       '</div>';
                    searchResultsList.appendChild(anchor);
                });
                renderRelated(data.related || []);
            }

            function performSearch(term) {
                if (!input || !button) return;
                if (!term) {
                    input.focus();
                    return;
                }
                addSearchTerm(term);
                showPopup();
                toggleResultState(true);
                if (searchResultsCount) searchResultsCount.textContent = 'Searching...';
                if (searchResultsList) searchResultsList.innerHTML = '<div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Loading results…</div>';
                var data = 'search=' + encodeURIComponent(term) + '&filters=' + encodeURIComponent(JSON.stringify(getSelectedFilters()));
                fetch('search_records.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: data
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.error) {
                        if (searchResultsCount) searchResultsCount.textContent = 'Search failed';
                        if (searchResultsList) searchResultsList.innerHTML = '<div class="py-6 text-center text-sm text-red-600 dark:text-red-400">Unable to fetch results.</div>';
                        renderRelated([]);
                        return;
                    }
                    renderSearchResults(data, term);
                })
                .catch(function() {
                    if (searchResultsCount) searchResultsCount.textContent = 'Search error';
                    if (searchResultsList) searchResultsList.innerHTML = '<div class="py-6 text-center text-sm text-red-600 dark:text-red-400">Network error. Try again.</div>';
                    renderRelated([]);
                });
            }

            function addSearchTerm(term) {
                saveRecentSearch(term);
            }

            if (button && input) {
                button.addEventListener('click', function() {
                    performSearch(input.value.trim());
                });
            }

            if (input) {
                input.addEventListener('focus', function() {
                    renderRecentSearches();
                    showPopup();
                    toggleResultState(false);
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performSearch(input.value.trim());
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (input) input.value = '';
                    toggleResultState(false);
                    renderRecentSearches();
                });
            }

            if (clearRecentBtn) {
                clearRecentBtn.addEventListener('click', function() {
                    clearRecentSearches();
                });
            }

            filterButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var filter = button.getAttribute('data-filter');
                    toggleFilter(filter);
                });
            });

            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    if (input && input.value.trim()) {
                        performSearch(input.value.trim());
                    }
                });
            }

            document.addEventListener('click', function(event) {
                if (!searchPopup || !input) return;
                if (searchPopup.contains(event.target) || event.target === input || event.target === button) return;
                hidePopup();
            });

            renderRecentSearches();
            updateFilterUI();
        })();
    </script>
    <script>
        (function() {
            var toggleBtn = document.getElementById('latest-files-toggle');
            var list = document.getElementById('latestFilesList');
            var txt = document.getElementById('latest-files-toggle-text');
            var icon = document.getElementById('latest-files-toggle-icon');
            if (!toggleBtn || !list || !txt || !icon) return;

            function setExpanded(expanded) {
                list.classList.toggle('hidden', !expanded);
                toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                txt.textContent = expanded ? 'Hide List' : 'Show List';
                icon.className = expanded ? 'bi bi-chevron-up text-xs' : 'bi bi-chevron-down text-xs';
            }

            setExpanded(true);
            toggleBtn.addEventListener('click', function() {
                var expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
                setExpanded(!expanded);
            });
        })();
    </script>
    <script src="assets/js/folder-otp.js"></script>
    <script>
        (function () {
            if (!window.folderOTP) return;
            // Advanced search results: require OTP before opening a folder
            var searchList = document.getElementById('searchResultsList');
            if (searchList) {
                searchList.addEventListener('click', function (e) {
                    var link = e.target.closest('a[href*="folder_view.php"]');
                    if (!link) return;
                    e.preventDefault();
                    window.folderOTP.guard(link.href);
                });
            }
            // Latest Archive Files Visit: require OTP before opening a folder
            var latestList = document.getElementById('latestFilesList');
            if (latestList) {
                latestList.addEventListener('click', function (e) {
                    var link = e.target.closest('a[href*="folder_view.php"]');
                    if (!link) return;
                    e.preventDefault();
                    window.folderOTP.guard(link.href);
                });
            }
        })();
    </script>
</body>
</html>

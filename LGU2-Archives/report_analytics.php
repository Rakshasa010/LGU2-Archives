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
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) $user_data = $res->fetch_assoc();
$stmt->close();

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;

// Helper to get directory size (bytes)
function dir_size($path) {
    $size = 0;
    if (!is_dir($path)) return 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// Fetch stats from DB
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM legislative_records");
$stats['total_records'] = ($result && $row = $result->fetch_assoc()) ? (int)$row['total'] : 0;

$result = $conn->query("SELECT type, COUNT(*) as cnt FROM legislative_records GROUP BY type");
$stats['by_type'] = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $stats['by_type'][$r['type']] = (int)$r['cnt'];
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
// Filters
$q_start = isset($_GET['start']) ? $_GET['start'] : null;
$q_end = isset($_GET['end']) ? $_GET['end'] : null;
$q_type = isset($_GET['type']) ? $_GET['type'] : null;
$q_format = isset($_GET['format']) ? $_GET['format'] : null;
$q_event = isset($_GET['event']) ? $_GET['event'] : null;
$f_start = null;
$f_end = null;
if ($q_start) {
    $d = DateTime::createFromFormat('Y-m-d', $q_start);
    if ($d) $f_start = $d->format('Y-m-d');
}
if ($q_end) {
    $d = DateTime::createFromFormat('Y-m-d', $q_end);
    if ($d) $f_end = $d->format('Y-m-d');
}
$safe_type = $q_type ? $conn->real_escape_string($q_type) : null;
$safe_format = $q_format ? $conn->real_escape_string(strtolower($q_format)) : null;
$safe_event = $q_event ? $conn->real_escape_string(strtolower($q_event)) : null;
$dl_where = "event_type='download'";
if ($f_start) $dl_where .= " AND created_at >= '".$conn->real_escape_string($f_start)." 00:00:00'";
if ($f_end) $dl_where .= " AND created_at <= '".$conn->real_escape_string($f_end)." 23:59:59'";
if ($safe_type) $dl_where .= " AND record_type = '".$safe_type."'";
if ($safe_format) $dl_where .= " AND download_format = '".$safe_format."'";
$act_where = "1=1";
if ($safe_event) $act_where .= " AND event_type = '".$safe_event."'";
if ($f_start) $act_where .= " AND created_at >= '".$conn->real_escape_string($f_start)." 00:00:00'";
if ($f_end) $act_where .= " AND created_at <= '".$conn->real_escape_string($f_end)." 23:59:59'";
if ($safe_type) $act_where .= " AND record_type = '".$safe_type."'";
if ($safe_format) $act_where .= " AND download_format = '".$safe_format."'";

// Downloads + Activity (prefer analytics_events if available; fallback to legacy last_accessed)
$stats['downloads'] = 0;
$stats['downloads_by_type'] = [];
$stats['downloads_by_format'] = [];
$stats['recent_activity'] = [];
$stats['recent_downloads'] = []; // legacy fallback table

$ae_count = $conn->query("SELECT COUNT(*) AS cnt FROM analytics_events WHERE $dl_where");
if ($ae_count && ($row = $ae_count->fetch_assoc())) {
    $stats['downloads'] = (int)$row['cnt'];

    $ae_by_type = $conn->query("SELECT COALESCE(record_type,'Unknown') AS k, COUNT(*) AS cnt
                                FROM analytics_events
                                WHERE $dl_where
                                GROUP BY COALESCE(record_type,'Unknown')");
    if ($ae_by_type) while ($r = $ae_by_type->fetch_assoc()) $stats['downloads_by_type'][$r['k']] = (int)$r['cnt'];

    $ae_by_format = $conn->query("SELECT COALESCE(download_format,'unknown') AS k, COUNT(*) AS cnt
                                  FROM analytics_events
                                  WHERE $dl_where
                                  GROUP BY COALESCE(download_format,'unknown')");
    if ($ae_by_format) while ($r = $ae_by_format->fetch_assoc()) $stats['downloads_by_format'][strtoupper($r['k'])] = (int)$r['cnt'];

    $ae_recent = $conn->query("SELECT event_type, record_title, record_type, download_format, bytes, created_at
                               FROM analytics_events
                               WHERE $act_where
                               ORDER BY created_at DESC
                               LIMIT 15");
    if ($ae_recent) while ($r = $ae_recent->fetch_assoc()) $stats['recent_activity'][] = $r;
} else {
    // Legacy fallback: counts "records that have been accessed at least once"
    $result = $conn->query("SELECT COUNT(*) as downloads FROM legislative_records WHERE last_accessed IS NOT NULL");
    $stats['downloads'] = ($result && $row = $result->fetch_assoc()) ? (int)$row['downloads'] : 0;

    $result = $conn->query("SELECT type, COUNT(*) as cnt FROM legislative_records WHERE last_accessed IS NOT NULL GROUP BY type");
    if ($result) while ($r = $result->fetch_assoc()) $stats['downloads_by_type'][$r['type']] = (int)$r['cnt'];

    $result = $conn->query("SELECT id, title, type, author, last_accessed FROM legislative_records WHERE last_accessed IS NOT NULL ORDER BY last_accessed DESC LIMIT 10");
    if ($result) while ($r = $result->fetch_assoc()) $stats['recent_downloads'][] = $r;
}

$result = $conn->query("SELECT id, title, type, author, created_at FROM legislative_records ORDER BY created_at DESC LIMIT 10");
$stats['recent_added'] = [];
if ($result) while ($r = $result->fetch_assoc()) $stats['recent_added'][] = $r;

// Calculate uploads directory size (will show profile pictures and any other uploads)
$uploads_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$uploads_bytes = dir_size($uploads_path);

function format_bytes($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $e = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $e), 2) . ' ' . $units[$e];
}

$exportQuery = $_GET;
$exportQuery['export'] = 'csv';
$exportUrl = 'report_analytics.php?' . http_build_query($exportQuery);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'report_analytics_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report', 'Generated At']);
    fputcsv($out, ['Reports & Analytics', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Total Records', (string)($stats['total_records'] ?? 0)]);
    fputcsv($out, ['Total Downloads', (string)($stats['downloads'] ?? 0)]);
    fputcsv($out, ['Uploads Folder Size', format_bytes($uploads_bytes)]);
    fputcsv($out, []);
    fputcsv($out, ['Downloads by Type']);
    fputcsv($out, ['Type', 'Count']);
    if (!empty($stats['downloads_by_type'])) {
        foreach ($stats['downloads_by_type'] as $type => $count) {
            fputcsv($out, [$type, (string)$count]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['Downloads by Format']);
    fputcsv($out, ['Format', 'Count']);
    if (!empty($stats['downloads_by_format'])) {
        foreach ($stats['downloads_by_format'] as $format => $count) {
            fputcsv($out, [$format, (string)$count]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['Recent Activity']);
    if (!empty($stats['recent_activity'])) {
        fputcsv($out, ['Event', 'Title', 'Type', 'Format', 'Bytes', 'Date']);
        foreach ($stats['recent_activity'] as $row) {
            fputcsv($out, [
                $row['event_type'] ?? '',
                $row['record_title'] ?? '',
                $row['record_type'] ?? '',
                $row['download_format'] ?? '',
                isset($row['bytes']) ? (string)$row['bytes'] : '',
                $row['created_at'] ?? '',
            ]);
        }
    } elseif (!empty($stats['recent_downloads'])) {
        fputcsv($out, ['Title', 'Type', 'Author', 'Date']);
        foreach ($stats['recent_downloads'] as $row) {
            fputcsv($out, [
                $row['title'] ?? '',
                $row['type'] ?? '',
                $row['author'] ?? '',
                $row['last_accessed'] ?? '',
            ]);
        }
    } else {
        fputcsv($out, ['No recent activity available']);
    }
    fclose($out);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reports & Analytics - Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/archives-landing-head.js"></script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .card { border-radius: 0.75rem; }
        .skeleton { position: relative; overflow: hidden; }
        .skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: translateX(-100%);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
        .skeleton-block { height: 160px; border-radius: 0.5rem; background-color: #f3f4f6; }
        .skeleton-line { height: 12px; border-radius: 0.5rem; background-color: #f3f4f6; }
        .dark .skeleton-block, body.dark .skeleton-block,
        .dark .skeleton-line, body.dark .skeleton-line { background-color: rgba(100,116,139,0.35); }
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
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
        <nav class="flex-1 py-4 px-3 overflow-hidden">
            <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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

            <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-cloud-upload mr-3 text-lg"></i>
                <span>Export</span>
            </a>

           
            <!-- ANALYTICS Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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
            <nav class="flex-1 overflow-hidden py-4">
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

                    <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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
                    <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
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

                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Report & Analytics</h2>
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

            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900 p-4 sm:p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    <div class="card bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                            <div>
                                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Reports & Analytics</h1>
                                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Quick overview of records, downloads, and recent activity</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                            <div class="flex items-center gap-2">
                                <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Export CSV</a>
                                <a href="archives-landing.php" class="px-3 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Back</a>
                                <div class="relative">
                                    <button id="more-actions-btn" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200" title="More options">
                                        <i class="bi bi-three-dots-vertical text-lg"></i>
                                    </button>
                                    <div id="more-actions-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                        <button id="refresh-analytics" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Refresh Data</button>
                                        <a href="audit-logs.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">View Audit Logs</a>
                                    </div>
                                </div>
                            </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">Download location: your browser’s default Downloads folder.</div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300 flex items-center justify-center"><i class="bi bi-file-earmark-text"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Total Records</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['total_records']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 flex items-center justify-center"><i class="bi bi-download"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Total Downloads</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['downloads']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-300 flex items-center justify-center"><i class="bi bi-hdd"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Uploads Folder Size</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo format_bytes($uploads_bytes); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-300 flex items-center justify-center"><i class="bi bi-tags"></i></div>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-500">Types</div>
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">
                                            <?php if (!empty($stats['by_type'])): ?>
                                                <?php foreach ($stats['by_type'] as $k=>$v) echo '<span class="inline-block mr-2">'.htmlspecialchars($k).': <strong>'.(int)$v.'</strong></span>'; ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">No types</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="text-xs text-gray-600 dark:text-gray-400">From</label>
                                <input id="filter-from" type="date" value="<?php echo htmlspecialchars($f_start ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <label class="text-xs text-gray-600 dark:text-gray-400 ml-2">To</label>
                                <input id="filter-to" type="date" value="<?php echo htmlspecialchars($f_end ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <select id="filter-type" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <option value="">All Types</option>
                                    <?php foreach (($stats['by_type'] ?? []) as $k=>$v): ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($safe_type === $k ? 'selected' : ''); ?>><?php echo htmlspecialchars($k); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex md:justify-end">
                                <button id="apply-filters" aria-pressed="false" class="px-3 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Apply</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="col-span-2 card p-4 sm:p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Records by Type</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo count($stats['by_type'] ?? []); ?> types</span>
                            </div>
                            <div id="sk-records" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <canvas id="recordsTypeChart" height="180"></canvas>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Downloads by Type</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo array_sum($stats['downloads_by_type'] ?? []); ?></span>
                            </div>
                            <div id="sk-downloads-type" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <canvas id="downloadsTypeChart" height="180"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Downloads by Format</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo array_sum($stats['downloads_by_format'] ?? []); ?></span>
                            </div>
                            <div id="sk-downloads-format" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <canvas id="downloadsFormatChart" height="180"></canvas>
                        </div>
                        <div class="lg:col-span-2 card p-4 sm:p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Recent Activity</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo count($stats['recent_activity'] ?? []); ?></span>
                            </div>
                            <div id="sk-recent-activity" class="skeleton space-y-2 mb-2">
                                <div class="skeleton-line w-2/3"></div>
                                <div class="skeleton-line w-full"></div>
                                <div class="skeleton-line w-5/6"></div>
                                <div class="skeleton-line w-full"></div>
                                <div class="skeleton-line w-4/5"></div>
                                <div class="skeleton-line w-full"></div>
                            </div>
                            <?php if (!empty($stats['recent_activity'])): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500">
                                            <tr><th class="py-2 pr-3">When</th><th class="py-2 pr-3">Event</th><th class="py-2 pr-3">Title</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Format</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                        <?php foreach ($stats['recent_activity'] as $a): ?>
                                            <tr class="even:bg-gray-50 dark:even:bg-slate-800/60">
                                                <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['created_at']); ?></td>
                                                <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['event_type']); ?></td>
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars($a['record_title'] ?? ''); ?></td>
                                                <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['record_type'] ?? ''); ?></td>
                                                <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars(strtoupper($a['download_format'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-sm text-gray-500">No tracked activity yet. Downloads will appear here after using the download modal.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Recent Downloads</h3>
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <?php if (empty($stats['recent_downloads'])): ?>
                                    <div class="text-gray-500">This section is legacy-based. Use "Recent Activity" above for per-download events.</div>
                                <?php else: ?>
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500">
                                            <tr><th>Title</th><th>Type</th><th>Author</th><th>When</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_downloads'] as $row): ?>
                                                <tr class="border-t"><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo htmlspecialchars($row['type']); ?></td><td><?php echo htmlspecialchars($row['author']); ?></td><td><?php echo htmlspecialchars($row['last_accessed']); ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Recently Added</h3>
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <?php if (empty($stats['recent_added'])): ?>
                                    <div class="text-gray-500">No recent records.</div>
                                <?php else: ?>
                                    <ul class="list-disc pl-5">
                                        <?php foreach ($stats['recent_added'] as $r): ?>
                                            <li><?php echo htmlspecialchars($r['title'].' — '.$r['type'].' — '.date('M j, Y', strtotime($r['created_at']))); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality (mobile + desktop)
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('sidebar-collapsed'));
        });

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
        profileBtn?.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown?.classList.toggle('hidden'); });
        document.addEventListener('click', () => profileDropdown?.classList.add('hidden'));

        const notifBtn = document.getElementById('notification-btn');
        const notifDropdown = document.getElementById('notification-dropdown');
        notifBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.toggle('hidden');
        });
        document.addEventListener('click', () => notifDropdown?.classList.add('hidden'));

        const moreBtn = document.getElementById('more-actions-btn');
        const moreDropdown = document.getElementById('more-actions-dropdown');
        const refreshBtn = document.getElementById('refresh-analytics');
        moreBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.add('hidden');
            moreDropdown?.classList.toggle('hidden');
        });
        refreshBtn?.addEventListener('click', () => {
            moreDropdown?.classList.add('hidden');
            location.reload();
        });
        document.addEventListener('click', () => moreDropdown?.classList.add('hidden'));

        if (localStorage.getItem('sidebarCollapsed') === 'true') sidebar?.classList.add('sidebar-collapsed');

        // Charts
        const byType = <?php echo json_encode($stats['by_type']); ?>;
        const downloadsByType = <?php echo json_encode($stats['downloads_by_type']); ?>;
        const downloadsByFormat = <?php echo json_encode($stats['downloads_by_format']); ?>;
        function labelsAndData(obj) { const labels = Object.keys(obj); const data = Object.values(obj); return { labels, data }; }
        const rt = labelsAndData(byType);
        const dt = labelsAndData(downloadsByType);
        const df = labelsAndData(downloadsByFormat);
        const hideSk = (id) => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); };
        const recordsCtx = document.getElementById('recordsTypeChart')?.getContext('2d');
        if (recordsCtx) { new Chart(recordsCtx, { type: 'pie', data: { labels: rt.labels, datasets: [{ data: rt.data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } }); hideSk('sk-records'); }
        const downloadsCtx = document.getElementById('downloadsTypeChart')?.getContext('2d');
        if (downloadsCtx) { new Chart(downloadsCtx, { type: 'bar', data: { labels: dt.labels, datasets: [{ label: 'Downloads', data: dt.data, backgroundColor: '#2563eb' }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, precision:0 } } } }); hideSk('sk-downloads-type'); }
        const downloadsFormatCtx = document.getElementById('downloadsFormatChart')?.getContext('2d');
        if (downloadsFormatCtx) { new Chart(downloadsFormatCtx, { type: 'doughnut', data: { labels: df.labels, datasets: [{ data: df.data, backgroundColor: ['#dc2626','#3b82f6','#10b981','#6b7280'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } }); hideSk('sk-downloads-format'); }
        hideSk('sk-recent-activity');
        const applyBtn = document.getElementById('apply-filters');
        let filtersApplied = false;
        function updateApplyBtn() {
            if (!applyBtn) return;
            applyBtn.classList.remove('bg-red-600','bg-red-700');
            applyBtn.classList.add(filtersApplied ? 'bg-red-700' : 'bg-red-600');
            applyBtn.setAttribute('aria-pressed', String(filtersApplied));
        }
        function currentFilters() {
            const from = document.getElementById('filter-from')?.value || '';
            const to = document.getElementById('filter-to')?.value || '';
            const type = document.getElementById('filter-type')?.value || '';
            const params = new URLSearchParams();
            if (from) params.set('start', from);
            if (to) params.set('end', to);
            if (type) params.set('type', type);
            return params;
        }
        function initFiltersApplied() {
            const qs = new URLSearchParams(window.location.search);
            filtersApplied = qs.has('start') || qs.has('end') || qs.has('type') || qs.has('format') || qs.has('event');
            updateApplyBtn();
        }
        applyBtn?.addEventListener('click', () => {
            const params = currentFilters();
            const url = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            window.location.assign(url);
        });
        initFiltersApplied();

    </script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

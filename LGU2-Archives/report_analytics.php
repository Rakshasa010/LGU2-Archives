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

// Downloads + Activity (prefer analytics_events if available; fallback to legacy last_accessed)
$stats['downloads'] = 0;
$stats['downloads_by_type'] = [];
$stats['downloads_by_format'] = [];
$stats['recent_activity'] = [];
$stats['recent_downloads'] = []; // legacy fallback table

$ae_count = $conn->query("SELECT COUNT(*) AS cnt FROM analytics_events WHERE event_type='download'");
if ($ae_count && ($row = $ae_count->fetch_assoc())) {
    $stats['downloads'] = (int)$row['cnt'];

    $ae_by_type = $conn->query("SELECT COALESCE(record_type,'Unknown') AS k, COUNT(*) AS cnt
                                FROM analytics_events
                                WHERE event_type='download'
                                GROUP BY COALESCE(record_type,'Unknown')");
    if ($ae_by_type) while ($r = $ae_by_type->fetch_assoc()) $stats['downloads_by_type'][$r['k']] = (int)$r['cnt'];

    $ae_by_format = $conn->query("SELECT COALESCE(download_format,'unknown') AS k, COUNT(*) AS cnt
                                  FROM analytics_events
                                  WHERE event_type='download'
                                  GROUP BY COALESCE(download_format,'unknown')");
    if ($ae_by_format) while ($r = $ae_by_format->fetch_assoc()) $stats['downloads_by_format'][strtoupper($r['k'])] = (int)$r['cnt'];

    $ae_recent = $conn->query("SELECT event_type, record_title, record_type, download_format, bytes, created_at
                               FROM analytics_events
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
        /* make main content appear offset when sidebar visible */
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
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
            
            <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-folder mr-3"></i>
                <span class="sidebar-text">Export</span>
            </a>
                    
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>

            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white bg-red-700 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="flex h-screen overflow-hidden">
        <!-- Desktop Sidebar -->
        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
            <div class="p-6 border-b border-red-700 sidebar-logo">
                <a href="archives-landing.php" class="flex items-center space-x-3 hover:opacity-80 transition-all duration-300">
                    <div class="bg-white rounded-full shadow-md flex items-center justify-center overflow-hidden" style="width:56px; height:56px;">
                        <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="object-contain">
                    </div>
                    <div>
                        <h1 class="text-lg font-bold">LAS</h1>
                        <p class="text-xs text-red-200">City of Valenzuela</p>
                    </div>
                </a>
            </div>
            <nav class="flex-1 overflow-y-hidden py-4 px-3">
                <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1">
                    <i class="bi bi-speedometer2 mr-3 text-lg"></i>
                    <span>Dashboard Archives</span>
                </a>
                <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1">
                    <i class="bi bi-folder mr-3 text-lg"></i>
                    <span>Main Storage Archives</span>
                </a>

                <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-cloud-upload mr-3"></i>
                    <span class="sidebar-text">Export</span>
                </a>

                <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1">
                    <i class="bi bi-trash mr-3 text-lg"></i>
                    <span>Recently Deleted</span>
                </a>
                <div class="mt-4 pt-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                    <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white bg-red-700 rounded-lg mb-1">
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
                        <div class="flex items-center">
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            <!-- Back to Dashboard + Theme toggle -->
                            <div class="hidden md:flex items-center ml-3 space-x-2">
                                <a href="archives-landing.php" class="px-3 py-1 bg-gray-100 dark:bg-slate-700 rounded text-sm text-gray-700 dark:text-gray-200 hover:opacity-90">&larr;</a>
                            </div>
                        </div>
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Reports & Analytics</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Overview of archive activity</p>
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
                            <div class="relative">
                                <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                    <?php if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                                        <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
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

            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900 p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <div class="text-sm text-gray-500">Total Records</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['total_records']; ?></div>
                        </div>
                        <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <div class="text-sm text-gray-500">Total Downloads</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['downloads']; ?></div>
                        </div>
                        <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <div class="text-sm text-gray-500">Uploads Folder Size</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?php echo format_bytes($uploads_bytes); ?></div>
                        </div>
                        <div class="card p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <div class="text-sm text-gray-500">Types</div>
                            <div class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                <?php if (!empty($stats['by_type'])): ?>
                                    <?php foreach ($stats['by_type'] as $k=>$v) echo '<span class="inline-block mr-2">'.htmlspecialchars($k).': <strong>'.(int)$v.'</strong></span>'; ?>
                                <?php else: ?>
                                    <span class="text-gray-500">No types</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="col-span-2 card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Records by Type</h3>
                            <canvas id="recordsTypeChart" height="160"></canvas>
                        </div>
                        <div class="card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Downloads by Type</h3>
                            <canvas id="downloadsTypeChart" height="160"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Downloads by Format</h3>
                            <canvas id="downloadsFormatChart" height="160"></canvas>
                        </div>
                        <div class="lg:col-span-2 card p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Recent Activity</h3>
                            <?php if (!empty($stats['recent_activity'])): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500">
                                            <tr><th>When</th><th>Event</th><th>Title</th><th>Type</th><th>Format</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($stats['recent_activity'] as $a): ?>
                                            <tr class="border-t">
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars($a['created_at']); ?></td>
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars($a['event_type']); ?></td>
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars($a['record_title'] ?? ''); ?></td>
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars($a['record_type'] ?? ''); ?></td>
                                                <td class="py-2 pr-3"><?php echo htmlspecialchars(strtoupper($a['download_format'] ?? '')); ?></td>
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

        if (localStorage.getItem('sidebarCollapsed') === 'true') sidebar?.classList.add('sidebar-collapsed');

        // Charts
        const byType = <?php echo json_encode($stats['by_type']); ?>;
        const downloadsByType = <?php echo json_encode($stats['downloads_by_type']); ?>;
        const downloadsByFormat = <?php echo json_encode($stats['downloads_by_format']); ?>;
        function labelsAndData(obj) { const labels = Object.keys(obj); const data = Object.values(obj); return { labels, data }; }
        const rt = labelsAndData(byType);
        const dt = labelsAndData(downloadsByType);
        const df = labelsAndData(downloadsByFormat);
        const recordsCtx = document.getElementById('recordsTypeChart')?.getContext('2d');
        if (recordsCtx) new Chart(recordsCtx, { type: 'pie', data: { labels: rt.labels, datasets: [{ data: rt.data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });
        const downloadsCtx = document.getElementById('downloadsTypeChart')?.getContext('2d');
        if (downloadsCtx) new Chart(downloadsCtx, { type: 'bar', data: { labels: dt.labels, datasets: [{ label: 'Downloads', data: dt.data, backgroundColor: '#2563eb' }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, precision:0 } } } });
        const downloadsFormatCtx = document.getElementById('downloadsFormatChart')?.getContext('2d');
        if (downloadsFormatCtx) new Chart(downloadsFormatCtx, { type: 'doughnut', data: { labels: df.labels, datasets: [{ data: df.data, backgroundColor: ['#dc2626','#3b82f6','#10b981','#6b7280'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });

    </script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

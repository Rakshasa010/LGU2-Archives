<?php
include 'authdatabase.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$display_name = 'User';
$profile_picture = null;
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $ud = $res->fetch_assoc();
        $display_name = $ud['full_name'] ?? $display_name;
        $profile_picture = $ud['profile_picture'] ?? $profile_picture;
    }
    $stmt->close();
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#dc2626', light: '#f97316' }
                    }
                }
            }
        }
    </script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        html, body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        .toggle-pill { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .625rem; border-radius: 9999px; border: 1px solid rgba(203,213,225,.6); }
        .toggle-track { position: relative; width: 40px; height: 20px; border-radius: 9999px; background-color: rgba(203,213,225,.6); }
        .dark .toggle-track { background-color: rgba(30,41,59,.6); }
        .toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 9999px; background: white; transition: transform .2s ease; }
        .dark .toggle-thumb { transform: translateX(20px); }
    </style>
</head>
        <div class="flex min-h-screen">
        <?php
        $sidebar_active_page = 'version-tracking';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <!-- Main Content (Outer Wrapper with 2nd Sidebar) -->
        <div class="flex-1 flex flex-col overflow-hidden bg-[#171a21]">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 z-40 transition-colors duration-200 w-full flex-shrink-0">
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
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Version Tracking</h2>
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
                        
                            <!-- Notification Dropdown -->
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

                            <!-- User Profile Dropdown -->
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
            <div class="flex-1 flex overflow-hidden bg-gray-50 dark:bg-[#171a21] transition-colors duration-200">
            
            <!-- Secondary Dark Menu (Version Tracking Context) -->
            <aside class="w-64 bg-white dark:bg-[#1e232d] flex flex-col flex-shrink-0 border-r border-gray-200 dark:border-[#242934] hidden md:flex text-gray-700 dark:text-gray-300 transition-colors duration-200">
                <div class="flex-1 overflow-y-auto p-5">
                    <h2 class="text-xs font-bold tracking-widest text-gray-500 dark:text-[#727a8a] mb-5 mt-2 uppercase px-1">Version Tracking</h2>
                    <div class="relative mb-8">
                        <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 dark:text-gray-500"></i>
                        <input type="text" placeholder="Search folders..." class="w-full bg-gray-100 dark:bg-[#141820] text-sm text-gray-800 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-600 rounded-md pl-9 pr-3 py-2 border border-transparent dark:border-[#242934] focus:outline-none focus:ring-1 focus:ring-red-500 dark:focus:border-gray-500 transition-colors shadow-inner">
                    </div>
                    
                    <div class="mb-3 text-[10px] font-bold tracking-widest text-gray-500 dark:text-[#5c6370] uppercase px-1">Folders</div>
                    <div class="space-y-0.5" id="vt-folders-list">
                        <button type="button" onclick="viewFolder('ordRes','Ordinances & Resolutions')" class="w-full flex items-center justify-between px-2 py-2 rounded bg-blue-50 dark:bg-blue-900/10 text-blue-800 dark:text-blue-100 hover:bg-gray-100 dark:hover:bg-[#2a303c] transition-colors group">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded flex items-center justify-center bg-red-100 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 group-hover:bg-red-200 dark:group-hover:bg-red-500/20 transition-colors"><i class="bi bi-file-earmark-text text-red-600 dark:text-red-400 text-[10px]"></i></div>
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100">Ordinances &amp; Resos</span>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('billing','Billing')" class="w-full flex items-center justify-between px-2 py-2 rounded text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2a303c] transition-colors group">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded flex items-center justify-center bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/20 transition-colors"><i class="bi bi-receipt text-emerald-600 dark:text-emerald-400 text-[10px]"></i></div>
                                <span class="text-[13px] font-medium">Billing</span>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('publicHearing','Public Hearings')" class="w-full flex items-center justify-between px-2 py-2 rounded text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2a303c] transition-colors group">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded flex items-center justify-center bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 group-hover:bg-blue-100 dark:group-hover:bg-blue-500/20 transition-colors"><i class="bi bi-megaphone text-blue-600 dark:text-blue-400 text-[10px]"></i></div>
                                <span class="text-[13px] font-medium">Public Hearings</span>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('meeting','Meeting/Sessions')" class="w-full flex items-center justify-between px-2 py-2 rounded text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2a303c] transition-colors group">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded flex items-center justify-center bg-purple-50 dark:bg-purple-500/10 border border-purple-100 dark:border-purple-500/20 group-hover:bg-purple-100 dark:group-hover:bg-purple-500/20 transition-colors"><i class="bi bi-journal-text text-purple-600 dark:text-purple-400 text-[10px]"></i></div>
                                <span class="text-[13px] font-medium">Meeting/Sessions</span>
                            </div>
                        </button>
                    </div>

                    <div class="mt-8 mb-3 text-[10px] font-bold tracking-widest text-gray-500 dark:text-[#5c6370] uppercase px-1">Recent</div>
                    <div class="space-y-1.5 px-2" id="vt-recent-list">
                        <!-- Loaded dynamically -->
                        <div class="py-2 text-xs text-gray-400 italic">No recent items</div>
                    </div>
                </div>
            </aside>

            <!-- Main Panel -->
            <div class="flex-1 flex flex-col overflow-hidden bg-gray-50 dark:bg-[#171a21] transition-colors duration-200" id="mainContentArea">
                <!-- Topbar -->
                <div class="flex flex-wrap items-center justify-between px-6 lg:px-10 py-5 border-b border-gray-200 dark:border-[#242934] bg-white dark:bg-[#1a1e27] z-10 w-full sticky top-0 transition-colors duration-200">
                    <div class="flex items-center gap-2">
                        <button id="mobile-menu-btn" class="md:hidden text-gray-500 hover:text-gray-800 dark:hover:text-white p-2 focus:outline-none transition-colors">
                            <i class="bi bi-list text-2xl"></i>
                        </button>
                        <div class="flex items-center text-sm font-medium text-gray-500 dark:text-[#727a8a]">
                            <span>Files</span>
                            <i class="bi bi-chevron-right text-[9px] mx-3 opacity-60"></i>
                            <span class="text-gray-800 dark:text-gray-200 tracking-wide font-semibold" id="breadcrumbFolder">Dashboard</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button class="px-3 py-1.5 border border-gray-200 dark:border-[#3b4253] bg-white dark:bg-transparent hover:bg-gray-50 dark:hover:bg-[#2a303c] text-gray-600 dark:text-gray-300 rounded text-xs font-semibold flex items-center gap-2 transition-colors">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </div>
                
                <!-- Main Content Body -->
                <div class="flex-1 overflow-y-auto w-full p-6 lg:p-10 hide-scrollbar" id="dynamicContent">
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-10" id="statsBar" style="display:none;">
                        <div class="bg-white dark:bg-[#1e232d] shadow border border-gray-100 dark:border-[#2c3240] rounded-xl p-5 hover:border-gray-200 dark:hover:border-[#3b4253] transition-colors relative overflow-hidden group">
                            <!-- Subtle glow background effect -->
                            <div class="absolute -top-10 -right-10 w-32 h-32 bg-red-50 dark:bg-red-500/5 rounded-full blur-2xl group-hover:bg-red-100 dark:group-hover:bg-red-500/10 transition-colors"></div>
                            
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.3)] dark:shadow-[0_0_8px_rgba(239,68,68,0.8)]"></div>
                                <span class="text-xs text-gray-500 dark:text-[#8a92a3] tracking-wide">Total files</span>
                            </div>
                            <div class="text-3xl font-light text-gray-900 dark:text-white mb-0.5 tracking-tight" id="statTotalFiles">0</div>
                            <div class="text-[11px] text-gray-400 dark:text-[#5c6370]" id="statTotalSub">across this folder</div>
                        </div>
                        <div class="bg-white dark:bg-[#1e232d] shadow border border-gray-100 dark:border-[#2c3240] rounded-xl p-5 hover:border-gray-200 dark:hover:border-[#3b4253] transition-colors relative overflow-hidden group">
                            <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-50 dark:bg-blue-500/5 rounded-full blur-2xl group-hover:bg-blue-100 dark:group-hover:bg-blue-500/10 transition-colors"></div>
                            
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.3)] dark:shadow-[0_0_8px_rgba(59,130,246,0.8)]"></div>
                                <span class="text-xs text-gray-500 dark:text-[#8a92a3] tracking-wide">Active versions</span>
                            </div>
                            <div class="text-3xl font-light text-gray-900 dark:text-white mb-0.5 tracking-tight" id="statActive">0</div>
                            <div class="text-[11px] text-gray-400 dark:text-[#5c6370]">this folder</div>
                        </div>
                        <div class="bg-white dark:bg-[#1e232d] shadow border border-gray-100 dark:border-[#2c3240] rounded-xl p-5 hover:border-gray-200 dark:hover:border-[#3b4253] transition-colors relative overflow-hidden group">
                            <div class="absolute -top-10 -right-10 w-32 h-32 bg-emerald-50 dark:bg-emerald-500/5 rounded-full blur-2xl group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/10 transition-colors"></div>
                            
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.3)] dark:shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                                <span class="text-xs text-gray-500 dark:text-[#8a92a3] tracking-wide">Updated today</span>
                            </div>
                            <div class="text-3xl font-light text-gray-900 dark:text-white mb-0.5 tracking-tight" id="statToday">0</div>
                            <div class="text-[11px] text-gray-400 dark:text-[#5c6370]">recent updates</div>
                        </div>
                    </div>

                    <!-- File Grid Section -->
                    <div id="gridSection" style="display:none;">
                        <h3 class="text-[17px] font-semibold text-gray-900 dark:text-gray-100 mb-6 tracking-tight" id="gridTitle">0 files</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6" id="filesGrid">
                            <!-- JS populated cards go here -->
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div id="emptyState" class="flex flex-col items-center justify-center py-32 text-center h-full">
                        <div class="w-20 h-20 bg-white dark:bg-[#1e232d] rounded-2xl flex items-center justify-center mb-6 shadow border border-gray-100 dark:border-[#2a303c]">
                            <i class="bi bi-stack text-3xl text-red-500/70"></i>
                        </div>
                        <h3 class="text-xl font-medium text-gray-800 dark:text-gray-200">Select a folder</h3>
                        <p class="text-sm text-gray-500 dark:text-[#727a8a] mt-2 max-w-xs leading-relaxed">Choose a category from the sidebar to view file version tracks and metrics.</p>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <div id="versionModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeVersionModal()"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="vm-title" class="text-2xl font-bold text-gray-800 dark:text-gray-200">Version History</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeVersionModal()">&times;</button>
                </div>
                <div id="vm-list" class="space-y-3"></div>
            </div>
        </div>
    </div>

    <script>
        function viewFolder(key, label) {
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('statsBar').style.display = 'grid';
            document.getElementById('gridSection').style.display = 'block';
            
            document.getElementById('breadcrumbFolder').textContent = label;
            document.getElementById('gridTitle').textContent = 'Loading...';
            
            var grid = document.getElementById('filesGrid');
            grid.innerHTML = '';
            
            var typesToFetch = [];
            if (key === 'ordRes') typesToFetch = ['Ordinance', 'Resolution'];
            else if (key === 'billing') typesToFetch = ['Billing'];
            else if (key === 'publicHearing') typesToFetch = ['Public Hearing'];
            else if (key === 'meeting') typesToFetch = ['Meeting'];

            var promises = typesToFetch.map(function(t) {
                return fetch('legislative_api.php?action=get_files&type=' + encodeURIComponent(t))
                    .then(function(r){ return r.json(); })
                    .then(function(d){ return d.success ? d.files : []; });
            });

            Promise.all(promises).then(function(results) {
                var allFiles = results.flat().sort(function(a, b) {
                    return new Date(b.created_at) - new Date(a.created_at);
                });

                document.getElementById('gridTitle').textContent = allFiles.length + ' files in ' + label;
                document.getElementById('statTotalFiles').textContent = allFiles.length;
                document.getElementById('statTotalSub').textContent = 'across this folder';
                document.getElementById('statActive').textContent = allFiles.filter(f => f.version && f.version > 1).length;
                
                let todayCount = 0;
                let todayStr = new Date().toISOString().split('T')[0];
                allFiles.forEach(f => {
                    if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                });
                document.getElementById('statToday').textContent = todayCount;

                if (!allFiles.length) {
                    grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">No files found</div>';
                } else {
                    allFiles.forEach(function(record){
                        var type = String(record.type||'');
                        var theme = {
                            bg: 'bg-red-500/10', border: 'border-red-500/20', iconColor: 'text-red-400', icon: 'bi-file-earmark-text', pill: 'bg-red-500/20 text-red-300 border-red-500/30'
                        };
                        if (type === 'Billing') {
                            theme = { bg: 'bg-emerald-500/10', border: 'border-emerald-500/20', iconColor: 'text-emerald-400', icon: 'bi-receipt', pill: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' };
                        } else if (type === 'Public Hearing') {
                            theme = { bg: 'bg-blue-500/10', border: 'border-blue-500/20', iconColor: 'text-blue-400', icon: 'bi-megaphone', pill: 'bg-blue-500/20 text-blue-300 border-blue-500/30' };
                        } else if (type === 'Meeting') {
                            theme = { bg: 'bg-purple-500/10', border: 'border-purple-500/20', iconColor: 'text-purple-400', icon: 'bi-journal-text', pill: 'bg-purple-500/20 text-purple-300 border-purple-500/30' };
                        }

                        var dateStr = String(record.month||'') + ' ' + String(record.year||'');
                        if(!dateStr.trim() && record.created_at) {
                            var d = new Date(record.created_at);
                            dateStr = d.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                        }

                        var verStr = record.version ? 'v' + record.version : 'v1.0';

                        card.className = 'bg-white dark:bg-[#1e232d] shadow-lg rounded-xl border border-gray-100 dark:border-[#2a303c] overflow-hidden hover:border-gray-200 dark:hover:border-[#3b4253] hover:shadow-xl transition-all duration-200 group cursor-pointer flex flex-col h-full';
                        card.onclick = function() { openVersionHistory(record); };

                        card.innerHTML = `
                            <div class="h-32 w-full ${theme.bg} ${theme.border} flex items-center justify-center p-4 relative overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-white dark:to-[#1e232d] opacity-50"></div>
                                <div class="w-16 h-16 rounded-xl bg-white dark:bg-[#1e232d] shadow border border-gray-100 dark:border-[#2a303c] flex items-center justify-center z-10 group-hover:scale-105 transition-transform duration-300">
                                    <i class="bi ${theme.icon} text-2xl ${theme.iconColor}"></i>
                                </div>
                            </div>
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="font-medium text-[13px] text-gray-800 dark:text-gray-200 truncate mb-1" title="${record.title}">${record.title}</div>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <div class="text-[11px] text-gray-500 dark:text-[#727a8a]">${dateStr}</div>
                                    <div class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${theme.pill}">${verStr}</div>
                                </div>
                            </div>
                        `;
                        grid.appendChild(card);
                    });
                }
            });
        }
        (function(){
            var list = document.getElementById('vt-folders-list');
            if (!list) return;
            fetch('archives_api.php?action=list_folders').then(function(r){ return r.json(); }).then(function(d){
                var folders = (d && d.success) ? (d.folders || []) : [];
                // Not generating Folders here automatically anymore because we hardcoded them to match design perfectly in HTML.
                // But if they are dynamic we can append them. Given the strict design, we keep the dynamic ones appended at bottom with grey styles.
                var html = folders.map(function(f){
                    return '<button type="button" data-id="'+String(f.id)+'" data-name="'+String(f.name)+'" class="w-full flex items-center justify-between px-2 py-2 rounded text-gray-400 hover:text-gray-200 hover:bg-[#2a303c] transition-colors group">'
                         + '<div class="flex items-center gap-2">'
                         + '<div class="w-5 h-5 rounded flex items-center justify-center bg-gray-500/10 border border-gray-500/20 group-hover:bg-gray-500/20 group-hover:border-gray-500/30 transition-colors"><i class="bi bi-folder-fill text-gray-500 text-[10px]"></i></div>'
                         + '<span class="text-[13px] font-medium">'+String(f.name)+'</span>'
                         + '</div></button>';
                }).join('');
                list.insertAdjacentHTML('beforeend', html);
                Array.prototype.forEach.call(list.querySelectorAll('button[data-id]'), function(btn){
                    btn.addEventListener('click', function(){
                        var folder = { id: parseInt(btn.getAttribute('data-id'),10), name: btn.getAttribute('data-name') };
                        viewArchiveFolder(folder);
                    });
                });
            }).catch(function(){});

            // Fetch Recent Files for sidebar
            var typesToFetch = ['Ordinance', 'Resolution', 'Billing', 'Public Hearing', 'Meeting'];
            var promises = typesToFetch.map(function(t) {
                return fetch('legislative_api.php?action=get_files&type=' + encodeURIComponent(t))
                    .then(function(r){ return r.json(); })
                    .then(function(d){ return d.success ? d.files : []; });
            });
            Promise.all(promises).then(function(results) {
                var allFiles = results.flat().sort(function(a, b) {
                    return new Date(b.created_at) - new Date(a.created_at);
                });
                var recList = document.getElementById('vt-recent-list');
                if(!recList) return;
                if(!allFiles.length) {
                    recList.innerHTML = '<div class="py-2 text-xs text-gray-400 italic">No recent items found</div>';
                    return;
                }
                recList.innerHTML = '';
                allFiles.slice(0, 5).forEach(function(f){
                    var type = String(f.type||'');
                    var dotColor = 'bg-red-500'; var shadowColor = 'dark:shadow-[0_0_8px_rgba(239,68,68,0.5)] shadow-[0_0_2px_rgba(239,68,68,0.3)]';
                    if (type === 'Billing') { dotColor = 'bg-emerald-500'; shadowColor = 'dark:shadow-[0_0_8px_rgba(16,185,129,0.5)] shadow-[0_0_2px_rgba(16,185,129,0.3)]'; }
                    else if (type === 'Public Hearing') { dotColor = 'bg-blue-500'; shadowColor = 'dark:shadow-[0_0_8px_rgba(59,130,246,0.5)] shadow-[0_0_2px_rgba(59,130,246,0.3)]'; }
                    else if (type === 'Meeting') { dotColor = 'bg-purple-500'; shadowColor = 'dark:shadow-[0_0_8px_rgba(168,85,247,0.5)] shadow-[0_0_2px_rgba(168,85,247,0.3)]'; }
                    
                    var dateStr = f.created_at ? new Date(f.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}) : 'Recently';
                    
                    var item = document.createElement('div');
                    item.className = 'flex items-center justify-between group cursor-pointer py-1';
                    item.onclick = function() { openVersionHistory(f); };
                    item.innerHTML = '<div class="flex items-center gap-2.5 min-w-0 pr-1">'
                        + '<div class="w-[6px] h-[6px] rounded-full ' + dotColor + ' flex-shrink-0 ' + shadowColor + '"></div>'
                        + '<span class="text-xs font-medium text-gray-600 dark:text-[#8a92a3] group-hover:text-gray-900 dark:group-hover:text-gray-200 truncate transition-colors" title="' + (f.title||f.name) + '">' + (f.title||f.name) + '</span>'
                        + '</div>'
                        + '<span class="text-[10px] text-gray-400 dark:text-[#5c6370] flex-shrink-0">' + dateStr + '</span>';
                    recList.appendChild(item);
                });
            });
        })();
        function clearFolder() {
            document.getElementById('emptyState').style.display = 'flex';
            document.getElementById('statsBar').style.display = 'none';
            document.getElementById('gridSection').style.display = 'none';
            document.getElementById('breadcrumbFolder').textContent = 'Dashboard';
        }
        function viewArchiveFolder(folder) {
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('statsBar').style.display = 'grid';
            document.getElementById('gridSection').style.display = 'block';
            
            document.getElementById('breadcrumbFolder').textContent = folder.name;
            document.getElementById('gridTitle').textContent = 'Loading...';
            
            var grid = document.getElementById('filesGrid');
            grid.innerHTML = '';
            
            fetch('archives_api.php?action=get_files&folder_id=' + encodeURIComponent(folder.id))
            .then(function(r){ return r.json(); })
            .then(function(d){
                var files = (d && d.success) ? (d.files || []) : [];
                document.getElementById('gridTitle').textContent = files.length + ' files in ' + folder.name;
                document.getElementById('statTotalFiles').textContent = files.length;
                document.getElementById('statTotalSub').textContent = 'in archive folder';
                document.getElementById('statActive').textContent = files.filter(f => f.version && f.version > 1).length;
                
                let todayCount = 0;
                let todayStr = new Date().toISOString().split('T')[0];
                files.forEach(f => {
                    if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                });
                document.getElementById('statToday').textContent = todayCount;

                if (!files.length) {
                    grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">No shadow files found</div>';
                } else {
                    files.forEach(function(f){
                        var theme = { bg: 'bg-orange-500/10', border: 'border-orange-500/20', iconColor: 'text-orange-400', icon: 'bi-archive', pill: 'bg-orange-500/20 text-orange-300 border-orange-500/30' };
                        var verStr = f.version ? 'v' + f.version : 'v1.0';

                        var card = document.createElement('div');
                        card.className = 'bg-white dark:bg-[#1e232d] shadow-lg rounded-xl border border-gray-100 dark:border-[#2a303c] overflow-hidden hover:border-gray-200 dark:hover:border-[#3b4253] hover:shadow-xl transition-all duration-200 group cursor-pointer flex flex-col h-full';
                        card.onclick = function() { openArchiveVersionHistory(f); };

                        card.innerHTML = `
                            <div class="h-32 w-full ${theme.bg} ${theme.border} flex items-center justify-center p-4 relative overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-white dark:to-[#1e232d] opacity-50"></div>
                                <div class="w-16 h-16 rounded-xl bg-white dark:bg-[#1e232d] shadow border border-gray-100 dark:border-[#2a303c] flex items-center justify-center z-10 group-hover:scale-105 transition-transform duration-300">
                                    <i class="bi ${theme.icon} text-2xl ${theme.iconColor}"></i>
                                </div>
                            </div>
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="font-medium text-[13px] text-gray-800 dark:text-gray-200 truncate mb-1" title="${f.title}">${f.title}</div>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <div class="text-[11px] text-gray-500 dark:text-[#727a8a] truncate pr-2">${f.created_at || 'Unknown'}</div>
                                    <div class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${theme.pill} flex-shrink-0">${verStr}</div>
                                </div>
                            </div>
                        `;
                        grid.appendChild(card);
                    });
                }
            });
        }

        function openArchiveVersionHistory(file) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (file && file.title ? file.title : 'File');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            fetch('archives_api.php?action=get_versions&id=' + (file && file.id ? file.id : ''))
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.success) {
                    if (!d.versions || d.versions.length === 0) {
                        list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                    } else {
                        list.innerHTML = d.versions.map(function(v){
                            return '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">'
                                 + '<div><div class="font-medium text-gray-800 dark:text-white">Version ' + String(v.version) + '</div>'
                                 + '<div class="text-xs text-gray-500 dark:text-gray-400">' + (v.created_at || '') + '</div></div>'
                                 + '<div class="flex space-x-2"><a href="#" onclick="window.open(\'' + encodeURI(file.file_path) + '\', \'_blank\')" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Open</a></div>'
                                 + '</div>';
                        }).join('');
                    }
                } else {
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                }
            });
            document.getElementById('versionModal').classList.remove('hidden');
        }
        function openVersionHistory(record) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (record && record.title ? record.title : 'File');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            
            fetch('legislative_api.php?action=get_versions&id=' + (record && record.id ? record.id : ''))
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    if(d.versions.length === 0) {
                        list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                    } else {
                        list.innerHTML = d.versions.map(v => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">
                                <div>
                                    <div class="font-medium text-gray-800 dark:text-white">Version ${v.version}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ${v.created_at} • ${v.author}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="download.php?${new URLSearchParams({
                                        id: v.id,
                                        title: (record && record.title) ? record.title : (v.title || 'Document'),
                                        type: (record && record.type) ? record.type : '',
                                        month: (record && record.month) ? record.month : '',
                                        year: (record && record.year) ? record.year : '',
                                        author: (record && record.author) ? record.author : ''
                                    }).toString()}" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                }
            });

            document.getElementById('versionModal').classList.remove('hidden');
        }
        function closeVersionModal(){
            document.getElementById('versionModal').classList.add('hidden');
        }
    </script>
    <script>
        (function(){
            var root = document.documentElement;
            var btn = document.getElementById('themeToggle');
            var sun = document.getElementById('sunIcon');
            var moon = document.getElementById('moonIcon');
            function apply(mode){
                var isDark = mode === 'dark';
                root.classList.toggle('dark', isDark);
                try { localStorage.setItem('theme', mode); } catch(e){}
                if (sun && moon) {
                    sun.classList.toggle('hidden', isDark);
                    moon.classList.toggle('hidden', !isDark);
                }
                root.dispatchEvent(new CustomEvent('themechange', { detail: { mode: mode } }));
            }
            var saved = 'light';
            try {
                saved = localStorage.getItem('theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch(e){}
            apply(saved);
            if (btn) {
                btn.addEventListener('click', function(){
                    apply(root.classList.contains('dark') ? 'light' : 'dark');
                });
            }
        })();
    </script>
    <script>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('sidebar-collapsed');
        }
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.remove('-translate-x-full');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
                    sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
                }
            });
        }
        if (closeMobileSidebar) {
            closeMobileSidebar.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.add('-translate-x-full');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                    sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
                }
            });
        }
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                if (mobileSidebar) mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            });
        }
        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notification-btn');
        const notifDropdown = document.getElementById('notification-dropdown');
        const notifCount = document.getElementById('notif-count');

        profileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown?.classList.add('hidden');
            profileDropdown?.classList.toggle('hidden');
        });

        notifBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.toggle('hidden');
            try {
                var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
                if (ids.length > 0) {
                    fetch('notifications_log.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
                    }).then(function(){});
                }
            } catch(e){}
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
                profileDropdown?.classList.add('hidden');
            }
            if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
                notifDropdown?.classList.add('hidden');
            }
        });

        (function(){
            function renderNotifList(items){
                var container = document.getElementById('notif-list');
                if (!container) return;
                if (!items || items.length === 0) {
                    container.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No notifications</div>';
                    return;
                }
                var html = items.map(function(n){
                    var href = n.link ? n.link : ('audit-logs.php?id='+encodeURIComponent(n.id));
                    var badge = '';
                    var textWeight = (n.status === 'unread') ? 'font-semibold' : 'font-medium';
                    if (n.status === 'unread') badge = ' ring-2 ring-red-200';
                    return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
                           '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
                           '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
                           '<div class="flex-1 min-w-0">'+
                           '<p class="text-sm '+textWeight+' text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
                           '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
                           '</div></a>';
                }).join('');
                container.innerHTML = html;
            }
            function escapeHtml(s){
                if (typeof s !== 'string') return '';
                return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); });
            }
            function fetchLatest(){
                fetch('notifications_fetch.php?page_size=5&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (d && d.success) renderNotifList(d.items||[]);
                }).catch(function(){});
            }
            function fetchUnread(){
                fetch('notifications_fetch.php?status=unread&page_size=1&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (!notifCount) return;
                    var total = (d && d.success) ? (d.total||0) : 0;
                    notifCount.textContent = String(total);
                    notifCount.style.display = total > 0 ? 'inline-flex' : 'none';
                }).catch(function(){});
            }
            function refresh(){
                fetchLatest();
                fetchUnread();
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', refresh);
            } else {
                refresh();
            }
            window.addEventListener('focus', refresh);
        })();
    </script>
    <script src="assets/js/storage-status.js"></script>

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

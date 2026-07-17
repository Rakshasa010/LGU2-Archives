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

// Fetch archive folders for sidebar
$archive_folders = [];
$folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
if ($folders_result && $folders_result->num_rows > 0) {
    while ($row = $folders_result->fetch_assoc()) {
        $archive_folders[] = $row;
    }
}

// Handle folder parameter from sidebar dropdown
$selected_folder = isset($_GET['folder']) ? trim($_GET['folder']) : null;
$page_title = "Version Tracking";
$page_subtitle = "Select a folder from the sidebar to view files";

// Set page title and subtitle based on selected folder
if ($selected_folder) {
    switch ($selected_folder) {
        case 'ordinances':
            $page_title = "Version Tracking - Ordinances & Resolutions";
            $page_subtitle = "Track versions for ordinances and resolutions";
            break;
        case 'billing':
            $page_title = "Version Tracking - Billing";
            $page_subtitle = "Track versions for billing records";
            break;
        case 'public-hearings':
            $page_title = "Version Tracking - Public Hearings";
            $page_subtitle = "Track versions for public hearing records";
            break;
        case 'meetings':
            $page_title = "Version Tracking - Meeting Records";
            $page_subtitle = "Track versions for meeting and session records";
            break;
        default:
            // Check if it's an archive folder
            if (strpos($selected_folder, 'archive_') === 0) {
                $folder_id = (int)str_replace('archive_', '', $selected_folder);
                foreach ($archive_folders as $folder) {
                    if ($folder['id'] == $folder_id) {
                        $page_title = "Version Tracking - " . $folder['name'];
                        $page_subtitle = "Track versions for " . $folder['name'] . " files";
                        break;
                    }
                }
            }
            break;
    }
}

$conn->close();
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
    <title>Version Tracking</title>
    <meta name="description" content="Version tracking for legislative records">
    <meta name="keywords" content="version tracking, archives, legislative records">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <?php include 'includes/header_scripts.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <style>
        .toggle-pill { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .625rem; border-radius: 9999px; border: 1px solid rgba(203,213,225,.6); }
        .toggle-track { position: relative; width: 40px; height: 20px; border-radius: 9999px; background-color: rgba(203,213,225,.6); }
        .dark .toggle-track { background-color: rgba(30,41,59,.6); }
        .toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 9999px; background: white; transition: transform .2s ease; }
        .dark .toggle-thumb { transform: translateX(20px); }
    </style>
</head>
<body class="bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.16),_transparent_38%),linear-gradient(135deg,_#fef2f2_0%,_#f8fafc_50%,_#fef2f2_100%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.14),_transparent_35%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f172a_100%)] font-sans antialiased transition-colors duration-200">
    <div>
        <?php
        $sidebar_active_page = 'version-tracking';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col min-h-screen md:ml-64">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side -->
                        <div class="flex items-center">
                            <!-- Mobile Menu Button -->
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            
                            <!-- Logo (Mobile) -->
                            <div class="mobile-only flex items-center ml-2">
                                <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                            </div>
                        </div>
                        
                        <!-- Page Title -->
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

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <!-- Content Wrapper with Max Width -->
                <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
                    <!-- Header Section -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <h1 id="vt-page-title" class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($page_title); ?></h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($page_subtitle); ?></p>
                        </div>
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-80">
                            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                            <input type="text" id="vt-search-files" placeholder="Search in folder..." class="w-full bg-white dark:bg-slate-800 text-sm text-gray-800 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-600 rounded-lg pl-9 pr-3 py-2 border border-gray-200 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-500 transition-colors">
                        </div>
                    </div>

                    <!-- Files Section -->
                    <div id="vt-content-area" class="space-y-6">
                        <!-- Empty State -->
                        <div id="vt-empty-state" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-12 text-center">
                            <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-folder text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No folder selected</h3>
                            <p class="text-gray-500 dark:text-gray-400">Select a folder from the sidebar to view files</p>
                        </div>

                        <!-- Files Display (hidden by default) -->
                        <div id="vt-files-section" class="hidden space-y-6">
                            <!-- Stats Bar -->
                            <div id="vt-stats-bar" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                                    <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300"><i class="bi bi-file-earmark text-xl"></i></div>
                                    <div><div class="text-2xl font-bold text-gray-800 dark:text-gray-100" id="vt-stat-total">0</div><div class="text-xs text-gray-500 dark:text-gray-400 font-medium">files</div></div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-red-200 dark:border-red-900/50 shadow-sm flex items-center gap-4 relative overflow-hidden group">
                                    <div class="absolute inset-0 bg-red-50 dark:bg-red-900/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/40 rounded-lg flex items-center justify-center text-red-600 dark:text-red-400 relative z-10"><i class="bi bi-clock-history text-xl"></i></div>
                                    <div class="relative z-10"><div class="text-2xl font-bold text-red-600 dark:text-red-400" id="vt-stat-versions">0</div><div class="text-xs text-red-500 dark:text-red-400/80 font-semibold">with versions</div></div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center text-blue-600 dark:bg-blue-400"><i class="bi bi-calendar text-xl"></i></div>
                                    <div><div class="text-2xl font-bold text-gray-800 dark:text-gray-100" id="vt-stat-today">0</div><div class="text-xs text-gray-500 dark:text-gray-400 font-medium">today</div></div>
                                </div>
                            </div>

                            <!-- Files Container -->
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Files</h2>
                                    <!-- Sorting Control -->
                                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-slate-600 overflow-hidden">
                                        <button class="vt-sort-btn px-4 py-2 text-sm font-medium bg-red-600 text-white" data-sort="daily">
                                            Daily
                                        </button>
                                        <button class="vt-sort-btn px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-sort="monthly">
                                            Monthly
                                        </button>
                                        <button class="vt-sort-btn px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700" data-sort="yearly">
                                            Yearly
                                        </button>
                                    </div>
                                </div>
                                <div id="vt-files-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <!-- Files will be rendered here -->
                                </div>
                                <!-- Empty States -->
                                <div id="vt-no-files" class="hidden text-center py-12">
                                    <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="bi bi-inbox text-4xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No files in this folder</h3>
                                    <p class="text-gray-500 dark:text-gray-400">No files found in this folder</p>
                                </div>
                                <div id="vt-no-search-results" class="hidden text-center py-12">
                                    <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="bi bi-search text-4xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No results</h3>
                                    <p id="vt-no-search-results-text" class="text-gray-500 dark:text-gray-400">No files match your search</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        <?php include 'includes/footer.php'; ?>
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
        // Global state
        let vtSelectedFolder = null;
        let vtAllFiles = [];
        let vtSortMode = 'daily'; // daily, monthly, yearly

        // Folder config
        const vtFolderConfig = {
            ordRes: { label: 'Ordinances & Resos', types: ['Ordinance', 'Resolution'], iconColor: 'orange' },
            billing: { label: 'Billing', types: ['Billing'], iconColor: 'green' },
            publicHearing: { label: 'Public Hearings', types: ['Public Hearing'], iconColor: 'blue' },
            meeting: { label: 'Meeting/Sessions', types: ['Meeting'], iconColor: 'purple' },
            phpFiles: { label: 'PHP Files', types: [], iconColor: 'teal' } // Using mock data for now
        };

        // Initialize
        (function() {
            // Auto-select folder based on URL parameter
            var urlParams = new URLSearchParams(window.location.search);
            var folderParam = urlParams.get('folder');
            
            if (folderParam) {
                // Map folder parameters to their display configuration
                var folderMapping = {
                    'ordinances': { key: 'ordRes', label: 'Ordinances & Resolutions' },
                    'billing': { key: 'billing', label: 'Billing' },
                    'public-hearings': { key: 'publicHearing', label: 'Public Hearings' },
                    'meetings': { key: 'meeting', label: 'Meeting Records' }
                };
                
                // Check if it's an archive folder
                if (folderParam.startsWith('archive_')) {
                    var folderId = folderParam.replace('archive_', '');
                    // Find the archive folder name from the dropdown links
                    var archiveLink = document.querySelector('a[href="version_tracking.php?folder=' + folderParam + '"]');
                    if (archiveLink) {
                        var folderName = archiveLink.querySelector('span').textContent;
                        selectArchiveFolder('archive_' + folderId, folderName, folderId);
                    }
                } else if (folderMapping[folderParam]) {
                    // Auto-select the legislative folder
                    var config = folderMapping[folderParam];
                    selectFolder(config.key, config.label);
                }
            }

            // Attach folder button click handlers
            document.querySelectorAll('.vt-folder-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var key = this.getAttribute('data-folder-key');
                    var label = this.getAttribute('data-folder-label');
                    selectFolder(key, label);
                });
            });

            // Search functionality
            var searchInput = document.getElementById('vt-search-files');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterFiles(this.value);
                });
            }

            // Sort mode buttons
            document.querySelectorAll('.vt-sort-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    vtSortMode = this.getAttribute('data-sort');
                    // Update active button styling
                    document.querySelectorAll('.vt-sort-btn').forEach(function(b) {
                        b.classList.remove('bg-red-600', 'text-white');
                        b.classList.add('text-gray-600', 'dark:text-gray-300');
                    });
                    this.classList.remove('text-gray-600', 'dark:text-gray-300');
                    this.classList.add('bg-red-600', 'text-white');
                    // Re-render files
                    var searchInput = document.getElementById('vt-search-files');
                    filterFiles(searchInput ? searchInput.value : '');
                });
            });
        })();

        function selectArchiveFolder(key, label, folderId) {
            // Update selected folder state
            vtSelectedFolder = key;
            document.getElementById('vt-page-title').textContent = label;
            document.getElementById('page-title').textContent = label;
            document.getElementById('vt-search-files').placeholder = 'Search in ' + label + '...';

            // Show files section, hide empty state
            document.getElementById('vt-empty-state').classList.add('hidden');
            document.getElementById('vt-files-section').classList.remove('hidden');
            document.getElementById('vt-no-search-results').classList.add('hidden');

            // Load archive files
            loadArchiveFiles(folderId);
        }

        function loadArchiveFiles(folderId) {
            var grid = document.getElementById('vt-files-grid');
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">Loading...</div>';

            // Fetch archive files for this folder
            fetch('archives_api.php?action=get_files&folder_id=' + encodeURIComponent(folderId))
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        vtAllFiles = d.files.map(function(f) {
                            return {
                                id: f.id,
                                title: f.title || f.name,
                                created_at: f.created_at,
                                version: f.version || 1,
                                type: 'Archive',
                                file_path: f.file_path,
                                author: f.author || f.created_by || 'System'
                            };
                        }).sort(function(a, b) {
                            return new Date(b.created_at) - new Date(a.created_at);
                        });
                    } else {
                        vtAllFiles = [];
                    }

                    // Update stats
                    document.getElementById('vt-stat-total').textContent = vtAllFiles.length;
                    document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                    
                    let todayCount = 0;
                    let todayStr = new Date().toISOString().split('T')[0];
                    vtAllFiles.forEach(f => {
                        if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                    });
                    document.getElementById('vt-stat-today').textContent = todayCount;

                    // Clear search input and render files
                    document.getElementById('vt-search-files').value = '';
                    renderFiles(vtAllFiles);
                })
                .catch(error => {
                    console.error('Error loading archive files:', error);
                    vtAllFiles = [];
                    document.getElementById('vt-stat-total').textContent = '0';
                    document.getElementById('vt-stat-versions').textContent = '0';
                    document.getElementById('vt-stat-today').textContent = '0';
                    renderFiles([]);
                });
        }

        function selectFolder(key, label) {
            // Update selected folder state
            vtSelectedFolder = key;
            document.getElementById('vt-page-title').textContent = label;
            document.getElementById('vt-search-files').placeholder = 'Search in ' + label + '...';

            // Update active state on folder buttons
            document.querySelectorAll('.vt-folder-btn').forEach(function(btn) {
                btn.classList.remove('bg-red-600/90', 'to-orange-500/80', 'ring-1', 'ring-white/20', 'border', 'border-white/15', 'text-white');
                btn.classList.add('text-white/80', 'hover:bg-white/10');
            });
            document.querySelectorAll('.vt-folder-btn[data-folder-key="' + key + '"]').forEach(function(btn) {
                btn.classList.add('bg-red-600/90', 'to-orange-500/80', 'ring-1', 'ring-white/20', 'border', 'border-white/15', 'text-white');
            });

            // Show files section, hide empty state
            document.getElementById('vt-empty-state').classList.add('hidden');
            document.getElementById('vt-files-section').classList.remove('hidden');
            document.getElementById('vt-no-search-results').classList.add('hidden');

            // Load files
            loadFiles(key, event.currentTarget);
        }

        function loadFiles(key, buttonEl) {
            var grid = document.getElementById('vt-files-grid');
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">Loading...</div>';

            // Check if it's an archive folder
            const isArchiveFolder = buttonEl && buttonEl.getAttribute('data-folder-type') === 'archive';
            
            if (isArchiveFolder) {
                const folderId = buttonEl.getAttribute('data-folder-id');
                // Fetch archive files for this folder
                fetch('legislative_api.php?action=get_archive_files&folder_id=' + encodeURIComponent(folderId))
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            vtAllFiles = d.files.map(function(f) {
                                return {
                                    id: f.id,
                                    title: f.name,
                                    created_at: f.created_at,
                                    version: f.version || 1,
                                    type: 'Archive',
                                    file_path: f.file_path,
                                    author: f.author
                                };
                            }).sort(function(a, b) {
                                return new Date(b.created_at) - new Date(a.created_at);
                            });
                        } else {
                            vtAllFiles = [];
                        }

                        // Update stats
                        document.getElementById('vt-stat-total').textContent = vtAllFiles.length;
                        document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                        
                        let todayCount = 0;
                        let todayStr = new Date().toISOString().split('T')[0];
                        vtAllFiles.forEach(f => {
                            if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                        });
                        document.getElementById('vt-stat-today').textContent = todayCount;

                        // Clear search input and render files
                        document.getElementById('vt-search-files').value = '';
                        renderFiles(vtAllFiles);
                    });
            } else if (key === 'phpFiles') {
                // Mock data for PHP Files
                vtAllFiles = [
                    { id: 1, title: 'authdatabase.php', created_at: '2026-07-05T10:30:00Z', version: 2, type: 'PHP' },
                    { id: 2, title: 'sidebar-centralized.php', created_at: '2026-07-04T09:15:00Z', version: 3, type: 'PHP' },
                    { id: 3, title: 'storage.php', created_at: '2026-06-28T14:20:00Z', version: 1, type: 'PHP' },
                    { id: 4, title: 'version_tracking.php', created_at: '2026-06-25T11:45:00Z', version: 5, type: 'PHP' },
                    { id: 5, title: 'report_analytics.php', created_at: '2026-05-10T16:00:00Z', version: 2, type: 'PHP' }
                ];
                
                // Update stats
                document.getElementById('vt-stat-total').textContent = vtAllFiles.length;
                document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                
                let todayCount = 0;
                let todayStr = new Date().toISOString().split('T')[0];
                vtAllFiles.forEach(f => {
                    if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                });
                document.getElementById('vt-stat-today').textContent = todayCount;

                // Clear search input and render files
                document.getElementById('vt-search-files').value = '';
                renderFiles(vtAllFiles);
            } else {
                const config = vtFolderConfig[key];
                var promises = config.types.map(function(t) {
                    return fetch('legislative_api.php?action=get_files&type=' + encodeURIComponent(t))
                        .then(function(r){ return r.json(); })
                        .then(function(d){ return d.success ? d.files : []; });
                });

                Promise.all(promises).then(function(results) {
                    vtAllFiles = results.flat().sort(function(a, b) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    // Update stats
                    document.getElementById('vt-stat-total').textContent = vtAllFiles.length;
                    document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                    
                    let todayCount = 0;
                    let todayStr = new Date().toISOString().split('T')[0];
                    vtAllFiles.forEach(f => {
                        if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                    });
                    document.getElementById('vt-stat-today').textContent = todayCount;

                    // Clear search input and render files
                    document.getElementById('vt-search-files').value = '';
                    renderFiles(vtAllFiles);
                });
            }
        }

        function renderFiles(files) {
            var gridContainer = document.getElementById('vt-files-grid');
            
            if (!files.length) {
                gridContainer.classList.add('hidden');
                // Check if it's a search no results or empty folder
                var searchInput = document.getElementById('vt-search-files');
                if (searchInput && searchInput.value.trim()) {
                    document.getElementById('vt-no-files').classList.add('hidden');
                    document.getElementById('vt-no-search-results-text').textContent = 'No files match "' + searchInput.value + '"';
                    document.getElementById('vt-no-search-results').classList.remove('hidden');
                } else {
                    document.getElementById('vt-no-search-results').classList.add('hidden');
                    document.getElementById('vt-no-files').classList.remove('hidden');
                }
                return;
            }

            // Show grid, hide empty states
            gridContainer.classList.remove('hidden');
            document.getElementById('vt-no-files').classList.add('hidden');
            document.getElementById('vt-no-search-results').classList.add('hidden');
            gridContainer.innerHTML = '';

            if (vtSortMode === 'daily') {
                // Daily: flat list sorted by date (most recent first)
                files.forEach(function(record) {
                    appendFileCard(gridContainer, record);
                });
            } else {
                // Group by year or month
                var grouped = {};
                files.forEach(function(record) {
                    var date = new Date(record.created_at);
                    var key;
                    if (vtSortMode === 'yearly') {
                        key = date.getFullYear().toString();
                    } else { // monthly
                        key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                    }
                    if (!grouped[key]) {
                        grouped[key] = [];
                    }
                    grouped[key].push(record);
                });

                // Sort groups in descending order
                var sortedKeys = Object.keys(grouped).sort().reverse();

                sortedKeys.forEach(function(key) {
                    // Add group header
                    var header = document.createElement('h3');
                    header.className = 'col-span-full mt-4 mb-2 text-lg font-bold text-gray-800 dark:text-gray-200';
                    if (vtSortMode === 'yearly') {
                        header.textContent = key;
                    } else {
                        var year = parseInt(key.split('-')[0]);
                        var month = parseInt(key.split('-')[1]) - 1;
                        var date = new Date(year, month, 1);
                        header.textContent = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    }
                    gridContainer.appendChild(header);

                    // Add files in this group
                    grouped[key].forEach(function(record) {
                        appendFileCard(gridContainer, record);
                    });
                });
            }
        }

        function appendFileCard(container, record) {
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
            } else if (type === 'PHP') {
                theme = { bg: 'bg-teal-500/10', border: 'border-teal-500/20', iconColor: 'text-teal-400', icon: 'bi-code-slash', pill: 'bg-teal-500/20 text-teal-300 border-teal-500/30' };
            } else if (type === 'Archive') {
                theme = { bg: 'bg-slate-500/10', border: 'border-slate-500/20', iconColor: 'text-slate-400', icon: 'bi-file-earmark', pill: 'bg-slate-500/20 text-slate-300 border-slate-500/30' };
            }

            var dateStr = String(record.month||'') + ' ' + String(record.year||'');
            if(!dateStr.trim() && record.created_at) {
                var d = new Date(record.created_at);
                dateStr = d.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
            }

            var verStr = record.version ? 'v' + record.version : 'v1.0';

            var card = document.createElement('div');
            card.className = 'bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden hover:border-gray-200 dark:hover:border-slate-600 hover:shadow-xl transition-all duration-200 group cursor-pointer flex flex-col h-full';
            card.onclick = function() { openVersionHistory(record); };

            card.innerHTML = `
                <div class="h-32 w-full ${theme.bg} ${theme.border} flex items-center justify-center p-4 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-white dark:to-slate-800 opacity-50"></div>
                    <div class="w-16 h-16 rounded-xl bg-white dark:bg-slate-800 shadow border border-gray-200 dark:border-slate-700 flex items-center justify-center z-10 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi ${theme.icon} text-2xl ${theme.iconColor}"></i>
                    </div>
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <div class="font-medium text-sm text-gray-800 dark:text-gray-200 truncate mb-1" title="${record.title}">${record.title}</div>
                    <div class="mt-auto flex items-center justify-between pt-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">${dateStr}</div>
                        <div class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${theme.pill}">${verStr}</div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        }

        function filterFiles(query) {
            if (!vtAllFiles.length) return;

            query = query.toLowerCase().trim();
            
            if (!query) {
                renderFiles(vtAllFiles);
                return;
            }

            var filteredFiles = vtAllFiles.filter(function(file) {
                return (file.title || '').toLowerCase().includes(query) || (file.name || '').toLowerCase().includes(query);
            });

            renderFiles(filteredFiles);
        }

        function openVersionHistory(record) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (record && record.title ? record.title : 'File');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            
            // Check if it's an archive file (type 'Archive')
            const isArchiveFile = record && record.type === 'Archive';
            
            if (isArchiveFile) {
                // Use archives_api.php for archive file versions
                fetch('archives_api.php?action=get_versions&id=' + (record && record.id ? record.id : ''))
                .then(r => r.json())
                .then(d => {
                    if(d.success) {
                        if(d.versions.length === 0) {
                            list.innerHTML = '<div class="text-center text-gray-500">No version history found.</div>';
                        } else {
                            list.innerHTML = d.versions.map(v => `
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                                    <div>
                                        <div class="font-medium text-gray-800 dark:text-white">Version ${v.version}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            ${v.created_at} • ${record.author || 'System'}
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="${record.file_path || '#'}" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a>
                                    </div>
                                </div>
                            `).join('');
                        }
                    } else {
                        list.innerHTML = '<div class="text-red-500 text-center">Failed to load version history</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading archive versions:', error);
                    list.innerHTML = '<div class="text-red-500 text-center">Error loading version history</div>';
                });
            } else {
                fetch('legislative_api.php?action=get_versions&id=' + (record && record.id ? record.id : ''))
                .then(r => r.json())
                .then(d => {
                    if(d.success) {
                        if(d.versions.length === 0) {
                            list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                        } else {
                            list.innerHTML = d.versions.map(v => `
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
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
            }

            document.getElementById('versionModal').classList.remove('hidden');
        }
        function closeVersionModal(){
            document.getElementById('versionModal').classList.add('hidden');
        }
    </script>
    <script src="assets/js/archives-landing.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
    <?php include 'includes/footer_scripts.php'; ?>

    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="hidden fixed inset-0 z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 max-w-md w-full p-6">
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
            
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !logoutModal?.classList.contains('hidden') === false) {
                    closeLogoutModal();
                }
            });
        })();
    </script>
</body>
</html>

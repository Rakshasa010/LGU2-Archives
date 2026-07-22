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

// Fetch all folders (archive + legislative) for main content area
$all_folders = [];
// Archive folders with file counts
if ($conn->query("SHOW TABLES LIKE 'archive_files'")->num_rows > 0) {
    $af_result = $conn->query("
        SELECT af.id, af.name, af.slug, af.created_at,
               COUNT(DISTINCT af2.id) AS file_count,
               MAX(af2.created_at) AS last_modified
        FROM archive_folders af
        LEFT JOIN archive_files af2 ON af2.folder_id = af.id
        GROUP BY af.id, af.name, af.slug, af.created_at
        ORDER BY af.created_at DESC
    ");
    if ($af_result) {
        while ($row = $af_result->fetch_assoc()) {
            $all_folders[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'source' => 'archive',
                'file_count' => (int)$row['file_count'],
                'last_modified' => $row['last_modified'],
                'created_at' => $row['created_at'],
                'icon_color' => 'slate',
                'icon' => 'bi-folder-fill'
            ];
        }
    }
}
// Legislative folders with file counts
if ($conn->query("SHOW TABLES LIKE 'legislative_folders'")->num_rows > 0) {
    $lf_result = $conn->query("
        SELECT lf.id, lf.name, lf.type, lf.created_at,
               COUNT(DISTINCT lr.id) AS file_count,
               MAX(lr.created_at) AS last_modified
        FROM legislative_folders lf
        LEFT JOIN legislative_records lr ON lr.folder_id = lf.id
        GROUP BY lf.id, lf.name, lf.type, lf.created_at
        ORDER BY lf.created_at DESC
    ");
    if ($lf_result) {
        while ($row = $lf_result->fetch_assoc()) {
            $type = strtolower($row['type'] ?? '');
            $icon = 'bi-folder-fill';
            $icon_color = 'slate';
            if ($type === 'ordinance' || $type === 'resolution') { $icon = 'bi-file-earmark-text'; $icon_color = 'orange'; }
            elseif ($type === 'billing') { $icon = 'bi-receipt'; $icon_color = 'green'; }
            elseif ($type === 'public hearing') { $icon = 'bi-megaphone'; $icon_color = 'blue'; }
            elseif ($type === 'meeting') { $icon = 'bi-journal-text'; $icon_color = 'purple'; }
            $all_folders[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'source' => 'legislative',
                'file_count' => (int)$row['file_count'],
                'last_modified' => $row['last_modified'],
                'created_at' => $row['created_at'],
                'icon_color' => $icon_color,
                'icon' => $icon
            ];
        }
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

// Encode all folders for JavaScript
$all_folders_json = json_encode($all_folders, JSON_HEX_TAG | JSON_HEX_AMP);

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

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <!-- Content Wrapper with Max Width -->
                <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
                    <!-- Header Section -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div class="flex items-center gap-3">
                            <button id="vt-back-btn" onclick="vtBackToFolders()" class="hidden items-center justify-center w-9 h-9 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" title="Back to folders">
                                <i class="bi bi-arrow-left text-lg"></i>
                            </button>
                            <div>
                                <h1 id="vt-page-title" class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($page_title); ?></h1>
                                <p id="vt-page-subtitle" class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($page_subtitle); ?></p>
                            </div>
                        </div>
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-80">
                            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                            <input type="text" id="vt-search-files" placeholder="Search in folder..." class="w-full bg-white dark:bg-slate-800 text-sm text-gray-800 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-600 rounded-lg pl-9 pr-3 py-2 border border-gray-200 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-500 transition-colors">
                        </div>
                    </div>

                    <!-- Files Section -->
                    <div id="vt-content-area" class="space-y-6">
                        <!-- Folder Grid (shown when no folder is selected) -->
                        <div id="vt-folder-grid-section" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">Select a Folder</h2>
                                <span class="text-xs text-gray-500 dark:text-gray-400" id="vt-folder-count"></span>
                            </div>
                            <div id="vt-folder-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Folder cards rendered by JS -->
                            </div>
                            <!-- Empty state: zero folders -->
                            <div id="vt-zero-folders" class="hidden bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-12 text-center">
                                <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-folder text-4xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No folders yet</h3>
                                <p class="text-gray-500 dark:text-gray-400">Create a folder in Main Storage Archives to get started</p>
                            </div>
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
                                <!-- Pagination Controls -->
                                <div id="vt-pagination" class="mt-6"></div>
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

    <script src="assets/js/pagination.js"></script>
    <script>
        // Global state
        let vtSelectedFolder = null;
        let vtAllFiles = [];
        let vtFilteredFiles = [];
        let vtSortMode = 'daily';
        let vtCurrentPage = 1;
        let vtCurrentLoadContext = null;
        const vtPageSize = 20;
        const vtPagination = new PaginationControls('vt-pagination', { onPageChange: function(page) { vtCurrentPage = page; if (vtCurrentLoadContext) vtCurrentLoadContext(); } });

        // Folder data from PHP (all database folders)
        const vtAllFolders = <?php echo $all_folders_json; ?>;

        // Icon color map to Tailwind classes
        const vtColorMap = {
            orange: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-500 dark:text-orange-400', border: 'border-orange-200 dark:border-orange-800/50', hoverBorder: 'hover:border-orange-400 dark:hover:border-orange-600' },
            green:  { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-500 dark:text-green-400', border: 'border-green-200 dark:border-green-800/50', hoverBorder: 'hover:border-green-400 dark:hover:border-green-600' },
            blue:   { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-500 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800/50', hoverBorder: 'hover:border-blue-400 dark:hover:border-blue-600' },
            purple: { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-500 dark:text-purple-400', border: 'border-purple-200 dark:border-purple-800/50', hoverBorder: 'hover:border-purple-400 dark:hover:border-purple-600' },
            slate:  { bg: 'bg-slate-100 dark:bg-slate-700/50', text: 'text-slate-500 dark:text-slate-400', border: 'border-slate-200 dark:border-slate-700', hoverBorder: 'hover:border-slate-400 dark:hover:border-slate-500' }
        };

        function vtRenderFolderGrid() {
            var grid = document.getElementById('vt-folder-grid');
            var countEl = document.getElementById('vt-folder-count');
            var zeroEl = document.getElementById('vt-zero-folders');

            if (!vtAllFolders.length) {
                grid.classList.add('hidden');
                zeroEl.classList.remove('hidden');
                if (countEl) countEl.textContent = '';
                return;
            }

            zeroEl.classList.add('hidden');
            grid.classList.remove('hidden');
            if (countEl) countEl.textContent = vtAllFolders.length + ' folder' + (vtAllFolders.length !== 1 ? 's' : '');
            grid.innerHTML = '';

            vtAllFolders.forEach(function(folder) {
                var colors = vtColorMap[folder.icon_color] || vtColorMap.slate;
                var card = document.createElement('div');
                card.className = 'flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg ' + colors.hoverBorder + ' transition-all group h-40 cursor-pointer vt-folder-card';
                card.setAttribute('data-folder-source', folder.source);
                card.setAttribute('data-folder-id', folder.id);
                card.setAttribute('data-folder-name', folder.name);

                var dateStr = '';
                if (folder.last_modified) {
                    var d = new Date(folder.last_modified);
                    dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } else if (folder.created_at) {
                    var d = new Date(folder.created_at);
                    dateStr = 'Created ' + d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }

                card.innerHTML =
                    '<div class="flex items-start justify-between">' +
                        '<div class="w-12 h-12 ' + colors.bg + ' rounded-xl flex items-center justify-center ' + colors.text + ' text-2xl group-hover:scale-110 transition-transform">' +
                            '<i class="bi ' + folder.icon + '"></i>' +
                        '</div>' +
                        '<div class="text-xs font-medium px-2 py-0.5 rounded-full ' + colors.bg + ' ' + colors.text + ' border ' + colors.border + '">' +
                            (folder.source === 'legislative' ? 'Legislative' : 'Archive') +
                        '</div>' +
                    '</div>' +
                    '<div class="min-w-0 mt-4">' +
                        '<div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">' + escapeHtml(folder.name) + '</div>' +
                        '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' +
                            folder.file_count + ' file' + (folder.file_count !== 1 ? 's' : '') +
                            (dateStr ? ' &middot; ' + dateStr : '') +
                        '</div>' +
                    '</div>';

                card.addEventListener('click', function() {
                    vtSelectFolderFromGrid(folder);
                });
                grid.appendChild(card);
            });
        }

        function escapeHtml(s) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(s || ''));
            return div.innerHTML;
        }

        function vtSelectFolderFromGrid(folder) {
            // Hide folder grid, show files section
            document.getElementById('vt-folder-grid-section').classList.add('hidden');
            document.getElementById('vt-files-section').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.add('flex');

            // Update header
            document.getElementById('vt-page-title').textContent = 'Version Tracking — ' + folder.name;
            document.getElementById('page-title').textContent = folder.name;
            document.getElementById('vt-page-subtitle').textContent = 'Track versions for ' + folder.name + ' files';
            document.getElementById('vt-search-files').placeholder = 'Search in ' + folder.name + '...';

            vtSelectedFolder = folder.source + '_' + folder.id;

            if (folder.source === 'archive') {
                selectArchiveFolder('archive_' + folder.id, folder.name, folder.id);
            } else {
                // Find the matching vtFolderConfig key or load directly
                var typeLower = (folder.name || '').toLowerCase();
                var configKey = null;
                if (typeLower.indexOf('ordinance') >= 0 || typeLower.indexOf('resolution') >= 0) configKey = 'ordRes';
                else if (typeLower.indexOf('billing') >= 0) configKey = 'billing';
                else if (typeLower.indexOf('public hearing') >= 0) configKey = 'publicHearing';
                else if (typeLower.indexOf('meeting') >= 0) configKey = 'meeting';

                if (configKey && vtFolderConfig[configKey]) {
                    selectFolder(configKey, folder.name);
                } else {
                    // Generic legislative folder — load via legislative_api with folder_id
                    vtLoadLegislativeFolder(folder.id, folder.name);
                }
            }

            // Update URL without reload
            var newUrl = 'version_tracking.php?folder=' + vtSelectedFolder;
            history.pushState(null, '', newUrl);
        }

        function vtLoadLegislativeFolder(folderId, folderName) {
            vtCurrentLoadContext = function() { vtLoadLegislativeFolder(folderId, folderName); };
            var grid = document.getElementById('vt-files-grid');
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">Loading...</div>';

            fetch('legislative_api.php?action=get_files&folder_id=' + encodeURIComponent(folderId) + '&page=' + vtCurrentPage + '&page_size=' + vtPageSize)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success && d.files) {
                        vtAllFiles = d.files.map(function(f) {
                            return { id: f.id, title: f.name || f.title, created_at: f.created_at, version: f.version || 1, type: 'Archive', file_path: f.file_path, author: f.author };
                        });
                    } else {
                        vtAllFiles = [];
                    }
                    document.getElementById('vt-stat-total').textContent = d.total || vtAllFiles.length;
                    document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(function(f) { return f.version && f.version > 1; }).length;
                    var todayStr = new Date().toISOString().split('T')[0];
                    var todayCount = vtAllFiles.filter(function(f) { return f.created_at && f.created_at.startsWith(todayStr); }).length;
                    document.getElementById('vt-stat-today').textContent = todayCount;
                    document.getElementById('vt-search-files').value = '';
                    vtFilteredFiles = [];
                    vtPagination.setPage(d.page || 1);
                    vtPagination.update(d.total || vtAllFiles.length);
                    renderFiles(vtAllFiles);
                })
                .catch(function(error) {
                    console.error('Error loading legislative folder:', error);
                    vtAllFiles = [];
                    vtFilteredFiles = [];
                    renderFiles([]);
                });
        }

        function vtBackToFolders() {
            document.getElementById('vt-files-section').classList.add('hidden');
            document.getElementById('vt-folder-grid-section').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.add('hidden');
            document.getElementById('vt-back-btn').classList.remove('flex');
            document.getElementById('vt-page-title').textContent = 'Version Tracking';
            document.getElementById('vt-page-subtitle').textContent = 'Select a folder to view version history';
            document.getElementById('page-title').textContent = 'Version Tracking';
            document.getElementById('vt-search-files').placeholder = 'Search in folder...';
            vtSelectedFolder = null;
            vtAllFiles = [];
            vtFilteredFiles = [];
            history.pushState(null, '', 'version_tracking.php');
        }

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
            // Render folder grid on page load
            vtRenderFolderGrid();

            // Auto-select folder based on URL parameter
            var urlParams = new URLSearchParams(window.location.search);
            var folderParam = urlParams.get('folder');
            
            if (folderParam) {
                // Check if it's an archive folder
                if (folderParam.startsWith('archive_')) {
                    var folderId = folderParam.replace('archive_', '');
                    // Find from vtAllFolders
                    var found = vtAllFolders.find(function(f) { return f.source === 'archive' && String(f.id) === folderId; });
                    if (found) {
                        vtSelectFolderFromGrid(found);
                        return;
                    }
                    // Fallback: find from sidebar links
                    var archiveLink = document.querySelector('a[href="version_tracking.php?folder=' + folderParam + '"]');
                    if (archiveLink) {
                        var folderName = archiveLink.querySelector('span').textContent;
                        selectArchiveFolder('archive_' + folderId, folderName, folderId);
                    }
                } else if (folderParam.startsWith('legislative_')) {
                    var legId = folderParam.replace('legislative_', '');
                    var found = vtAllFolders.find(function(f) { return f.source === 'legislative' && String(f.id) === legId; });
                    if (found) {
                        vtSelectFolderFromGrid(found);
                        return;
                    }
                } else {
                    // Map legislative folder params to their display configuration
                    var folderMapping = {
                        'ordinances': { key: 'ordRes', label: 'Ordinances & Resolutions' },
                        'billing': { key: 'billing', label: 'Billing' },
                        'public-hearings': { key: 'publicHearing', label: 'Public Hearings' },
                        'meetings': { key: 'meeting', label: 'Meeting Records' }
                    };
                    
                    if (folderMapping[folderParam]) {
                        var config = folderMapping[folderParam];
                        selectFolder(config.key, config.label);
                    }
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

            document.querySelectorAll('.vt-sort-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    vtSortMode = this.getAttribute('data-sort');
                    document.querySelectorAll('.vt-sort-btn').forEach(function(b) {
                        b.classList.remove('bg-red-600', 'text-white');
                        b.classList.add('text-gray-600', 'dark:text-gray-300');
                    });
                    this.classList.remove('text-gray-600', 'dark:text-gray-300');
                    this.classList.add('bg-red-600', 'text-white');
                    var searchInput = document.getElementById('vt-search-files');
                    filterFiles(searchInput ? searchInput.value : '');
                });
            });
        })();

        function selectArchiveFolder(key, label, folderId) {
            vtCurrentPage = 1;
            vtFilteredFiles = [];
            vtSelectedFolder = key;
            document.getElementById('vt-page-title').textContent = 'Version Tracking — ' + label;
            document.getElementById('page-title').textContent = label;
            document.getElementById('vt-page-subtitle').textContent = 'Track versions for ' + label + ' files';
            document.getElementById('vt-search-files').placeholder = 'Search in ' + label + '...';

            // Hide folder grid, show files section
            document.getElementById('vt-folder-grid-section').classList.add('hidden');
            document.getElementById('vt-files-section').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.add('flex');
            document.getElementById('vt-no-search-results').classList.add('hidden');

            // Load archive files
            loadArchiveFiles(folderId);
        }

        function loadArchiveFiles(folderId) {
            vtCurrentLoadContext = function() { loadArchiveFiles(folderId); };
            var grid = document.getElementById('vt-files-grid');
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">Loading...</div>';

            fetch('archives_api.php?action=get_files&folder_id=' + encodeURIComponent(folderId) + '&page=' + vtCurrentPage + '&page_size=' + vtPageSize)
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
                        });
                    } else {
                        vtAllFiles = [];
                    }

                    document.getElementById('vt-stat-total').textContent = d.total || vtAllFiles.length;
                    document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                    
                    let todayCount = 0;
                    let todayStr = new Date().toISOString().split('T')[0];
                    vtAllFiles.forEach(f => {
                        if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                    });
                    document.getElementById('vt-stat-today').textContent = todayCount;

                    document.getElementById('vt-search-files').value = '';
                    vtFilteredFiles = [];
                    vtPagination.setPage(d.page || 1);
                    vtPagination.update(d.total || vtAllFiles.length);
                    renderFiles(vtAllFiles);
                })
                .catch(error => {
                    console.error('Error loading archive files:', error);
                    vtAllFiles = [];
                    vtFilteredFiles = [];
                    document.getElementById('vt-stat-total').textContent = '0';
                    document.getElementById('vt-stat-versions').textContent = '0';
                    document.getElementById('vt-stat-today').textContent = '0';
                    renderFiles([]);
                });
        }

        function selectFolder(key, label) {
            vtCurrentPage = 1;
            vtFilteredFiles = [];
            vtSelectedFolder = key;
            document.getElementById('vt-page-title').textContent = 'Version Tracking — ' + label;
            document.getElementById('page-title').textContent = label;
            document.getElementById('vt-search-files').placeholder = 'Search in ' + label + '...';

            // Update active state on folder buttons
            document.querySelectorAll('.vt-folder-btn').forEach(function(btn) {
                btn.classList.remove('bg-red-600/90', 'to-orange-500/80', 'ring-1', 'ring-white/20', 'border', 'border-white/15', 'text-white');
                btn.classList.add('text-white/80', 'hover:bg-white/10');
            });
            document.querySelectorAll('.vt-folder-btn[data-folder-key="' + key + '"]').forEach(function(btn) {
                btn.classList.add('bg-red-600/90', 'to-orange-500/80', 'ring-1', 'ring-white/20', 'border', 'border-white/15', 'text-white');
            });

            // Hide folder grid, show files section
            document.getElementById('vt-folder-grid-section').classList.add('hidden');
            document.getElementById('vt-files-section').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.remove('hidden');
            document.getElementById('vt-back-btn').classList.add('flex');
            document.getElementById('vt-no-search-results').classList.add('hidden');

            // Load files
            loadFiles(key);
        }

        function loadFiles(key) {
            vtCurrentLoadContext = function() { loadFiles(key); };
            var grid = document.getElementById('vt-files-grid');
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-gray-500">Loading...</div>';

            if (key === 'phpFiles') {
                vtAllFiles = [
                    { id: 1, title: 'authdatabase.php', created_at: '2026-07-05T10:30:00Z', version: 2, type: 'PHP' },
                    { id: 2, title: 'sidebar-centralized.php', created_at: '2026-07-04T09:15:00Z', version: 3, type: 'PHP' },
                    { id: 3, title: 'storage.php', created_at: '2026-06-28T14:20:00Z', version: 1, type: 'PHP' },
                    { id: 4, title: 'version_tracking.php', created_at: '2026-06-25T11:45:00Z', version: 5, type: 'PHP' },
                    { id: 5, title: 'report_analytics.php', created_at: '2026-05-10T16:00:00Z', version: 2, type: 'PHP' }
                ];
                
                document.getElementById('vt-stat-total').textContent = vtAllFiles.length;
                document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                
                let todayCount = 0;
                let todayStr = new Date().toISOString().split('T')[0];
                vtAllFiles.forEach(f => {
                    if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                });
                document.getElementById('vt-stat-today').textContent = todayCount;

                document.getElementById('vt-search-files').value = '';
                vtFilteredFiles = [];
                vtPagination.update(vtAllFiles.length);
                renderFiles(vtAllFiles);
            } else {
                const config = vtFolderConfig[key];
                var promises = config.types.map(function(t) {
                    return fetch('legislative_api.php?action=get_files&type=' + encodeURIComponent(t) + '&page=' + vtCurrentPage + '&page_size=' + vtPageSize)
                        .then(function(r){ return r.json(); })
                        .then(function(d){ return d; });
                });

                Promise.all(promises).then(function(results) {
                    var allFiles = [];
                    var totalCount = 0;
                    results.forEach(function(d) {
                        if (d.success && d.files) {
                            allFiles = allFiles.concat(d.files);
                            totalCount += (d.total || d.files.length);
                        }
                    });
                    vtAllFiles = allFiles.sort(function(a, b) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    document.getElementById('vt-stat-total').textContent = totalCount || vtAllFiles.length;
                    document.getElementById('vt-stat-versions').textContent = vtAllFiles.filter(f => f.version && f.version > 1).length;
                    
                    let todayCount = 0;
                    let todayStr = new Date().toISOString().split('T')[0];
                    vtAllFiles.forEach(f => {
                        if (f.created_at && f.created_at.startsWith(todayStr)) todayCount++;
                    });
                    document.getElementById('vt-stat-today').textContent = todayCount;

                    document.getElementById('vt-search-files').value = '';
                    vtFilteredFiles = [];
                    vtPagination.setPage(results[0]?.page || 1);
                    vtPagination.update(totalCount || vtAllFiles.length);
                    renderFiles(vtAllFiles);
                });
            }
        }

        function renderFiles(files) {
            var gridContainer = document.getElementById('vt-files-grid');
            
            if (!files.length) {
                gridContainer.classList.add('hidden');
                var searchInput = document.getElementById('vt-search-files');
                if (searchInput && searchInput.value.trim()) {
                    document.getElementById('vt-no-files').classList.add('hidden');
                    document.getElementById('vt-no-search-results-text').textContent = 'No files match "' + searchInput.value + '"';
                    document.getElementById('vt-no-search-results').classList.remove('hidden');
                } else {
                    document.getElementById('vt-no-search-results').classList.add('hidden');
                    document.getElementById('vt-no-files').classList.remove('hidden');
                }
                document.getElementById('vt-pagination').style.display = 'none';
                return;
            }

            gridContainer.classList.remove('hidden');
            document.getElementById('vt-no-files').classList.add('hidden');
            document.getElementById('vt-no-search-results').classList.add('hidden');
            gridContainer.innerHTML = '';

            var sortedFiles = files.slice().sort(function(a, b) {
                return new Date(b.created_at) - new Date(a.created_at);
            });

            if (vtSortMode === 'monthly') {
                sortedFiles.sort(function(a, b) {
                    var da = new Date(a.created_at), db = new Date(b.created_at);
                    var km = db.getFullYear() * 12 + db.getMonth() - (da.getFullYear() * 12 + da.getMonth());
                    return km !== 0 ? km : new Date(b.created_at) - new Date(a.created_at);
                });
            } else if (vtSortMode === 'yearly') {
                sortedFiles.sort(function(a, b) {
                    var da = new Date(a.created_at), db = new Date(b.created_at);
                    var ky = db.getFullYear() - da.getFullYear();
                    return ky !== 0 ? ky : new Date(b.created_at) - new Date(a.created_at);
                });
            }

            sortedFiles.forEach(function(record) {
                appendFileCard(gridContainer, record);
            });

            if (files.length > vtPageSize) {
                document.getElementById('vt-pagination').style.display = '';
            } else {
                document.getElementById('vt-pagination').style.display = 'none';
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
                vtFilteredFiles = [];
                renderFiles(vtAllFiles);
                return;
            }

            vtFilteredFiles = vtAllFiles.filter(function(file) {
                return (file.title || '').toLowerCase().includes(query) || (file.name || '').toLowerCase().includes(query);
            });

            renderFiles(vtFilteredFiles);
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
</body>
</html>

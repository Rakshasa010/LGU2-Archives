<?php
$sidebar_layout = $sidebar_layout ?? 'full';
$sidebar_include_overlay = $sidebar_include_overlay ?? true;
$sidebar_is_admin = $sidebar_is_admin ?? ($is_admin ?? false);
$sidebar_user_data = $sidebar_user_data ?? ($user_data ?? null);

if (is_array($sidebar_user_data) && isset($sidebar_user_data['role'])) {
    $sidebar_is_admin = $sidebar_is_admin || strtolower((string)$sidebar_user_data['role']) === 'admin';
}

$sidebar_active_page = isset($sidebar_active_page) ? trim(strtolower((string)$sidebar_active_page)) : '';
if ($sidebar_active_page === '') {
    $sidebar_current_script = basename($_SERVER['PHP_SELF'] ?? 'archives-landing.php');
    $sidebar_script_map = [
        'archives-landing.php' => 'dashboard',
        'storage.php' => 'storage',
        'folder_view.php' => 'storage',
        'billing.php' => 'storage',
        'meeting-records.php' => 'storage',
        'ordinances-resolution.php' => 'storage',
        'public-hearings.php' => 'storage',
        'export.php' => 'export',
        'version_tracking.php' => 'version-tracking',
        'report_analytics.php' => 'report-analytics',
        'user_management.php' => 'user-management',
        'audit-logs.php' => 'audit-logs',
        'profile_management.php' => ''
    ];
    $sidebar_active_page = $sidebar_script_map[$sidebar_current_script] ?? '';
}

// Fetch archive folders for Version Tracking dropdown (only if not already provided)
if (!isset($archive_folders) || !is_array($archive_folders)) {
    $archive_folders = [];
    // Check if $conn is available and still open
    if (isset($conn) && $conn instanceof mysqli) {
        $is_open = false;
        // Check if connection is still open with error suppression
        if (@$conn->ping()) {
            $is_open = true;
        } else {
            // Alternative check: see if we can get connection stats
            $stat = @$conn->stat();
            if ($stat !== false) {
                $is_open = true;
            }
        }
        
        if ($is_open) {
            try {
                $folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
                if ($folders_result && $folders_result->num_rows > 0) {
                    while ($row = $folders_result->fetch_assoc()) {
                        $archive_folders[] = $row;
                    }
                }
            } catch (Error $e) {
                // If connection is closed or any error, just use empty array
                $archive_folders = [];
            } catch (Exception $e) {
                // Catch any other exceptions just in case
                $archive_folders = [];
            }
        }
    }
}

$sidebar_nav_base = 'group flex w-full items-center px-4 py-3 text-white/90 hover:text-white rounded-2xl mb-1.5 transition-all duration-300 hover:translate-x-1 hover:bg-white/12 hover:shadow-[0_10px_25px_rgba(0,0,0,0.18)]';
$sidebar_nav_active = $sidebar_nav_base . ' bg-gradient-to-r from-red-600/90 to-orange-500/80 ring-1 ring-white/20 border border-white/15 shadow-[0_12px_30px_rgba(185,28,28,0.3)]';

$sidebar_link = function ($href, $icon, $label, $pageKey, $desktop = false) use ($sidebar_active_page, $sidebar_nav_base, $sidebar_nav_active) {
    $isActive = $sidebar_active_page !== '' && $sidebar_active_page === $pageKey;
    $classes = $isActive ? $sidebar_nav_active : $sidebar_nav_base;
    $textClass = $desktop ? ' class="sidebar-text"' : '';
    $iconClass = $desktop ? '' : ' text-lg';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"><i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' mr-3' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '"></i><span' . $textClass . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};
?>
<?php if ($sidebar_include_overlay): ?>
<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300" aria-hidden="true"></div>
<?php endif; ?>
<style>
/* COMPREHENSIVE RESET TO ELIMINATE ALL GAPS */
html, body {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: 100% !important;
    overflow-x: hidden !important;
    line-height: 1 !important;
}

/* Remove any default spacing from wrapper divs */
body > div {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: 100% !important;
}

/* Desktop sidebar - fixed positioning with NO gaps */
#sidebar {
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    right: auto !important;
    bottom: 0 !important;
    width: 16rem !important;
    height: 100vh !important;
    max-height: 100vh !important;
    z-index: 30 !important;
    display: flex !important;
    flex-direction: column !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    outline: none !important;
    /* Hide scrollbar */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}
#sidebar::-webkit-scrollbar {
    display: none; /* Chrome, Safari and Opera */
}

/* FORCE sidebar to exact left edge - override all Tailwind classes */
aside#sidebar {
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0 !important;
    transform: translateX(0) !important;
    left: 0 !important;
    box-shadow: none !important;
    border-left: none !important;
}

/* Aggressive reset for any potential spacing issues */
aside#sidebar,
aside#sidebar.sidebar,
aside#sidebar.sidebar-expanded {
    margin: 0 !important;
    left: 0 !important;
    transform: none !important;
}

/* Fix sidebar internal spacing */
#sidebar > * {
    margin-left: 0 !important;
}

/* Ensure sidebar navigation has proper internal padding */
#sidebar nav {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

/* Force sidebar logo section positioning */
#sidebar .sidebar-logo,
#sidebar .p-6 {
    padding-left: 1.5rem !important;
    margin-left: 0 !important;
}

/* ABSOLUTE POSITIONING SOLUTION FOR MAIN CONTENT - NO GAPS */
@media (min-width: 768px) {
    /* Main content takes remaining space using absolute positioning */
    .flex.flex-col.min-h-screen {
        position: absolute !important;
        left: 16rem !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: calc(100% - 16rem) !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow-y: auto !important;
        border: none !important;
        outline: none !important;
    }
    
    /* Ensure navbar and main elements have no top spacing */
    .flex.flex-col.min-h-screen > * {
        margin-top: 0 !important;
    }
    
    .flex.flex-col.min-h-screen > nav:first-child {
        margin-top: 0 !important;
        padding-top: 0 !important;
        top: 0 !important;
    }
}

/* Mobile - normal flow positioning */
@media (max-width: 767px) {
    .flex.flex-col.min-h-screen {
        position: static !important;
        left: auto !important;
        right: auto !important;
        top: auto !important;
        bottom: auto !important;
        width: 100% !important;
        height: auto !important;
        min-height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}

/* Ensure box sizing is correct everywhere */
* {
    box-sizing: border-box !important;
}

</style>
<div id="mobile-sidebar" class="sticky inset-y-0 left-0 min-h-screen transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-[#6b0f0f] via-[#bf1e2e] to-[#4b0f0f] text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-[0_25px_70px_rgba(0,0,0,0.35)] visible">
    <div class="p-4 border-b border-white/10 sidebar-header bg-white/5 backdrop-blur-sm">
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
            <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90" aria-label="Close sidebar">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
    </div>
    <nav class="flex-1 py-4 px-3 overflow-y-auto">
        <?php echo $sidebar_link('archives-landing.php', 'bi bi-speedometer2', 'Dashboard Archives', 'dashboard'); ?>
        <?php echo $sidebar_link('storage.php', 'bi bi-folder', 'Main Storage Archives', 'storage'); ?>
        <?php echo $sidebar_link('export.php', 'bi bi-cloud-upload', 'Request Copy', 'export'); ?>
        <?php if ($sidebar_is_admin): ?>
        <a href="recent_deleted.php" class="hidden"></a>
        <?php endif; ?>
        
        <!-- Version Tracking with Nested Folders -->
        <div class="mb-1.5">
            <div class="group flex w-full items-center justify-between px-4 py-3 text-white/90 hover:text-white rounded-2xl transition-all duration-300 hover:translate-x-1 hover:bg-white/12 hover:shadow-[0_10px_25px_rgba(0,0,0,0.18)]">
                <a href="version_tracking.php" class="flex items-center gap-3" aria-current="<?php echo $sidebar_active_page === 'version-tracking' ? 'page' : 'false'; ?>">
                    <i class="bi bi-book text-lg"></i>
                    <span>Version Tracking</span>
                </a>
                <button type="button" id="version-tracking-toggle" aria-expanded="false" aria-controls="version-tracking-submenu" class="text-xs transition-transform duration-200 hover:bg-white/10 p-1 rounded-lg" onclick="event.stopPropagation();">
                    <i class="bi bi-chevron-down" id="version-tracking-chevron"></i>
                </button>
            </div>
            <div id="version-tracking-submenu" class="mt-1 ml-4 space-y-1 overflow-hidden max-h-0">
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="ordRes" data-folder-label="Ordinances & Resos">
                    <div class="w-8 h-8 rounded-lg bg-orange-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-file-earmark-text text-orange-400"></i>
                    </div>
                    <span>Ordinances & Resos</span>
                </button>
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="billing" data-folder-label="Billing">
                    <div class="w-8 h-8 rounded-lg bg-green-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-receipt text-green-400"></i>
                    </div>
                    <span>Billing</span>
                </button>
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="publicHearing" data-folder-label="Public Hearings">
                    <div class="w-8 h-8 rounded-lg bg-blue-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-megaphone text-blue-400"></i>
                    </div>
                    <span>Public Hearings</span>
                </button>
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="meeting" data-folder-label="Meeting/Sessions">
                    <div class="w-8 h-8 rounded-lg bg-purple-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-journal-text text-purple-400"></i>
                    </div>
                    <span>Meeting/Sessions</span>
                </button>
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="phpFiles" data-folder-label="PHP Files">
                    <div class="w-8 h-8 rounded-lg bg-teal-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-code-slash text-teal-400"></i>
                    </div>
                    <span>PHP Files</span>
                </button>
                <?php foreach ($archive_folders as $folder): ?>
                <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="archive-<?php echo (int)$folder['id']; ?>" data-folder-label="<?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-folder-id="<?php echo (int)$folder['id']; ?>" data-folder-type="archive">
                    <div class="w-8 h-8 rounded-lg bg-slate-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-folder-fill text-slate-400"></i>
                    </div>
                    <span><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-4 pt-4 border-t border-red-700/50">
            <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
            <?php echo $sidebar_link('report_analytics.php', 'bi bi-graph-up', 'Reports & Analytics', 'report-analytics'); ?>
        </div>
        <div class="mt-4 pt-4 border-t border-red-700/50">
            <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
            <?php if ($sidebar_is_admin): ?>
            <?php echo $sidebar_link('user_management.php', 'bi bi-people', 'User Management', 'user-management'); ?>
            <?php endif; ?>
            <?php echo $sidebar_link('audit-logs.php', 'bi bi-shield-check', 'Audit Logs', 'audit-logs'); ?>
        </div>
        <div class="mt-6 pt-4 border-t border-red-700/50 px-2">
            <div class="text-xs font-semibold text-red-200 mb-2 px-2 uppercase tracking-wide">Centralized Storage Overview</div>
            <div class="bg-gradient-to-br from-red-900/50 to-red-800/30 backdrop-blur-lg rounded-xl p-4 border border-red-700/50 hover:border-red-600/70 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-red-100 font-medium">Capacity Used</span>
                    <span class="text-sm font-bold text-white rounded-full px-2 py-0.5 bg-red-600/40" id="mobile-storage-pct">0%</span>
                </div>
                <div class="w-full bg-red-900/60 rounded-full h-2.5 overflow-hidden mb-3 shadow-inner">
                    <div class="bg-gradient-to-r from-red-400 to-orange-500 h-2.5 rounded-full transition-all duration-500" id="mobile-storage-bar" style="width: 0%;"></div>
                </div>
                <div class="text-xs text-red-100/80" id="mobile-storage-text">0 B of 50 GB</div>
                <div class="mt-2 text-xs text-red-100/60" id="mobile-storage-files">0 files tracked</div>
                <div class="mt-2 text-[11px] text-red-100/75">Combined view for legislative and archive files.</div>
            </div>
        </div>
    </nav>
</div>
<?php if ($sidebar_layout === 'full'): ?>
<aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-[#6b0f0f] via-[#bf1e2e] to-[#4b0f0f] text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out shadow-[16px_0_45px_rgba(0,0,0,0.24)] border-r border-white/10 backdrop-blur-xl fixed top-0 left-0 h-screen z-30 overflow-hidden md:flex">
    <div class="p-6 border-b border-white/10 sidebar-logo bg-white/5 backdrop-blur-sm flex-shrink-0">
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
    <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden scrollbar-thin scrollbar-thumb-red-600/50 scrollbar-track-transparent">
        <div class="px-4 space-y-1">
            <?php echo $sidebar_link('archives-landing.php', 'bi bi-speedometer2', 'Dashboard Archives', 'dashboard', true); ?>
            <?php echo $sidebar_link('storage.php', 'bi bi-folder', 'Main Storage Archives', 'storage', true); ?>
            <?php echo $sidebar_link('export.php', 'bi bi-cloud-upload', 'Request Copy', 'export', true); ?>
            <?php if ($sidebar_is_admin): ?>
            <a href="recent_deleted.php" class="hidden"></a>
            <?php endif; ?>
            
            <!-- Version Tracking with Nested Folders (Desktop) -->
            <div class="mb-1.5">
                <div class="group flex w-full items-center justify-between px-4 py-3 text-white/90 hover:text-white rounded-2xl transition-all duration-300 hover:translate-x-1 hover:bg-white/12 hover:shadow-[0_10px_25px_rgba(0,0,0,0.18)]">
                    <a href="version_tracking.php" class="flex items-center gap-3" aria-current="<?php echo $sidebar_active_page === 'version-tracking' ? 'page' : 'false'; ?>">
                        <i class="bi bi-book"></i>
                        <span class="sidebar-text">Version Tracking</span>
                    </a>
                    <button type="button" id="version-tracking-toggle-desktop" aria-expanded="false" aria-controls="version-tracking-submenu-desktop" class="text-xs transition-transform duration-200 hover:bg-white/10 p-1 rounded-lg" onclick="event.stopPropagation();">
                        <i class="bi bi-chevron-down" id="version-tracking-chevron-desktop"></i>
                    </button>
                </div>
                <div id="version-tracking-submenu-desktop" class="mt-1 ml-4 space-y-1 overflow-hidden max-h-0">
                    <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="ordRes" data-folder-label="Ordinances & Resos">
                        <div class="w-8 h-8 rounded-lg bg-orange-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-file-earmark-text text-orange-400"></i>
                        </div>
                        <span class="sidebar-text">Ordinances & Resos</span>
                    </button>
                    <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="billing" data-folder-label="Billing">
                        <div class="w-8 h-8 rounded-lg bg-green-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-receipt text-green-400"></i>
                        </div>
                        <span class="sidebar-text">Billing</span>
                    </button>
                    <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="publicHearing" data-folder-label="Public Hearings">
                        <div class="w-8 h-8 rounded-lg bg-blue-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-megaphone text-blue-400"></i>
                        </div>
                        <span class="sidebar-text">Public Hearings</span>
                    </button>
                    <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="meeting" data-folder-label="Meeting/Sessions">
                        <div class="w-8 h-8 rounded-lg bg-purple-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-journal-text text-purple-400"></i>
                        </div>
                        <span class="sidebar-text">Meeting/Sessions</span>
                    </button>
                    
                    <?php foreach ($archive_folders as $folder): ?>
                    <button type="button" class="vt-folder-btn group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10" data-folder-key="archive-<?php echo (int)$folder['id']; ?>" data-folder-label="<?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-folder-id="<?php echo (int)$folder['id']; ?>" data-folder-type="archive">
                        <div class="w-8 h-8 rounded-lg bg-slate-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-folder-fill text-slate-400"></i>
                        </div>
                        <span class="sidebar-text"><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
            <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
            <?php echo $sidebar_link('report_analytics.php', 'bi bi-graph-up', 'Reports & Analytics', 'report-analytics', true); ?>
        </div>
        <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
            <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
            <?php if ($sidebar_is_admin): ?>
            <?php echo $sidebar_link('user_management.php', 'bi bi-people', 'User Management', 'user-management', true); ?>
            <?php endif; ?>
            <?php echo $sidebar_link('audit-logs.php', 'bi bi-shield-check', 'Audit Logs', 'audit-logs', true); ?>
        </div>
        <div class="mt-6 pt-4 mx-4 border-t border-white/10">
            <div class="text-xs font-semibold text-red-100 mb-2 px-2 uppercase tracking-[0.2em]">Centralized Storage Overview</div>
            <div class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-xl rounded-2xl p-4 border border-white/15 shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] hover:border-white/25 transition-all cursor-pointer" title="Click to view storage details">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-red-100 font-medium">Capacity Used</span>
                    <span class="text-sm font-bold text-white rounded-full px-2 py-0.5 bg-red-600/40" id="desktop-storage-pct">0%</span>
                </div>
                <div class="w-full bg-red-900/60 rounded-full h-2.5 overflow-hidden mb-3 shadow-inner">
                    <div class="bg-gradient-to-r from-red-400 to-orange-500 h-2.5 rounded-full transition-all duration-500" id="desktop-storage-bar" style="width: 0%;"></div>
                </div>
                <div class="text-xs text-red-100/80" id="desktop-storage-text">0 B of 50 GB</div>
                <div class="mt-2 text-xs text-red-100/60" id="desktop-storage-files">0 files tracked</div>
                <div class="mt-2 text-[11px] text-red-100/75">Combined view for legislative and archive files.</div>
            </div>
        </div>
    </nav>
</aside>
<?php endif; ?>
<script>
// Prevent sidebar from scrolling with main content
(function(){
    function preventSidebarScroll(){
        var sidebar = document.getElementById('sidebar');
        if(!sidebar) return;
        
        // Prevent wheel events on sidebar from bubbling to parent
        sidebar.addEventListener('wheel', function(e) {
            var nav = sidebar.querySelector('nav');
            if (!nav) return;
            
            var navRect = nav.getBoundingClientRect();
            var sidebarRect = sidebar.getBoundingClientRect();
            
            // Only allow scrolling if the wheel event is within the nav area
            if (e.target.closest('nav')) {
                // Check if nav content overflows
                if (nav.scrollHeight > nav.clientHeight) {
                    // Allow scrolling within nav bounds
                    var atTop = nav.scrollTop === 0;
                    var atBottom = nav.scrollTop >= (nav.scrollHeight - nav.clientHeight);
                    
                    if ((e.deltaY < 0 && atTop) || (e.deltaY > 0 && atBottom)) {
                        e.preventDefault();
                    }
                } else {
                    // If nav doesn't overflow, prevent scrolling
                    e.preventDefault();
                }
            } else {
                // Prevent scrolling outside of nav area
                e.preventDefault();
            }
        }, { passive: false });
        
        // Prevent touch scrolling on sidebar (for mobile)
        sidebar.addEventListener('touchmove', function(e) {
            if (!e.target.closest('nav')) {
                e.preventDefault();
            }
        }, { passive: false });
    }
    
    if(document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            preventSidebarScroll();
        });
    } else {
        preventSidebarScroll();
    }
})();

// Version Tracking dropdown toggle
(function() {
    function setupToggle(toggleId, submenuId, chevronId) {
        var toggle = document.getElementById(toggleId);
        var submenu = document.getElementById(submenuId);
        var chevron = document.getElementById(chevronId);
        
        if (!toggle || !submenu) return;
        
        toggle.addEventListener('click', function() {
            var isExpanded = submenu.style.maxHeight && submenu.style.maxHeight !== '0px';
            if (isExpanded) {
                submenu.style.maxHeight = '0px';
                toggle.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                toggle.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        });
    }
    
    // Initialize toggles
    setupToggle('version-tracking-toggle', 'version-tracking-submenu', 'version-tracking-chevron');
    setupToggle('version-tracking-toggle-desktop', 'version-tracking-submenu-desktop', 'version-tracking-chevron-desktop');
})();
</script>
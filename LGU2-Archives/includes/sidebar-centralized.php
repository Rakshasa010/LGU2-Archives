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
/* Reset default margins and padding */
html, body {
    margin: 0 !important;
    padding: 0 !important;
}
@media (min-width: 768px) {
    /* Desktop sidebar - fixed positioning with NO gaps */
#sidebar {
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    right: auto !important;
    bottom: 0 !important;
    width: 18rem !important;
    height: 100vh !important;
    max-height: 100vh !important;
    z-index: 30 !important;
    display: flex !important;
    flex-direction: column !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    outline: none !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    /* Hide scrollbar */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

/* Hide scrollbar in WebKit (Chrome/Edge/Safari) while keeping scrollability */
#sidebar::-webkit-scrollbar,
#sidebar nav::-webkit-scrollbar,
#mobile-sidebar nav::-webkit-scrollbar {
    width: 0;
    height: 0;
    display: none;
    background: transparent;
}
#sidebar nav,
#mobile-sidebar nav {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
    
/* ABSOLUTE POSITIONING SOLUTION FOR MAIN CONTENT - NO GAPS */
@media (min-width: 768px) {
    /* Main content takes remaining space using absolute positioning */
    .flex.flex-col.min-h-screen {
        position: absolute !important;
        left: 18rem !important;
        top: 0 !important;
        right: 0 !important;
        bottom: auto !important;
        width: calc(100% - 18rem) !important;
        min-height: 100vh !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow-y: visible !important;
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

}

@media (max-width: 767px) {
    body {
        padding-left: 0 !important;
    }
}
</style>
<div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-[#6b0f0f] via-[#bf1e2e] to-[#4b0f0f] text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-[0_25px_70px_rgba(0,0,0,0.35)] visible">
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
        <?php echo $sidebar_link('export.php', 'bi bi-cloud-upload', 'Export', 'export'); ?>
        <?php echo $sidebar_link('external-documents.php', 'bi bi-cloud-arrow-down', 'External Documents', 'external-documents'); ?>
        <?php if ($sidebar_is_admin): ?>
        <?php echo $sidebar_link('llrm-integration.php', 'bi bi-cloud-arrow-up-down', 'LLRM Integration', 'llrm-integration'); ?>
        <a href="recent_deleted.php" class="hidden"></a>
        <?php endif; ?>
        
        <!-- Version Tracking with Dropdown (Mobile) -->
        <div class="mb-1.5">
            <div class="group flex w-full items-center justify-between px-4 py-3 text-white/90 hover:text-white rounded-2xl transition-all duration-300 hover:translate-x-1 hover:bg-white/12 hover:shadow-[0_10px_25px_rgba(0,0,0,0.18)]">
                <a href="version_tracking.php" class="flex items-center gap-3" aria-current="<?php echo $sidebar_active_page === 'version-tracking' ? 'page' : 'false'; ?>">
                    <i class="bi bi-book text-lg"></i>
                    <span>Version Tracking</span>
                </a>
                <button type="button" id="version-tracking-toggle-mobile" aria-expanded="false" aria-controls="version-tracking-submenu-mobile" class="text-xs transition-transform duration-200 hover:bg-white/10 p-1 rounded-lg" onclick="event.stopPropagation();">
                    <i class="bi bi-chevron-down" id="version-tracking-chevron-mobile"></i>
                </button>
            </div>
            <div id="version-tracking-submenu-mobile" class="mt-1 ml-4 space-y-1 overflow-hidden max-h-0 transition-all duration-300">
                <!-- Legislative Folders -->
                <a href="version_tracking.php?folder=ordinances" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                    <div class="w-8 h-8 rounded-lg bg-orange-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-file-earmark-text text-orange-400"></i>
                    </div>
                    <span>Ordinances & Resolutions</span>
                </a>
                <a href="version_tracking.php?folder=public-hearings" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                    <div class="w-8 h-8 rounded-lg bg-blue-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-megaphone text-blue-400"></i>
                    </div>
                    <span>Public Hearings</span>
                </a>
                <a href="version_tracking.php?folder=meetings" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                    <div class="w-8 h-8 rounded-lg bg-purple-100/20 flex items-center justify-center mr-3">
                        <i class="bi bi-journal-text text-purple-400"></i>
                    </div>
                    <span>Meeting Records</span>
                </a>
                
                <!-- Dynamic Archive Folders -->
                <?php if (!empty($archive_folders)): ?>
                    <?php foreach ($archive_folders as $folder): ?>
                    <a href="version_tracking.php?folder=archive_<?php echo (int)$folder['id']; ?>" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                        <div class="w-8 h-8 rounded-lg bg-slate-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-folder-fill text-slate-400"></i>
                        </div>
                        <span><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                <div class="mt-2 text-[11px] text-red-100/75">Total disk usage across all upload folders.</div>
            </div>
        </div>
    </nav>
</div>
<?php if ($sidebar_layout === 'full'): ?>
<aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-[#6b0f0f] via-[#bf1e2e] to-[#4b0f0f] text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed left-0 top-0 z-50 shadow-[16px_0_45px_rgba(0,0,0,0.24)] border-r border-white/10 backdrop-blur-xl hidden md:flex">
    <div class="p-6 border-b border-white/10 sidebar-logo bg-white/5 backdrop-blur-sm">
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
    <nav class="flex-1 py-4 overflow-y-auto">
        <div class="px-4 space-y-1">
            <?php echo $sidebar_link('archives-landing.php', 'bi bi-speedometer2', 'Dashboard Archives', 'dashboard', true); ?>
            <?php echo $sidebar_link('storage.php', 'bi bi-folder', 'Main Storage Archives', 'storage', true); ?>
            <?php echo $sidebar_link('export.php', 'bi bi-cloud-upload', 'Export', 'export', true); ?>
            <?php echo $sidebar_link('external-documents.php', 'bi bi-cloud-arrow-down', 'External Documents', 'external-documents', true); ?>
            <?php if ($sidebar_is_admin): ?>
            <?php echo $sidebar_link('llrm-integration.php', 'bi bi-cloud-arrow-up-down', 'LLRM Integration', 'llrm-integration', true); ?>
            <a href="recent_deleted.php" class="hidden"></a>
            <?php endif; ?>
            
            <!-- Version Tracking with Dropdown (Desktop) -->
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
                <div id="version-tracking-submenu-desktop" class="mt-1 ml-4 space-y-1 overflow-hidden max-h-0 transition-all duration-300">
                    <!-- Legislative Folders -->
                    <a href="version_tracking.php?folder=ordinances" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                        <div class="w-8 h-8 rounded-lg bg-orange-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-file-earmark-text text-orange-400"></i>
                        </div>
                        <span class="sidebar-text">Ordinances & Resolutions</span>
                    </a>
                    <a href="version_tracking.php?folder=public-hearings" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                        <div class="w-8 h-8 rounded-lg bg-blue-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-megaphone text-blue-400"></i>
                        </div>
                        <span class="sidebar-text">Public Hearings</span>
                    </a>
                    <a href="version_tracking.php?folder=meetings" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                        <div class="w-8 h-8 rounded-lg bg-purple-100/20 flex items-center justify-center mr-3">
                            <i class="bi bi-journal-text text-purple-400"></i>
                        </div>
                        <span class="sidebar-text">Meeting Records</span>
                    </a>
                    
                    <!-- Dynamic Archive Folders -->
                    <?php if (!empty($archive_folders)): ?>
                        <?php foreach ($archive_folders as $folder): ?>
                        <a href="version_tracking.php?folder=archive_<?php echo (int)$folder['id']; ?>" class="vt-folder-link group flex w-full items-center px-4 py-2 text-white/80 hover:text-white rounded-xl transition-all duration-300 hover:bg-white/10">
                            <div class="w-8 h-8 rounded-lg bg-slate-100/20 flex items-center justify-center mr-3">
                                <i class="bi bi-folder-fill text-slate-400"></i>
                            </div>
                            <span class="sidebar-text"><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                <div class="mt-2 text-[11px] text-red-100/75">Total disk usage across all upload folders.</div>
            </div>
        </div>
    </nav>
</aside>
<?php endif; ?>
<script>
// Defensive layout helper: ensure fixed desktop sidebar doesn't cover main content.
(function(){
    function adjustMainOffset(){
        try{
            var aside = document.getElementById('sidebar');
            if(!aside) return;
            var w = window.innerWidth || document.documentElement.clientWidth;
            
            // Reset any default margins/padding first
            document.documentElement.style.margin = '0';
            document.documentElement.style.padding = '0';
            document.body.style.margin = '0';
            
            // Apply padding to body on md+ (desktop) where sidebar is visible and fixed
            if(w >= 768){
                document.body.style.paddingLeft = '16rem';
            } else {
                // remove padding on small screens where sidebar is hidden
                document.body.style.paddingLeft = '0';
            }
        }catch(e){ console && console.warn && console.warn('sidebar layout adjust failed', e); }
    }
    window.addEventListener('resize', adjustMainOffset);
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', adjustMainOffset); else adjustMainOffset();
})();

// Sidebar dropdown functionality (Version Tracking, External Documents, LLRM Integration)
(function() {
    function setupDropdown(toggleId, submenuId, chevronId) {
        var toggle = document.getElementById(toggleId);
        var submenu = document.getElementById(submenuId);
        var chevron = document.getElementById(chevronId);
        
        if (!toggle || !submenu) return;
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var isExpanded = submenu.style.maxHeight && submenu.style.maxHeight !== '0px';
            
            if (isExpanded) {
                // Collapse
                submenu.style.maxHeight = '0px';
                submenu.style.opacity = '0';
                toggle.setAttribute('aria-expanded', 'false');
                if (chevron) {
                    chevron.style.transform = 'rotate(0deg)';
                }
            } else {
                // Expand
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                submenu.style.opacity = '1';
                toggle.setAttribute('aria-expanded', 'true');
                if (chevron) {
                    chevron.style.transform = 'rotate(180deg)';
                }
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!toggle.contains(e.target) && !submenu.contains(e.target)) {
                submenu.style.maxHeight = '0px';
                submenu.style.opacity = '0';
                toggle.setAttribute('aria-expanded', 'false');
                if (chevron) {
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        });
    }
    
    var dropdowns = [
        ['version-tracking-toggle-mobile', 'version-tracking-submenu-mobile', 'version-tracking-chevron-mobile'],
        ['version-tracking-toggle-desktop', 'version-tracking-submenu-desktop', 'version-tracking-chevron-desktop']
    ];
    
    // Initialize dropdowns for both mobile and desktop
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            dropdowns.forEach(function(d) { setupDropdown(d[0], d[1], d[2]); });
        });
    } else {
        dropdowns.forEach(function(d) { setupDropdown(d[0], d[1], d[2]); });
    }
})();
</script>
<script>
// Centralized Storage Overview: populates the sidebar storage card on every page.
(function () {
    function fmtBytes(bytes) {
        if (bytes <= 0) return '0 B';
        var units = ['B','KB','MB','GB','TB'];
        var e = Math.floor(Math.log(bytes) / Math.log(1024));
        e = Math.max(0, Math.min(e, units.length - 1));
        var v = bytes / Math.pow(1024, e);
        return (e >= 3 ? v.toFixed(1) : Math.round(v)) + ' ' + units[e];
    }

    function apply(data) {
        var pct = String(data.percent || 0) + '%';
        var text = (data.used_human || '0 B') + ' of ' + (data.total_human || '50 GB');
        var files = String(data.file_count || 0) + ' files tracked';
        ['mobile', 'desktop'].forEach(function (k) {
            var bar = document.getElementById(k + '-storage-bar');
            var pctEl = document.getElementById(k + '-storage-pct');
            var textEl = document.getElementById(k + '-storage-text');
            var filesEl = document.getElementById(k + '-storage-files');
            if (bar) bar.style.width = pct;
            if (pctEl) pctEl.textContent = pct;
            if (textEl) textEl.textContent = text;
            if (filesEl) filesEl.textContent = files;
        });
    }

    function load() {
        fetch('storage_status.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.success) apply(d); })
            .catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
    window.addEventListener('focus', load);
    setInterval(load, 60000);
})();
</script>
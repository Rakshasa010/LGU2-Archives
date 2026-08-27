<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'authdatabase.php';
require_once 'includes/llrm-service.php';

// Verify admin
$userId = (int)$_SESSION['user_id'];
$roleStmt = $conn->prepare("SELECT role, full_name, dark_mode, profile_picture FROM users WHERE id = ?");
$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$user = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

$profile_picture_url = null;
if (is_string($user['profile_picture'] ?? null) && $user['profile_picture'] !== '') {
    $candidatePath = $user['profile_picture'];
    $candidateUrl = $user['profile_picture'];
    if (strpos($user['profile_picture'], 'uploads/') !== 0) {
        $candidatePath = 'uploads/profile_pictures/' . $user['profile_picture'];
        $candidateUrl = 'uploads/profile_pictures/' . $user['profile_picture'];
    }
    if (file_exists($candidatePath)) {
        $profile_picture_url = $candidateUrl;
    }
}

if (!$user || strtolower($user['role'] ?? '') !== 'admin') {
    header('Location: archives-landing.php');
    exit;
}

$llrm = new LLRMService();
$stats = $llrm->getStats();
$types = $llrm->getTypes();
$health = $llrm->healthCheck();

$pageTitle = 'LLRM Integration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Archives</title>
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
<?php
include 'includes/header_scripts.php';
?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
<style>
    .stat-card { transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .llrm-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
</style>
</head>
<body class="bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.16),_transparent_38%),linear-gradient(135deg,_#fef2f2_0%,_#f8fafc_50%,_#fef2f2_100%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.14),_transparent_35%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f172a_100%)] font-sans antialiased transition-colors duration-200 min-h-screen">
    <div>
    <?php
        $sidebar_active_page = 'llrm-integration';
        $sidebar_include_overlay = true;
        $sidebar_user_data = $user;
        require_once 'includes/sidebar-centralized.php';
    ?>
    <div class="flex flex-col min-h-screen md:ml-72">
        <!-- Header / Navbar -->
        <nav class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl border-b border-white/70 dark:border-slate-700/70 shadow-[0_10px_35px_rgba(15,23,42,0.08)] sticky top-0 z-40 transition-colors duration-200">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <i class="bi bi-list text-2xl"></i>
                        </button>
                        <div class="mobile-only flex items-center ml-2">
                            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">
                        </div>
                    </div>
                    <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                        <div class="ml-2 md:ml-4 min-w-0">
                            <h2 class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100"><i class="bi bi-cloud-arrow-up-down text-red-600 dark:text-red-400 mr-1"></i>LLRM Integration</h2>
                        </div>
                    </div>
                    <div class="flex items-center space-x-1 md:space-x-4">
                        <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                            <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </button>
                        <div class="relative">
                            <button id="notification-btn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors relative" title="Notifications">
                                <i class="bi bi-bell-fill text-xl text-gray-700 dark:text-gray-300"></i>
                                <span id="notif-count" class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-red-600 bg-red-100 rounded-full">0</span>
                            </button>
                            <div id="notification-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                <div class="p-4">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Notifications</div>
                                    <div id="notif-list" class="space-y-2">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Loading notifications...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="relative">
                            <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                <?php if ($profile_picture_url): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php else: ?>
                                    <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden sm:block text-left">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                                </div>
                                <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                            </button>
                            <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
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
        <div class="p-4 sm:p-6 max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 llrm-badge rounded-xl flex items-center justify-center">
                        <i class="bi bi-cloud-arrow-up-down text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="bi bi-cloud-arrow-up-down text-red-600 dark:text-red-400 mr-1"></i>LLRM Integration</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><i class="bi bi-arrow-repeat text-blue-600 dark:text-blue-400 mr-1"></i>Legislative Records Management System — Two-way sync</p>
                    </div>
                </div>
            </div>

            <!-- Connection Status -->
            <div id="llrm-connection" class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700 mb-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full <?php echo (isset($health['success']) && $health['success']) ? 'bg-green-500' : 'bg-red-500'; ?> animate-pulse"></div>
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Connection Status</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo (isset($health['success']) && $health['success']) ? 'Connected to LLRM API v' . ($health['version'] ?? '1') : 'Connection failed'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="llrmAction('health')" class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                            <i class="bi bi-arrow-clockwise mr-1"></i> Test Connection
                        </button>
                        <button onclick="llrmAction('batch_push', {batch_size: 10})" class="px-4 py-2 text-sm font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                            <i class="bi bi-cloud-upload mr-1"></i> Push 10 Records
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <?php if (isset($stats['success']) && $stats['success'] && isset($stats['stats'])): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Documents</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['stats']['total_documents'] ?? 0); ?></div>
                </div>
                <div class="stat-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Recent (7 days)</div>
                    <div class="text-2xl font-bold text-green-600"><?php echo number_format($stats['stats']['recent_uploads_7d'] ?? 0); ?></div>
                </div>
                <div class="stat-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">LAS Documents</div>
                    <div class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['stats']['by_source_system']['las'] ?? 0); ?></div>
                </div>
                <div class="stat-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">OCR Completed</div>
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['stats']['by_ocr_status']['completed'] ?? 0); ?></div>
                </div>
            </div>

            <!-- By Type & Status -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <div id="llrm-by-type" class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Documents by Type</h3>
                    <div class="space-y-2">
                        <?php foreach (($stats['stats']['by_type'] ?? []) as $type => $count): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400 capitalize"><?php echo htmlspecialchars(ucfirst($type)); ?></span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo number_format($count); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="llrm-by-status" class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Documents by Status</h3>
                    <div class="space-y-2">
                        <?php foreach (($stats['stats']['by_status'] ?? []) as $status => $count): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400 capitalize"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo number_format($count); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card p-6 bg-yellow-50 dark:bg-yellow-900/20 rounded-2xl border border-yellow-200 dark:border-yellow-800 mb-6">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <i class="bi bi-exclamation-triangle mr-1"></i>
                    Could not fetch LLRM statistics. Check connection and API key.
                </p>
            </div>
            <?php endif; ?>

            <!-- Search & List -->
            <div id="llrm-search" class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Search LLRM Archive</h3>
                <div class="flex gap-2 mb-4">
                    <input type="text" id="llrmSearchInput" class="flex-1 px-4 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="Search LLRM documents..." onkeydown="if(event.key==='Enter') llrmSearch()">
                    <button onclick="llrmSearch()" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">Search</button>
                </div>
                <div id="llrmSearchResults" class="space-y-2"></div>
            </div>

            <!-- Document Types -->
            <?php if (isset($types['success']) && $types['success']): ?>
            <div id="llrm-types" class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Available Document Types</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (($types['document_types'] ?? []) as $t): ?>
                    <span class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-full">
                        <span class="font-mono text-purple-600"><?php echo htmlspecialchars($t['prefix']); ?></span>
                        <?php echo htmlspecialchars($t['label']); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function llrmAction(action, params = {}) {
            const formData = new FormData();
            Object.keys(params).forEach(k => formData.append(k, params[k]));
            
            fetch('api/llrm-integration.php?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + JSON.stringify(data, null, 2));
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Request failed: ' + err));
        }

        function llrmSearch() {
            const q = document.getElementById('llrmSearchInput').value.trim();
            if (!q) return;
            
            const container = document.getElementById('llrmSearchResults');
            container.innerHTML = '<div class="text-sm text-gray-500">Searching...</div>';
            
            fetch('api/llrm-integration.php?action=search&q=' + encodeURIComponent(q) + '&per_page=10')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.documents) {
                        container.innerHTML = data.documents.map(d => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white text-sm">${d.title || 'Untitled'}</div>
                                    <div class="text-xs text-gray-500">${d.reference_number || ''} · ${d.document_type || ''} · ${d.status || ''}</div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="llrmDownload(${d.id})" class="text-xs px-2 py-1 bg-blue-600 text-white rounded">Download</button>
                                    <button onclick="llrmGet(${d.id})" class="text-xs px-2 py-1 bg-gray-600 text-white rounded">Details</button>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="text-sm text-gray-500">No results or error: ' + (data.error || '') + '</div>';
                    }
                })
                .catch(err => {
                    container.innerHTML = '<div class="text-sm text-red-500">Error: ' + err + '</div>';
                });
        }

        function llrmDownload(id) {
            window.open('api/llrm-integration.php?action=download&id=' + id, '_blank');
        }

        function llrmGet(id) {
            fetch('api/llrm-integration.php?action=get&id=' + id)
                .then(r => r.json())
                .then(data => {
                    alert(JSON.stringify(data, null, 2));
                });
        }
    </script>
        </main>
        <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script>
        (function(){
            var profileBtn = document.getElementById('profile-btn');
            var profileDropdown = document.getElementById('profile-dropdown');
            var notifBtn = document.getElementById('notification-btn');
            var notifDropdown = document.getElementById('notification-dropdown');
            var notifCount = document.getElementById('notif-count');

            profileBtn && profileBtn.addEventListener('click', function(e){
                e.stopPropagation();
                notifDropdown && notifDropdown.classList.add('hidden');
                profileDropdown && profileDropdown.classList.toggle('hidden');
            });

            notifBtn && notifBtn.addEventListener('click', function(e){
                e.stopPropagation();
                profileDropdown && profileDropdown.classList.add('hidden');
                notifDropdown && notifDropdown.classList.toggle('hidden');
                try {
                    var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
                    if (ids.length > 0) {
                        fetch('notifications_log.php', {
                            method:'POST',
                            headers:{'Content-Type':'application/x-www-form-urlencoded'},
                            body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
                        }).then(function(){});
                    }
                } catch(err){}
            });

            document.addEventListener('click', function(e){
                if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
                    profileDropdown && profileDropdown.classList.add('hidden');
                }
                if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
                    notifDropdown && notifDropdown.classList.add('hidden');
                }
            });

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
                html += '<div class="pt-2"><button id="mark-all-read" class="w-full px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Mark all as read</button></div>';
                container.innerHTML = html;
                var btnAll = container.querySelector('#mark-all-read');
                if (btnAll) {
                    btnAll.addEventListener('click', function(){
                        container.querySelectorAll('a[data-id]').forEach(function(a){
                            a.classList.remove('ring-2','ring-red-200');
                            var p = a.querySelector('p.text-sm');
                            if (p) { p.classList.remove('font-semibold'); p.classList.add('font-medium'); }
                        });
                        try {
                            fetch('notifications_update.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'all=1&status=read'
                            }).then(function(){ refresh(); }).catch(function(){ refresh(); });
                        } catch(e){ refresh(); }
                        notifCount && (notifCount.textContent = '0', notifCount.style.display = 'none');
                    });
                }
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
        (function(){
            var sidebarToggle = document.getElementById('sidebar-toggle');
            var mobileMenuBtn = document.getElementById('mobile-menu-btn');
            var sidebar = document.getElementById('sidebar');
            var mobileSidebar = document.getElementById('mobile-sidebar');
            var sidebarOverlay = document.getElementById('sidebar-overlay');
            var closeMobileSidebar = document.getElementById('close-mobile-sidebar');

            sidebarToggle && sidebarToggle.addEventListener('click', function(){
                sidebar && sidebar.classList.toggle('sidebar-collapsed');
                try { localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-collapsed')); } catch(err){}
            });

            mobileMenuBtn && mobileMenuBtn.addEventListener('click', function(){
                mobileSidebar && mobileSidebar.classList.remove('-translate-x-full');
                sidebarOverlay && (sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none'),
                                   sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto'));
            });

            closeMobileSidebar && closeMobileSidebar.addEventListener('click', function(){
                mobileSidebar && mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay && (sidebarOverlay.classList.add('opacity-0', 'pointer-events-none'),
                                   sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto'));
            });

            sidebarOverlay && sidebarOverlay.addEventListener('click', function(){
                mobileSidebar && mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            });
        })();
    </script>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

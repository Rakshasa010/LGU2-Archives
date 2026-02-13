<?php
include 'authdatabase.php';

$sql = "SELECT id, title, type, month, year, author, created_at, last_accessed 
        FROM legislative_records 
        WHERE type IN ('Ordinance','Resolution','Billing','Public Hearing','Meeting')
        ORDER BY year DESC, month DESC, created_at DESC";
$result = $conn->query($sql);

$records_by_type = [
    'Ordinance' => [],
    'Resolution' => [],
    'Billing' => [],
    'Public Hearing' => [],
    'Meeting' => []
];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (isset($records_by_type[$row['type']])) {
            $records_by_type[$row['type']][] = $row;
        }
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
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300" aria-hidden="true"></div>
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
                <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90" aria-label="Close sidebar">
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
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>
            <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-cloud-upload mr-3 text-lg"></i>
                <span>Export</span>
            </a>

            <a href="version_tracking.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-book mr-3"></i>
                <span class="sidebar-text">Version Tracking</span>
            </a>
            
            <div class="mt-6 pt-4 border-t border-red-700/50 px-2">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-red-100">Storage Usage</span>
                        <span class="text-xs font-bold text-white" id="mobile-storage-percent">2%</span>
                    </div>
                    <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                        <div class="bg-white h-full rounded-full" id="mobile-storage-bar" style="width: 2%;"></div>
                    </div>
                    <div class="text-xs text-red-100"><span id="mobile-storage-used">1.0 GB</span> of <span id="mobile-storage-total">50.0 GB</span></div>
                </div>
            </div>
        </nav>
    </div>
    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
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
            <nav class="flex-1 overflow-y-hidden py-4">
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
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                    <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-graph-up mr-3"></i>
                        <span class="sidebar-text">Reports & Analytics</span>
                    </a>
                </div>
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                    <a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-shield-check mr-3"></i>
                        <span class="sidebar-text">Audit Logs</span>
                    </a>
                </div>
                <div class="mt-6 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                    <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-red-100">Storage Usage</span>
                            <span class="text-xs font-bold text-white" id="desktop-storage-percent">2%</span>
                        </div>
                        <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-white h-full rounded-full" id="desktop-storage-bar" style="width: 2%;"></div>
                        </div>
                        <div class="text-xs text-red-100"><span id="desktop-storage-used">1.0 GB</span> of <span id="desktop-storage-total">50.0 GB</span></div>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden">
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center space-x-4">
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200" aria-label="Open sidebar">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                            <a href="archives-landing.php" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                <span class="text-xl">←</span>
                                <span class="font-semibold">Back to Archives</span>
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                                <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="mb-8 pb-6 border-b border-gray-200 dark:border-slate-700">
                        <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Version Tracking</h1>
                        <p class="text-gray-600 dark:text-gray-400">Browse folders and view mock version history of files</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                        <button type="button" onclick="viewFolder('ordRes','Ordinances & Resolutions')" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 text-left hover:shadow-xl transition-all group">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-orange-600 dark:text-orange-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                <div>
                                    <div class="font-semibold">Ordinances & Resolutions</div>
                                    <div class="text-xs text-gray-500">From main storage archive</div>
                                </div>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('billing','Billing')" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 text-left hover:shadow-xl transition-all group">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <div>
                                    <div class="font-semibold">Billing</div>
                                    <div class="text-xs text-gray-500">From main storage archive</div>
                                </div>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('publicHearing','Public Hearings')" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 text-left hover:shadow-xl transition-all group">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                <div>
                                    <div class="font-semibold">Public Hearings</div>
                                    <div class="text-xs text-gray-500">From main storage archive</div>
                                </div>
                            </div>
                        </button>
                        <button type="button" onclick="viewFolder('meeting','Meeting/Sessions Records')" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 text-left hover:shadow-xl transition-all group">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-purple-600 dark:text-purple-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                <div>
                                    <div class="font-semibold">Meeting/Sessions Records</div>
                                    <div class="text-xs text-gray-500">From main storage archive</div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <div id="filesPanel" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 mt-6 hidden">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div id="filesPanelTitle" class="font-semibold text-gray-800 dark:text-gray-200">Files</div>
                                <div id="filesPanelMeta" class="text-xs text-gray-500 dark:text-gray-400"></div>
                            </div>
                            <button type="button" onclick="clearFolder()" class="px-3 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600">Close</button>
                        </div>
                        <div id="filesPanelList" class="space-y-3"></div>
                    </div>
                </div>
            </main>
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
        var recordsData = <?php
            $ordRes = array_merge($records_by_type['Ordinance'], $records_by_type['Resolution']);
            echo json_encode([
                'ordRes' => $ordRes,
                'billing' => $records_by_type['Billing'],
                'publicHearing' => $records_by_type['Public Hearing'],
                'meeting' => $records_by_type['Meeting']
            ]);
        ?>;
        function viewFolder(key, label) {
            var arr = recordsData[key] || [];
            var panel = document.getElementById('filesPanel');
            var title = document.getElementById('filesPanelTitle');
            var meta = document.getElementById('filesPanelMeta');
            var list = document.getElementById('filesPanelList');
            title.textContent = label;
            meta.textContent = (arr.length ? arr.length + ' files' : 'No files');
            list.innerHTML = '';
            if (!arr.length) {
                var empty = document.createElement('div');
                empty.className = 'text-sm text-gray-500 dark:text-gray-400';
                empty.textContent = 'No files found';
                list.appendChild(empty);
            } else {
                arr.forEach(function(record){
                    var row = document.createElement('div');
                    row.className = 'flex items-start justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-slate-600';
                    var left = document.createElement('div');
                    left.className = 'min-w-0';
                    var titleEl = document.createElement('div');
                    titleEl.className = 'font-medium text-gray-800 dark:text-gray-200 truncate';
                    titleEl.textContent = record.title;
                    var metaEl = document.createElement('div');
                    metaEl.className = 'text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5';
                    var badge = document.createElement('span');
                    badge.className = 'px-2 py-0.5 rounded text-[11px]';
                    var type = String(record.type||'');
                    var cls = type === 'Billing' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300' :
                              type === 'Public Hearing' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' :
                              type === 'Meeting' ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-800 dark:text-violet-300' :
                              'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300';
                    badge.className += ' ' + cls;
                    badge.textContent = type;
                    var sep1 = document.createElement('span'); sep1.className = 'mx-1.5'; sep1.textContent = '•';
                    var dateEl = document.createElement('span'); dateEl.textContent = String(record.month||'') + ' ' + String(record.year||'');
                    var sep2 = document.createElement('span'); sep2.className = 'mx-1.5'; sep2.textContent = '•';
                    var authEl = document.createElement('span'); authEl.textContent = record.author || '';
                    metaEl.appendChild(badge); metaEl.appendChild(sep1); metaEl.appendChild(dateEl); metaEl.appendChild(sep2); metaEl.appendChild(authEl);
                    left.appendChild(titleEl); left.appendChild(metaEl);
                    var btn = document.createElement('button');
                    btn.className = 'ml-4 px-3 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600';
                    btn.textContent = 'History';
                    btn.addEventListener('click', function(){
                        openVersionHistory(record.id, record.title, record.created_at);
                    });
                    row.appendChild(left);
                    row.appendChild(btn);
                    list.appendChild(row);
                });
            }
            panel.classList.remove('hidden');
        }
        function clearFolder() {
            var panel = document.getElementById('filesPanel');
            var list = document.getElementById('filesPanelList');
            list.innerHTML = '';
            panel.classList.add('hidden');
        }
        function openVersionHistory(id, title, createdAt) {
            var list = document.getElementById('vm-list');
            var header = document.getElementById('vm-title');
            header.textContent = 'Version History — ' + (title || 'File');
            list.innerHTML = '';

            var base = new Date(createdAt || Date.now());
            var versions = [
                { v: '1.0.2', date: new Date(base.getTime() + 1000*60*60*24*30), note: 'Minor fixes and metadata update' },
                { v: '1.0.1', date: new Date(base.getTime() + 1000*60*60*24*15), note: 'Text corrections' },
                { v: '1.0.0', date: base, note: 'Initial upload' }
            ];

            versions.forEach(function(item){
                var row = document.createElement('div');
                row.className = 'flex items-start justify-between p-3 rounded-lg bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600';
                var left = document.createElement('div');
                left.innerHTML = '<div class="font-semibold text-gray-800 dark:text-gray-200">Version ' + item.v + '</div>' +
                                 '<div class="text-xs text-gray-500 dark:text-gray-400">' + item.date.toLocaleDateString() + ' • ' + item.note + '</div>';
                var right = document.createElement('div');
                right.innerHTML = '<button class="px-3 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600">View</button>';
                row.appendChild(left);
                row.appendChild(right);
                list.appendChild(row);
            });

            document.getElementById('versionModal').classList.remove('hidden');
        }
        function closeVersionModal(){
            document.getElementById('versionModal').classList.add('hidden');
        }
    </script>
    <script src="assets/js/theme-toggle.js"></script>
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
    </script>
    <script src="assets/js/storage-status.js"></script>
</body>
</html>

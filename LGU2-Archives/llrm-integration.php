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
$roleStmt = $conn->prepare("SELECT role, full_name, dark_mode FROM users WHERE id = ?");
$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$user = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$user || strtolower($user['role'] ?? '') !== 'admin') {
    header('Location: archives-landing.php');
    exit;
}

$llrm = new LLRMService();
$stats = $llrm->getStats();
$types = $llrm->getTypes();
$health = $llrm->healthCheck();

$pageTitle = 'LLRM Integration';
include 'includes/header_scripts.php';
?>
<style>
    .stat-card { transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .llrm-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
</style>

<body class="bg-gray-50 dark:bg-slate-900 min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-1 lg:ml-64 min-h-screen">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="p-4 sm:p-6 max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 llrm-badge rounded-xl flex items-center justify-center">
                        <i class="bi bi-cloud-arrow-up-down text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">LLRM Integration</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Legislative Records Management System — Two-way sync</p>
                    </div>
                </div>
            </div>

            <!-- Connection Status -->
            <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700 mb-6">
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
                <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
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
                <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
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
            <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Search LLRM Archive</h3>
                <div class="flex gap-2 mb-4">
                    <input type="text" id="llrmSearchInput" class="flex-1 px-4 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="Search LLRM documents..." onkeydown="if(event.key==='Enter') llrmSearch()">
                    <button onclick="llrmSearch()" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">Search</button>
                </div>
                <div id="llrmSearchResults" class="space-y-2"></div>
            </div>

            <!-- Document Types -->
            <?php if (isset($types['success']) && $types['success']): ?>
            <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700">
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
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

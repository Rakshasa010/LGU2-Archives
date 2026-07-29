<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'authdatabase.php';

$userId = (int)$_SESSION['user_id'];
$roleStmt = $conn->prepare("SELECT role, full_name, dark_mode FROM users WHERE id = ?");
$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$user = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$user) {
    header('Location: login.php');
    exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS external_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL DEFAULT 'archive',
    document_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'archived',
    description TEXT NULL,
    tags VARCHAR(500) NULL,
    reference_number VARCHAR(100) NULL,
    file_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    file_size BIGINT DEFAULT 0,
    file_type VARCHAR(100) NULL,
    source_system VARCHAR(50) NOT NULL DEFAULT 'llrm',
    external_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$search = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterSource = $_GET['source'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (title LIKE ? OR description LIKE ? OR reference_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if (!empty($filterType)) {
    $where .= " AND document_type = ?";
    $params[] = $filterType;
    $types .= "s";
}
if (!empty($filterSource)) {
    $where .= " AND source_system = ?";
    $params[] = $filterSource;
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM external_documents $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Fetch page
$listSql = "SELECT * FROM external_documents $where ORDER BY created_at DESC LIMIT ?, ?";
$listStmt = $conn->prepare($listSql);
$allParams = array_merge($params, [$offset, $perPage]);
$allTypes = $types . "ii";
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$res = $listStmt->get_result();
$documents = [];
while ($row = $res->fetch_assoc()) {
    $documents[] = $row;
}
$listStmt->close();

// Get unique source systems for filter
$sourceRes = $conn->query("SELECT DISTINCT source_system FROM external_documents");
$sources = [];
while ($s = $sourceRes->fetch_assoc()) {
    $sources[] = $s['source_system'];
}

// Get unique document types for filter
$typeRes = $conn->query("SELECT DISTINCT document_type FROM external_documents");
$docTypes = [];
while ($t = $typeRes->fetch_assoc()) {
    $docTypes[] = $t['document_type'];
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

$pageTitle = 'External Documents';
include 'includes/header_scripts.php';

function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $e = floor(log($bytes, 1024));
    $e = min($e, count($units) - 1);
    return round($bytes / pow(1024, $e), $e >= 2 ? 1 : 0) . ' ' . $units[$e];
}
?>
<style>
    .doc-card { transition: all 0.3s; }
    .doc-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .source-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
</style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 min-h-screen">
    <?php
        $sidebar_active_page = 'external-documents';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
    ?>
    <div class="flex flex-col min-h-screen md:ml-72">

        <div class="p-4 sm:p-6 max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 source-badge rounded-xl flex items-center justify-center">
                        <i class="bi bi-cloud-arrow-down text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">External Documents</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Documents received from external systems (LLRM, etc.)</p>
                    </div>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="doc-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Received</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($total); ?></div>
                </div>
                <div class="doc-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Source Systems</div>
                    <div class="text-2xl font-bold text-purple-600"><?php echo count($sources); ?></div>
                </div>
                <div class="doc-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Document Types</div>
                    <div class="text-2xl font-bold text-blue-600"><?php echo count($docTypes); ?></div>
                </div>
                <div class="doc-card p-4 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">This Page</div>
                    <div class="text-2xl font-bold text-green-600"><?php echo count($documents); ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700 mb-6">
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm" placeholder="Search title, description, reference...">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Type</label>
                        <select name="type" class="px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">All Types</option>
                            <?php foreach ($docTypes as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filterType === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($t)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Source</label>
                        <select name="source" class="px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">All Sources</option>
                            <?php foreach ($sources as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $filterSource === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition">Filter</button>
                    <a href="external-documents.php" class="px-4 py-2 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium transition">Clear</a>
                </form>
            </div>

            <!-- Documents List -->
            <?php if (empty($documents)): ?>
                <div class="card p-12 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700 text-center">
                    <i class="bi bi-inbox text-5xl text-gray-300 dark:text-gray-600 mb-3 block"></i>
                    <p class="text-gray-500 dark:text-gray-400">No external documents received yet.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Documents pushed from LLRM will appear here.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($documents as $doc):
                        $fileExt = strtolower(pathinfo($doc['file_name'] ?? '', PATHINFO_EXTENSION));
                        $iconClass = 'bi-file-earmark-text text-blue-500';
                        if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                        elseif ($fileExt === 'pdf') $iconClass = 'bi-file-earmark-pdf text-red-500';
                        elseif (in_array($fileExt, ['mp4','avi','mov','webm'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                        elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';
                        elseif (in_array($fileExt, ['xls','xlsx'])) $iconClass = 'bi-file-earmark-spreadsheet text-green-600';
                    ?>
                    <div class="doc-card bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                        <div class="h-32 bg-gray-100 dark:bg-slate-700 flex items-center justify-center relative">
                            <?php if (in_array($fileExt, ['jpg','jpeg','png','gif','webp']) && !empty($doc['file_path']) && file_exists($doc['file_path'])): ?>
                                <img src="<?php echo htmlspecialchars($doc['file_path']); ?>" class="w-full h-full object-cover" loading="lazy" alt="Preview">
                            <?php else: ?>
                                <i class="bi <?php echo $iconClass; ?> text-5xl"></i>
                            <?php endif; ?>
                            <span class="absolute top-2 right-2 px-2 py-1 text-xs font-bold text-white source-badge rounded-full uppercase"><?php echo htmlspecialchars($doc['source_system']); ?></span>
                        </div>
                        <div class="p-4">
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate mb-2" title="<?php echo htmlspecialchars($doc['title']); ?>"><?php echo htmlspecialchars($doc['title']); ?></div>
                            <div class="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                                <div class="flex justify-between">
                                    <span class="font-medium">Type:</span>
                                    <span class="capitalize"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Date:</span>
                                    <span><?php echo !empty($doc['document_date']) ? date('M d, Y', strtotime($doc['document_date'])) : 'N/A'; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Ref:</span>
                                    <span class="font-mono"><?php echo htmlspecialchars($doc['reference_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Size:</span>
                                    <span><?php echo formatFileSize((int)$doc['file_size']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Received:</span>
                                    <span><?php echo date('M d, Y g:i A', strtotime($doc['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($doc['description'])): ?>
                                <div class="mt-2 pt-2 border-t border-gray-100 dark:border-slate-600 text-xs text-gray-500 dark:text-gray-400 line-clamp-2"><?php echo htmlspecialchars($doc['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($doc['tags'])): ?>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <?php foreach (explode(',', $doc['tags']) as $tag): ?>
                                        <span class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-slate-600 text-gray-600 dark:text-gray-300 rounded-full"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3 flex gap-2">
                                <?php if (!empty($doc['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download="<?php echo htmlspecialchars($doc['file_name'] ?? ''); ?>" class="flex-1 px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-center transition">
                                        <i class="bi bi-download mr-1"></i> Download
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($doc['file_path']) && in_array($fileExt, ['jpg','jpeg','png','gif','webp','pdf'])): ?>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="flex-1 px-3 py-1.5 text-xs font-medium bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-200 rounded-lg text-center transition">
                                        <i class="bi bi-eye mr-1"></i> View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($filterType); ?>&source=<?php echo urlencode($filterSource); ?>" class="px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">Previous</a>
                    <?php endif; ?>
                    <span class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($filterType); ?>&source=<?php echo urlencode($filterSource); ?>" class="px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    </div>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

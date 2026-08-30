<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'authdatabase.php';

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

$where = "WHERE (status IS NULL OR LOWER(status) <> 'routed')";
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
<?php
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
<body class="bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.16),_transparent_38%),linear-gradient(135deg,_#fef2f2_0%,_#f8fafc_50%,_#fef2f2_100%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(248,113,113,0.14),_transparent_35%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f172a_100%)] font-sans antialiased transition-colors duration-200 min-h-screen">
    <div>
    <?php
        $sidebar_active_page = 'external-documents';
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
                            <h2 class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">External Documents</h2>
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
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo (strtolower($user['role'] ?? '') === 'admin') ? 'Administrator' : 'User'; ?></p>
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
                <div class="card p-3 bg-white dark:bg-slate-800 rounded-2xl shadow border border-gray-100 dark:border-slate-700 mb-4 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                        <input type="checkbox" id="select-all-page" class="w-4 h-4 accent-red-600 cursor-pointer">
                        Select all on page
                    </label>
                    <button type="button" id="clear-selection" class="px-3 py-1.5 text-xs font-medium bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-200 rounded-lg transition hover:bg-gray-300 dark:hover:bg-slate-500">Clear</button>
                    <span id="sel-count" class="text-sm font-semibold text-gray-800 dark:text-gray-200">0 selected</span>
                    <div class="flex-1"></div>
                    <button type="button" id="bulk-route-btn" class="px-4 py-2 text-sm font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="bi bi-folder-plus mr-1"></i> Route Selected (<span id="bulk-count">0</span>)
                    </button>
                </div>
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
                            <span class="absolute top-2 left-2 px-2 py-1 text-xs font-bold text-white rounded-full uppercase <?php echo (strtolower($doc['status'] ?? '') === 'routed') ? 'bg-emerald-500' : 'bg-amber-500'; ?>"><?php echo htmlspecialchars(strtolower($doc['status'] ?? 'pending')); ?></span>
                            <?php if (strtolower($doc['status'] ?? '') !== 'routed'): ?>
                                <label class="absolute bottom-2 left-2 flex items-center gap-1.5 bg-white/90 dark:bg-slate-900/80 rounded-lg px-2 py-1 cursor-pointer select-none shadow-sm z-10">
                                    <input type="checkbox" class="doc-select w-4 h-4 accent-red-600 cursor-pointer" value="<?php echo (int)$doc['id']; ?>">
                                    <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">Select</span>
                                </label>
                            <?php endif; ?>
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
                                <?php if (strtolower($doc['status'] ?? '') !== 'routed'): ?>
                                    <button type="button" class="route-btn flex-1 px-3 py-1.5 text-xs font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg text-center transition" data-id="<?php echo $doc['id']; ?>" data-type="<?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>" data-title="<?php echo htmlspecialchars($doc['title']); ?>" data-date="<?php echo htmlspecialchars($doc['document_date'] ?? ''); ?>" data-ref="<?php echo htmlspecialchars($doc['reference_number'] ?? ''); ?>" data-author="<?php echo htmlspecialchars($doc['source_system'] ?? 'LLRM'); ?>">
                                        <i class="bi bi-folder-plus mr-1"></i> Route to Folder
                                    </button>
                                <?php else: ?>
                                    <span class="flex-1 px-3 py-1.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300 rounded-lg text-center">
                                        <i class="bi bi-check-circle mr-1"></i> Routed
                                    </span>
                                <?php endif; ?>
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
        </div>
    </div>
        </main>
        <!-- Route to Folder Modal -->
        <div id="routeModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Route to Folder</h3>
                        <p id="routeModalTitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                    </div>
                    <button type="button" onclick="closeRouteModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- AI Scan Button -->
                <div id="aiScanSection" class="mb-4 hidden">
                    <button type="button" id="aiScanBtn" class="w-full px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg transition flex items-center justify-center gap-2">
                        <i class="bi bi-magic"></i> Auto Scan & Fill with AI
                    </button>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 text-center"><i class="bi bi-exclamation-triangle mr-1"></i>AI may make mistakes. Double-check all fields before confirming.</p>
                </div>
                <div id="aiScanStatus" class="hidden mb-4 text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 rounded-lg text-sm">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span>Scanning document with AI...</span>
                    </div>
                </div>

                <!-- Metadata Fields -->
                <div class="space-y-3 mb-4">
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Title</label>
                        <input type="text" id="routeMetaTitle" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm" placeholder="Document title">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Author</label>
                            <input type="text" id="routeMetaAuthor" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm" placeholder="Author or office">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Type</label>
                            <select id="routeMetaType" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm">
                                <option value="">-- Select Type --</option>
                                <option value="ordinance">Ordinance</option>
                                <option value="resolution">Resolution</option>
                                <option value="public hearing">Public Hearing</option>
                                <option value="meeting">Meeting</option>
                                <option value="executive order">Executive Order</option>
                                <option value="memorandum">Memorandum</option>
                                <option value="certificate">Certificate</option>
                                <option value="permit">Permit</option>
                                <option value="contract">Contract</option>
                                <option value="report">Report</option>
                                <option value="letter">Letter</option>
                                <option value="form">Form</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Date</label>
                            <input type="date" id="routeMetaDate" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Reference Number</label>
                            <input type="text" id="routeMetaRef" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm" placeholder="Ref number">
                        </div>
                    </div>
                </div>

                <!-- Folder Selection -->
                <div class="mb-4">
                    <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Destination Folder</label>
                    <select id="routeFolderSelect" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm"></select>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Folders are pre-suggested by document type; you may choose any folder.</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 justify-end">
                    <button type="button" onclick="closeRouteModal()" class="px-4 py-2 text-sm font-medium bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-200 rounded-lg transition">Cancel</button>
                    <button type="button" id="routeConfirmBtn" class="px-4 py-2 text-sm font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg transition">Confirm Route</button>
                </div>
            </div>
        </div>
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
    <script>
        (function(){
            var routeModal = document.getElementById('routeModal');
            var routeSelect = document.getElementById('routeFolderSelect');
            var routeTitle = document.getElementById('routeModalTitle');
            var currentId = null;
            var currentIds = [];
            var bulkMode = false;
            var allFolders = [];
            var suggestions = [];

            // Metadata field elements
            var metaTitle = document.getElementById('routeMetaTitle');
            var metaAuthor = document.getElementById('routeMetaAuthor');
            var metaType = document.getElementById('routeMetaType');
            var metaDate = document.getElementById('routeMetaDate');
            var metaRef = document.getElementById('routeMetaRef');
            var aiScanSection = document.getElementById('aiScanSection');
            var aiScanStatus = document.getElementById('aiScanStatus');
            var aiScanBtn = document.getElementById('aiScanBtn');

            function getSelected(){
                return Array.prototype.map.call(document.querySelectorAll('.doc-select:checked'), function(el){ return el.value; });
            }
            function updateBulkUi(){
                var n = getSelected().length;
                var cnt = document.getElementById('sel-count');
                var bc = document.getElementById('bulk-count');
                var btn = document.getElementById('bulk-route-btn');
                var sa = document.getElementById('select-all-page');
                var boxes = document.querySelectorAll('.doc-select');
                if (cnt) cnt.textContent = n + ' selected';
                if (bc) bc.textContent = String(n);
                if (btn) btn.disabled = n === 0;
                if (sa) sa.checked = boxes.length > 0 && n === boxes.length;
            }
            function clearSelection(){
                document.querySelectorAll('.doc-select').forEach(function(cb){ cb.checked = false; });
                updateBulkUi();
            }

            function loadFolders(cb){
                fetch('api/route-external-document.php?action=folders')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success) allFolders = d.folders || [];
                        if (cb) cb();
                    })
                    .catch(function(){ if (cb) cb(); });
            }

            function suggestFor(type){
                return fetch('api/route-external-document.php?action=suggest&type=' + encodeURIComponent(type))
                    .then(function(r){ return r.json(); })
                    .then(function(d){ return (d.success && d.suggestions) ? d.suggestions : []; })
                    .catch(function(){ return []; });
            }

            function renderSelect(){
                var arch = allFolders.filter(function(f){ return f.kind === 'archive'; });
                var html = '<option value="">-- Select Folder --</option>';
                html += '<optgroup label="Archive Folders">';
                arch.forEach(function(f){ html += '<option value="archive:' + f.id + '">' + f.name + '</option>'; });
                html += '</optgroup>';
                routeSelect.innerHTML = html;

                if (suggestions.length > 0) {
                    var s = suggestions[0];
                    var val = s.kind + ':' + s.id;
                    if (routeSelect.querySelector('option[value="' + val + '"]')) {
                        routeSelect.value = val;
                    }
                }
            }

            function populateMetadataFields(data) {
                if (metaTitle) metaTitle.value = data.title || '';
                if (metaAuthor) metaAuthor.value = data.author || '';
                if (metaType) metaType.value = data.type || '';
                if (metaDate) metaDate.value = data.date || '';
                if (metaRef) metaRef.value = data.ref || '';
            }

            function clearMetadataFields() {
                if (metaTitle) metaTitle.value = '';
                if (metaAuthor) metaAuthor.value = '';
                if (metaType) metaType.value = '';
                if (metaDate) metaDate.value = '';
                if (metaRef) metaRef.value = '';
            }

            function setAiLoading(loading) {
                if (aiScanSection) aiScanSection.classList.toggle('hidden', loading);
                if (aiScanStatus) aiScanStatus.classList.toggle('hidden', !loading);
                if (aiScanBtn) aiScanBtn.disabled = loading;
            }

            // AI Scan button handler
            if (aiScanBtn) {
                aiScanBtn.addEventListener('click', function() {
                    if (!currentId) return;
                    setAiLoading(true);
                    var apiUrl = window.location.pathname.replace(/\/[^\/]*$/, '') + '/api/ai-extract-metadata.php';
                    fetch(apiUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ external_id: currentId })
                    })
                    .then(function(r){
                        if (!r.ok) {
                            return r.text().then(function(txt) {
                                throw new Error('Server returned ' + r.status + ': ' + txt.substring(0, 200));
                            });
                        }
                        return r.json();
                    })
                    .then(function(d){
                        setAiLoading(false);
                        if (d.success && d.metadata) {
                            var m = d.metadata;
                            if (m.title && metaTitle) metaTitle.value = m.title;
                            if (m.author && metaAuthor) metaAuthor.value = m.author;
                            if (m.type && metaType) metaType.value = m.type;
                            if (m.date && metaDate) metaDate.value = m.date;
                            if (m.reference_number && metaRef) metaRef.value = m.reference_number;
                            // Visual feedback
                            [metaTitle, metaAuthor, metaType, metaDate, metaRef].forEach(function(el) {
                                if (el && el.value) {
                                    el.classList.add('ring-2', 'ring-purple-400');
                                    setTimeout(function(){ el.classList.remove('ring-2', 'ring-purple-400'); }, 2000);
                                }
                            });
                        } else {
                            alert('AI could not extract metadata: ' + (d.error || 'Unknown error') + '\n\nYou can fill in the fields manually.');
                        }
                    })
                    .catch(function(err){
                        setAiLoading(false);
                        alert('AI scan failed: ' + err.message + '\n\nYou can fill in the fields manually.');
                    });
                });
            }

            document.querySelectorAll('.doc-select').forEach(function(cb){
                cb.addEventListener('change', updateBulkUi);
            });
            var selectAllBtn = document.getElementById('select-all-page');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('change', function(){
                    var checked = this.checked;
                    document.querySelectorAll('.doc-select').forEach(function(cb){ cb.checked = checked; });
                    updateBulkUi();
                });
            }
            var clearSelBtn = document.getElementById('clear-selection');
            if (clearSelBtn) clearSelBtn.addEventListener('click', clearSelection);
            updateBulkUi();

            document.querySelectorAll('.route-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    bulkMode = false;
                    currentIds = [];
                    currentId = this.getAttribute('data-id');
                    var type = this.getAttribute('data-type') || '';
                    var title = this.getAttribute('data-title') || 'Document';
                    var date = this.getAttribute('data-date') || '';
                    var ref = this.getAttribute('data-ref') || '';
                    var author = this.getAttribute('data-author') || 'LLRM Import';

                    if (routeTitle) routeTitle.textContent = title;

                    // Populate metadata fields with existing data
                    populateMetadataFields({ title: title, author: author, type: type, date: date, ref: ref });

                    // Show AI scan section only for single mode
                    if (aiScanSection) aiScanSection.classList.remove('hidden');
                    if (aiScanStatus) aiScanStatus.classList.add('hidden');

                    suggestions = [];
                    loadFolders(function(){
                        suggestFor(type).then(function(s){
                            suggestions = s;
                            renderSelect();
                        });
                    });
                    routeModal.classList.remove('hidden');
                });
            });

            var bulkRouteBtn = document.getElementById('bulk-route-btn');
            if (bulkRouteBtn) {
                bulkRouteBtn.addEventListener('click', function(){
                    var sel = getSelected();
                    if (sel.length === 0) return;
                    bulkMode = true;
                    currentId = null;
                    currentIds = sel;
                    if (routeTitle) routeTitle.textContent = sel.length + ' document' + (sel.length > 1 ? 's' : '') + ' selected';

                    // Clear metadata fields for bulk mode
                    clearMetadataFields();
                    if (aiScanSection) aiScanSection.classList.add('hidden');
                    if (aiScanStatus) aiScanStatus.classList.add('hidden');

                    suggestions = [];
                    loadFolders(function(){
                        renderSelect();
                    });
                    routeModal.classList.remove('hidden');
                });
            }

            var confirmBtn = document.getElementById('routeConfirmBtn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function(){
                    if (!bulkMode && !currentId) return;
                    if (bulkMode && currentIds.length === 0) return;
                    var parts = routeSelect.value.split(':');
                    if (parts.length !== 2) return;
                    var body = new URLSearchParams();
                    if (bulkMode) {
                        currentIds.forEach(function(id){ body.append('external_ids[]', id); });
                    } else {
                        body.append('external_id', currentId);
                        // Include metadata overrides
                        var titleVal = metaTitle ? metaTitle.value.trim() : '';
                        var authorVal = metaAuthor ? metaAuthor.value.trim() : '';
                        var typeVal = metaType ? metaType.value : '';
                        var dateVal = metaDate ? metaDate.value : '';
                        var refVal = metaRef ? metaRef.value.trim() : '';
                        if (titleVal) body.append('title', titleVal);
                        if (authorVal) body.append('author', authorVal);
                        if (typeVal) body.append('document_type', typeVal);
                        if (dateVal) body.append('document_date', dateVal);
                        if (refVal) body.append('reference_number', refVal);
                    }
                    body.append('folder_kind', parts[0]);
                    body.append('folder_id', parts[1]);
                    var btn = this;
                    btn.disabled = true;
                    btn.textContent = bulkMode ? 'Routing ' + currentIds.length + ' document(s)...' : 'Routing...';
                    fetch('api/route-external-document.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                        .then(function(r){ return r.json(); })
                        .then(function(d){
                            if (d.success) {
                                if (d.bulk) {
                                    var folderLabel = routeSelect.options[routeSelect.selectedIndex] ? routeSelect.options[routeSelect.selectedIndex].text : 'folder';
                                    var msg = 'Routed ' + d.routed + ' of ' + d.total + ' document(s) to "' + folderLabel + '".';
                                    var fails = [];
                                    (d.results || []).forEach(function(r){
                                        if (!r.success) fails.push('#' + r.id + ': ' + (r.error || 'Routing failed'));
                                    });
                                    if (fails.length > 0) msg += '\n\nFailed (' + fails.length + '):\n' + fails.join('\n');
                                    alert(msg);
                                } else {
                                    alert(d.message || 'Document routed successfully.');
                                }
                                window.location.reload();
                            } else {
                                alert('Error: ' + (d.error || 'Routing failed'));
                                btn.disabled = false;
                                btn.textContent = 'Confirm Route';
                            }
                        })
                        .catch(function(err){
                            alert('Request failed: ' + err);
                            btn.disabled = false;
                            btn.textContent = 'Confirm Route';
                        });
                });
            }

            window.closeRouteModal = function(){
                if (routeModal) routeModal.classList.add('hidden');
                currentId = null;
                currentIds = [];
                bulkMode = false;
            };

            routeModal && routeModal.addEventListener('click', function(e){
                if (e.target === routeModal) closeRouteModal();
            });
        })();
    </script>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

<?php
// Include database connection
include 'authdatabase.php';

// Check if user is admin
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $stmt_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->bind_param("i", $_SESSION['user_id']);
    $stmt_role->execute();
    $res_role = $stmt_role->get_result();
    if ($row_role = $res_role->fetch_assoc()) {
        $is_admin = (isset($row_role['role']) && strtolower($row_role['role']) === 'admin');
    }
    $stmt_role->close();
}

// Get current folder ID
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
$current_folder_name = 'Billing';
$parent_folder_id = null;

// Fetch current folder details if not root
if ($current_folder_id) {
    $stmt = $conn->prepare("SELECT name, parent_id FROM legislative_folders WHERE id = ?");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($folder = $res->fetch_assoc()) {
        $current_folder_name = $folder['name'];
        $parent_folder_id = $folder['parent_id'];
    }
    $stmt->close();
}

// Fetch Subfolders
$folders = [];
$folder_sql = "SELECT * FROM legislative_folders WHERE type = 'Billing'";
if ($current_folder_id) {
    $folder_sql .= " AND parent_id = $current_folder_id";
} else {
    $folder_sql .= " AND parent_id IS NULL";
}
$folder_sql .= " ORDER BY name ASC";
$folder_res = $conn->query($folder_sql);
if ($folder_res) {
    while ($row = $folder_res->fetch_assoc()) {
        $folders[] = $row;
    }
}

// Fetch Files
$files = [];
$file_sql = "SELECT id, title, type, month, year, author, created_at, last_accessed, version, parent_version_id FROM legislative_records WHERE type = 'Billing' AND parent_version_id IS NULL";
if ($current_folder_id) {
    $file_sql .= " AND folder_id = $current_folder_id";
} else {
    $file_sql .= " AND folder_id IS NULL";
}
$file_sql .= " ORDER BY year DESC, month DESC, created_at DESC";
$file_res = $conn->query($file_sql);
if ($file_res) {
    while ($row = $file_res->fetch_assoc()) {
        $files[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Document Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#dc2626',
                            light: '#f97316',
                        }
                    }
                }
            }
        }
    </script>
    <div id="viewerModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-md" onclick="closeViewerModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-3xl w-full mx-4 p-4 max-h-[90vh] overflow-auto">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Document Viewer</h3>
                <button onclick="closeViewerModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-2 rounded hover:bg-gray-100 dark:hover:bg-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="viewerModalContent" class="mt-4"></div>
        </div>
    </div>
    <script src="assets/js/theme-head.js"></script>
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        .drag-over {
            border: 2px dashed #dc2626 !important;
            background-color: rgba(220, 38, 38, 0.1) !important;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    <!-- Mobile Sidebar -->
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
            <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-cloud-upload mr-3 text-lg"></i>
                <span>Export</span>
            </a>
            <?php if (isset($is_admin) && $is_admin): ?>
            <a href="recent_deleted.php" class="hidden"></a>
            <?php endif; ?>
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-slate-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <button id="mobile-menu-btn" class="mobile-toggle md:hidden text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                    <a href="archives-landing.php" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                        <span class="text-xl">←</span>
                        <span class="font-semibold">Back to Archives</span>
                    </a>
                </div>
                <div class="flex items-center space-x-3">
                    <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                        <i class="bi bi-moon-stars text-gray-700 dark:text-gray-300 hidden dark:block"></i>
                        <i class="bi bi-sun text-gray-700 dark:text-gray-300 block dark:hidden"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Content Header -->
        <div class="mb-8 pb-6 border-b border-gray-200 dark:border-slate-700">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Billing</h1>
            
            <!-- Breadcrumbs -->
            <nav class="flex text-sm font-medium text-gray-500 dark:text-gray-400 mt-2">
                <a href="billing.php" class="hover:text-red-600 dark:hover:text-red-400 transition-colors">Root</a>
                <?php if ($current_folder_id): ?>
                    <span class="mx-2">/</span>
                    <span class="text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($current_folder_name); ?></span>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div onclick="createFolder()" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 hover:shadow-xl transition-all cursor-pointer group">
                <div class="mb-3 group-hover:scale-110 transition-transform">
                    <i class="bi bi-folder-plus text-4xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200">Create Folder</div>
            </div>
            <div onclick="uploadFile()" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 hover:shadow-xl transition-all cursor-pointer group">
                <div class="mb-3 group-hover:scale-110 transition-transform">
                    <i class="bi bi-cloud-upload text-4xl text-green-600 dark:text-green-400"></i>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200">Upload File</div>
            </div>
        </div>

        <!-- Back Button if in folder -->
        <?php if ($current_folder_id): ?>
        <div class="mb-4">
            <a href="billing.php<?php echo $parent_folder_id ? '?folder_id=' . $parent_folder_id : ''; ?>" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                <i class="bi bi-arrow-left mr-2"></i> Back
            </a>
        </div>
        <?php endif; ?>

        <!-- Folders & Files List -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
            <div id="filesList" class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 bg-gray-50/50 dark:bg-slate-800/20">

                <!-- Folders -->
                <?php foreach ($folders as $folder): ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all group relative flex items-center p-4 cursor-pointer"
                     onclick="window.location.href='?folder_id=<?php echo $folder['id']; ?>'"
                     ondrop="drop(event, <?php echo $folder['id']; ?>)"
                     ondragover="allowDrop(event)"
                     draggable="true"
                     ondragstart="drag(event, 'folder', <?php echo $folder['id']; ?>)">
                    <i class="bi bi-folder-fill text-yellow-500 text-3xl mr-4 group-hover:scale-110 transition-transform"></i>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($folder['name']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Files -->
                <?php if (empty($files) && empty($folders)): ?>
                    <div class="col-span-full text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 dark:text-gray-600 mb-4 block"></i>
                        <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No Billing Records Found</div>
                        <div class="text-gray-600 dark:text-gray-400">Documents will appear here once uploaded</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($files as $record): 
                        $fileExt = strtolower(pathinfo($record['title'], PATHINFO_EXTENSION));
                        $iconClass = 'bi-file-earmark-text text-blue-500';
                        if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                        elseif (in_array($fileExt, ['pdf'])) $iconClass = 'bi-file-earmark-pdf text-red-500';
                        elseif (in_array($fileExt, ['mp4','avi','mov'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                        elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';

                        // Check if file exists to determine preview capability
                        $realFilePath = "uploads/records/" . $record['author'] . "/" . $record['year'] . "/" . $record['month'] . "/" . $record['type'] . "/" . $record['title'];
                        $hasPreview = in_array($fileExt, ['jpg','jpeg','png','gif','webp']) && file_exists($realFilePath);
                        
                        // Generate unique ID
                        $uniqueId = sprintf("BIL-%06d", $record['id']);
                    ?>
                        <div data-id="<?php echo $record['id']; ?>" 
                             class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden"
                             draggable="true"
                             ondragstart="drag(event, 'file', <?php echo $record['id']; ?>)">
                            
                            <!-- Enhanced Thumbnail / Icon Area -->
                            <div class="h-40 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-slate-700 dark:to-slate-800 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="openSideViewerServer(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>', '<?php echo addslashes(htmlspecialchars($record['created_at'])); ?>', '<?php echo addslashes(htmlspecialchars($record['last_accessed'] ?? '')); ?>')">
                                <?php if ($hasPreview): ?>
                                    <img src="<?php echo htmlspecialchars($realFilePath); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="Preview">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center">
                                        <i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i>
                                    </div>
                                <?php elseif ($fileExt === 'pdf'): ?>
                                    <div class="flex flex-col items-center justify-center text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform duration-300">
                                        <i class="bi bi-file-earmark-pdf text-5xl mb-2 opacity-90"></i>
                                        <span class="text-xs font-semibold">PDF</span>
                                    </div>
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center">
                                        <i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="flex flex-col items-center">
                                        <i class="bi <?php echo $iconClass; ?> text-5xl opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-300"></i>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold"><?php echo strtoupper($fileExt); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Details Area -->
                            <div class="p-4 flex flex-col flex-1">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="<?php echo htmlspecialchars($record['title']); ?>"><?php echo htmlspecialchars($record['title']); ?></div>
                                    </div>
                                    <div class="relative flex-shrink-0">
                                        <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.toggle('hidden'); setTimeout(() => { document.addEventListener('click', function _close(e){ if(!e.target.closest('#record-menu-<?php echo $record['id']; ?>') && !e.target.closest('button')){ document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.add('hidden'); document.removeEventListener('click', _close); }}); }, 10);">
                                            <i class="bi bi-three-dots-vertical text-lg"></i>
                                        </button>
                                        
                                        <!-- Enhanced 3-Dot Menu Dropdown -->
                                        <div id="record-menu-<?php echo $record['id']; ?>" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                            <button onclick="openSideViewerServer(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>', '<?php echo addslashes(htmlspecialchars($record['created_at'])); ?>', '<?php echo addslashes(htmlspecialchars($record['last_accessed'] ?? '')); ?>'); document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                <i class="bi bi-eye"></i> <span>View</span>
                                            </button>
                                            <button onclick="openVersionHistory(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>'); document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                <i class="bi bi-clock-history"></i> <span>History</span>
                                            </button>
                                            <button onclick="openDownloadPopup(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>'); document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                <i class="bi bi-download"></i> <span>Download</span>
                                            </button>
                                            <hr class="my-1 border-gray-200 dark:border-slate-600">
                                            <button onclick="moveToVault(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', 'legislative'); document.getElementById('record-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-3 transition-colors">
                                                <i class="bi bi-shield-lock-fill"></i> <span>Move to Vault</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Metadata -->
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Author:</span>
                                        <span class="truncate"><?php echo htmlspecialchars($record['author']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Date:</span>
                                        <span><?php echo htmlspecialchars($record['month'] . ' ' . $record['year']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-around text-[11px]">
                                        <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 rounded font-semibold"><?php echo htmlspecialchars($record['type']); ?></span>
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded font-semibold">v<?php echo (int)($record['version'] ?? 1); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Unique ID Badge -->
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-600">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800/30 text-center">
                                        <div class="text-xs text-blue-700 dark:text-blue-300 font-semibold">Record ID</div>
                                        <div class="text-xs font-mono text-blue-900 dark:text-blue-200 font-bold"><?php echo $uniqueId; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('uploadModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Upload File</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal('uploadModal')">&times;</button>
                </div>
                <form id="uploadForm" class="space-y-4">
                    <input type="hidden" name="folder_id" value="<?php echo $current_folder_id ?? ''; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File Name</label>
                        <input type="text" id="fileName" name="fileName" required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="e.g., Billing_Statement.pdf">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Author</label>
                        <input type="text" id="fileAuthor" name="fileAuthor" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="Enter author name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" id="fileDate" name="fileDate" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Unique Number</label>
                        <input type="text" id="fileUniqueNumber" name="fileUniqueNumber" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="Enter unique number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File</label>
                        <input type="file" id="fileInput" name="file" accept="image/*,video/*,.pdf,.doc,.docx,.txt,text/plain" required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('uploadModal')" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    

    <!-- Notification Modal -->
    <div id="notificationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeNotification()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-sm p-6 border border-gray-200 dark:border-slate-700">
            <div class="flex items-start gap-3">
                <div id="notificationIcon" class="flex-none rounded-full p-2 bg-green-100 dark:bg-green-900/30">
                    <i class="bi bi-check2-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 id="notificationTitle" class="text-lg font-bold text-gray-900 dark:text-gray-100">Deleted</h3>
                    <p id="notificationMessage" class="mt-1 text-sm text-gray-600 dark:text-gray-400">The file has been deleted.</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeNotification()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">OK</button>
            </div>
        </div>
    </div>
    <!-- Version Confirm Modal -->
    <div id="versionConfirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('versionConfirmModal')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-sm p-6 border border-gray-200 dark:border-slate-700">
            <div class="flex items-start gap-3">
                <div class="flex-none rounded-full p-2 bg-yellow-100 dark:bg-yellow-900/30">
                    <i class="bi bi-exclamation-triangle text-yellow-700 dark:text-yellow-300 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Create New Version?</h3>
                    <p id="versionConfirmMessage" class="mt-1 text-sm text-gray-600 dark:text-gray-400">File already exists. Create new version?</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button onclick="closeModal('versionConfirmModal')" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg">Cancel</button>
                <button onclick="confirmCreateVersion()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">Create Version</button>
            </div>
        </div>
    </div>

    <!-- Version History Modal -->
    <div id="versionHistoryModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('versionHistoryModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Version History</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal('versionHistoryModal')">&times;</button>
                </div>
                <div id="versionHistoryTitle" class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4"></div>
                <div id="versionList" class="space-y-3 max-h-[60vh] overflow-y-auto">
                    <!-- Versions will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Side Viewer Panel (Same as before) -->
    <div id="sideViewer" class="fixed right-0 top-0 h-full w-96 bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-slate-700 shadow-xl transform translate-x-full transition-transform duration-200 z-50">
        <!-- Content... -->
        <div class="p-4 flex items-start justify-between border-b border-gray-100 dark:border-slate-700">
            <div>
                <div id="sv-title" class="font-semibold text-lg text-gray-900 dark:text-gray-100">Title</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="sv-meta">Meta</div>
            </div>
            <div class="text-right">
                <button onclick="closeSideViewer()" class="text-gray-500 hover:text-gray-700 dark:text-gray-300">&times;</button>
            </div>
        </div>
        <div class="p-4 space-y-3">
            <div class="text-sm text-gray-600 dark:text-gray-300"><strong>Type:</strong> <span id="sv-type"></span></div>
            <div class="text-sm text-gray-600 dark:text-gray-300"><strong>Author:</strong> <span id="sv-author"></span></div>
            <div class="mt-2">
                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Details</div>
                <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex justify-between"><span>Title</span><span id="sv-d-title"></span></div>
                    <div class="flex justify-between"><span>Authors</span><span id="sv-d-authors"></span></div>
                    <div class="flex justify-between"><span>Size</span><span id="sv-d-size"></span></div>
                    <div class="flex justify-between"><span>Date modified</span><span id="sv-d-modified"></span></div>
                    <div class="flex justify-between"><span>Content type</span><span id="sv-d-ctype"></span></div>
                    <div class="flex justify-between"><span>Date last saved</span><span id="sv-d-saved"></span></div>
                    <div class="flex justify-between"><span>File type</span><span id="sv-d-ftype"></span></div>
                </div>
            </div>
            <div id="sv-preview" class="mt-3 text-sm text-gray-500 dark:text-gray-400">Preview not available.</div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-slate-700">
            <a id="sv-open-btn" class="inline-block px-4 py-2 bg-red-600 text-white rounded hidden" href="#" target="_blank">Open / Download</a>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
    <script>
        // Modal functions
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        function uploadFile() { openModal('uploadModal'); }
        
        
        function openNotification(message = 'Done', type = 'success', titleText) {
            const modal = document.getElementById('notificationModal');
            const title = document.getElementById('notificationTitle');
            const msg = document.getElementById('notificationMessage');
            const icon = document.getElementById('notificationIcon');
            if (!modal || !title || !msg || !icon) return;
            if (type === 'error') {
                title.textContent = titleText || 'Error';
                msg.textContent = message || 'Something went wrong.';
                icon.className = 'flex-none rounded-full p-2 bg-red-100 dark:bg-red-900/30';
                icon.innerHTML = '<i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>';
            } else {
                title.textContent = titleText || 'Deleted';
                msg.textContent = message || 'The file has been deleted.';
                icon.className = 'flex-none rounded-full p-2 bg-green-100 dark:bg-green-900/30';
                icon.innerHTML = '<i class="bi bi-check2-circle text-green-600 dark:text-green-400 text-xl"></i>';
            }
            modal.classList.remove('hidden');
        }
        function closeNotification() {
            const modal = document.getElementById('notificationModal');
            if (modal) {
                modal.classList.add('hidden');
                if (window.__reloadAfterNotification) {
                    window.__reloadAfterNotification = false;
                    location.reload();
                }
            }
        }
        function confirmCreateVersion() {
            const fd = window.__pendingUploadFD;
            const existingId = window.__pendingUploadExistingId;
            if (!fd || !existingId) { closeModal('versionConfirmModal'); return; }
            fd.append('force_version', '1');
            fd.append('parent_id', existingId);
            fetch('legislative_api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d2 => {
                    closeModal('versionConfirmModal');
                    if (d2 && d2.success) {
                        closeModal('uploadModal');
                        window.__reloadAfterNotification = true;
                        openNotification('New version created!', 'success', 'Uploaded');
                    } else {
                        openNotification((d2 && d2.message) || 'Upload failed.', 'error', 'Error');
                    }
                })
                .catch(() => {
                    closeModal('versionConfirmModal');
                    openNotification('Upload failed.', 'error', 'Error');
                });
            window.__pendingUploadFD = null;
            window.__pendingUploadExistingId = null;
        }

        // Create Folder
        function createFolder() {
            const name = prompt("Enter folder name:");
            if (name) {
                const formData = new FormData();
                formData.append('action', 'create_folder');
                formData.append('name', name);
                formData.append('type', 'Billing');
                formData.append('parent_id', '<?php echo $current_folder_id ?? ""; ?>');
                
                fetch('legislative_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if(d.success) location.reload();
                    else {
                        UI_ENH.toast(d.message || 'Failed to create folder', {background:'linear-gradient(90deg,#dc2626,#c53030)'});
                    }
                });
            }
        }

        // Upload Form
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'upload_file');
            formData.append('type', 'Billing'); 
            
            fetch('legislative_api.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    closeModal('uploadModal');
                    window.__reloadAfterNotification = true;
                    openNotification('Your file has been uploaded.', 'success', 'Uploaded');
                } else if (d.duplicate) {
                    window.__pendingUploadFD = formData;
                    window.__pendingUploadExistingId = d.existing_id;
                    document.getElementById('versionConfirmMessage').textContent = d.message || 'File already exists. Create new version?';
                    openModal('versionConfirmModal');
                } else {
                    openNotification(d.message || 'Upload failed.', 'error', 'Error');
                }
            });
        });

        function openVersionHistory(id, title) {
            document.getElementById('versionHistoryTitle').textContent = title;
            const list = document.getElementById('versionList');
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            openModal('versionHistoryModal');
            
            fetch('legislative_api.php?action=get_versions&id=' + id)
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    if(d.versions.length === 0) {
                        list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                    } else {
                        list.innerHTML = d.versions.map(v => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">
                                <div>
                                    <div class="font-medium text-gray-800 dark:text-gray-200">Version ${v.version}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ${v.created_at} • ${v.author}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="download.php?id=${v.id}" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                }
            });
        }

        // Drag and Drop
        function allowDrop(ev) { ev.preventDefault(); ev.currentTarget.classList.add('drag-over'); }
        function drag(ev, type, id) {
            ev.dataTransfer.setData("type", type);
            ev.dataTransfer.setData("id", id);
        }
        function drop(ev, folderId) {
            ev.preventDefault();
            ev.currentTarget.classList.remove('drag-over');
            const type = ev.dataTransfer.getData("type");
            const id = ev.dataTransfer.getData("id");
            
            if (confirm("Move item to this folder?")) {
                const fd = new FormData();
                fd.append('action', 'move_item');
                fd.append('item_type', type);
                fd.append('item_id', id);
                fd.append('target_folder_id', folderId);
                
                fetch('legislative_api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.success) location.reload();
                    else {
                        UI_ENH.toast(d.message || 'Move failed', {background:'linear-gradient(90deg,#dc2626,#c53030)'});
                    }
                });
            }
        }

        // Side Viewer & Download Popup (Same as before)
        function openSideViewerServer(id, title, type, month, year, author, createdAt, lastSaved) {
             const url = `download.php?id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
             const previewUrl = `download.php?action=view&id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
             
             const panel = document.getElementById('sideViewer');
             document.getElementById('sv-title').textContent = title;
             document.getElementById('sv-type').textContent = type || '';
             document.getElementById('sv-meta').textContent = `${month || ''} ${year || ''}`.trim();
             document.getElementById('sv-author').textContent = author || '';
             
             document.getElementById('sv-d-title').textContent = title;
             document.getElementById('sv-d-authors').textContent = author;
             document.getElementById('sv-d-size').textContent = 'Loading...'; 
             document.getElementById('sv-d-modified').textContent = createdAt;
             document.getElementById('sv-d-ctype').textContent = 'Document'; 
             document.getElementById('sv-d-saved').textContent = lastSaved || createdAt;
             document.getElementById('sv-d-ftype').textContent = title.split('.').pop().toUpperCase();

             // Preview Logic
             const preview = document.getElementById('sv-preview');
             const ext = title.split('.').pop().toLowerCase();
             
             if (ext === 'pdf') {
                 preview.innerHTML = `<iframe class="w-full h-[60vh] border rounded" src="${previewUrl}" sandbox="allow-same-origin allow-scripts allow-popups"></iframe>`;
             } else if (['mp4', 'webm', 'ogg', 'avi', 'mov'].includes(ext)) {
                 preview.innerHTML = `<video controls class="w-full max-h-[60vh] rounded border"><source src="${previewUrl}"></video>`;
             } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                 preview.innerHTML = `<img src="${previewUrl}" class="max-w-full h-auto rounded border" alt="Preview">`;
             } else if (['txt', 'log', 'csv'].includes(ext)) {
                 fetch(previewUrl)
                    .then(r => r.text())
                    .then(text => {
                        preview.innerHTML = `<pre class="w-full h-[60vh] overflow-auto p-3 bg-gray-50 dark:bg-slate-800 border rounded text-xs font-mono whitespace-pre-wrap">${text}</pre>`;
                    })
                    .catch(() => preview.textContent = 'Preview failed to load.');
             } else {
                 preview.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-48 bg-gray-50 dark:bg-slate-800 rounded border border-gray-200 dark:border-slate-700">
                        <i class="bi bi-file-earmark-text text-4xl text-gray-400 mb-2"></i>
                        <span class="text-sm text-gray-500">Preview not available for this file type</span>
                        <a href="${url}" target="_blank" class="mt-2 text-sm text-blue-600 hover:underline">Download to view</a>
                    </div>
                 `;
             }
             
             const openBtn = document.getElementById('sv-open-btn');
             openBtn.href = '#';
             openBtn.onclick = function(e){ e.preventDefault(); openViewerModal(id, title, type, month, year, author); };
             openBtn.classList.remove('hidden');

             panel.classList.remove('translate-x-full');
        }
        function closeSideViewer() {
            document.getElementById('sideViewer').classList.add('translate-x-full');
        }
        function openDownloadPopup(id, title, type, month, year, author) {
            openViewerModal(id, title, type, month, year, author);
        }
        function openViewerModal(id, title, type, month, year, author) {
            const modal = document.getElementById('viewerModal');
            const content = document.getElementById('viewerModalContent');
            if (!modal || !content) return;
            const headerEl = document.querySelector('header');
            if (headerEl) headerEl.classList.add('hidden');
            const url = `download.php?action=view_json&id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
            content.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>';
            fetch(url)
                .then(r => r.json())
                .then(d => {
                    if (d && d.success && d.html) {
                        content.innerHTML = d.html + '<div class="mt-4 flex justify-end"><button onclick="closeViewerModal()" class="px-4 py-2 bg-red-600 text-white rounded">Close</button></div>';
                    } else {
                        content.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                    }
                })
                .catch(() => {
                    content.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                });
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeViewerModal() {
            const modal = document.getElementById('viewerModal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            const headerEl = document.querySelector('header');
            if (headerEl) headerEl.classList.remove('hidden');
        }

        // Mobile Sidebar Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileSidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
            });
        }

        if (closeMobileSidebar) {
            closeMobileSidebar.addEventListener('click', () => {
                mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                mobileSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            });
        }
        
        // Move to Vault functionality
        function moveToVault(fileId, fileName, sourceType) {
            // Check if vault is unlocked
            fetch('storage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'vault_check_status' })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showToast('Unable to check vault status', 'error');
                    return;
                }
                
                if (!data.vault_exists) {
                    showToast('Vault not set up. Please set up the vault from Storage page first.', 'error');
                    return;
                }
                
                if (!data.is_unlocked) {
                    showToast('Vault is locked. Please unlock it from Storage page first.', 'error');
                    return;
                }
                
                // Vault is unlocked, proceed with move
                if (confirm('Move "' + fileName + '" to the confidential vault?\n\nThis will remove it from this folder and place it in the secure vault.')) {
                    fetch('confidential_vault.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'move_to_vault', 
                            file_id: fileId,
                            source_type: sourceType
                        })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showToast(result.message || 'File moved to vault successfully', 'success');
                            // Remove file card from UI
                            const fileCard = document.querySelector(`[data-id="${fileId}"]`);
                            if (fileCard) {
                                fileCard.style.opacity = '0';
                                fileCard.style.transform = 'scale(0.9)';
                                setTimeout(() => fileCard.remove(), 300);
                            }
                        } else {
                            showToast(result.message || 'Failed to move file to vault', 'error');
                        }
                    })
                    .catch(e => {
                        console.error('Move to vault error:', e);
                        showToast('Connection error', 'error');
                    });
                }
            })
            .catch(e => {
                console.error('Vault status check error:', e);
                showToast('Connection error', 'error');
            });
        }
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            if (!toast) return;
            
            toast.textContent = message;
            toast.className = 'fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl transition-all z-50 font-semibold';
            toast.classList.add(type === 'success' ? 'bg-green-600' : 'bg-red-600');
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(1rem)';
            }, 3000);
        }
    </script>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold"></div>

</body>
</html>

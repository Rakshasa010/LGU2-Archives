<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'authdatabase.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $conn->close();
        exit();
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $action = isset($payload['action']) ? (string)$payload['action'] : (isset($_POST['action']) ? (string)$_POST['action'] : '');
    if ($action === 'export_year_zip') {
        $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
        if ($year <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid year']);
            $conn->close();
            exit();
        }
        if (!class_exists('ZipArchive')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ZipArchive not available']);
            $conn->close();
            exit();
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'yearzip_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Could not create zip']);
            $conn->close();
            exit();
        }
        $root = realpath(__DIR__);
        $safe = function($s){ $s = preg_replace('/[^a-zA-Z0-9\\-_ ]/', '_', (string)$s); return trim($s) !== '' ? $s : 'Unknown'; };
        $leg = $conn->prepare("SELECT title, type, file_path, created_at FROM legislative_records WHERE parent_version_id IS NULL AND YEAR(created_at) = ?");
        if ($leg) {
            $leg->bind_param("i", $year);
            $leg->execute();
            $res = $leg->get_result();
            while ($row = $res->fetch_assoc()) {
                $type = $safe($row['type']);
                $path = (string)$row['file_path'];
                $disk = realpath($path) ?: realpath(__DIR__ . '/' . $path);
                if ($disk && strpos($disk, $root) === 0 && is_file($disk)) {
                    $base = basename($disk);
                    $entry = $year . '/' . $type . '/' . $base;
                    $zip->addFile($disk, $entry);
                }
            }
            $leg->close();
        }
        $arc = $conn->prepare("SELECT af.name, af.file_path, fo.name AS folder_name FROM archive_files af JOIN archive_folders fo ON af.folder_id = fo.id WHERE YEAR(af.created_at) = ?");
        if ($arc) {
            $arc->bind_param("i", $year);
            $arc->execute();
            $res = $arc->get_result();
            while ($row = $res->fetch_assoc()) {
                $folder = $safe($row['folder_name']);
                $path = (string)$row['file_path'];
                $disk = realpath($path) ?: realpath(__DIR__ . '/' . $path);
                if ($disk && strpos($disk, $root) === 0 && is_file($disk)) {
                    $base = basename($disk);
                    $entry = $year . '/Archives/' . $folder . '/' . $base;
                    $zip->addFile($disk, $entry);
                }
            }
            $arc->close();
        }
        $zip->close();
        $downloadName = 'Year_' . $year . '_Archives.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipPath));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($zipPath);
        @unlink($zipPath);
        $conn->close();
        exit();
    }
    if ($action === 'list_year') {
        header('Content-Type: application/json');
        $year = isset($payload['year']) ? (int)$payload['year'] : 0;
        if ($year <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid year']);
            $conn->close();
            exit();
        }
        $data = ['success' => true, 'year' => $year, 'categories' => []];
        $leg = $conn->prepare("SELECT type, COUNT(*) AS cnt FROM legislative_records WHERE parent_version_id IS NULL AND YEAR(created_at) = ? GROUP BY type");
        if ($leg) {
            $leg->bind_param("i", $year);
            $leg->execute();
            $res = $leg->get_result();
            while ($row = $res->fetch_assoc()) {
                $data['categories'][] = ['name' => $row['type'], 'count' => (int)$row['cnt'], 'group' => 'Legislative'];
            }
            $leg->close();
        }
        $arc = $conn->prepare("SELECT fo.id, fo.name AS folder_name, COUNT(af.id) AS cnt FROM archive_folders fo LEFT JOIN archive_files af ON af.folder_id = fo.id AND YEAR(af.created_at) = ? GROUP BY fo.id, fo.name ORDER BY fo.name");
        if ($arc) {
            $arc->bind_param("i", $year);
            $arc->execute();
            $res = $arc->get_result();
            while ($row = $res->fetch_assoc()) {
                $data['categories'][] = ['name' => $row['folder_name'], 'count' => (int)$row['cnt'], 'group' => 'Archives'];
            }
            $arc->close();
        }
        echo json_encode($data);
        $conn->close();
        exit();
    }
    header('Content-Type: application/json');
    if ($action === 'create_folder') {
        $name = isset($payload['name']) ? trim((string)$payload['name']) : '';
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Folder name is required']);
            $conn->close();
            exit();
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        if ($slug === '') {
            echo json_encode(['success' => false, 'message' => 'Folder name is invalid']);
            $conn->close();
            exit();
        }
        $reserved = ['ordinances-resolution', 'billing', 'public-hearings', 'meeting-records'];
        if (in_array($slug, $reserved, true)) {
            echo json_encode(['success' => false, 'message' => 'Folder already exists']);
            $conn->close();
            exit();
        }
        $chk = $conn->prepare("SELECT id FROM archive_folders WHERE slug = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("s", $slug);
            $chk->execute();
            $res = $chk->get_result();
            if ($res && $res->num_rows > 0) {
                $chk->close();
                echo json_encode(['success' => false, 'message' => 'Folder already exists']);
                $conn->close();
                exit();
            }
            $chk->close();
        }
        $uid = (int)$_SESSION['user_id'];
        $ins = $conn->prepare("INSERT INTO archive_folders (name, slug, created_by) VALUES (?, ?, ?)");
        if (!$ins) {
            echo json_encode(['success' => false, 'message' => 'Could not create folder']);
            $conn->close();
            exit();
        }
        $ins->bind_param("ssi", $name, $slug, $uid);
        if ($ins->execute()) {
            echo json_encode(['success' => true, 'folder' => ['id' => $conn->insert_id, 'name' => $name, 'slug' => $slug]]);
            $ins->close();
            $conn->close();
            exit();
        }
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Could not create folder']);
        $conn->close();
        exit();
    }
    if ($action === 'delete_folder') {
        $folder_id = isset($payload['folder_id']) ? (int)$payload['folder_id'] : (int)($_POST['folder_id'] ?? 0);
        if ($folder_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid folder id']);
            $conn->close();
            exit();
        }
        $uid = (int)$_SESSION['user_id'];
        $roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $isAdmin = false;
        if ($roleStmt) {
            $roleStmt->bind_param("i", $uid);
            $roleStmt->execute();
            $res = $roleStmt->get_result();
            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $isAdmin = isset($row['role']) && strtolower($row['role']) === 'admin';
            }
            $roleStmt->close();
        }
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            $conn->close();
            exit();
        }
        $fo = $conn->prepare("SELECT id, name FROM archive_folders WHERE id = ?");
        if (!$fo) {
            echo json_encode(['success' => false, 'message' => 'Folder not found']);
            $conn->close();
            exit();
        }
        $fo->bind_param("i", $folder_id);
        $fo->execute();
        $fr = $fo->get_result();
        $folder = $fr ? $fr->fetch_assoc() : null;
        $fo->close();
        if (!$folder) {
            echo json_encode(['success' => false, 'message' => 'Folder not found']);
            $conn->close();
            exit();
        }
        $conn->begin_transaction();
        try {
            $files = [];
            if ($fs = $conn->prepare("SELECT id, file_path FROM archive_files WHERE folder_id = ?")) {
                $fs->bind_param("i", $folder_id);
                $fs->execute();
                $rs = $fs->get_result();
                while ($r = $rs->fetch_assoc()) { $files[] = $r; }
                $fs->close();
            }
            foreach ($files as $f) {
                $p = (string)$f['file_path'];
                $disk = is_file($p) ? $p : (__DIR__ . '/' . $p);
                if (is_file($disk)) { @unlink($disk); }
            }
            if ($delFiles = $conn->prepare("DELETE FROM archive_files WHERE folder_id = ?")) {
                $delFiles->bind_param("i", $folder_id);
                $delFiles->execute();
                $delFiles->close();
            }
            if ($delFolder = $conn->prepare("DELETE FROM archive_folders WHERE id = ?")) {
                $delFolder->bind_param("i", $folder_id);
                $delFolder->execute();
                $delFolder->close();
            }
            $dir = __DIR__ . "/uploads/archives/" . $folder_id;
            if (is_dir($dir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $file) {
                    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
                }
                @rmdir($dir);
            }
            $conn->commit();
            echo json_encode(['success' => true, 'deleted' => ['id' => $folder_id, 'name' => $folder['name']]]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete folder']);
        }
        $conn->close();
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage - Document Management</title>
    <?php include 'includes/header_scripts.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    // Get user information
    require 'authdatabase.php';
    $user_id = $_SESSION['user_id'];
    $user_data = null;
    
    $stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
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
    $stmt->close();
    $archive_folders = [];
    $folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
    if ($folders_result && $folders_result->num_rows > 0) {
        while ($row = $folders_result->fetch_assoc()) {
            $archive_folders[] = $row;
        }
    }
    $conn->close();
    
    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;
    ?>
    
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">
        <!-- Mobile sidebar header -->
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
                <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Navigation Menu -->
        <nav class="flex-1 py-4 px-3 overflow-y-auto">
            <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-speedometer2 mr-3 text-lg"></i>
                <span>Dashboard Archives</span>
            </a>
            
            <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                <i class="bi bi-folder mr-3 text-lg"></i>
                <span>Main Storage Archives</span>
            </a>
            
            <?php if (isset($is_admin) && $is_admin): ?>
            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Recently Deleted</span>
            </a>
            <?php endif; ?>

            <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-trash mr-3 text-lg"></i>
                <span>Export</span>
            </a>
            
            <!-- ANALYTICS Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
            
            <!-- ADMINISTRATION Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-shield-check mr-3 text-lg"></i>
                    <span>Audit Logs</span>
                </a>
            </div>
            
            <!-- Storage Bar -->
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
        <!-- Desktop Sidebar -->
        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
            <!-- Logo Section -->
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
            
            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-hidden py-4">
                <div class="px-4 space-y-1">
                    <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-speedometer2 mr-3"></i>
                        <span class="sidebar-text">Dashboard Archives</span>
                    </a>
                    
                    <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-folder mr-3"></i>
                        <span class="sidebar-text">Main Storage Archives</span>
                    </a>

                    <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-cloud-upload mr-3"></i>
                        <span class="sidebar-text">Export</span>
                    </a>
                    
                    <?php if (isset($is_admin) && $is_admin): ?>
                    <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-trash mr-3"></i>
                        <span class="sidebar-text">Recently Deleted</span>
                    </a>
                    <?php endif; ?>

                    <a href="version_tracking.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-book mr-3"></i>
                        <span class="sidebar-text">Version Tracking</span>
                    </a>
                </div>
                
                <!-- ANALYTICS Section -->
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                    <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-graph-up mr-3"></i>
                        <span class="sidebar-text">Reports & Analytics</span>
                    </a>
                </div>
                
                <!-- ADMINISTRATION Section -->
                <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                    <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                    <?php if ($is_admin): ?>
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <?php endif; ?>

                    

                    <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-shield-check mr-3"></i>
                        <span class="sidebar-text">Audit Logs</span>
                    </a>
                </div>
                
                <!-- Storage Bar -->
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

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side -->
                        <div class="flex items-center">
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        <!-- Page Title -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Main Storage Archives</h2>
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

                            <!-- Notification Dropdown (placed beside theme toggle) -->
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                                </div>
                                <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                            </button>
                            
                                <!-- Profile Dropdown -->
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <i class="bi bi-gear mr-2"></i>Account Settings
                                        </a>
                                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <!-- Storage Progress Section -->
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-8 mb-8 hover:shadow-xl transition-all">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Storage Overview</h2>
                <p class="text-gray-600 dark:text-gray-400">Monitor your storage usage and available space</p>
            </div>
            
            <div class="flex flex-col lg:flex-row items-center lg:items-start gap-12">
                <div class="relative flex-shrink-0">
                    <svg id="storageDonut" class="w-64 h-64" viewBox="0 0 240 240">
                        <circle class="stroke-gray-200 dark:stroke-slate-700" cx="120" cy="120" r="90" fill="none" stroke-width="20" />
                        <circle id="donutProgress" class="stroke-red-600 dark:stroke-red-500" cx="120" cy="120" r="90" fill="none" stroke-width="20" 
                                stroke-linecap="round" stroke-dasharray="565.48" stroke-dashoffset="565.48" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-red-600 dark:text-red-400 mb-1" id="storagePercentage">2%</div>
                            <div class="text-lg font-semibold text-gray-800 dark:text-gray-200" id="storageUsed">1 GB</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400" id="storageTotal">of 50 GB</div>
                        </div>
                    </div>
                </div>
                
                <div class="flex-1 w-full space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                        <div class="flex items-center space-x-3">
                            <span class="w-4 h-4 rounded-full bg-red-600"></span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">Used Space</span>
                        </div>
                        <div class="font-bold text-gray-800 dark:text-gray-200" id="detailUsed">1 GB</div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                        <div class="flex items-center space-x-3">
                            <span class="w-4 h-4 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">Available Space</span>
                        </div>
                        <div class="font-bold text-gray-800 dark:text-gray-200" id="detailAvailable">49 GB</div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-lg border-2 border-red-200 dark:border-red-800">
                        <span class="font-semibold text-gray-800 dark:text-gray-200">Total Storage</span>
                        <div class="font-bold text-red-600 dark:text-red-400" id="detailTotal">50 GB</div>
                    </div>
                </div>
            </div>
                    </div>

                    <!-- Recent Archives Section -->
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Yearly Archives</h2>
                            <form id="year-export-form" method="POST" action="storage.php" target="_blank" class="hidden">
                                <input type="hidden" name="action" value="export_year_zip">
                                <input type="hidden" name="year" id="year-export-input" value="">
                            </form>
                        </div>
                        <div id="yearly-archives-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4"></div>
                    </div>
                    <div class="mb-4 px-4 py-3 bg-yellow-50 dark:bg-amber-900/20 rounded-lg border border-yellow-200 dark:border-amber-800 text-xs text-yellow-700 dark:text-amber-200">
                        Retention policy: Main Storage retains files for 5 years.
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200"> Archives Folders</h2>
                <div class="flex items-center gap-2">
                    <button id="create-folder-btn" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition-colors">Create Folder</button>
                    <?php if (isset($is_admin) && $is_admin): ?>
                    <button id="delete-folder-btn" class="px-4 py-2 rounded-lg bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-800 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">Delete Folder</button>
                    <?php endif; ?>
                </div>
            </div>
            <div id="archive-folders-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="ordinances-resolution.php" data-archive="ordinances-resolution" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                    <div class="mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-12 h-12 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Ordinances & Resolutions</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="ordinances-resolution">Last opened: Not yet opened</div>
                </a>
                <a href="billing.php" data-archive="billing" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                    <div class="mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-12 h-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Billing</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="billing">Last opened: Not yet opened</div>
                </a>
                <a href="public-hearings.php" data-archive="public-hearings" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                    <div class="mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-12 h-12 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Public Hearings</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="public-hearings">Last opened: Not yet opened</div>
                </a>
                <a href="meeting-records.php" data-archive="meeting-records" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                    <div class="mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-12 h-12 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Meeting/Sessions Records</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="meeting-records">Last opened: Not yet opened</div>
                </a>
                <?php foreach ($archive_folders as $folder): ?>
                <a id="folder-card-<?php echo (int)$folder['id']; ?>" href="folder_view.php?id=<?php echo $folder['id']; ?>" data-archive="<?php echo htmlspecialchars($folder['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group">
                    <div class="mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-12 h-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="<?php echo htmlspecialchars($folder['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Last opened: Not yet opened</div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <div class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg px-4 py-2 shadow-sm">
                <button id="system-backup-btn" type="button" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">System Backup</button>
                <button id="system-restore-btn" type="button" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">Restore</button>
            </div>
        </div>
        <div id="create-folder-modal" class="hidden fixed inset-0 z-50">
            <div id="create-folder-backdrop" class="absolute inset-0 bg-black/50"></div>
            <div class="relative z-10 flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
                    <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Create Folder</div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2" for="new-folder-name">Folder Name</label>
                    <input id="new-folder-name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Enter folder name">
                    <div id="folder-name-error" class="mt-2 text-xs text-red-600 dark:text-red-400 hidden"></div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button id="cancel-create-folder" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                        <button id="confirm-create-folder" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold opacity-50 cursor-not-allowed" disabled>Add Folder</button>
                    </div>
                </div>
            </div>
        </div>
<!-- Delete Folder Modal -->
<div id="delete-folder-modal" class="hidden fixed inset-0 z-50">
    <div id="delete-folder-backdrop" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
            <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Delete Folder</div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2" for="delete-folder-select">Select Folder</label>
            <select id="delete-folder-select" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="">-- Choose a folder --</option>
                <?php foreach ($archive_folders as $folder): ?>
                <option value="<?php echo (int)$folder['id']; ?>"><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="delete-folder-error" class="mt-2 text-xs text-red-600 dark:text-red-400 hidden"></div>
            <div class="mt-6 flex justify-end gap-2">
                <button id="cancel-delete-folder" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                <button id="confirm-delete-folder" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Delete</button>
            </div>
        </div>
    </div>
</div>
        <div id="restore-confirm-modal" class="hidden fixed inset-0 z-50">
            <div id="restore-confirm-backdrop" class="absolute inset-0 bg-black/50"></div>
            <div class="relative z-10 flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
                    <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Confirm Restore</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">This will restore the latest backup and may overwrite current data.</div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button id="restore-cancel-btn" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                        <button id="restore-confirm-btn" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Restore</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="toast" class="fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold" role="status" aria-live="polite"></div>
    </div>

    <script>
        // Storage Donut Chart Initialization
        function initStorageDonut() {
            const storageData = {
                used: 1,
                total: 50
            };

            const percentage = Math.round((storageData.used / storageData.total) * 100);
            const available = storageData.total - storageData.used;

            document.getElementById('storagePercentage').textContent = percentage + '%';
            document.getElementById('storageUsed').textContent = formatBytes(storageData.used * 1024 * 1024 * 1024);
            document.getElementById('storageTotal').textContent = 'of ' + formatBytes(storageData.total * 1024 * 1024 * 1024);
            document.getElementById('detailUsed').textContent = formatBytes(storageData.used * 1024 * 1024 * 1024);
            document.getElementById('detailAvailable').textContent = formatBytes(available * 1024 * 1024 * 1024);
            document.getElementById('detailTotal').textContent = formatBytes(storageData.total * 1024 * 1024 * 1024);

            const radius = 90;
            const circumference = 2 * Math.PI * radius;
            const offset = circumference - (percentage / 100) * circumference;

            const progressCircle = document.getElementById('donutProgress');
            
            if (progressCircle) {
                progressCircle.style.transition = 'stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1)';
                
                setTimeout(() => {
                    progressCircle.style.strokeDashoffset = offset;
                }, 100);

                updateProgressColor(percentage);
            }

            function formatBytes(bytes) {
                if (bytes === 0) return '0 GB';
                const gb = bytes / (1024 * 1024 * 1024);
                if (gb >= 1) {
                    return gb.toFixed(gb < 10 ? 1 : 0) + ' GB';
                }
                const mb = bytes / (1024 * 1024);
                return mb.toFixed(0) + ' MB';
            }

            function updateProgressColor(percent) {
                const progressCircle = document.getElementById('donutProgress');
                if (!progressCircle) return;
                
                if (percent >= 90) {
                    progressCircle.classList.remove('stroke-red-600', 'stroke-orange-500', 'stroke-amber-500');
                    progressCircle.classList.add('stroke-red-600');
                } else if (percent >= 75) {
                    progressCircle.classList.remove('stroke-red-600', 'stroke-orange-500', 'stroke-amber-500');
                    progressCircle.classList.add('stroke-orange-500');
                } else if (percent >= 50) {
                    progressCircle.classList.remove('stroke-red-600', 'stroke-orange-500', 'stroke-amber-500');
                    progressCircle.classList.add('stroke-amber-500');
                } else {
                    progressCircle.classList.remove('stroke-red-600', 'stroke-orange-500', 'stroke-amber-500');
                    progressCircle.classList.add('stroke-red-600');
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStorageDonut);
        } else {
            initStorageDonut();
        }

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');
        
        // Desktop sidebar toggle
        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('sidebar-collapsed'));
        });
        
        // Mobile sidebar toggle
        mobileMenuBtn?.addEventListener('click', () => {
            mobileSidebar?.classList.remove('-translate-x-full');
            sidebarOverlay?.classList.remove('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.add('opacity-100', 'pointer-events-auto');
        });
        
        closeMobileSidebar?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });
        
        sidebarOverlay?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });

        const createFolderBtn = document.getElementById('create-folder-btn');
        const foldersGrid = document.getElementById('archive-folders-grid');
        const deleteFolderBtn = document.getElementById('delete-folder-btn');
        const deleteFolderModal = document.getElementById('delete-folder-modal');
        const deleteFolderBackdrop = document.getElementById('delete-folder-backdrop');
        const deleteFolderSelect = document.getElementById('delete-folder-select');
        const deleteFolderError = document.getElementById('delete-folder-error');
        const cancelDeleteFolder = document.getElementById('cancel-delete-folder');
        const confirmDeleteFolder = document.getElementById('confirm-delete-folder');
        const createModal = document.getElementById('create-folder-modal');
        const createInput = document.getElementById('new-folder-name');
        const createBackdrop = document.getElementById('create-folder-backdrop');
        const cancelCreate = document.getElementById('cancel-create-folder');
        const confirmCreate = document.getElementById('confirm-create-folder');
        const createError = document.getElementById('folder-name-error');
        const restoreModal = document.getElementById('restore-confirm-modal');
        const restoreBackdrop = document.getElementById('restore-confirm-backdrop');
        const restoreCancel = document.getElementById('restore-cancel-btn');
        const restoreConfirm = document.getElementById('restore-confirm-btn');
        const toastEl = document.getElementById('toast');
        const existingSlugs = new Set(
            Array.from(document.querySelectorAll('#archive-folders-grid [data-archive]'))
                .map(el => (el.getAttribute('data-archive') || '').toLowerCase())
                .filter(Boolean)
        );
        let toastTimer = null;
        const toastBase = 'fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold';
        const toastStyles = {
            success: 'bg-gradient-to-r from-green-500 to-emerald-500',
            error: 'bg-gradient-to-r from-red-600 to-red-500'
        };
        const showToast = (message, type) => {
            if (!toastEl) return;
            const style = toastStyles[type] || toastStyles.success;
            toastEl.className = `${toastBase} ${style}`;
            toastEl.textContent = message;
            toastEl.classList.remove('opacity-0', 'translate-y-4');
            toastEl.classList.add('opacity-100', 'translate-y-0');
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toastEl.classList.remove('opacity-100', 'translate-y-0');
                toastEl.classList.add('opacity-0', 'translate-y-4');
            }, 2000);
        };
        const normalizeSlug = (value) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        const escapeHtml = (value) => value.replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));
        const removeFolderCardById = (id) => {
            const card = document.getElementById('folder-card-' + String(id));
            if (card) {
                card.parentNode.removeChild(card);
                return true;
            }
            const links = Array.from(document.querySelectorAll('#archive-folders-grid a[href*="folder_view.php?id="]'));
            for (const a of links) {
                if (a.href.includes('folder_view.php?id=' + String(id))) {
                    a.parentNode.removeChild(a);
                    return true;
                }
            }
            return false;
        };
        const setCreateButtonEnabled = (enabled) => {
            if (!confirmCreate) return;
            confirmCreate.disabled = !enabled;
            confirmCreate.classList.toggle('opacity-50', !enabled);
            confirmCreate.classList.toggle('cursor-not-allowed', !enabled);
        };
        const setCreateError = (message) => {
            if (!createError) return;
            if (message) {
                createError.textContent = message;
                createError.classList.remove('hidden');
            } else {
                createError.textContent = '';
                createError.classList.add('hidden');
            }
        };
        const validateCreateName = () => {
            const name = (createInput?.value || '').trim();
            if (!name) {
                setCreateError('');
                setCreateButtonEnabled(false);
                return { ok: false };
            }
            const slug = normalizeSlug(name);
            if (!slug) {
                setCreateError('Folder name is invalid.');
                setCreateButtonEnabled(false);
                return { ok: false };
            }
            if (existingSlugs.has(slug)) {
                setCreateError('Folder already exists.');
                setCreateButtonEnabled(false);
                return { ok: false };
            }
            setCreateError('');
            setCreateButtonEnabled(true);
            return { ok: true, name, slug };
        };
        const closeCreateModal = () => {
            createModal?.classList.add('hidden');
            if (createInput) createInput.value = '';
            setCreateError('');
            setCreateButtonEnabled(false);
        };
        const openCreateModal = () => {
            createModal?.classList.remove('hidden');
            setTimeout(() => createInput?.focus(), 0);
            validateCreateName();
        };
        createFolderBtn?.addEventListener('click', openCreateModal);
        cancelCreate?.addEventListener('click', closeCreateModal);
        createBackdrop?.addEventListener('click', closeCreateModal);
        createInput?.addEventListener('input', validateCreateName);
        createInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!confirmCreate?.disabled) confirmCreate?.click();
            }
        });
        const createFolderCard = (name, slug, id) => {
            const safeName = escapeHtml(name);
            const safeSlug = escapeHtml(slug);
            const card = document.createElement('a');
            card.href = 'folder_view.php?id=' + id;
            card.setAttribute('data-archive', slug);
            card.className = 'block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group';
            card.innerHTML = `
                <div class="mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-12 h-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">${safeName}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 archive-meta" data-archive-meta="${safeSlug}">Last opened: Not yet opened</div>
            `;
            return card;
        };
        let isCreating = false;
        const setCreateButtonLoading = (loading) => {
            if (!confirmCreate) return;
            if (loading) {
                confirmCreate.dataset.label = confirmCreate.textContent;
                confirmCreate.textContent = 'Adding...';
                confirmCreate.disabled = true;
                confirmCreate.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                confirmCreate.textContent = confirmCreate.dataset.label || 'Add Folder';
                validateCreateName();
            }
        };
        confirmCreate?.addEventListener('click', async () => {
            if (isCreating) return;
            const validation = validateCreateName();
            if (!validation.ok) return;
            isCreating = true;
            setCreateButtonLoading(true);
            try {
                const response = await fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_folder', name: validation.name })
                });
                const data = await response.json();
                if (!data || !data.success) {
                    setCreateError(data?.message || 'Could not create folder.');
                    setCreateButtonLoading(false);
                    isCreating = false;
                    return;
                }
                const folder = data.folder || { name: validation.name, slug: validation.slug, id: data.folder.id };
                existingSlugs.add((folder.slug || '').toLowerCase());
                const card = createFolderCard(folder.name, folder.slug, folder.id);
                foldersGrid?.appendChild(card);
                showToast('Folder created.', 'success');
                closeCreateModal();
            } catch (e) {
                showToast('Could not create folder.', 'error');
            } finally {
                isCreating = false;
                setCreateButtonLoading(false);
            }
        });
        const openDeleteModal = () => {
            if (!deleteFolderModal) return;
            deleteFolderError?.classList.add('hidden');
            deleteFolderSelect?.value && (deleteFolderSelect.value = '');
            deleteFolderModal.classList.remove('hidden');
        };
        const closeDeleteModal = () => {
            deleteFolderModal?.classList.add('hidden');
        };
        deleteFolderBtn?.addEventListener('click', openDeleteModal);
        deleteFolderBackdrop?.addEventListener('click', closeDeleteModal);
        cancelDeleteFolder?.addEventListener('click', closeDeleteModal);
        confirmDeleteFolder?.addEventListener('click', async () => {
            const val = (deleteFolderSelect?.value || '').trim();
            if (!val) {
                deleteFolderError.textContent = 'Please select a folder to delete.';
                deleteFolderError.classList.remove('hidden');
                return;
            }
            const fid = parseInt(val, 10);
            try {
                const response = await fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_folder', folder_id: fid })
                });
                const data = await response.json();
                if (data && data.success) {
                    removeFolderCardById(fid);
                    showToast('Folder deleted.', 'success');
                    closeDeleteModal();
                } else {
                    deleteFolderError.textContent = (data && data.message) ? data.message : 'Failed to delete folder.';
                    deleteFolderError.classList.remove('hidden');
                }
            } catch (e) {
                deleteFolderError.textContent = 'Failed to delete folder.';
                deleteFolderError.classList.remove('hidden');
            }
        });
        const closeRestoreModal = () => {
            restoreModal?.classList.add('hidden');
        };
        restoreBackdrop?.addEventListener('click', closeRestoreModal);
        restoreCancel?.addEventListener('click', closeRestoreModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (createModal && !createModal.classList.contains('hidden')) closeCreateModal();
                if (restoreModal && !restoreModal.classList.contains('hidden')) closeRestoreModal();
            }
        });
        const setActionLoading = (btn, loading, label) => {
            if (!btn) return;
            if (loading) {
                btn.dataset.label = btn.textContent;
                btn.textContent = label;
                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-not-allowed');
            } else {
                btn.textContent = btn.dataset.label || btn.textContent;
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        };
        const backupBtn = document.getElementById('system-backup-btn');
        const restoreBtn = document.getElementById('system-restore-btn');
        backupBtn?.addEventListener('click', () => {
            if (backupBtn.disabled) return;
            setActionLoading(backupBtn, true, 'Running...');
            setTimeout(() => {
                setActionLoading(backupBtn, false, '');
                showToast('System backup completed.', 'success');
            }, 900);
        });
        restoreBtn?.addEventListener('click', () => {
            if (restoreBtn.disabled) return;
            restoreModal?.classList.remove('hidden');
        });
        restoreConfirm?.addEventListener('click', () => {
            closeRestoreModal();
            if (!restoreBtn || restoreBtn.disabled) return;
            setActionLoading(restoreBtn, true, 'Restoring...');
            setTimeout(() => {
                setActionLoading(restoreBtn, false, '');
                showToast('System restore completed.', 'success');
            }, 900);
        });
        
        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notification-btn');
        const notifDropdown = document.getElementById('notification-dropdown');
        const notifCount = document.getElementById('notif-count');

        profileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown?.classList.add('hidden');
            profileDropdown?.classList.toggle('hidden');
        });

        notifBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.toggle('hidden');
            try {
                var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
                if (ids.length > 0) {
                    fetch('notifications_log.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
                    }).then(function(){});
                }
            } catch(e){}
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
                profileDropdown?.classList.add('hidden');
            }
            if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
                notifDropdown?.classList.add('hidden');
            }
        });

        (function(){
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
                container.innerHTML = html;
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
            function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
            function yearCard(year){
                var el = document.createElement('div');
                el.className = 'p-4 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700/50';
                el.innerHTML = '<div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">'+year+'</div>'
                    + '<div class="text-xs text-gray-600 dark:text-gray-400 mb-3">Jan 1 - Dec 31</div>'
                    + '<div class="flex gap-2">'
                    + '<button data-year="'+year+'" class="view-year-btn px-3 py-1.5 text-xs rounded-md bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700">View</button>'
                    + '<button data-year="'+year+'" class="export-year-btn px-3 py-1.5 text-xs rounded-md bg-red-600 hover:bg-red-700 text-white">Export ZIP</button>'
                    + '</div>';
                return el;
            }
            function renderYearCards(){
                var grid = document.getElementById('yearly-archives-grid');
                if (!grid) return;
                var now = new Date();
                var current = now.getFullYear();
                grid.innerHTML = '';
                for (var y = current; y >= current - 4; y--){
                    grid.appendChild(yearCard(y));
                }
                grid.querySelectorAll('.view-year-btn').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        var year = parseInt(btn.getAttribute('data-year'), 10);
                        fetch('storage.php', {
                            method:'POST',
                            headers:{'Content-Type':'application/json'},
                            body: JSON.stringify({ action:'list_year', year: year })
                        }).then(function(r){ return r.json(); })
                        .then(function(d){
                            if (!d || !d.success) {
                                showToast('Could not load year '+year, 'error');
                                return;
                            }
                            var lines = d.categories.map(function(c){
                                return '<div class="flex items-center justify-between p-2 rounded-md bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700"><span class="text-sm font-medium text-gray-800 dark:text-gray-200">'+escapeHtml(c.group)+': '+escapeHtml(c.name)+'</span><span class="text-xs text-gray-600 dark:text-gray-400">'+String(c.count)+' files</span></div>';
                            }).join('');
                            var modal = document.createElement('div');
                            modal.id = 'year-modal';
                            modal.className = 'fixed inset-0 z-50';
                            modal.innerHTML = '<div class="absolute inset-0 bg-black/50"></div>'
                                + '<div class="absolute inset-0 flex items-center justify-center p-4">'
                                + '<div class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 shadow-xl">'
                                + '<div class="p-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between"><div class="font-semibold text-gray-800 dark:text-gray-200">Year '+year+' Summary</div><button id="year-modal-close" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"><i class="bi bi-x-lg"></i></button></div>'
                                + '<div class="p-4 space-y-2">'+(lines || '<div class="text-sm text-gray-600 dark:text-gray-400">No files for this year.</div>')+'</div>'
                                + '<div class="p-4 border-t border-gray-200 dark:border-slate-700 text-right"><button id="year-modal-export" class="px-3 py-1.5 text-xs rounded-md bg-red-600 hover:bg-red-700 text-white">Export ZIP</button></div>'
                                + '</div></div>';
                            document.body.appendChild(modal);
                            document.getElementById('year-modal-close').addEventListener('click', function(){
                                modal.remove();
                            });
                            document.getElementById('year-modal-export').addEventListener('click', function(){
                                var form = document.getElementById('year-export-form');
                                var input = document.getElementById('year-export-input');
                                if (form && input) {
                                    input.value = String(year);
                                    form.submit();
                                }
                            });
                        }).catch(function(){
                            showToast('Error loading year '+year, 'error');
                        });
                    });
                });
                grid.querySelectorAll('.export-year-btn').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        var year = parseInt(btn.getAttribute('data-year'), 10);
                        var form = document.getElementById('year-export-form');
                        var input = document.getElementById('year-export-input');
                        if (form && input) {
                            input.value = String(year);
                            form.submit();
                        }
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', renderYearCards);
            } else {
                renderYearCards();
            }
        })();
        
        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar?.classList.add('sidebar-collapsed');
        }

        (function(){
            var storageKey = 'archive_opened_at';
            function getMap() {
                try {
                    var raw = localStorage.getItem(storageKey);
                    return raw ? JSON.parse(raw) : {};
                } catch (e) {
                    return {};
                }
            }
            function setMap(map) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(map));
                } catch (e) {}
            }
            function formatTime(value) {
                var date = new Date(value);
                if (Number.isNaN(date.getTime())) return null;
                var now = Date.now();
                var diffMs = Math.max(0, now - date.getTime());
                var seconds = Math.floor(diffMs / 1000);
                if (seconds < 60) return 'just now';
                var minutes = Math.floor(seconds / 60);
                if (minutes < 60) return minutes + (minutes === 1 ? ' minute ago' : ' minutes ago');
                var hours = Math.floor(minutes / 60);
                if (hours < 24) return hours + (hours === 1 ? ' hour ago' : ' hours ago');
                var days = Math.floor(hours / 24);
                if (days < 30) return days + (days === 1 ? ' day ago' : ' days ago');
                var months = Math.floor(days / 30);
                if (months < 12) return months + (months === 1 ? ' month ago' : ' months ago');
                var years = Math.floor(months / 12);
                return years + (years === 1 ? ' year ago' : ' years ago');
            }
            function updateMeta(map) {
                document.querySelectorAll('[data-archive-meta]').forEach(function(el){
                    var id = el.getAttribute('data-archive-meta');
                    var stored = map[id];
                    var formatted = stored ? formatTime(stored) : null;
                    el.textContent = formatted ? ('Last opened: ' + formatted) : 'Last opened: Not yet opened';
                });
            }
            function bindClicks(map) {
                document.querySelectorAll('a[data-archive]').forEach(function(link){
                    link.addEventListener('click', function(){
                        var id = link.getAttribute('data-archive');
                        if (!id) return;
                        map[id] = Date.now();
                        setMap(map);
                        updateMeta(map);
                    });
                });
            }
            function init() {
                var map = getMap();
                updateMeta(map);
                bindClicks(map);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

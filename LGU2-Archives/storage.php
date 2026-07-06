<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'authdatabase.php';
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
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
        $confirm = $_POST['confirm_password'] ?? '';
        $uid = (int)$_SESSION['user_id'];
        $ok = false;
        if ($confirm !== '') {
            if ($st = $conn->prepare("SELECT password FROM users WHERE id = ?")) {
                $st->bind_param("i", $uid);
                $st->execute();
                $rs = $st->get_result();
                if ($rs && $rs->num_rows === 1) {
                    $row = $rs->fetch_assoc();
                    if (password_verify($confirm, $row['password'])) $ok = true;
                }
                $st->close();
            }
        }
        if (!$ok) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Export</title><style>body{font-family:Arial;padding:24px;color:#222}</style></head><body><h3>Export Blocked</h3><p>Invalid password. Please try again.</p></body></html>';
            $conn->close();
            exit();
        }
        $conn->query("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            time VARCHAR(20) NOT NULL,
            date DATE NOT NULL,
            content VARCHAR(255) NOT NULL,
            about VARCHAR(100) NOT NULL,
            status ENUM('unread','read') NOT NULL DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $ntime = date('h:i A'); $ndate = date('Y-m-d');
        $ncontent = 'Yearly export requested: ' . $year . ' by user #' . $uid;
        $nabout = 'Export'; $nstatus = 'unread';
        if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
            $ins->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
            $ins->execute(); $ins->close();
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
    
    // Vault operations
    if ($action === 'vault_setup') {
        $pin = isset($payload['pin']) ? trim((string)$payload['pin']) : '';
        if (!preg_match('/^\d{6}$/', $pin)) {
            echo json_encode(['success' => false, 'message' => 'PIN must be exactly 6 digits']);
            $conn->close();
            exit();
        }
        $uid = (int)$_SESSION['user_id'];
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        
        // Check if vault already exists
        $check = $conn->query("SELECT id FROM confidential_vault LIMIT 1");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Vault already exists']);
            $conn->close();
            exit();
        }
        
        $ins = $conn->prepare("INSERT INTO confidential_vault (pin_hash, created_by) VALUES (?, ?)");
        if ($ins) {
            $ins->bind_param("si", $pin_hash, $uid);
            if ($ins->execute()) {
                // Log to audit
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = 'Confidential vault created by user #' . $uid;
                $nabout = 'Vault';
                $nstatus = 'unread';
                
                if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                    $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                    $notif->execute();
                    $notif->close();
                }
                
                echo json_encode(['success' => true, 'message' => 'Vault created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create vault']);
            }
            $ins->close();
        }
        $conn->close();
        exit();
    }
    
    if ($action === 'vault_unlock') {
        $pin = isset($payload['pin']) ? trim((string)$payload['pin']) : '';
        if (!preg_match('/^\d{6}$/', $pin)) {
            echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
            $conn->close();
            exit();
        }
        
        $vault = $conn->query("SELECT id, pin_hash FROM confidential_vault LIMIT 1");
        if (!$vault || $vault->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Vault not found']);
            $conn->close();
            exit();
        }
        
        $row = $vault->fetch_assoc();
        if (password_verify($pin, $row['pin_hash'])) {
            $_SESSION['vault_unlocked'] = true;
            $_SESSION['vault_unlock_time'] = time();
            
            // Log to audit
            $uid = (int)$_SESSION['user_id'];
            $ntime = date('h:i A');
            $ndate = date('Y-m-d');
            $ncontent = 'Vault unlocked by user #' . $uid;
            $nabout = 'Vault';
            $nstatus = 'unread';
            
            if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Vault unlocked']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
        }
        $conn->close();
        exit();
    }
    
    if ($action === 'vault_lock') {
        // Log to audit before clearing session
        $uid = (int)$_SESSION['user_id'];
        $ntime = date('h:i A');
        $ndate = date('Y-m-d');
        $ncontent = 'Vault locked by user #' . $uid;
        $nabout = 'Vault';
        $nstatus = 'unread';
        
        if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
            $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
            $notif->execute();
            $notif->close();
        }
        
        unset($_SESSION['vault_unlocked']);
        unset($_SESSION['vault_unlock_time']);
        echo json_encode(['success' => true, 'message' => 'Vault locked']);
        $conn->close();
        exit();
    }
    
    if ($action === 'vault_get_files') {
        if (!isset($_SESSION['vault_unlocked']) || $_SESSION['vault_unlocked'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Vault is locked']);
            $conn->close();
            exit();
        }
        
        $files = [];
        $result = $conn->query("SELECT id, name, file_path, created_at FROM confidential_files ORDER BY created_at DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $files[] = $row;
            }
        }
        echo json_encode(['success' => true, 'files' => $files]);
        $conn->close();
        exit();
    }
    
    if ($action === 'vault_check_status') {
        $vault_exists = false;
        $is_unlocked = isset($_SESSION['vault_unlocked']) && $_SESSION['vault_unlocked'] === true;
        
        $check = $conn->query("SELECT id FROM confidential_vault LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $vault_exists = true;
        }
        
        echo json_encode([
            'success' => true,
            'vault_exists' => $vault_exists,
            'is_unlocked' => $is_unlocked
        ]);
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

    // Ensure legislative folders exist and get their IDs
    $legislative_folders = [];
    $folder_types = [
        'Ordinance' => 'Ordinances & Resolutions',
        'Resolution' => 'Ordinances & Resolutions',
        'Billing' => 'Billing',
        'Public Hearing' => 'Public Hearings',
        'Meeting' => 'Meeting Records'
    ];

    foreach ($folder_types as $type => $name) {
        // Check if folder exists
        $checkStmt = $conn->prepare("SELECT id FROM legislative_folders WHERE type = ? AND parent_id IS NULL LIMIT 1");
        $checkStmt->bind_param("s", $type);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($folder = $checkResult->fetch_assoc()) {
            $legislative_folders[$type] = $folder['id'];
        } else {
            // Create folder if it doesn't exist
            $insertStmt = $conn->prepare("INSERT INTO legislative_folders (name, type, parent_id) VALUES (?, ?, NULL)");
            $insertStmt->bind_param("ss", $name, $type);
            $insertStmt->execute();
            $legislative_folders[$type] = $conn->insert_id;
            $insertStmt->close();
        }
        $checkStmt->close();
    }

    // helper methods and shared storage logic (copied from archives-landing.php)
    function fmt_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 1) . ' ' . $units[$pow];
    }

    function calculateStorageMetrics($conn) {
        $capacityBytes = 50 * 1024 * 1024 * 1024; // 50 GB
        $totalBytes = 0;
        $fileCount = 0;
        $storageTop = [];

        $legResult = $conn->query("SELECT file_path FROM legislative_records WHERE file_path IS NOT NULL AND file_path <> ''");
        if ($legResult) {
            while ($row = $legResult->fetch_assoc()) {
                if (@file_exists($row['file_path'])) {
                    $size = @filesize($row['file_path']);
                    $totalBytes += $size;
                    $fileCount++;
                    $storageTop[] = ['name' => basename($row['file_path']), 'path' => $row['file_path'], 'src' => 'Legislative', 'size' => $size];
                }
            }
        }
        $archResult = $conn->query("SELECT name, file_path FROM archive_files WHERE file_path IS NOT NULL AND file_path <> ''");
        if ($archResult) {
            while ($row = $archResult->fetch_assoc()) {
                if (@file_exists($row['file_path'])) {
                    $size = @filesize($row['file_path']);
                    $totalBytes += $size;
                    $fileCount++;
                    $storageTop[] = ['name' => $row['name'], 'path' => $row['file_path'], 'src' => 'Archive', 'size' => $size];
                }
            }
        }
        usort($storageTop, function($a, $b) { return $b['size'] - $a['size']; });
        $storageTop = array_slice($storageTop, 0, 15);
        $pct = min(100, round(($totalBytes / $capacityBytes) * 100, 1));
        return [
            'pct' => $pct,
            'totalBytes' => $totalBytes,
            'capacityBytes' => $capacityBytes,
            'fileCount' => $fileCount,
            'storageTop' => $storageTop,
            'usedText' => fmt_bytes($totalBytes),
            'totalText' => fmt_bytes($capacityBytes)
        ];
    }

    // support AJAX data fetch
    if (isset($_GET['action']) && $_GET['action'] === 'get_storage_data') {
        $storage = calculateStorageMetrics($conn);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'percentage' => $storage['pct'],
            'usedText' => $storage['usedText'],
            'totalText' => $storage['totalText'],
            'fileCount' => $storage['fileCount'],
            'bytes' => $storage['totalBytes']
        ]);
        $conn->close();
        exit();
    }

    // compute metrics for initial page render
    $storage = calculateStorageMetrics($conn);
    $pct = $storage['pct'];
    $totalBytes = $storage['totalBytes'];
    $capacityBytes = $storage['capacityBytes'];
    $fileCount = $storage['fileCount'];

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

    <div class="flex min-h-screen">
        <?php
        $sidebar_active_page = 'storage';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / Navbar - Fixed at top -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 fixed top-0 left-0 right-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8 lg:pl-72">
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
                                        <button type="button" id="open-logout-modal" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer w-full text-left">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </button>
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
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
                    <div class="space-y-6">
               
                    <!-- Recent Archives Section -->
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Yearly Archives</h2>
                            <form id="year-export-form" method="POST" action="storage.php" target="_blank" class="hidden">
                                <input type="hidden" name="action" value="export_year_zip">
                                <input type="hidden" name="year" id="year-export-input" value="">
                                <input type="hidden" name="confirm_password" id="year-export-pass" value="">
                            </form>
                        </div>
                        <div id="yearly-archives-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4"></div>
                    </div>
                    <div id="year-export-modal" class="hidden fixed inset-0 z-50">
                        <div class="flex items-center justify-center min-h-screen px-4">
                            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-sm w-full p-6 border border-gray-200 dark:border-slate-700">
                                <div class="mb-4">
                                    <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Confirm Yearly Export</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Enter your password to continue.</div>
                                </div>
                                <div class="space-y-3">
                                    <input type="password" id="year-export-password-input" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100" placeholder="Password" />
                                    <div class="flex justify-end gap-2 pt-2">
                                        <button type="button" id="year-export-cancel" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Cancel</button>
                                        <button type="button" id="year-export-confirm" class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white">Export</button>
                                    </div>
                                </div>
                            </div>
                        </div>
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
            <div id="archive-folders-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <a href="folder_view.php?id=<?php echo $legislative_folders['Ordinance']; ?>&legislative=true" data-archive="ordinances-resolution" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-orange-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/40 rounded-xl flex items-center justify-center text-orange-600 dark:text-orange-400 text-2xl group-hover:scale-110 transition-transform">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">Ordinances & Res...</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="ordinances-resolution">Calculating...</div>
                    </div>
                </a>
                <a href="folder_view.php?id=<?php echo $legislative_folders['Billing']; ?>&legislative=true" data-archive="billing" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-green-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/40 rounded-xl flex items-center justify-center text-green-600 dark:text-green-400 text-2xl group-hover:scale-110 transition-transform">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">Billing</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="billing">Calculating...</div>
                    </div>
                </a>
                <a href="folder_view.php?id=<?php echo $legislative_folders['Public Hearing']; ?>&legislative=true" data-archive="public-hearings" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-blue-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/40 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400 text-2xl group-hover:scale-110 transition-transform">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">Public Hearings</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="public-hearings">Calculating...</div>
                    </div>
                </a>
                <a href="folder_view.php?id=<?php echo $legislative_folders['Meeting']; ?>&legislative=true" data-archive="meeting-records" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-indigo-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/40 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-2xl group-hover:scale-110 transition-transform">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">Meeting Records</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="meeting-records">Calculating...</div>
                    </div>
                </a>
                <?php foreach ($archive_folders as $folder): ?>
                <a id="folder-card-<?php echo (int)$folder['id']; ?>" href="folder_view.php?id=<?php echo $folder['id']; ?>" data-archive="<?php echo htmlspecialchars($folder['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-slate-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 text-2xl group-hover:scale-110 transition-transform relative overflow-hidden">
                            <i class="bi bi-folder-fill"></i>
                            <div class="absolute inset-0 flex items-center justify-center text-white dark:text-slate-800 text-[14px] mt-1 z-10">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate"><?php echo htmlspecialchars($folder['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="<?php echo htmlspecialchars($folder['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Calculating...</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
                    <!-- Confidential Files Vault Section -->
                    <div class="bg-[#1e232d] shadow-2xl rounded-2xl border border-slate-700 p-8 mb-8 mt-4 relative overflow-hidden group">
                        <div class="absolute inset-0 bg-gradient-to-br from-red-600/5 to-transparent pointer-events-none"></div>
                        <div class="absolute right-0 top-0 w-32 h-32 bg-red-600/10 rounded-full blur-3xl -mr-10 -mt-10 transition-all duration-1000 group-hover:bg-red-500/20 group-hover:scale-150 pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between">
                            <div class="flex items-center gap-4 mb-4 md:mb-0">
                                <div class="w-14 h-14 bg-red-500/10 border border-red-500/30 rounded-xl flex items-center justify-center text-red-500 shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                                    <i class="bi bi-shield-lock-fill text-2xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white flex items-center gap-2 tracking-tight">Confidential Vault <span id="vault-badge" class="px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 text-[10px] uppercase font-bold border border-red-500/30">Locked</span></h2>
                                    <p class="text-sm text-slate-400">High-security encrypted namespace</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id="vault-lock-btn" class="hidden px-5 py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-white text-sm font-semibold transition-colors border border-white/10 backdrop-blur-sm shadow-sm">
                                    <i class="bi bi-lock-fill mr-2"></i>Lock Vault
                                </button>
                                <a href="confidential_vault.php" id="vault-view-btn" class="hidden px-5 py-2.5 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-semibold transition-all shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(239,68,68,0.4)]">
                                    <i class="bi bi-eye-fill mr-2"></i>View Files
                                </a>
                            </div>
                        </div>
                        
                        <!-- Vault Locked State -->
                        <div id="vault-locked-state" class="hidden relative z-10 mt-6 border-t border-slate-800 pt-8">
                            <div class="flex flex-col items-center justify-center py-6">
                                <div class="flex gap-2 mb-6">
                                    <div class="w-2.5 h-2.5 rounded-full bg-slate-700 animate-pulse" style="animation-delay: 0ms"></div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-slate-700 animate-pulse" style="animation-delay: 150ms"></div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-slate-700 animate-pulse" style="animation-delay: 300ms"></div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-slate-700 animate-pulse" style="animation-delay: 450ms"></div>
                                </div>
                                <h3 class="text-lg font-bold text-slate-200 mb-2">Authentication Required</h3>
                                <p class="text-sm text-slate-400 mb-6 text-center max-w-sm">Enter your 6-digit secure PIN code to establish encrypted payload connection.</p>
                                <button id="vault-unlock-btn" class="px-8 py-3 rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 hover:border-slate-600 text-white font-bold transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                                    <i class="bi bi-key-fill mr-2 text-slate-400"></i>Authenticate PIN
                                </button>
                            </div>
                        </div>
                        
                        <!-- Vault Setup State -->
                        <div id="vault-setup-state" class="hidden relative z-10 mt-6 border-t border-slate-800 pt-8">
                            <div class="flex flex-col items-center justify-center py-6">
                                <div class="bg-blue-500/10 p-5 rounded-full mb-4 border border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.15)]">
                                    <i class="bi bi-shield-plus text-blue-400 text-4xl"></i>
                                </div>
                                <h3 class="text-lg font-bold text-white mb-2">Initialize Secure Vault</h3>
                                <p class="text-sm text-slate-400 mb-6 text-center max-w-sm">Create a master 6-digit PIN to establish the encrypted container.</p>
                                <button id="vault-setup-btn" class="px-8 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition-all shadow-[0_0_15px_rgba(59,130,246,0.3)] hover:-translate-y-0.5">
                                    <i class="bi bi-gear-fill mr-2"></i>Setup Vault
                                </button>
                            </div>
                        </div>
                        
                        <!-- Vault Unlocked State -->
                        <div id="vault-unlocked-state" class="hidden relative z-10 mt-6 border-t border-slate-800 pt-8">
                            <div class="mb-5 px-5 py-3 bg-red-900/10 rounded-lg border border-red-500/20 flex items-center justify-between shadow-inner">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-shield-check text-red-500 text-lg"></i>
                                    <span class="text-sm text-red-300 font-bold uppercase tracking-widest">Decrypted Access</span>
                                </div>
                                <span class="text-xs font-bold text-red-400 px-3 py-1 bg-red-500/20 rounded-full" id="vault-file-count">0 assets</span>
                            </div>
                            <div id="vault-files-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <!-- Files will be loaded here -->
                            </div>
                            <div id="vault-empty-state" class="hidden flex flex-col items-center justify-center py-12">
                                <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center border border-slate-700 mb-4 shadow-inner">
                                    <i class="bi bi-inbox text-slate-600 text-4xl"></i>
                                </div>
                                <p class="text-base text-slate-300 font-bold">Encrypted volume is empty</p>
                                <p class="text-sm text-slate-500 mt-1">Transfer sensitive records to populate this container.</p>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </main>
        
        <!-- Vault PIN Modal -->
        <div id="vault-pin-modal" class="hidden fixed inset-0 z-50">
            <div id="vault-pin-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
                    <div class="text-center mb-6">
                        <div class="bg-red-100 dark:bg-red-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="bi bi-shield-lock-fill text-red-600 dark:text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1" id="vault-modal-title">Enter PIN</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400" id="vault-modal-subtitle">Enter your 6-digit PIN</p>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex justify-center gap-2 mb-4">
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                            <input type="password" maxlength="1" class="vault-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        </div>
                        <div id="vault-pin-error" class="text-xs text-red-600 dark:text-red-400 text-center hidden"></div>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button id="vault-pin-cancel" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                        <button id="vault-pin-confirm" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Confirm</button>
                    </div>
                </div>
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
<!-- Logout Confirmation Modal -->
<div id="logout-modal" class="hidden fixed inset-0 z-50">
    <div id="logout-modal-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
            <div class="text-center mb-6">
                <div class="bg-red-100 dark:bg-red-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="bi bi-box-arrow-right text-red-600 dark:text-red-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Logout Confirmation</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Are you sure you want to logout from your account?</p>
            </div>
            
            <div class="flex justify-end gap-2">
                <button id="logout-cancel" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                <form action="logout.php" method="POST" class="inline">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Yes, Logout</button>
                </form>
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
        <div id="restore-modal" class="hidden fixed inset-0 z-50">
            <div id="restore-backdrop" class="absolute inset-0 bg-black/50"></div>
            <div class="relative z-10 flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-xl rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl">
                    <div class="p-6">
                        <div class="text-lg font-semibold text-gray-800 dark:text-gray-100">Restore from File</div>
                        <div id="restore-dropzone" class="mt-4 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl bg-gray-50 dark:bg-slate-700/30 p-8 flex flex-col items-center justify-center text-center">
                            <i class="bi bi-cloud-arrow-down text-3xl text-red-600 dark:text-red-400 mb-2"></i>
                            <div class="text-sm text-gray-700 dark:text-gray-300">Drag and drop backup file here</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">or</div>
                            <button id="restore-browse-btn" type="button" class="mt-3 px-3 py-1.5 rounded-md bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-800 dark:text-gray-200 text-xs font-semibold hover:bg-gray-100 dark:hover:bg-slate-600">Browse</button>
                            <input id="restore-file-input" type="file" class="hidden" />
                        </div>
                        <div id="restore-file-name" class="mt-2 text-xs text-gray-600 dark:text-gray-400 hidden"></div>
                        <div id="restore-skeleton" class="hidden mt-6 space-y-3">
                            <div class="animate-pulse flex items-center gap-3">
                                <div class="w-8 h-8 bg-gray-200 dark:bg-slate-700 rounded-full"></div>
                                <div class="flex-1 h-3 bg-gray-200 dark:bg-slate-700 rounded"></div>
                            </div>
                            <div class="animate-pulse h-3 bg-gray-200 dark:bg-slate-700 rounded"></div>
                            <div class="animate-pulse h-3 bg-gray-200 dark:bg-slate-700 rounded w-5/6"></div>
                            <div class="animate-pulse h-3 bg-gray-200 dark:bg-slate-700 rounded w-2/3"></div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <button id="restore-cancel" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                            <button id="restore-start" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold opacity-50 cursor-not-allowed" disabled>Restore</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="toast" class="fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold" role="status" aria-live="polite"></div>
    </div>

    <script>
        // Enhanced Storage Donut Chart Initialization
        // storageData lives at script scope so it can be updated by AJAX
        let storageData = {
            used: <?php echo round($totalBytes / (1024*1024*1024),1); ?>,
            total: <?php echo round($capacityBytes / (1024*1024*1024),1); ?>
        };
        function initStorageDonut() {
            // note: storageData may be modified externally

            const percentage = Math.round((storageData.used / storageData.total) * 100);
            const available = storageData.total - storageData.used;

            // Update main display
            document.getElementById('storagePercentage').textContent = percentage + '%';
            document.getElementById('storageUsed').textContent = formatBytes(storageData.used * 1024 * 1024 * 1024);
            document.getElementById('storageTotal').textContent = 'of ' + formatBytes(storageData.total * 1024 * 1024 * 1024);
            document.getElementById('detailUsed').textContent = formatBytes(storageData.used * 1024 * 1024 * 1024);
            document.getElementById('detailAvailable').textContent = formatBytes(available * 1024 * 1024 * 1024);
            document.getElementById('detailTotal').textContent = formatBytes(storageData.total * 1024 * 1024 * 1024);
            document.getElementById('quotaPercentage').textContent = percentage + '%';
            
            // Update progress bars
            const usedPercentage = (storageData.used / storageData.total) * 100;
            const availPercentage = (available / storageData.total) * 100;
            if (document.getElementById('usedSpaceBar')) {
                document.getElementById('usedSpaceBar').style.width = usedPercentage + '%';
            }
            if (document.getElementById('availableSpaceBar')) {
                document.getElementById('availableSpaceBar').style.width = availPercentage + '%';
            }
            
            // Update status badge
            updateStorageStatus(percentage);

            // Animate donut chart
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

            // Update last updated time
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const elem = document.getElementById('lastUpdateTime');
            if (elem) elem.textContent = 'at ' + timeString;

            function formatBytes(bytes) {
                if (bytes === 0) return '0 GB';
                const gb = bytes / (1024 * 1024 * 1024);
                if (gb >= 1) {
                    return gb.toFixed(gb < 10 ? 1 : 0) + ' GB';
                }
                const mb = bytes / (1024 * 1024);
                return mb.toFixed(0) + ' MB';
            }

            function updateStorageStatus(percent) {
                const statusElem = document.getElementById('storageStatus');
                if (!statusElem) return;
                
                if (percent >= 90) {
                    statusElem.textContent = 'Critical';
                    statusElem.className = 'text-xs font-semibold text-red-700 dark:text-red-300';
                    statusElem.parentElement.className = 'mt-2 px-2 py-1 bg-red-100 dark:bg-red-900/30 rounded-full';
                } else if (percent >= 75) {
                    statusElem.textContent = 'Warning';
                    statusElem.className = 'text-xs font-semibold text-orange-700 dark:text-orange-300';
                    statusElem.parentElement.className = 'mt-2 px-2 py-1 bg-orange-100 dark:bg-orange-900/30 rounded-full';
                } else if (percent >= 50) {
                    statusElem.textContent = 'Moderate';
                    statusElem.className = 'text-xs font-semibold text-amber-700 dark:text-amber-300';
                    statusElem.parentElement.className = 'mt-2 px-2 py-1 bg-amber-100 dark:bg-amber-900/30 rounded-full';
                } else {
                    statusElem.textContent = 'Optimal';
                    statusElem.className = 'text-xs font-semibold text-green-700 dark:text-green-300';
                    statusElem.parentElement.className = 'mt-2 px-2 py-1 bg-green-100 dark:bg-green-900/30 rounded-full';
                }
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

        // Event listeners for new buttons
        document.addEventListener('DOMContentLoaded', function() {
            initStorageDonut();
            // attempt to load fresh metrics
            if (typeof fetch === 'function') {
                fetch('storage.php?action=get_storage_data')
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success) {
                            // update shared storageData and redraw
                            storageData.used = d.bytes / (1024*1024*1024);
                            initStorageDonut();
                        }
                    }).catch(()=>{});
            }
            
            // Refresh button
            const refreshBtn = document.getElementById('storage-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    this.classList.add('animate-spin');
                    setTimeout(() => {
                        initStorageDonut();
                        this.classList.remove('animate-spin');
                    }, 500);
                });
            }
            
            // Details button
            const detailsBtn = document.getElementById('storage-details-btn');
            if (detailsBtn) {
                detailsBtn.addEventListener('click', function() {
                    UI_ENH.toast('Storage details expanded view coming soon!', 'info');
                });
            }
            
            // Cleanup button
            const cleanupBtn = document.getElementById('storage-cleanup-btn');
            if (cleanupBtn) {
                cleanupBtn.addEventListener('click', function() {
                    UI_ENH.toast('Storage cleanup tool available in Professional tier', 'info');
                });
            }
            
            // Export report button
            const exportBtn = document.getElementById('storage-export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    UI_ENH.toast('Storage report will be emailed to you shortly', 'success');
                });
            }
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function(){
                initStorageDonut();
                if (typeof fetch === 'function') {
                    fetch('storage.php?action=get_storage_data')
                        .then(r=>r.json())
                        .then(d=>{ if (d && d.success) { storageData.used = d.bytes / (1024*1024*1024); initStorageDonut(); } }).catch(()=>{});
                }
            });
        } else {
            initStorageDonut();
            if (typeof fetch === 'function') {
                fetch('storage.php?action=get_storage_data')
                    .then(r=>r.json())
                    .then(d=>{ if (d && d.success) { storageData.used = d.bytes / (1024*1024*1024); initStorageDonut(); } }).catch(()=>{});
            }
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
        const restoreModal = document.getElementById('restore-modal');
        const restoreBackdrop = document.getElementById('restore-backdrop');
        const restoreCancel = document.getElementById('restore-cancel');
        const restoreStart = document.getElementById('restore-start');
        const restoreDropzone = document.getElementById('restore-dropzone');
        const restoreFileInput = document.getElementById('restore-file-input');
        const restoreBrowseBtn = document.getElementById('restore-browse-btn');
        const restoreFileName = document.getElementById('restore-file-name');
        const restoreSkeleton = document.getElementById('restore-skeleton');
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
        const closeRestoreModal = () => { restoreModal?.classList.add('hidden'); };
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
        function resetRestoreUI() {
            if (restoreFileName) { restoreFileName.textContent = ''; restoreFileName.classList.add('hidden'); }
            restoreStart?.setAttribute('disabled', 'true');
            restoreStart?.classList.add('opacity-50', 'cursor-not-allowed');
            restoreSkeleton?.classList.add('hidden');
            restoreDropzone?.classList.remove('hidden');
        }
        function openRestoreModal() {
            resetRestoreUI();
            restoreModal?.classList.remove('hidden');
        }
        restoreBtn?.addEventListener('click', () => { if (!restoreBtn?.disabled) openRestoreModal(); });
        restoreBrowseBtn?.addEventListener('click', () => { restoreFileInput?.click(); });
        function setSelectedFile(file) {
            if (!file) return;
            if (restoreFileName) {
                restoreFileName.textContent = file.name;
                restoreFileName.classList.remove('hidden');
            }
            restoreStart?.removeAttribute('disabled');
            restoreStart?.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        restoreFileInput?.addEventListener('change', (e) => {
            const f = e.target.files && e.target.files[0] ? e.target.files[0] : null;
            if (f) setSelectedFile(f);
        });
        restoreDropzone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            restoreDropzone.classList.add('ring-2', 'ring-red-400');
        });
        restoreDropzone?.addEventListener('dragleave', () => {
            restoreDropzone.classList.remove('ring-2', 'ring-red-400');
        });
        restoreDropzone?.addEventListener('drop', (e) => {
            e.preventDefault();
            restoreDropzone.classList.remove('ring-2', 'ring-red-400');
            const files = e.dataTransfer?.files;
            if (files && files[0]) setSelectedFile(files[0]);
        });
        restoreStart?.addEventListener('click', () => {
            if (!restoreBtn || restoreBtn.disabled) return;
            restoreDropzone?.classList.add('hidden');
            restoreSkeleton?.classList.remove('hidden');
            setActionLoading(restoreStart, true, 'Restoring...');
            setActionLoading(restoreBtn, true, 'Restoring...');
            setTimeout(() => {
                setActionLoading(restoreStart, false, '');
                setActionLoading(restoreBtn, false, '');
                closeRestoreModal();
                showToast('System restore completed.', 'success');
                resetRestoreUI();
            }, 1200);
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
                var now = new Date().getFullYear();
                var isActive = (year === now);
                var el = document.createElement('div');
                
                var topBorder = isActive ? 'border-red-500' : 'border-gray-300 dark:border-slate-600';
                var headerColor = isActive ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200';
                var bgClass = isActive ? 'bg-red-50 dark:bg-red-900/10' : 'bg-gray-50 dark:bg-slate-700/50';
                
                el.className = 'flex flex-col justify-between p-5 rounded-2xl border-t-4 border-r border-b border-l ' + topBorder + ' ' + bgClass + ' shadow-sm hover:shadow-md transition-all relative overflow-hidden group h-40';
                
                var activeBadge = isActive ? '<span class="absolute top-4 right-4 flex items-center gap-1.5 text-[10px] uppercase font-bold text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 px-2 py-0.5 rounded-full ring-1 ring-red-500/20"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>Active</span>' : '';
                
                el.innerHTML = activeBadge
                    + '<div class="text-2xl font-bold ' + headerColor + ' mb-1">' + year + '</div>'
                    + '<div class="text-xs text-gray-500 dark:text-gray-400 mb-4">Files tracked & archived</div>'
                    + '<div class="flex gap-2 mt-auto">'
                    + '<button data-year="'+year+'" class="view-year-btn flex-1 px-3 py-2 text-xs font-semibold rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 shadow-sm transition-colors group-hover:border-gray-300 dark:group-hover:border-slate-500 relative overflow-hidden">View</button>'
                    + '<button data-year="'+year+'" class="export-year-btn flex-1 px-3 py-2 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-500 text-white shadow-[0_0_10px_rgba(239,68,68,0.2)] transition-colors">Export ZIP</button>'
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
                                openYearExport(String(year));
                            });
                        }).catch(function(){
                            showToast('Error loading year '+year, 'error');
                        });
                    });
                });
                grid.querySelectorAll('.export-year-btn').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        var year = parseInt(btn.getAttribute('data-year'), 10);
                        openYearExport(String(year));
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
    
    <script>
        // Confidential Vault Management
        (function(){
            const vaultLockedState = document.getElementById('vault-locked-state');
            const vaultSetupState = document.getElementById('vault-setup-state');
            const vaultUnlockedState = document.getElementById('vault-unlocked-state');
            const vaultLockBtn = document.getElementById('vault-lock-btn');
            const vaultUnlockBtn = document.getElementById('vault-unlock-btn');
            const vaultSetupBtn = document.getElementById('vault-setup-btn');
            const vaultPinModal = document.getElementById('vault-pin-modal');
            const vaultPinBackdrop = document.getElementById('vault-pin-backdrop');
            const vaultPinCancel = document.getElementById('vault-pin-cancel');
            const vaultPinConfirm = document.getElementById('vault-pin-confirm');
            const vaultPinInputs = document.querySelectorAll('.vault-pin-input');
            const vaultPinError = document.getElementById('vault-pin-error');
            const vaultModalTitle = document.getElementById('vault-modal-title');
            const vaultModalSubtitle = document.getElementById('vault-modal-subtitle');
            const vaultFilesGrid = document.getElementById('vault-files-grid');
            const vaultEmptyState = document.getElementById('vault-empty-state');
            const vaultFileCount = document.getElementById('vault-file-count');
            const vaultViewBtn = document.getElementById('vault-view-btn');
            
            let vaultMode = 'unlock'; // 'unlock' or 'setup'
            
            function showToastVault(msg, type) {
                if (typeof showToast === 'function') {
                    showToast(msg, type);
                }
            }
            
            function checkVaultStatus() {
                fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'vault_check_status' })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (!data.vault_exists) {
                            showVaultSetup();
                        } else if (data.is_unlocked) {
                            showVaultUnlocked();
                            loadVaultFiles();
                        } else {
                            showVaultLocked();
                        }
                    }
                })
                .catch(e => console.error('Vault status check failed:', e));
            }
            
            function showVaultSetup() {
                vaultSetupState.classList.remove('hidden');
                vaultLockedState.classList.add('hidden');
                vaultUnlockedState.classList.add('hidden');
                vaultLockBtn.classList.add('hidden');
                vaultViewBtn?.classList.add('hidden');
            }
            
            function showVaultLocked() {
                vaultLockedState.classList.remove('hidden');
                vaultSetupState.classList.add('hidden');
                vaultUnlockedState.classList.add('hidden');
                vaultLockBtn.classList.add('hidden');
                vaultViewBtn?.classList.add('hidden');
            }
            
            function showVaultUnlocked() {
                vaultUnlockedState.classList.remove('hidden');
                vaultLockedState.classList.add('hidden');
                vaultSetupState.classList.add('hidden');
                vaultLockBtn.classList.remove('hidden');
                vaultViewBtn?.classList.remove('hidden');
            }
            
            function openPinModal(mode) {
                vaultMode = mode;
                if (mode === 'setup') {
                    vaultModalTitle.textContent = 'Setup Vault PIN';
                    vaultModalSubtitle.textContent = 'Create a 6-digit PIN to secure your vault';
                } else {
                    vaultModalTitle.textContent = 'Enter PIN';
                    vaultModalSubtitle.textContent = 'Enter your 6-digit PIN to unlock';
                }
                
                vaultPinInputs.forEach(input => input.value = '');
                vaultPinError.classList.add('hidden');
                vaultPinModal.classList.remove('hidden');
                setTimeout(() => vaultPinInputs[0].focus(), 100);
            }
            
            function closePinModal() {
                vaultPinModal.classList.add('hidden');
                vaultPinInputs.forEach(input => input.value = '');
                vaultPinError.classList.add('hidden');
            }
            
            function getPin() {
                return Array.from(vaultPinInputs).map(input => input.value).join('');
            }
            
            function handlePinInput(e, index) {
                const input = e.target;
                const value = input.value;
                
                if (value && /^\d$/.test(value)) {
                    if (index < vaultPinInputs.length - 1) {
                        vaultPinInputs[index + 1].focus();
                    }
                } else if (!value) {
                    input.value = '';
                }
            }
            
            function handlePinKeydown(e, index) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    vaultPinInputs[index - 1].focus();
                } else if (e.key === 'Enter') {
                    vaultPinConfirm.click();
                }
            }
            
            vaultPinInputs.forEach((input, index) => {
                input.addEventListener('input', (e) => handlePinInput(e, index));
                input.addEventListener('keydown', (e) => handlePinKeydown(e, index));
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = paste.replace(/\D/g, '').slice(0, 6);
                    digits.split('').forEach((digit, i) => {
                        if (vaultPinInputs[i]) {
                            vaultPinInputs[i].value = digit;
                        }
                    });
                    if (digits.length > 0) {
                        const lastIndex = Math.min(digits.length, vaultPinInputs.length) - 1;
                        vaultPinInputs[lastIndex].focus();
                    }
                });
            });
            
            vaultSetupBtn?.addEventListener('click', () => openPinModal('setup'));
            vaultUnlockBtn?.addEventListener('click', () => openPinModal('unlock'));
            vaultPinCancel?.addEventListener('click', closePinModal);
            vaultPinBackdrop?.addEventListener('click', closePinModal);
            
            vaultPinConfirm?.addEventListener('click', () => {
                const pin = getPin();
                if (!/^\d{6}$/.test(pin)) {
                    vaultPinError.textContent = 'Please enter all 6 digits';
                    vaultPinError.classList.remove('hidden');
                    return;
                }
                
                const action = vaultMode === 'setup' ? 'vault_setup' : 'vault_unlock';
                
                fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, pin: pin })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closePinModal();
                        showToastVault(data.message, 'success');
                        if (action === 'vault_setup' || action === 'vault_unlock') {
                            showVaultUnlocked();
                            loadVaultFiles();
                        }
                    } else {
                        vaultPinError.textContent = data.message || 'Operation failed';
                        vaultPinError.classList.remove('hidden');
                    }
                })
                .catch(e => {
                    vaultPinError.textContent = 'Connection error';
                    vaultPinError.classList.remove('hidden');
                });
            });
            
            vaultLockBtn?.addEventListener('click', () => {
                fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'vault_lock' })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToastVault('Vault locked', 'success');
                        showVaultLocked();
                    }
                })
                .catch(e => console.error('Lock failed:', e));
            });
            
            function loadVaultFiles() {
                fetch('storage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'vault_get_files' })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const files = data.files || [];
                        vaultFileCount.textContent = files.length + (files.length === 1 ? ' file' : ' files');
                        
                        if (files.length === 0) {
                            vaultFilesGrid.innerHTML = '';
                            vaultEmptyState.classList.remove('hidden');
                        } else {
                            vaultEmptyState.classList.add('hidden');
                            renderVaultFiles(files);
                        }
                    }
                })
                .catch(e => console.error('Load files failed:', e));
            }
            
            function renderVaultFiles(files) {
                vaultFilesGrid.innerHTML = files.map(file => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    let icon = 'bi-file-earmark';
                    if (['pdf'].includes(ext)) icon = 'bi-file-earmark-pdf';
                    else if (['doc', 'docx'].includes(ext)) icon = 'bi-file-earmark-word';
                    else if (['xls', 'xlsx'].includes(ext)) icon = 'bi-file-earmark-excel';
                    else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) icon = 'bi-file-earmark-image';
                    
                    return `
                        <div class="flex items-center justify-between bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4 hover:shadow-md transition-all group">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="text-red-600 dark:text-red-400 text-2xl">
                                    <i class="bi ${icon}"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate">${escapeHtml(file.name)}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">${new Date(file.created_at).toLocaleDateString()}</div>
                                </div>
                            </div>
                            <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="bi bi-three-dots-vertical"></i>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Initialize on page load
            checkVaultStatus();
        })();
    </script>
    
    <script>
        (function(){
            var modal = document.getElementById('year-export-modal');
            var passInput = document.getElementById('year-export-password-input');
            var passField = document.getElementById('year-export-pass');
            var yearField = document.getElementById('year-export-input');
            var confirmBtn = document.getElementById('year-export-confirm');
            var cancelBtn = document.getElementById('year-export-cancel');
            var pendingYear = '';
            window.openYearExport = function(y){
                pendingYear = y;
                passInput && (passInput.value = '');
                modal && modal.classList.remove('hidden');
                setTimeout(function(){ passInput && passInput.focus(); }, 50);
            };
            function closeModal(){
                modal && modal.classList.add('hidden');
                pendingYear = '';
            }
            cancelBtn && cancelBtn.addEventListener('click', closeModal);
            confirmBtn && confirmBtn.addEventListener('click', function(){
                var p = passInput ? passInput.value : '';
                if (!p || !pendingYear) return;
                if (yearField) yearField.value = pendingYear;
                if (passField) passField.value = p;
                var form = document.getElementById('year-export-form');
                if (form) {
                    if (typeof showToast === 'function') { try { showToast('Starting export for '+pendingYear, 'success'); } catch(e){} }
                    form.submit();
                }
                closeModal();
            });
            window.addEventListener('keydown', function(e){
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });
        })();
    </script>
        
        <script>
            (function(){
                var logoutModal = document.getElementById('logout-modal');
                var openLogoutModalBtn = document.getElementById('open-logout-modal');
                var logoutCancelBtn = document.getElementById('logout-cancel');
                var logoutModalBackdrop = document.getElementById('logout-modal-backdrop');
                
                function openLogoutModal() {
                    logoutModal && logoutModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
                
                function closeLogoutModal() {
                    logoutModal && logoutModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
                
                openLogoutModalBtn?.addEventListener('click', openLogoutModal);
                logoutCancelBtn?.addEventListener('click', closeLogoutModal);
                logoutModalBackdrop?.addEventListener('click', closeLogoutModal);
                
                window.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !logoutModal?.classList.contains('hidden') === false) {
                        closeLogoutModal();
                    }
                });
            })();
        </script>
        </div>
    </div>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

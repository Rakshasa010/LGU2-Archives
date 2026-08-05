<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['action']) && $_GET['action'] === 'get_storage_data') {
    require 'authdatabase.php';
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $conn->close();
        exit();
    }
    if (!function_exists('storage_dir_metrics')) {
        require_once __DIR__ . '/includes/storage_shared.php';
    }
    $storage = storage_dir_metrics(__DIR__ . '/uploads');
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
        $zipPassword = trim((string)($_POST['zip_password'] ?? ''));
        $zipPasswordConfirm = trim((string)($_POST['zip_password_confirm'] ?? ''));
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
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Export</title><style>body{font-family:Arial;padding:24px;color:#222}</style></head><body><h3>Export Blocked</h3><p>Invalid account password. Use the same password you use to log in.</p></body></html>';
            $conn->close();
            exit();
        }
        if ($zipPassword === '' || strlen($zipPassword) < 4) {
            http_response_code(400);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Export</title><style>body{font-family:Arial;padding:24px;color:#222}</style></head><body><h3>Export Blocked</h3><p>ZIP password is required and must be at least 4 characters.</p></body></html>';
            $conn->close();
            exit();
        }
        if ($zipPassword !== $zipPasswordConfirm) {
            http_response_code(400);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Export</title><style>body{font-family:Arial;padding:24px;color:#222}</style></head><body><h3>Export Blocked</h3><p>ZIP passwords do not match. Please try again.</p></body></html>';
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
        $nabout = 'Export (Archives ZIP)'; $nstatus = 'unread';
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
        $zip->setPassword($zipPassword);
        $zipEncryptMethod = defined('ZipArchive::EM_AES_256') ? ZipArchive::EM_AES_256 : (defined('ZipArchive::EM_AES_128') ? ZipArchive::EM_AES_128 : null);
        $addEncryptedFile = function($disk, $entry) use ($zip, $zipEncryptMethod) {
            $zip->addFile($disk, $entry);
            if ($zipEncryptMethod !== null && method_exists($zip, 'setEncryptionName')) {
                $zip->setEncryptionName($entry, $zipEncryptMethod);
            }
        };
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
                    $addEncryptedFile($disk, $entry);
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
                    $addEncryptedFile($disk, $entry);
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
    if ($action === 'year_overview') {
        header('Content-Type: application/json');
        $current = (int)date('Y');
        $result = [];
        for ($y = $current; $y >= $current - 4; $y--) {
            $result[(string)$y] = ['files' => 0, 'categories' => 0, 'size' => 0];
        }
        $leg = $conn->query("SELECT YEAR(created_at) AS yr, COUNT(*) AS cnt, COUNT(DISTINCT type) AS cats, COALESCE(SUM(file_size),0) AS sz FROM legislative_records WHERE parent_version_id IS NULL AND YEAR(created_at) BETWEEN ".($current - 4)." AND ".$current." GROUP BY YEAR(created_at)");
        if ($leg) {
            while ($row = $leg->fetch_assoc()) {
                $yr = (string)$row['yr'];
                if (isset($result[$yr])) {
                    $result[$yr]['files'] += (int)$row['cnt'];
                    $result[$yr]['categories'] += (int)$row['cats'];
                    $result[$yr]['size'] += (int)$row['sz'];
                }
            }
        }
        $arc = $conn->query("SELECT YEAR(af.created_at) AS yr, COUNT(*) AS cnt, COUNT(DISTINCT af.folder_id) AS cats, COALESCE(SUM(af.file_size),0) AS sz FROM archive_files af WHERE af.created_at IS NOT NULL AND YEAR(af.created_at) BETWEEN ".($current - 4)." AND ".$current." GROUP BY YEAR(af.created_at)");
        if ($arc) {
            while ($row = $arc->fetch_assoc()) {
                $yr = (string)$row['yr'];
                if (isset($result[$yr])) {
                    $result[$yr]['files'] += (int)$row['cnt'];
                    $result[$yr]['categories'] += (int)$row['cats'];
                    $result[$yr]['size'] += (int)$row['sz'];
                }
            }
        }
        echo json_encode(['success' => true, 'years' => $result]);
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
        $reserved = ['ordinances-resolution', 'public-hearings', 'meeting-records', 'other-documents'];
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
        $prefix = generate_document_prefix($name);
        $ins = $conn->prepare("INSERT INTO archive_folders (name, slug, created_by, document_prefix) VALUES (?, ?, ?, ?)");
        if (!$ins) {
            echo json_encode(['success' => false, 'message' => 'Could not create folder']);
            $conn->close();
            exit();
        }
        $ins->bind_param("ssis", $name, $slug, $uid, $prefix);
        if ($ins->execute()) {
            echo json_encode(['success' => true, 'folder' => ['id' => $conn->insert_id, 'name' => $name, 'slug' => $slug, 'color' => folder_color_class($name)]]);
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
    
    // Hidden folder operations
    if ($action === 'hidden_folder_setup') {
        $pin = isset($payload['pin']) ? trim((string)$payload['pin']) : '';
        if (!preg_match('/^\d{6}$/', $pin)) {
            echo json_encode(['success' => false, 'message' => 'PIN must be exactly 6 digits']);
            $conn->close();
            exit();
        }
        $uid = (int)$_SESSION['user_id'];
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        
        // Check if user already has a hidden folder
        $check = $conn->prepare("SELECT id FROM user_hidden_folders WHERE user_id = ?");
        $check->bind_param("i", $uid);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $check->close();
            // Update existing folder
            $stmt = $conn->prepare("UPDATE user_hidden_folders SET pin_hash = ?, is_setup = TRUE WHERE user_id = ?");
            $stmt->bind_param("si", $pin_hash, $uid);
        } else {
            $check->close();
            // Create new folder
            $stmt = $conn->prepare("INSERT INTO user_hidden_folders (user_id, pin_hash, is_setup) VALUES (?, ?, TRUE)");
            $stmt->bind_param("is", $uid, $pin_hash);
        }
        
        if ($stmt->execute()) {
            $_SESSION['hidden_folder_unlocked'] = true;
            // Log to audit
            $ntime = date('h:i A');
            $ndate = date('Y-m-d');
            $ncontent = 'Hidden folder set up by user #' . $uid;
            $nabout = 'Hidden Folder';
            $nstatus = 'unread';
            
            if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Hidden folder set up successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to set up hidden folder']);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
    
    if ($action === 'hidden_folder_unlock') {
        $pin = isset($payload['pin']) ? trim((string)$payload['pin']) : '';
        if (!preg_match('/^\d{6}$/', $pin)) {
            echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
            $conn->close();
            exit();
        }
        
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT pin_hash, is_setup FROM user_hidden_folders WHERE user_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Hidden folder not found']);
            $stmt->close();
            $conn->close();
            exit();
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row['is_setup']) {
            echo json_encode(['success' => false, 'message' => 'Hidden folder not set up']);
            $conn->close();
            exit();
        }
        
        if (password_verify($pin, $row['pin_hash'])) {
            $_SESSION['hidden_folder_unlocked'] = true;
            
            // Log to audit
            $ntime = date('h:i A');
            $ndate = date('Y-m-d');
            $ncontent = 'Hidden folder unlocked by user #' . $uid;
            $nabout = 'Hidden Folder';
            $nstatus = 'unread';
            
            if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Hidden folder unlocked']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
        }
        $conn->close();
        exit();
    }
    
    if ($action === 'hidden_folder_lock') {
        // Log to audit before clearing session
        $uid = (int)$_SESSION['user_id'];
        $ntime = date('h:i A');
        $ndate = date('Y-m-d');
        $ncontent = 'Hidden folder locked by user #' . $uid;
        $nabout = 'Hidden Folder';
        $nstatus = 'unread';
        
        if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
            $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
            $notif->execute();
            $notif->close();
        }
        
        unset($_SESSION['hidden_folder_unlocked']);
        echo json_encode(['success' => true, 'message' => 'Hidden folder locked']);
        $conn->close();
        exit();
    }
    
    if ($action === 'hidden_folder_get_files') {
        if (!isset($_SESSION['hidden_folder_unlocked']) || $_SESSION['hidden_folder_unlocked'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Hidden folder is locked']);
            $conn->close();
            exit();
        }
        
        $uid = (int)$_SESSION['user_id'];
        $files = [];
        $stmt = $conn->prepare("SELECT id, name, file_path, created_at FROM hidden_files WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'files' => $files]);
        $conn->close();
        exit();
    }
    
    if ($action === 'hidden_folder_check_status') {
        $uid = (int)$_SESSION['user_id'];
        $folder_exists = false;
        $folder_setup = false;
        $is_unlocked = isset($_SESSION['hidden_folder_unlocked']) && $_SESSION['hidden_folder_unlocked'] === true;
        
        $stmt = $conn->prepare("SELECT is_setup FROM user_hidden_folders WHERE user_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $folder_exists = true;
            $row = $result->fetch_assoc();
            $folder_setup = (bool)$row['is_setup'];
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'folder_exists' => $folder_exists,
            'folder_setup' => $folder_setup,
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
        'Public Hearing' => 'Public Hearings',
        'Meeting' => 'Meeting Records',
        'Other' => 'Other / External Documents'
    ];

    // Define custom prefixes for specific folders as per user request
    $custom_prefixes = [
        'Ordinances & Resolutions' => 'Ordinance-Resolution',
        'Meeting Records' => 'Meeting-Records'
    ];

    foreach ($folder_types as $type => $name) {
        // Check if folder exists
        $checkStmt = $conn->prepare("SELECT id, document_prefix FROM legislative_folders WHERE type = ? AND parent_id IS NULL LIMIT 1");
        $checkStmt->bind_param("s", $type);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($folder = $checkResult->fetch_assoc()) {
            $legislative_folders[$type] = $folder['id'];
            // If document prefix is missing, update it
            if (empty($folder['document_prefix'])) {
                $prefix = isset($custom_prefixes[$name]) ? $custom_prefixes[$name] : generate_document_prefix($name);
                $updateStmt = $conn->prepare("UPDATE legislative_folders SET document_prefix = ? WHERE id = ?");
                $updateStmt->bind_param("si", $prefix, $folder['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } else {
            // Create folder if it doesn't exist
            $prefix = isset($custom_prefixes[$name]) ? $custom_prefixes[$name] : generate_document_prefix($name);
            $insertStmt = $conn->prepare("INSERT INTO legislative_folders (name, type, parent_id, document_prefix) VALUES (?, ?, NULL, ?)");
            $insertStmt->bind_param("sss", $name, $type, $prefix);
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

    // Deterministic folder icon color from the folder name (FNV-1a over a 10-color palette).
    function folder_color_class($name) {
        $palette = [
            'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400',
            'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400',
            'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400',
            'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400',
            'bg-teal-100 dark:bg-teal-900/40 text-teal-600 dark:text-teal-400',
            'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
            'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400',
            'bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400',
            'bg-pink-100 dark:bg-pink-900/40 text-pink-600 dark:text-pink-400',
            'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400',
        ];
        $hash = 2166136261;
        $s = strtolower((string)$name);
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($s[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }
        return $palette[$hash % count($palette)];
    }

    function calculateStorageMetrics($conn) {
        if (!function_exists('storage_dir_metrics')) {
            require_once __DIR__ . '/includes/storage_shared.php';
        }
        return storage_dir_metrics(__DIR__ . '/uploads');
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
    $archive_folders_all = $archive_folders;
    $archive_folders_total = count($archive_folders);
    $page_size_folders = 20;
    $archive_folders = array_slice($archive_folders, 0, $page_size_folders);
    $conn->close();
    
    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;
    ?>

    <div>
        <?php
        $sidebar_active_page = 'storage';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <!-- Main Content -->
        <div class="flex flex-col min-h-screen md:ml-72">
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
                <!-- Content Wrapper with Max Width -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="space-y-6">
               
                    <!-- Recent Archives Section -->
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Yearly Archives</h2>
                            <form id="year-export-form" method="POST" action="storage.php" target="_blank" class="hidden">
                                <input type="hidden" name="action" value="export_year_zip">
                                <input type="hidden" name="year" id="year-export-input" value="">
                                <input type="hidden" name="confirm_password" id="year-export-pass" value="">
                                <input type="hidden" name="zip_password" id="year-export-zip-pass" value="">
                                <input type="hidden" name="zip_password_confirm" id="year-export-zip-pass-confirm" value="">
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
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Enter your account password and provide the ZIP password for the export.</div>
                                    <div id="year-export-zip-mode-text" class="text-xs text-gray-500 dark:text-gray-400">First time? Create a ZIP password below.</div>
                                    <div class="relative">
                                        <input type="password" id="year-export-password-input" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100" placeholder="Account password" />
                                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400" data-toggle-password="year-export-password-input" aria-label="Show account password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input type="password" id="year-export-zip-password-input" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100" placeholder="Create ZIP password (min 4 chars)" />
                                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400" data-toggle-password="year-export-zip-password-input" aria-label="Show ZIP password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div id="year-export-zip-confirm-wrap" class="relative">
                                        <input type="password" id="year-export-zip-password-confirm-input" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100" placeholder="Confirm ZIP password" />
                                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400" data-toggle-password="year-export-zip-password-confirm-input" aria-label="Show ZIP confirmation password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
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
                <a href="folder_view.php?id=<?php echo $legislative_folders['Other']; ?>&legislative=true" data-archive="other-documents" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-amber-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/40 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 text-2xl group-hover:scale-110 transition-transform">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();">
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="min-w-0 mt-4">
                        <div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">Other / External Docs</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="other-documents">Calculating...</div>
                    </div>
                </a>
                <?php foreach ($archive_folders as $folder): ?>
                <a id="folder-card-<?php echo (int)$folder['id']; ?>" href="folder_view.php?id=<?php echo $folder['id']; ?>" data-archive="<?php echo htmlspecialchars($folder['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-slate-500/50 transition-all group h-40">
                    <div class="flex items-start justify-between">
                        <div class="w-12 h-12 <?php echo folder_color_class($folder['name'] ?? ''); ?> rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform relative overflow-hidden">
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
            <!-- Folders Pagination -->
            <div id="foldersPagination" class="mt-4"></div>
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
                <?php foreach ($archive_folders_all as $folder): ?>
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

    <script src="assets/js/pagination.js"></script>
    <script>
        // Enhanced Storage Donut Chart Initialization
        // storageData lives at script scope so it can be updated by AJAX
        let storageData = {
            used: <?php echo ($capacityBytes > 0) ? $totalBytes / (1024*1024*1024) : 0; ?>,
            total: <?php echo round($capacityBytes / (1024*1024*1024), 3); ?>
        };
        function initStorageDonut() {
            // note: storageData may be modified externally

            const percentageRaw = (storageData.total > 0) ? (storageData.used / storageData.total) * 100 : 0;
            const percentage = Math.round(percentageRaw * 100) / 100;
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
            window.formatBytes = formatBytes;

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
            window.folderColorFallback = (name) => {
            const palette = [
                'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400',
                'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400',
                'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400',
                'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400',
                'bg-teal-100 dark:bg-teal-900/40 text-teal-600 dark:text-teal-400',
                'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
                'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400',
                'bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400',
                'bg-pink-100 dark:bg-pink-900/40 text-pink-600 dark:text-pink-400',
                'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400'
            ];
            let hash = 2166136261;
            const s = String(name || '').toLowerCase();
            for (let i = 0; i < s.length; i++) {
                hash ^= s.charCodeAt(i);
                hash = (hash * 16777619) >>> 0;
            }
            return palette[hash % palette.length];
        };
        const createFolderCard = (name, slug, id, color) => {
            const safeName = escapeHtml(name);
            const safeSlug = escapeHtml(slug);
            const iconColor = color || folderColorFallback(name);
            const card = document.createElement('a');
            card.href = 'folder_view.php?id=' + id;
            card.setAttribute('data-archive', slug);
            card.className = 'block bg-gradient-to-br from-white to-gray-50 dark:from-slate-700 dark:to-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-5 hover:shadow-xl transition-all group';
            card.innerHTML = `
                <div class="w-12 h-12 ${iconColor} rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform relative overflow-hidden">
                    <i class="bi bi-folder-fill"></i>
                    <div class="absolute inset-0 flex items-center justify-center text-white dark:text-slate-800 text-[14px] mt-1 z-10">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1 mt-3">${safeName}</div>
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
                const card = createFolderCard(folder.name, folder.slug, folder.id, folder.color);
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
            function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
            var yearStats = {};
            function loadYearStats(cb){
                fetch('storage.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'year_overview' })
                }).then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.success && d.years) yearStats = d.years;
                    if (typeof cb === 'function') cb();
                }).catch(function(){ if (typeof cb === 'function') cb(); });
            }
            function yearCard(year){
                var now = new Date().getFullYear();
                var isActive = (year === now);
                var el = document.createElement('div');
                
                var topBorder = isActive ? 'border-red-500' : 'border-gray-300 dark:border-slate-600';
                var headerColor = isActive ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200';
                var bgClass = isActive ? 'bg-red-50 dark:bg-red-900/10' : 'bg-gray-50 dark:bg-slate-700/50';
                
                el.className = 'flex flex-col justify-between p-5 rounded-2xl border-t-4 border-r border-b border-l ' + topBorder + ' ' + bgClass + ' shadow-sm hover:shadow-md transition-all relative overflow-hidden group min-h-40';
                
                var activeBadge = isActive ? '<span class="absolute top-4 right-4 flex items-center gap-1.5 text-[10px] uppercase font-bold text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 px-2 py-0.5 rounded-full ring-1 ring-red-500/20"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>Active</span>' : '';
                
                var st = yearStats[year] || {};
                var files = st.files || 0;
                var cats = st.categories || 0;
                var sizeLabel = st.size ? (typeof window.formatBytes === 'function' ? window.formatBytes(st.size) : (Math.round(st.size/1048576) + ' MB')) : '0 B';
                
                el.innerHTML = activeBadge
                    + '<div class="text-2xl font-bold ' + headerColor + ' mb-1">' + year + '</div>'
                    + '<div class="text-xs text-gray-500 dark:text-gray-400 mb-3">Files tracked & archived</div>'
                    + '<div class="grid grid-cols-3 gap-2 mb-4">'
                    + '<div class="rounded-lg bg-white/70 dark:bg-slate-800/70 border border-gray-200 dark:border-slate-600 px-2 py-1.5 text-center"><div class="text-sm font-bold text-gray-800 dark:text-gray-200">'+files+'</div><div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Files</div></div>'
                    + '<div class="rounded-lg bg-white/70 dark:bg-slate-800/70 border border-gray-200 dark:border-slate-600 px-2 py-1.5 text-center"><div class="text-sm font-bold text-gray-800 dark:text-gray-200">'+cats+'</div><div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Cat.</div></div>'
                    + '<div class="rounded-lg bg-white/70 dark:bg-slate-800/70 border border-gray-200 dark:border-slate-600 px-2 py-1.5 text-center"><div class="text-sm font-bold text-gray-800 dark:text-gray-200">'+sizeLabel+'</div><div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</div></div>'
                    + '</div>'
                    + '<div class="flex gap-2 mt-auto">'
                    + '<button data-year="'+year+'" class="view-year-btn flex-1 px-3 py-2 text-xs font-semibold rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 shadow-sm transition-colors group-hover:border-gray-300 dark:group-hover:border-slate-500 relative overflow-hidden">View</button>'
                    + '<button data-year="'+year+'" class="export-year-btn flex-1 px-3 py-2 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-500 text-white shadow-[0_0_10px_rgba(239,68,68,0.2)] transition-colors">Export ZIP</button>'
                    + '</div>';
                return el;
            }
            function renderYearCards(){
                var grid = document.getElementById('yearly-archives-grid');
                if (!grid) return;
                loadYearStats(function(){
                    var now = new Date();
                    var current = now.getFullYear();
                    grid.innerHTML = '';
                    for (var y = current; y >= current - 4; y--){
                        grid.appendChild(yearCard(y));
                    }
                    bindYearCardEvents(grid);
                });
            }
            function bindYearCardEvents(grid){
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
        (function(){
            var modal = document.getElementById('year-export-modal');
            var passInput = document.getElementById('year-export-password-input');
            var zipPassInput = document.getElementById('year-export-zip-password-input');
            var zipPassConfirmInput = document.getElementById('year-export-zip-password-confirm-input');
            var zipConfirmWrap = document.getElementById('year-export-zip-confirm-wrap');
            var zipModeText = document.getElementById('year-export-zip-mode-text');
            var passField = document.getElementById('year-export-pass');
            var zipPassField = document.getElementById('year-export-zip-pass');
            var zipPassConfirmField = document.getElementById('year-export-zip-pass-confirm');
            var yearField = document.getElementById('year-export-input');
            var confirmBtn = document.getElementById('year-export-confirm');
            var cancelBtn = document.getElementById('year-export-cancel');
            var pendingYear = '';
            var ZIP_STORAGE_KEY = 'year_export_zip_password';
            function showExportError(message){
                if (typeof showToast === 'function') {
                    try { showToast(message, 'error'); } catch (e) { alert(message); }
                } else {
                    alert(message);
                }
            }
            function getSavedZipPassword(){
                try { return localStorage.getItem(ZIP_STORAGE_KEY) || ''; } catch (e) { return ''; }
            }
            function setSavedZipPassword(value){
                try { if (value) localStorage.setItem(ZIP_STORAGE_KEY, value); else localStorage.removeItem(ZIP_STORAGE_KEY); } catch (e) {}
            }
            function updateZipMode(){
                var saved = getSavedZipPassword();
                var hasSaved = !!saved;
                if (zipModeText) {
                    zipModeText.textContent = hasSaved ? 'Using your saved ZIP password. Enter it again to continue.' : 'First time? Create a ZIP password below.';
                }
                if (zipConfirmWrap) {
                    zipConfirmWrap.style.display = hasSaved ? 'none' : '';
                }
                if (zipPassConfirmInput) {
                    zipPassConfirmInput.value = '';
                }
                if (zipPassInput) {
                    zipPassInput.value = '';
                    zipPassInput.placeholder = hasSaved ? 'ZIP password' : 'Create ZIP password (min 4 chars)';
                }
            }
            window.openYearExport = function(y){
                pendingYear = y;
                passInput && (passInput.value = '');
                zipPassInput && (zipPassInput.value = '');
                zipPassConfirmInput && (zipPassConfirmInput.value = '');
                updateZipMode();
                modal && modal.classList.remove('hidden');
                setTimeout(function(){ passInput && passInput.focus(); }, 50);
            };
            function closeModal(){
                modal && modal.classList.add('hidden');
                pendingYear = '';
            }
            cancelBtn && cancelBtn.addEventListener('click', closeModal);
            document.querySelectorAll('[data-toggle-password]').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var targetId = btn.getAttribute('data-toggle-password');
                    var input = targetId ? document.getElementById(targetId) : null;
                    if (!input) return;
                    var showing = input.type === 'text';
                    input.type = showing ? 'password' : 'text';
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
                    }
                    btn.setAttribute('aria-label', showing ? 'Show ' + (targetId === 'year-export-password-input' ? 'account password' : targetId === 'year-export-zip-password-input' ? 'ZIP password' : 'ZIP confirmation password') : 'Hide ' + (targetId === 'year-export-password-input' ? 'account password' : targetId === 'year-export-zip-password-input' ? 'ZIP password' : 'ZIP confirmation password'));
                });
            });
            confirmBtn && confirmBtn.addEventListener('click', function(){
                var p = passInput ? passInput.value : '';
                var zipP = zipPassInput ? zipPassInput.value : '';
                var zipPC = zipPassConfirmInput ? zipPassConfirmInput.value : '';
                var saved = getSavedZipPassword();
                if (!p || !pendingYear) {
                    showExportError('Please enter your account password and select a year.');
                    return;
                }
                if (!zipP || zipP.length < 4) {
                    showExportError('ZIP password must be at least 4 characters.');
                    return;
                }
                if (!saved) {
                    if (!zipPC || zipP !== zipPC) {
                        showExportError('ZIP passwords do not match.');
                        return;
                    }
                    setSavedZipPassword(zipP);
                } else if (saved && zipP !== saved) {
                    showExportError('The ZIP password does not match your saved password.');
                    return;
                }
                if (yearField) yearField.value = pendingYear;
                if (passField) passField.value = p;
                if (zipPassField) zipPassField.value = zipP;
                if (zipPassConfirmField) zipPassConfirmField.value = zipP;
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
        // Archive Folders Pagination
        (function(){
            const foldersGrid = document.getElementById('archive-folders-grid');
            const legisHeaders = foldersGrid ? foldersGrid.querySelectorAll('a[href*="legislative=true"]') : [];
            const legisCount = legisHeaders.length;
            const foldersPagination = new PaginationControls('foldersPagination', { onPageChange: loadFoldersPage });
            foldersPagination.update(<?php echo $archive_folders_total; ?>);

            if (<?php echo $archive_folders_total; ?> <= <?php echo $page_size_folders; ?>) {
                document.getElementById('foldersPagination').style.display = 'none';
            }

            function loadFoldersPage(page) {
                fetch('archives_api.php?action=list_folders&page=' + page + '&page_size=<?php echo $page_size_folders; ?>')
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.success) return;
                        // Remove user-created cards (keep legislative headers)
                        var allCards = Array.from(foldersGrid.querySelectorAll('a[data-archive]'));
                        allCards.forEach(function(card){
                            if (!card.getAttribute('href').includes('legislative=true')) {
                                card.remove();
                            }
                        });
                        // Add new cards
                        (data.folders || []).forEach(function(folder){
                            var card = document.createElement('a');
                            card.id = 'folder-card-' + folder.id;
                            card.href = 'folder_view.php?id=' + folder.id;
                            card.setAttribute('data-archive', folder.slug || '');
                            card.className = 'flex flex-col justify-between bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-slate-500/50 transition-all group h-40';
                            var createdDate = new Date(folder.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            var iconColor = folder.color || (typeof window.folderColorFallback === 'function' ? window.folderColorFallback(folder.name) : 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400');
                            card.innerHTML = '<div class="flex items-start justify-between"><div class="w-12 h-12 ' + iconColor + ' rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform relative overflow-hidden"><i class="bi bi-folder-fill"></i><div class="absolute inset-0 flex items-center justify-center text-white dark:text-slate-800 text-[14px] mt-1 z-10"><i class="bi bi-person-fill"></i></div></div><div class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="event.preventDefault();"><i class="bi bi-three-dots"></i></div></div><div class="min-w-0 mt-4"><div class="font-bold text-gray-900 dark:text-gray-100 text-lg truncate">' + (folder.name || '').replace(/</g, '&lt;') + '</div><div class="text-sm text-gray-500 dark:text-gray-400 archive-meta mt-1" data-archive-meta="' + (folder.slug || '').replace(/</g, '&lt;') + '">Created: ' + createdDate + '</div></div>';
                            foldersGrid.appendChild(card);
                        });
                    })
                    .catch(function(e){ console.error('Folder pagination error:', e); });
            }
        })();
    </script>

<script src="assets/js/folder-otp.js"></script>
    <script>
    (function () {
        var grid = document.getElementById('archive-folders-grid');
        if (!grid || !window.folderOTP) return;
        grid.addEventListener('click', function (e) {
            if (e.target.closest('.bi-three-dots')) return;
            var link = e.target.closest('a[href*="folder_view.php"]');
            if (!link) return;
            e.preventDefault();
            window.folderOTP.guard(link.href);
        });
    })();
    </script>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    </div>
    </div>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

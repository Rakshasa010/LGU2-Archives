<?php
require 'authdatabase.php';
// session_start() removed as it is handled in authdatabase.php

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_legislative = isset($_GET['legislative']) ? true : false;
if ($current_folder_id === 0) {
    header("Location: storage.php");
    exit();
}

// Fetch current folder info - check legislative first
$current_folder = null;
if ($is_legislative) {
    $stmt = $conn->prepare("SELECT * FROM legislative_folders WHERE id = ?");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $current_folder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM archive_folders WHERE id = ?");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $current_folder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$current_folder) {
    header("Location: storage.php");
    exit();
}

// Get parent folder for breadcrumb/back link
$parent_folder = null;
if (!$is_legislative && $current_folder['parent_id']) {
    $stmt = $conn->prepare("SELECT id, name FROM archive_folders WHERE id = ?");
    $stmt->bind_param("i", $current_folder['parent_id']);
    $stmt->execute();
    $parent_folder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a JSON request or standard form data
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $input['action'] ?? '';

    if ($action === 'create_folder') {
        header('Content-Type: application/json');
        $name = $_POST['name'] ?? $input['name'] ?? '';
        if (empty($name)) {
             echo json_encode(['success' => false, 'message' => 'Folder name required']);
             exit();
        }
        $parent_id = $current_folder_id;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . time();

        $stmt = $conn->prepare("INSERT INTO archive_folders (name, slug, parent_id, created_by) VALUES (?, ?, ?, ?)");
        $uid = $_SESSION['user_id'];
        $stmt->bind_param("ssii", $name, $slug, $parent_id, $uid);
        if ($stmt->execute()) {
             $new_id = $conn->insert_id;
             $folder_path = "uploads/archives/" . $new_id . "/";
             if (!file_exists($folder_path)) {
                 @mkdir($folder_path, 0777, true);
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
             $ncontent = 'Folder created: ' . $name . ' (ID ' . $new_id . ')';
             $nabout = 'Storage'; $nstatus = 'unread';
             if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                 $ins->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                 $ins->execute(); $ins->close();
             }
             echo json_encode(['success' => true, 'folder' => ['id' => $new_id, 'name' => $name, 'slug' => $slug]]);
        } else {
             echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
        }
        exit();
    }
    
    if ($action === 'upload_files_bulk') {
        header('Content-Type: application/json');
        
        // Check if POST was truncated due to post_max_size
        if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $max_size = ini_get('post_max_size');
            echo json_encode(['success' => false, 'message' => "Total upload size exceeds the limit ($max_size)."]);
            exit();
        }

        if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
            $author = $_POST['fileAuthor'] ?? null;
            $fdate = $_POST['fileDate'] ?? null;
            $unq_base = $_POST['fileUniqueNumber'] ?? null;
            if ($fdate === '') $fdate = null;

            $uploadedFiles = [];
            $errors = [];
            $target_dir = "uploads/archives/" . $current_folder_id . "/";
            if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }

            // Ensure columns exist (granular maintenance)
            $cols_needed = [
                'author' => "VARCHAR(255) DEFAULT NULL",
                'file_date' => "DATE DEFAULT NULL",
                'unique_number' => "VARCHAR(100) DEFAULT NULL",
                'version' => "INT DEFAULT 1",
                'parent_version_id' => "INT NULL"
            ];
            foreach ($cols_needed as $col => $def) {
                if ($conn->query("SHOW COLUMNS FROM archive_files LIKE '$col'")->num_rows == 0) {
                    $conn->query("ALTER TABLE archive_files ADD COLUMN $col $def");
                }
            }
            
            $log_file = 'uploads/archives/upload_log.txt';
            function log_upload_error($msg) {
                global $log_file;
                @file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
            }

            
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                $errCode = $_FILES['files']['error'][$i];
                if ($errCode === UPLOAD_ERR_OK) {
                    $name = $_FILES['files']['name'][$i];
                    $tmp_name = $_FILES['files']['tmp_name'][$i];
                    
                    $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $name);
                    $file_path = $target_dir . $safe_name;
                    $counter = 1;
                    $path_info = pathinfo($safe_name);
                    $base_name = $path_info['filename'];
                    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';

                    while (file_exists($file_path)) {
                        $file_path = $target_dir . $base_name . '_' . $counter . $extension;
                        $counter++;
                    }

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $final_name = basename($file_path);
                        $unq = empty($unq_base) ? null : ($unq_base . ($count > 1 ? "-$i" : ''));
                        $isBlankUnq = empty($unq);
                        $version = 1;
                        $parent_version_id = null;

                        $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path, author, file_date, unique_number, version, parent_version_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssii", $current_folder_id, $final_name, $file_path, $author, $fdate, $unq, $version, $parent_version_id);
                        if ($stmt->execute()) {
                            $new_id = $conn->insert_id;
                            if ($isBlankUnq) {
                                $unq = sprintf("DOC-%06d", $new_id);
                                $conn->query("UPDATE archive_files SET unique_number = '$unq' WHERE id = $new_id");
                            }

                            $bytes = @filesize($file_path) ?: 0;
                            $ext = strtolower(pathinfo($final_name, PATHINFO_EXTENSION));
                            $rtype = 'other';
                            if (in_array($ext, ['mp4','webm','ogg'])) $rtype = 'video';
                            elseif ($ext === 'pdf') $rtype = 'pdf';
                            elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $rtype = 'image';
                            
                            if ($ev = $conn->prepare("INSERT INTO analytics_events (event_type, user_id, record_id, record_title, record_type, bytes) VALUES (?,?,?,?,?,?)")) {
                                $etype = 'upload';
                                $uid = $_SESSION['user_id'] ?? null;
                                $ev->bind_param('sisssi', $etype, $uid, $new_id, $final_name, $rtype, $bytes);
                                $ev->execute();
                                $ev->close();
                            }

                            $uploadedFiles[] = [
                                'id' => $new_id,
                                'name' => $final_name,
                                'file_path' => $file_path,
                                'author' => $author,
                                'file_date' => $fdate,
                                'unique_number' => $unq,
                                'size' => $bytes,
                                'created_at' => date('Y-m-d H:i:s'),
                                'folder_id' => $current_folder_id
                            ];
                        } else {
                            $db_err = $stmt->error;
                            log_upload_error("Database insertion failed for $name: $db_err");
                            $errors[] = "Database error for " . $_FILES['files']['name'][$i] . " ($db_err)";
                        }
                    } else {
                        log_upload_error("Failed to move uploaded file $tmp_name to $file_path. Check permissions for $target_dir");
                        $errors[] = "Failed to move uploaded file: " . $_FILES['files']['name'][$i];
                    }
                } else {
                    switch ($errCode) {
                        case UPLOAD_ERR_INI_SIZE:   $errors[] = "{$_FILES['files']['name'][$i]}: File exceeds server limit."; break;
                        case UPLOAD_ERR_FORM_SIZE:  $errors[] = "{$_FILES['files']['name'][$i]}: File exceeds form limit."; break;
                        case UPLOAD_ERR_PARTIAL:    $errors[] = "{$_FILES['files']['name'][$i]}: Upload was unstable/interrupted. Please try again."; break;
                        case UPLOAD_ERR_NO_FILE:    $errors[] = "{$_FILES['files']['name'][$i]}: No file was uploaded."; break;
                        case UPLOAD_ERR_NO_TMP_DIR: $errors[] = "{$_FILES['files']['name'][$i]}: Missing temporary folder on server."; break;
                        case UPLOAD_ERR_CANT_WRITE: $errors[] = "{$_FILES['files']['name'][$i]}: Failed to write to disk."; break;
                        default: 
                            $err_msg = "Unknown upload error ($errCode).";
                            log_upload_error("Upload error for {$_FILES['files']['name'][$i]}: code $errCode");
                            $errors[] = "{$_FILES['files']['name'][$i]}: $err_msg"; 
                            break;
                    }
                }
            }

            if (!empty($uploadedFiles)) {
                $num = count($uploadedFiles);
                $ntime = date('h:i A'); $ndate = date('Y-m-d');
                $ncontent = ($num > 1) ? "$num files uploaded in folder #$current_folder_id" : "New upload: {$uploadedFiles[0]['name']} in folder #$current_folder_id";
                $nabout = 'Upload'; $nstatus = 'unread';
                if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                    $ins->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                    $ins->execute(); $ins->close();
                }
                echo json_encode([
                    'success' => true, 
                    'files' => $uploadedFiles,
                    'errors' => $errors
                ]);
            } else {
                $msg = !empty($errors) ? implode(' ', $errors) : 'Failed to upload files';
                echo json_encode(['success' => false, 'message' => $msg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No files provided or invalid request structure.']);
        }
        exit();
    }
    if ($action === 'check_duplicate') {
        header('Content-Type: application/json');
        $name = $_POST['name'] ?? $input['name'] ?? '';
        if ($name === '') {
            echo json_encode(['success' => false, 'exists' => false]);
            exit();
        }
        $stmt = $conn->prepare("SELECT id FROM archive_files WHERE folder_id = ? AND name = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("is", $current_folder_id, $name);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = ($res && $res->num_rows > 0);
            $stmt->close();
            echo json_encode(['success' => true, 'exists' => $exists]);
            exit();
        }
        echo json_encode(['success' => false, 'exists' => false]);
        exit();
    }

    
    if ($action === 'search_folder') {
        header('Content-Type: application/json');
        $term = $_POST['term'] ?? $input['term'] ?? '';
        $term = trim($term);
        if ($term === '') {
            echo json_encode(['success' => true, 'folders' => [], 'files' => []]);
            exit();
        }
        $like = '%' . $conn->real_escape_string($term) . '%';
        $folders = [];
        $files = [];
        // Search subfolders under current folder
        $stmt = $conn->prepare("SELECT id, name, created_at FROM archive_folders WHERE parent_id = ? AND name LIKE ? ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param("is", $current_folder_id, $like);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $folders[] = $row;
            }
        }
        $stmt->close();
        // Search files within retention
        $stmt = $conn->prepare("SELECT id, name, created_at, file_path FROM archive_files WHERE folder_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR) AND (name LIKE ? OR name LIKE ?) ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("iss", $current_folder_id, $like, $like);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $files[] = $row;
            }
        }
        $stmt->close();
        echo json_encode(['success' => true, 'folders' => $folders, 'files' => $files]);
        exit();
    }
}

// Fetch subfolders and files/records
$subfolders = [];
$files = [];
$legislative_records = [];

if ($is_legislative) {
        // Legislative folder - fetch legislative records
        $type = $current_folder['type'];
        if ($type === 'Ordinance') {
            // For Ordinances & Resolutions folder, get both types
            $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type IN ('Ordinance', 'Resolution') ORDER BY year DESC, month DESC, id DESC");
        } else {
            $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type = ? ORDER BY year DESC, month DESC, id DESC");
            $stmt->bind_param("s", $type);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $legislative_records[] = $row;
        $stmt->close();
    } else {
    // Archive folder - fetch subfolders and files
    $stmt = $conn->prepare("SELECT * FROM archive_folders WHERE parent_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $subfolders[] = $row;
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR) ORDER BY created_at DESC");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $files[] = $row;
    $stmt->close();
}

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_folder['name']); ?> - Document Management</title>
    <?php include 'includes/header_scripts.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    <div class="flex min-h-0">
        <?php
        $sidebar_active_page = 'storage';
        $sidebar_include_overlay = true;
        require_once 'includes/sidebar-centralized.php';
        ?>

        <div class="flex-1 min-h-0">
            <!-- Header -->
            <header class="sticky top-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-slate-700 shadow-sm">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center space-x-4">
                            <a href="<?php echo $is_legislative ? "storage.php" : ($parent_folder ? "folder_view.php?id=" . $parent_folder['id'] : "storage.php"); ?>" class="flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 text-red-600 dark:text-red-400 rounded-full hover:shadow-md transition-all font-semibold border border-red-100 dark:border-red-900/30">
                                <i class="bi bi-arrow-left text-lg"></i>
                                <span>Back to <?php echo $parent_folder ? htmlspecialchars($parent_folder['name']) : "Main Storage"; ?></span>
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                             <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                                <i class="bi bi-moon-stars text-gray-700 dark:text-gray-300 hidden dark:block"></i>
                                <i class="bi bi-sun text-gray-700 dark:text-gray-300 block dark:hidden"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Folder Title -->
        <div class="mb-8 pb-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                    <i class="bi bi-folder2-open text-red-600 mr-2"></i>
                    <?php echo htmlspecialchars($current_folder['name']); ?>
                </h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Created: <?php echo date('M j, Y', strtotime($current_folder['created_at'])); ?></p>
            </div>
        </div>

        <!-- Actions -->
        <?php if (!$is_legislative): ?>
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <button id="create-subfolder-btn" type="button" onclick="openCreateFolderModal()" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200">
                <i class="bi bi-folder-plus text-blue-600 dark:text-blue-400 text-lg"></i>
                Create Subfolder
            </button>
            <button id="upload-file-btn" type="button" onclick="openUploadModal()" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200">
                <i class="bi bi-cloud-upload text-green-600 dark:text-green-400 text-lg"></i>
                Upload File
            </button>
        </div>
        <?php endif; ?>

        <!-- Content List -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="font-semibold text-gray-700 dark:text-gray-300"><?php echo $is_legislative ? 'Records' : 'Folder Contents'; ?></h3>
                <span class="text-xs text-gray-500 bg-gray-200 dark:bg-slate-700 px-2 py-1 rounded-full"><?php echo $is_legislative ? count($legislative_records) : count($subfolders) + count($files); ?> items</span>
            </div>
            
            <div id="content-list" class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php if ($is_legislative): ?>
                    <!-- Legislative Records -->
                    <?php if (empty($legislative_records)): ?>
                        <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                            <i class="bi bi-file-earmark-text text-4xl mb-3 block opacity-50"></i>
                            <p>No records found</p>
                        </div>
                    <?php else: ?>
                        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20">
                        <?php foreach ($legislative_records as $record): 
                            $fileUrl = $record['file_path'] ?? '';
                            $fileExt = $fileUrl ? strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION)) : '';
                            $iconClass = 'bi-file-earmark-text text-blue-500';
                            if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                            elseif (in_array($fileExt, ['pdf'])) $iconClass = 'bi-file-earmark-pdf text-red-500';
                            elseif (in_array($fileExt, ['mp4','avi','mov'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                            elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';
                        ?>
                        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="record-<?php echo $record['id']; ?>">
                            <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="openLegislativeViewer(<?php echo $record['id']; ?>);">
                                <div class="flex flex-col items-center">
                                    <i class="bi <?php echo $iconClass; ?> text-5xl opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-300"></i>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold"><?php echo strtoupper($fileExt ?: 'FILE'); ?></span>
                                </div>
                            </div>
                            <div class="p-4 flex flex-col flex-1">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="<?php echo htmlspecialchars($record['title']); ?>"><?php echo htmlspecialchars($record['title']); ?></div>
                                    </div>
                                </div>
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Author:</span>
                                        <span class="truncate"><?php echo htmlspecialchars($record['author']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Date:</span>
                                        <span><?php echo htmlspecialchars($record['month']); ?> <?php echo htmlspecialchars($record['year']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Archive Folder Contents -->
                    <?php if (empty($subfolders) && empty($files)): ?>
                        <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                            <i class="bi bi-folder2-open text-4xl mb-3 block opacity-50"></i>
                            <p>This folder is empty</p>
                        </div>
                    <?php endif; ?>

                    <!-- Subfolders -->
                    <?php foreach ($subfolders as $folder): ?>
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group" id="folder-<?php echo $folder['id']; ?>">
                        <a href="folder_view.php?id=<?php echo $folder['id']; ?>" class="flex items-center flex-1 min-w-0 gap-4">
                            <i class="bi bi-folder-fill text-2xl text-yellow-500"></i>
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($folder['name']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('M d, Y', strtotime($folder['created_at'])); ?></div>
                            </div>
                        </a>
                        
                    </div>
                    <?php endforeach; ?>

                    <!-- Files -->
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20">
                    <?php foreach ($files as $file): 
                        $fileUrl = $file['file_path'];
                        $fileSize = file_exists($file['file_path']) ? filesize($file['file_path']) : 0;
                        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $iconClass = 'bi-file-earmark-text text-blue-500';
                        if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                        elseif (in_array($fileExt, ['pdf'])) $iconClass = 'bi-file-earmark-pdf text-red-500';
                        elseif (in_array($fileExt, ['mp4','avi','mov'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                        elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';
                        
                        // Format file size
                        $fileSizeDisplay = $fileSize > 0 ? (
                            $fileSize >= 1073741824 ? round($fileSize / 1073741824, 2) . ' GB' :
                            ($fileSize >= 1048576 ? round($fileSize / 1048576, 2) . ' MB' :
                            ($fileSize >= 1024 ? round($fileSize / 1024, 2) . ' KB' : $fileSize . ' B'))
                        ) : 'Unknown';
                        
                        // Get unique ID
                        $uniqueId = !empty($file['unique_number']) ? htmlspecialchars($file['unique_number']) : sprintf("DOC-%06d", $file['id']);
                    ?>
                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="file-<?php echo $file['id']; ?>">
                        <!-- Thumbnail Preview Area -->
                        <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="previewFile('<?php echo htmlspecialchars($file['name']); ?>', <?php echo $file['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $file['created_at']; ?>')">
                            <?php if (in_array($fileExt, ['jpg','jpeg','png','gif','webp']) && file_exists($fileUrl)): ?>
                                <img src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="Preview">
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center">
                                    <i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i>
                                </div>
                            <?php elseif ($fileExt === 'pdf' && file_exists($fileUrl)): ?>
                                <div class="flex flex-col items-center justify-center text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform duration-300">
                                    <i class="bi bi-file-earmark-pdf text-5xl mb-2 opacity-90"></i>
                                    <span class="text-xs font-semibold">PDF Preview</span>
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
                        
                        <!-- File Info Section -->
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="<?php echo htmlspecialchars($file['name']); ?>"><?php echo htmlspecialchars($file['name']); ?></div>
                                </div>
                                <div class="relative flex-shrink-0">
                                    <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.toggle('hidden'); setTimeout(() => { document.addEventListener('click', function _close(e){ if(!e.target.closest('#file-menu-<?php echo $file['id']; ?>') && !e.target.closest('button')){ document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden'); document.removeEventListener('click', _close); }}); }, 10);">
                                        <i class="bi bi-three-dots-vertical text-lg"></i>
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div id="file-menu-<?php echo $file['id']; ?>" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                        <button onclick="previewFile('<?php echo htmlspecialchars($file['name']); ?>', <?php echo $file['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $file['created_at']; ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                            <i class="bi bi-eye"></i> <span>View</span>
                                        </button>
                                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($file['name']); ?>" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors" title="Download file" onclick="document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');">
                                            <i class="bi bi-download"></i> <span>Download</span>
                                        </a>
                                        <button onclick="openArchiveVersionHistory(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                            <i class="bi bi-clock-history"></i> <span>History</span>
                                        </button>
                                        <hr class="my-1 border-gray-200 dark:border-slate-600">
                                        <button onclick="moveToVault(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-3 transition-colors">
                                            <i class="bi bi-shield-lock-fill"></i> <span>Move to Vault</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Metadata -->
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Author:</span>
                                    <span class="truncate"><?php echo !empty($file['author']) ? htmlspecialchars($file['author']) : 'Unknown'; ?></span>
                                </div>
                                <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Date:</span>
                                    <span><?php echo !empty($file['file_date']) ? date('M d, Y', strtotime($file['file_date'])) : date('M d, Y', strtotime($file['created_at'])); ?></span>
                                </div>
                                <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Size:</span>
                                    <span><?php echo $fileSizeDisplay; ?></span>
                                </div>
                            </div>
                            
                            <!-- Unique ID Badge -->
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-600">
                                <div class="bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800/30 text-center">
                                    <div class="text-xs text-blue-700 dark:text-blue-300 font-semibold">Document ID</div>
                                    <div class="text-xs font-mono text-blue-900 dark:text-blue-200 font-bold"><?php echo $uniqueId; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
    
            <script>
                function previewFile(name, id, url, size, created_at){
                    try{
                        var ext = (name || '').split('.').pop().toLowerCase();
                        var t = '';
                        if (['mp4','webm','ogg'].indexOf(ext) >= 0) t = 'video';
                        else if (ext === 'pdf') t = 'pdf';
                        else if (['jpg','jpeg','png','gif','webp'].indexOf(ext) >= 0) t = 'image';
                        // Prefer UI_ENH if available
                        if (window.UI_ENH && typeof window.UI_ENH.openPreview === 'function') {
                            window.UI_ENH.openPreview(url, t);
                            return;
                        }
                        // Fallback simple viewer
                        var w = window.open(url, '_blank');
                        if (!w) {
                            try { UI_ENH.toast('Popup blocked — file will open in a new tab.'); } catch(e) {}
                        }
                    }catch(e){ console.error(e);
                        try { UI_ENH.toast('Unable to preview file'); } catch(e) {}
                    }
                }

                function openLegislativeViewer(id) {
                    fetch('download.php?action=view_json&id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.html) {
                                document.getElementById('viewerModalContent').innerHTML = data.html;
                                document.getElementById('viewerModal').classList.remove('hidden');
                                document.body.style.overflow = 'hidden';
                            }
                        })
                        .catch(error => console.error('Error loading viewer:', error));
                }
            </script>
            </main>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCreateFolderModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6 border border-gray-200 dark:border-slate-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Create New Folder</h3>
            <input type="text" id="newFolderName" placeholder="Folder Name" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 mb-4 focus:ring-2 focus:ring-red-500 outline-none">
            <div class="flex justify-end gap-3">
                <button onclick="closeCreateFolderModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">Cancel</button>
                <button onclick="createFolder()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Create</button>
            </div>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div id="uploadFileModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm z-[101]" onclick="closeUploadModal()"></div>
        <div class="relative z-[102] bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6 border border-gray-200 dark:border-slate-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Upload File</h3>
           <form id="uploadForm" class="space-y-4">
                    <input type="hidden" name="folder_id" value="<?php echo $current_folder_id ?? ''; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File Name</label>
                        <input type="text" id="fileName" name="fileName" required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="e.g., Ordinance_No_123.pdf">
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
                        <input type="file" id="fileInput" name="file" accept="image/*,video/*,.pdf,.doc,.docx,.txt" multiple required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                        <div id="file-list-preview" class="mt-2 space-y-1"></div>
                    </div>
                    <div id="upload-progress" class="hidden text-sm text-gray-600 dark:text-gray-400 py-1"></div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        <button type="button" id="uploadBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors cursor-pointer font-medium focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Upload</button>
                    </div>
                </form>
        </div>
    </div>
    <div id="duplicateConfirmModal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeDuplicateConfirm()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-sm p-6 border border-gray-200 dark:border-slate-700">
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">File already exists</div>
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">Create new version?</div>
            <div class="flex justify-end gap-2">
                <button id="duplicateCancelBtn" type="button" class="px-4 py-2 text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                <button id="duplicateOkBtn" type="button" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">OK</button>
            </div>
        </div>
    </div>


    <!-- Notification Modal -->
    <div id="notificationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeNotification()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-sm p-6 border border-gray-200 dark:border-slate-700">
            <div class="flex items-start gap-3">
                <div id="notificationIcon" class="flex-none rounded-full p-2 bg-green-100 dark:bg-green-900/30">
                    <i class="bi bi-check2-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 id="notificationTitle" class="text-lg font-bold text-gray-900 dark:text-gray-100">Uploaded!</h3>
                    <p id="notificationMessage" class="mt-1 text-sm text-gray-600 dark:text-gray-400">Your file(s) have been uploaded.</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeNotification()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">OK</button>
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
                <div id="versionList" class="space-y-3 max-h-[60vh] overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <!-- Side Viewer Panel -->
    <div id="sideViewer" class="fixed right-0 top-0 h-full w-full sm:w-96 bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-slate-700 shadow-xl transform translate-x-full transition-transform duration-200 z-50">
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
            <div id="sv-preview" class="mt-3 text-sm text-gray-500 dark:text-gray-400">Preview not available. Use Open to download or view the file.</div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-slate-700">
            <a id="sv-open-btn" class="inline-block px-4 py-2 bg-red-600 text-white rounded hidden" href="#" target="_blank">Open / Download</a>
        </div>
    </div>

    <script>
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        let deleteId = null;
        let deleteIsFolder = false;

        // File Upload Functions
        function openCreateFolderModal() {
            const m = document.getElementById('createFolderModal');
            if (!m) return;
            if (m.classList.contains('hidden')) {
                m.classList.remove('hidden');
                document.getElementById('newFolderName')?.focus();
            } else {
                closeCreateFolderModal();
            }
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').classList.add('hidden');
            document.getElementById('newFolderName').value = '';
        }
        async function createFolder() {
            const nameInput = document.getElementById('newFolderName');
            const name = (nameInput && nameInput.value || '').trim();
            if (!name) { return; }
            try {
                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_folder', name })
                });
                const data = await response.json();
                if (data && data.success && data.folder && data.folder.id) {
                    closeCreateFolderModal();
                    window.location.href = 'folder_view.php?id=' + String(data.folder.id);
                } else {
                    openNotification((data && data.message) || 'Failed to create folder', 'error');
                }
            } catch (e) {
                openNotification('Failed to create folder', 'error');
            }
        }

        function openUploadModal() {
            try { closeSideViewer(); } catch(_) {}
            const m = document.getElementById('uploadFileModal');
            if (!m) return;
            if (m.classList.contains('hidden')) {
                m.classList.remove('hidden');
                try { document.getElementById('fileInput').focus(); } catch(_) {}
            } else {
                closeUploadModal();
            }
        }

        function closeUploadModal() {
            document.getElementById('uploadFileModal').classList.add('hidden');
            document.getElementById('fileInput').value = '';
            document.getElementById('file-list-preview').innerHTML = '';
            document.getElementById('upload-progress').classList.add('hidden');
        }
        (function(){
            const uploadForm = document.getElementById('uploadForm');
            const uploadBtn = document.getElementById('uploadBtn');
            uploadForm && uploadForm.addEventListener('submit', function(e){ e.preventDefault(); handleUpload(e); });
            uploadBtn && uploadBtn.addEventListener('click', function(e){ e.preventDefault(); handleUpload(e); });
            const createBtn = document.getElementById('create-subfolder-btn');
            const uploadToggleBtn = document.getElementById('upload-file-btn');
            function bindToggles(){
                if (createBtn && !createBtn.__bound) {
                    createBtn.addEventListener('click', function(e){ e.preventDefault(); openCreateFolderModal(); });
                    createBtn.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCreateFolderModal(); } });
                    createBtn.__bound = true;
                }
                if (uploadToggleBtn && !uploadToggleBtn.__bound) {
                    uploadToggleBtn.addEventListener('click', function(e){ e.preventDefault(); openUploadModal(); });
                    uploadToggleBtn.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openUploadModal(); } });
                    uploadToggleBtn.__bound = true;
                }
            }
            bindToggles();
            document.addEventListener('click', function(e){
                const openCreate = e.target.closest('#create-subfolder-btn');
                if (openCreate) { e.preventDefault(); openCreateFolderModal(); return; }
                const openUpload = e.target.closest('#upload-file-btn');
                if (openUpload) { e.preventDefault(); openUploadModal(); return; }
            });
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindToggles);
            } else {
                bindToggles();
            }
        })();

        let pendingDuplicateResolver = null;
        function openDuplicateConfirm() {
            const m = document.getElementById('duplicateConfirmModal');
            m.classList.remove('hidden');
        }
        function closeDuplicateConfirm() {
            const m = document.getElementById('duplicateConfirmModal');
            m.classList.add('hidden');
        }
        document.getElementById('duplicateCancelBtn')?.addEventListener('click', function(){
            if (typeof pendingDuplicateResolver === 'function') pendingDuplicateResolver(false);
            closeDuplicateConfirm();
        });
        document.getElementById('duplicateOkBtn')?.addEventListener('click', function(){
            if (typeof pendingDuplicateResolver === 'function') pendingDuplicateResolver(true);
            closeDuplicateConfirm();
            openNotification('New version created!', 'success');
        });
        function confirmDuplicateAsync() {
            return new Promise(function(resolve){
                pendingDuplicateResolver = resolve;
                openDuplicateConfirm();
            });
        }

        function updateFilePreview(files) {
            const preview = document.getElementById('file-list-preview');
            if (!preview) return;
            preview.innerHTML = '';
            Array.from(files).forEach(file => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between bg-white dark:bg-slate-700 p-2 rounded border border-gray-200 dark:border-slate-600';
                div.innerHTML = `
                    <span class="truncate">${escapeHtml(file.name)}</span>
                    <span class="text-xs text-gray-500">${(file.size / 1024).toFixed(1)} KB</span>
                `;
                preview.appendChild(div);
            });
        }

        function addSubfolderRow(folder) {
            const list = document.getElementById('content-list');
            if (!list || !folder || !folder.id) return;
            if (document.getElementById('folder-' + String(folder.id))) return;
            const el = document.createElement('div');
            el.className = 'p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group';
            el.id = 'folder-' + String(folder.id);
            el.innerHTML = `
                <a href="folder_view.php?id=${folder.id}" class="flex items-center flex-1 min-w-0 gap-4">
                    <i class="bi bi-folder-fill text-2xl text-yellow-500"></i>
                    <div class="min-w-0">
                        <div class="font-medium text-gray-800 dark:text-gray-200 truncate">${escapeHtml(folder.name || 'New Folder')}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Just now</div>
                    </div>
                </a>
                
            `;
            const emptyState = list.querySelector('.text-center');
            if (emptyState) emptyState.remove();
            const filesHeader = Array.from(list.children).find(function(node){
                return node.id && node.id.startsWith('file-');
            });
            if (filesHeader) {
                list.insertBefore(el, filesHeader);
            } else {
                list.appendChild(el);
            }
            const badge = document.querySelector('.bg-gray-50.dark\\:bg-slate-800\\/50 + span, .p-4.border-b span');
            const badgeEl = document.querySelector('.p-4.border-b.border-gray-200.dark\\:border-slate-700.bg-gray-50.dark\\:bg-slate-800\\/50 span.rounded-full');
            const countEl = badgeEl || badge;
            if (countEl) {
                const m = countEl.textContent.match(/\d+/);
                const old = m ? parseInt(m[0], 10) : 0;
                countEl.textContent = String(old + 1) + ' items';
            }
        }

        let isUploading = false;
        async function handleUpload(e) {
            if(e) e.preventDefault();
            if (isUploading) return;
            
            const fileInput = document.getElementById('fileInput');
            if (!fileInput || !fileInput.files.length) {
                openNotification('Please select a file to upload.', 'error');
                if(fileInput) { try { fileInput.focus(); } catch(_) {} }
                return;
            }

            if (fileInput.files.length > 3) {
                openNotification('Please select up to 3 files.', 'error');
                return;
            }

            isUploading = true;
            const uploadBtn = document.getElementById('uploadBtn');
            const originalBtnText = uploadBtn ? uploadBtn.textContent : 'Upload';
            if(uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Uploading...';
            }

            const progress = document.getElementById('upload-progress');
            if (progress) {
                progress.classList.remove('hidden');
                progress.textContent = `Starting upload of ${fileInput.files.length} file(s)...`;
            }

            const formData = new FormData();
            formData.append('action', 'upload_files_bulk');
            formData.append('fileAuthor', document.getElementById('fileAuthor')?.value || '');
            formData.append('fileDate', document.getElementById('fileDate')?.value || '');
            formData.append('fileUniqueNumber', document.getElementById('fileUniqueNumber')?.value || '');

            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }

            try {
                // Use XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'folder_view.php?id=<?php echo $current_folder_id; ?>', true);

                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable && progress) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progress.textContent = `Uploading: ${percent}% completed...`;
                    }
                };

                xhr.onload = function() {
                    isUploading = false;
                    if(uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = originalBtnText;
                    }
                    if (progress) progress.classList.add('hidden');

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success && data.files) {
                                addUploadedFilesToPage(data.files);
                                closeUploadModal();
                                document.getElementById('uploadForm').reset();
                                if (fileInput) fileInput.value = '';
                                // clear preview list correctly
                                updateFilePreview([]);
                                
                                let msg = `${data.files.length} file(s) uploaded successfully!`;
                                if (data.errors && data.errors.length > 0) {
                                    msg += " Note: Some files had issues: " + data.errors.join(' ');
                                    // treat any file-level errors as warnings so they stand out
                                    openNotification(msg, 'warning');
                                } else {
                                    openNotification(msg, 'success');
                                }
                            } else {
                                openNotification(data.message || 'Failed to upload files', 'error');
                            }
                        } catch(err) {
                            console.error('JSON Parse Error:', err, xhr.responseText);
                            openNotification('Server error: Invalid response format.', 'error');
                        }
                    } else {
                        openNotification('Server returned error: ' + xhr.status, 'error');
                    }
                };

                xhr.onerror = function() {
                    isUploading = false;
                    if(uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = originalBtnText;
                    }
                    if (progress) progress.classList.add('hidden');
                    openNotification('Network error occurred. The internet connection might be unstable.', 'error');
                };

                xhr.send(formData);

            } catch (e) {
                console.error(e);
                isUploading = false;
                if(uploadBtn) {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = originalBtnText;
                }
                if (progress) progress.classList.add('hidden');
                openNotification('An unexpected error occurred.', 'error');
            }
        }

        function addUploadedFilesToPage(files) {
            const contentList = document.getElementById('content-list');
            if (!contentList) return;
            
            // Remove empty state if exists
            const emptyMsg = contentList.querySelector('.text-center.text-gray-500');
            if (emptyMsg) {
                emptyMsg.parentElement?.remove();
            }

            // Find or create files grid container
            let filesGrid = contentList.querySelector('.grid.gap-4');
            if (!filesGrid) {
                filesGrid = document.createElement('div');
                filesGrid.className = 'p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20';
                contentList.appendChild(filesGrid);
            }

            // Add each uploaded file to the grid
            if (Array.isArray(files)) {
                files.forEach(file => {
                    try {
                        if (!file || !file.id) return;
                        
                        const fileExt = (file.name || '').split('.').pop().toLowerCase();
                        let iconClass = 'bi-file-earmark-text text-blue-500';
                        
                        if (['jpg','jpeg','png','gif','webp'].includes(fileExt)) iconClass = 'bi-file-earmark-image text-purple-500';
                        else if (fileExt === 'pdf') iconClass = 'bi-file-earmark-pdf text-red-500';
                        else if (['mp4','avi','mov'].includes(fileExt)) iconClass = 'bi-file-earmark-play text-pink-500';
                        else if (['doc','docx'].includes(fileExt)) iconClass = 'bi-file-earmark-word text-blue-700';

                        const fileSizeDisplay = formatFileSize(file.size || 0);
                        const uniqueId = file.unique_number ? escapeHtml(file.unique_number) : `DOC-${String(file.id).padStart(6, '0')}`;
                        const createdAt = file.created_at || new Date().toISOString();
                        const fileDate = file.file_date ? new Date(file.file_date).toLocaleDateString('en-US', {month: 'short', day: '2-digit', year: 'numeric'}) : new Date(createdAt).toLocaleDateString('en-US', {month: 'short', day: '2-digit', year: 'numeric'});
                        const filePath = file.file_path || `uploads/archives/${file.folder_id}/${file.name}`;

                        const fileCardHTML = `
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="file-${file.id}">
                                <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="previewFile('${escapeHtml(file.name).replace(/'/g, "\\'")}', ${file.id}, '${escapeHtml(filePath).replace(/'/g, "\\'")}', ${file.size || 0}, '${createdAt}')">
                                    <div class="flex flex-col items-center">
                                        <i class="bi ${iconClass} text-5xl opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-300"></i>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold">${fileExt.toUpperCase()}</span>
                                    </div>
                                </div>
                                
                                <div class="p-4 flex flex-col flex-1">
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                                        </div>
                                        <div class="relative flex-shrink-0">
                                            <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); const menu = document.getElementById('file-menu-${file.id}'); if(menu) { menu.classList.toggle('hidden'); setTimeout(() => { document.addEventListener('click', function _close(e){ if(menu && !e.target.closest('#file-menu-${file.id}') && !e.target.closest('button')){ menu.classList.add('hidden'); document.removeEventListener('click', _close); }}); }, 10); }">
                                                <i class="bi bi-three-dots-vertical text-lg"></i>
                                            </button>
                                            <div id="file-menu-${file.id}" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                                <button onclick="previewFile('${escapeHtml(file.name).replace(/'/g, "\\'")}', ${file.id}, '${escapeHtml(filePath).replace(/'/g, "\\'")}', ${file.size || 0}, '${createdAt}'); const m = document.getElementById('file-menu-${file.id}'); if(m) m.classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                    <i class="bi bi-eye"></i> <span>View</span>
                                                </button>
                                                <a href="${escapeHtml(filePath)}" download="${escapeHtml(file.name)}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors" title="Download file" onclick="const m = document.getElementById('file-menu-${file.id}'); if(m) m.classList.add('hidden');">
                                                    <i class="bi bi-download"></i> <span>Download</span>
                                                </a>
                                                <button onclick="openArchiveVersionHistory(${file.id}, '${escapeHtml(file.name).replace(/'/g, "\\'")}'); const m = document.getElementById('file-menu-${file.id}'); if(m) m.classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                    <i class="bi bi-clock-history"></i> <span>History</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2 text-xs">
                                        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">Author:</span>
                                            <span class="truncate">${file.author ? escapeHtml(file.author) : 'Unknown'}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">Date:</span>
                                            <span>${fileDate}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">Size:</span>
                                            <span>${fileSizeDisplay}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-600">
                                        <div class="bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800/30 text-center">
                                            <div class="text-xs text-blue-700 dark:text-blue-300 font-semibold">Document ID</div>
                                            <div class="text-xs font-mono text-blue-900 dark:text-blue-200 font-bold">${uniqueId}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                        filesGrid.insertAdjacentHTML('beforeend', fileCardHTML);
                    } catch (err) {
                        console.error('Error adding file card:', err, file);
                    }
                });
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            if (!text) return text;
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openSideViewer(data) {
            const panel = document.getElementById('sideViewer');
            if (!panel) return;
            document.getElementById('sv-title').textContent = data.title || 'Untitled';
            document.getElementById('sv-type').textContent = data.type || '';
            document.getElementById('sv-meta').textContent = `${data.month || ''} ${data.year || ''}`.trim();
            document.getElementById('sv-author').textContent = data.author || '';
            document.getElementById('sv-d-title').textContent = data.title || '';
            document.getElementById('sv-d-authors').textContent = data.author || '';
            document.getElementById('sv-d-size').textContent = data.size ? data.size : 'Unknown';
            document.getElementById('sv-d-modified').textContent = data.createdAt ? new Date(data.createdAt).toLocaleString() : 'Unknown';
            document.getElementById('sv-d-ctype').textContent = data.contentType || 'Unknown';
            document.getElementById('sv-d-saved').textContent = data.lastSaved ? new Date(data.lastSaved).toLocaleString() : 'Unknown';
            document.getElementById('sv-d-ftype').textContent = data.fileType || 'Unknown';
            const openBtn = document.getElementById('sv-open-btn');
            const preview = document.getElementById('sv-preview');

            const id = data.id ? String(data.id) : '0';
            const title = encodeURIComponent(data.title || 'Untitled');
            const type = encodeURIComponent(data.type || 'Archive Document');
            const month = encodeURIComponent(data.month || (data.createdAt ? new Date(data.createdAt).toLocaleString('default', { month: 'long' }) : ''));
            const year = encodeURIComponent(data.year || (data.createdAt ? new Date(data.createdAt).getFullYear() : ''));
            const author = encodeURIComponent(data.author || 'System');
            const viewerUrl = `download.php?action=view_json&id=${id}&title=${title}&type=${type}&month=${month}&year=${year}&author=${author}`;
            preview.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>';
            fetch(viewerUrl)
                .then(r => r.json())
                .then(d => {
                    if (d && d.success && d.html) {
                        preview.innerHTML = d.html;
                    } else {
                        preview.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                    }
                })
                .catch(() => {
                    preview.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                });

            if (data.downloadUrl) {
                openBtn.href = '#';
                openBtn.onclick = function(e) {
                    e.preventDefault();
                    openViewerModal(data);
                };
                openBtn.classList.remove('hidden');
            } else {
                openBtn.classList.add('hidden');
            }
            panel.classList.remove('translate-x-full');
            panel.classList.add('translate-x-0');
            document.body.style.overflow = 'hidden';
        }

        function closeSideViewer() {
            const panel = document.getElementById('sideViewer');
            if (!panel) return;
            panel.classList.remove('translate-x-0');
            panel.classList.add('translate-x-full');
            document.body.style.overflow = 'auto';
        }

        function previewFile(fileName, fileId, fileUrl, fileSize, createdAt) {
            const ext = fileName.split('.').pop().toUpperCase();
            const data = {
                id: fileId,
                title: fileName,
                type: 'File',
                month: new Date(createdAt).toLocaleString('default', { month: 'long' }),
                year: new Date(createdAt).getFullYear(),
                author: 'System', 
                downloadUrl: fileUrl,
                previewUrl: fileUrl,
                fileType: ext,
                size: (fileSize / 1024).toFixed(2) + ' KB',
                createdAt: createdAt,
                contentType: 'Document' 
            };
            lastPreviewData = data;
            openViewerModal(data);
        }

        function openViewerModal(data) {
            const modal = document.getElementById('viewerModal');
            const content = document.getElementById('viewerModalContent');
            const box = document.getElementById('viewerModalBox');
            if (!modal || !content) return;
            const id = data.id ? String(data.id) : '0';
            const title = encodeURIComponent(data.title || 'Untitled');
            const type = encodeURIComponent(data.type || 'Archive Document');
            const month = encodeURIComponent(data.month || (data.createdAt ? new Date(data.createdAt).toLocaleString('default', { month: 'long' }) : ''));
            const year = encodeURIComponent(data.year || (data.createdAt ? new Date(data.createdAt).getFullYear() : ''));
            const author = encodeURIComponent(data.author || 'System');
            if (data.previewUrl) {
                const ext = String(data.previewUrl).split('.').pop().toLowerCase();
                let viewer = '';
                if (['pdf'].includes(ext)) {
                    viewer = `<div class="space-y-4"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><iframe src="${data.previewUrl}" class="w-full h-[70vh] rounded-lg border"></iframe></div>`;
                } else if (['mp4','webm','ogg','avi','mov'].includes(ext)) {
                    viewer = `<div class="space-y-4 text-center"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><video controls class="w-full max-h-[70vh] rounded-lg border"><source src="${data.previewUrl}"></video></div>`;
                } else if (['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext)) {
                    viewer = `<div class="space-y-4 text-center"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><img src="${data.previewUrl}" class="max-h-[70vh] w-auto inline-block rounded-lg border" alt="Preview"/></div>`;
                } else if (['txt','csv','json','xml','md','log'].includes(ext)) {
                    viewer = `<div class="space-y-4"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><iframe src="${data.previewUrl}" class="w-full h-[70vh] rounded-lg border bg-white"></iframe></div>`;
                } else if (['doc','docx','xls','xlsx','ppt','pptx'].includes(ext)) {
                    const absolute = (location.origin + (data.previewUrl.startsWith('/') ? '' : '/') + data.previewUrl);
                    const gview = 'https://docs.google.com/gview?embedded=true&url=' + encodeURIComponent(absolute);
                    viewer = `<div class="space-y-4"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><iframe src="${gview}" class="w-full h-[70vh] rounded-lg border bg-white"></iframe><div class="text-xs text-gray-500 dark:text-gray-400">If the preview fails, use Open to view in a new tab.</div><div class="flex justify-end"><a href="${data.previewUrl}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">Open</a></div></div>`;
                } else {
                    viewer = `<div class="space-y-4"><div class="border-b pb-2 text-xl font-semibold">${decodeURIComponent(title)}</div><div class="text-sm text-gray-600 dark:text-gray-400">Preview not available for this file type.</div><div class="flex justify-end"><a href="${data.previewUrl}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">Open</a></div></div>`;
                }
                content.innerHTML = viewer;
            } else {
                const url = `download.php?action=view_json&id=${id}&title=${title}&type=${type}&month=${month}&year=${year}&author=${author}`;
                content.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">Loading…</div>';
                fetch(url)
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.html) {
                            content.innerHTML = d.html;
                        } else {
                            content.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                        }
                    })
                    .catch(() => {
                        content.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load viewer.</div>';
                    });
            }
            // Show modal with subtle scale-in animation and dim blur background
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (box) {
                box.classList.remove('opacity-0','scale-95');
                box.classList.add('opacity-100','scale-100');
            }
        }
        function closeViewerModal() {
            const modal = document.getElementById('viewerModal');
            const box = document.getElementById('viewerModalBox');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            if (box) {
                box.classList.remove('opacity-100','scale-100');
                box.classList.add('opacity-0','scale-95');
            }
            // Side panel preview removed; keeping focus on modal-only viewer
        }

        function downloadFile(fileName, fileId) {
             // ... existing downloadFile logic is mostly redundant with direct link, but kept if needed
        }
        
        
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        function openNotification(message = 'Uploaded!', type = 'success') {
            const modal = document.getElementById('notificationModal');
            const title = document.getElementById('notificationTitle');
            const msg = document.getElementById('notificationMessage');
            const icon = document.getElementById('notificationIcon');
            if (!modal || !title || !msg || !icon) return;
            // support three notification types: success, warning, error
            if (type === 'error') {
                title.textContent = 'Error';
                msg.textContent = message || 'Something went wrong.';
                icon.className = 'flex-none rounded-full p-2 bg-red-100 dark:bg-red-900/30';
                icon.innerHTML = '<i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>';
            } else if (type === 'warning') {
                title.textContent = 'Warning';
                msg.textContent = message || 'There were some issues.';
                icon.className = 'flex-none rounded-full p-2 bg-yellow-100 dark:bg-yellow-900/30';
                icon.innerHTML = '<i class="bi bi-exclamation-circle text-yellow-600 dark:text-yellow-400 text-xl"></i>';
            } else {
                // default to success
                title.textContent = 'Uploaded!';
                msg.textContent = message || 'Your file(s) have been uploaded.';
                icon.className = 'flex-none rounded-full p-2 bg-green-100 dark:bg-green-900/30';
                icon.innerHTML = '<i class="bi bi-check2-circle text-green-600 dark:text-green-400 text-xl"></i>';
            }
            modal.classList.remove('hidden');
        }
        function openVersionHistory(id, title) {
            const t = document.getElementById('versionHistoryTitle');
            const list = document.getElementById('versionList');
            if (!t || !list) return;
            t.textContent = title || '';
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            openModal('versionHistoryModal');
            fetch('legislative_api.php?action=get_versions&id=' + encodeURIComponent(id))
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.success) {
                        if (!Array.isArray(d.versions) || d.versions.length === 0) {
                            list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                        } else {
                            list.innerHTML = d.versions.map(function(v){
                                return '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">'
                                    + '<div><div class="font-medium text-gray-800 dark:text-gray-200">Version ' + String(v.version) + '</div>'
                                    + '<div class="text-xs text-gray-500 dark:text-gray-400">' + (v.created_at || '') + ' • ' + (v.author || '') + '</div></div>'
                                    + '<div class="flex space-x-2"><a href="download.php?id=' + encodeURIComponent(v.id) + '" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a></div>'
                                    + '</div>';
                            }).join('');
                        }
                    } else {
                        list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                    }
                })
                .catch(function(){
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                });
        }
        function openArchiveVersionHistory(id, title) {
            const t = document.getElementById('versionHistoryTitle');
            const list = document.getElementById('versionList');
            if (!t || !list) return;
            t.textContent = title || '';
            list.innerHTML = '<div class="text-center py-4">Loading...</div>';
            openModal('versionHistoryModal');
            fetch('archives_api.php?action=get_versions&id=' + encodeURIComponent(id))
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.success) {
                        if (!Array.isArray(d.versions) || d.versions.length === 0) {
                            list.innerHTML = '<div class="text-center text-gray-500">No history found.</div>';
                        } else {
                            list.innerHTML = d.versions.map(function(v){
                                return '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-100 dark:border-slate-600">'
                                    + '<div><div class="font-medium text-gray-800 dark:text-gray-200">Version ' + String(v.version) + '</div>'
                                    + '<div class="text-xs text-gray-500 dark:text-gray-400">' + (v.created_at || '') + '</div></div>'
                                    + '<div class="flex space-x-2"><a href="download.php?id=' + encodeURIComponent(v.id) + '" target="_blank" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30">Download</a></div>'
                                    + '</div>';
                            }).join('');
                        }
                    } else {
                        list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                    }
                })
                .catch(function(){
                    list.innerHTML = '<div class="text-red-500 text-center">Failed to load versions</div>';
                });
        }
        function closeNotification() {
            const modal = document.getElementById('notificationModal');
            if (modal) modal.classList.add('hidden');
        }
        
        // Move to Vault functionality
        function moveToVault(fileId, fileName) {
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
                            source_type: 'archive'
                        })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showToast(result.message || 'File moved to vault successfully', 'success');
                            // Remove file card from UI
                            const fileCard = document.getElementById('file-' + fileId);
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

        // Mobile Sidebar Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileSidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
                setTimeout(() => {
                    sidebarOverlay.classList.remove('opacity-0');
                    sidebarOverlay.classList.add('opacity-100');
                }, 10);
            });
        }

        function closeSidebar() {
            mobileSidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.remove('opacity-100');
            sidebarOverlay.classList.add('opacity-0');
            setTimeout(() => {
                sidebarOverlay.classList.add('hidden');
            }, 300);
        }

        if (closeMobileSidebar) {
            closeMobileSidebar.addEventListener('click', closeSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        (function(){
            const input = document.getElementById('folderSearchInput');
            const btn = document.getElementById('folderSearchBtn');
            const clearBtn = document.getElementById('folderClearBtn');
            const box = document.getElementById('folderSearchResults');
            const list = document.getElementById('folderSearchList');
            const countEl = document.getElementById('folderSearchCount');
            const termEl = document.getElementById('folderSearchTerm');
            const contentList = document.getElementById('content-list');
            function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
            function highlightText(text, term){
                if (!text || !term) return escapeHtml(text);
                try {
                    const re = new RegExp('('+term.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')+')','ig');
                    return escapeHtml(text).replace(re, '<mark class="px-1 rounded bg-yellow-200 dark:bg-amber-700/50">$1</mark>');
                } catch(_) { return escapeHtml(text); }
            }
            function setVisible(el, show){ if (el) el.classList.toggle('hidden', !show); }
            function render(items, term){
                const html = items.map(function(it){
                    if (it.kind === 'folder') {
                        return '<a href="folder_view.php?id='+String(it.id)+'" id="folder-'+String(it.id)+'" class="flex items-center justify-between p-3 rounded-lg border border-transparent hover:border-gray-200 dark:hover:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700/50 highlight-record">'
                             + '<div class="flex items-center gap-3"><i class="bi bi-folder-fill text-2xl text-yellow-500"></i>'
                             + '<div><div class="font-medium text-gray-800 dark:text-gray-200">'+highlightText(it.name, term)+'</div>'
                             + '<div class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(it.created_at)+'</div></div></div></a>';
                    } else {
                        return '<a href="folder_view.php?id=<?php echo $current_folder_id; ?>&highlight='+String(it.id)+'" id="file-'+String(it.id)+'" class="flex items-center justify-between p-3 rounded-lg border border-transparent hover:border-gray-200 dark:hover:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700/50 highlight-record">'
                             + '<div class="flex items-center gap-3 min-w-0"><i class="bi bi-file-earmark-text text-2xl text-blue-500"></i>'
                             + '<div class="min-w-0"><div class="font-medium text-gray-800 dark:text-gray-200 truncate">'+highlightText(it.name, term)+'</div>'
                             + '<div class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(it.created_at)+'</div></div></div>'
                             + '<div class="flex items-center gap-2"><span class="px-2 py-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 rounded">Open</span></div>'
                             + '</a>';
                    }
                }).join('');
                list.innerHTML = html || '<div class="text-sm text-gray-600 dark:text-gray-400">No results</div>';
            }
            function doSearch(){
                const term = (input && input.value || '').trim();
                if (!term) {
                    setVisible(box, false);
                    list.innerHTML = '';
                    countEl.textContent = '0 results';
                    termEl.textContent = '';
                    setVisible(contentList, true);
                    return;
                }
                fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'search_folder', term: term })
                }).then(function(r){ return r.json(); })
                .then(function(d){
                    const folders = (d && d.folders) ? d.folders.map(function(x){ x.kind='folder'; return x; }) : [];
                    const files = (d && d.files) ? d.files.map(function(x){ x.kind='file'; return x; }) : [];
                    const items = folders.concat(files);
                    countEl.textContent = String(items.length)+' results';
                    termEl.textContent = 'Showing for: '+term;
                    render(items, term);
                    setVisible(contentList, false);
                    setVisible(box, true);
                }).catch(function(){
                    list.innerHTML = '<div class="text-sm text-red-500">Search error</div>';
                    setVisible(contentList, false);
                    setVisible(box, true);
                });
            }
            btn && btn.addEventListener('click', doSearch);
            input && input.addEventListener('keydown', function(e){ if (e.key === 'Enter') doSearch(); });
            clearBtn && clearBtn.addEventListener('click', function(){
                input && (input.value = '');
                setVisible(box, false);
                list.innerHTML = '';
                countEl.textContent = '0 results';
                termEl.textContent = '';
                setVisible(contentList, true);
            });
        })();
    </script>
    <div id="viewerModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeViewerModal()"></div>
        <div id="viewerModalBox" class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-3xl w-full mx-4 p-4 max-h-[90vh] overflow-auto transform transition duration-200 ease-out opacity-0 scale-95">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Document Viewer</h3>
                <button onclick="closeViewerModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-2 rounded hover:bg-gray-100 dark:hover:bg-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="viewerModalContent" class="mt-4"></div>
            <!-- Single close button handled in header -->
        </div>
    </div>
    <script src="assets/js/highlight-record.js"></script>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold"></div>
    
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

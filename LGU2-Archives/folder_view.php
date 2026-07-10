<?php
require 'authdatabase.php';

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

// Fetch current folder info
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

// Ensure document prefix is set
if ($current_folder && empty($current_folder['document_prefix'])) {
    $prefix = generate_document_prefix($current_folder['name']);
    $current_folder['document_prefix'] = $prefix;
}

if (!$current_folder) {
    header("Location: storage.php");
    exit();
}

// Get parent folder
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
        $prefix = generate_document_prefix($name);

        $stmt = $conn->prepare("INSERT INTO archive_folders (name, slug, parent_id, created_by, document_prefix) VALUES (?, ?, ?, ?, ?)");
        $uid = $_SESSION['user_id'];
        $stmt->bind_param("ssiss", $name, $slug, $parent_id, $uid, $prefix);
        if ($stmt->execute()) {
             $new_id = $conn->insert_id;
             $folder_path = "uploads/archives/" . $new_id . "/";
             if (!file_exists($folder_path)) { @mkdir($folder_path, 0777, true); }
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
            $target_dir = $is_legislative ? "uploads/legislative/" . $current_folder_id . "/" : "uploads/archives/" . $current_folder_id . "/";
            if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }

            // Ensure columns exist for archive files
            if (!$is_legislative) {
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
            }

            // Ensure columns exist for legislative records
            if ($is_legislative) {
                $cols_needed = [
                    'author' => "VARCHAR(255) DEFAULT NULL",
                    'file_date' => "DATE DEFAULT NULL",
                    'unique_number' => "VARCHAR(100) DEFAULT NULL",
                    'version' => "INT DEFAULT 1",
                    'parent_version_id' => "INT NULL",
                    'folder_id' => "INT DEFAULT NULL",
                    'file_size' => "BIGINT DEFAULT NULL"
                ];
                foreach ($cols_needed as $col => $def) {
                    if ($conn->query("SHOW COLUMNS FROM legislative_records LIKE '$col'")->num_rows == 0) {
                        $conn->query("ALTER TABLE legislative_records ADD COLUMN $col $def");
                    }
                }
            }
            
            $log_file = $is_legislative ? "uploads/legislative/upload_log.txt" : "uploads/archives/upload_log.txt";
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
                    
                    $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\. ]/', '_', $name);
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

                        // Get folder's document prefix and last sequence
                        $folderPrefix = $current_folder['document_prefix'] ?? generate_document_prefix($current_folder['name']);
                        $lastSequence = $current_folder['last_sequence_number'] ?? 0;
                        $newSequence = $lastSequence + 1;

                        // Update folder's sequence number
                        if ($is_legislative) {
                            $updateSeqStmt = $conn->prepare("UPDATE legislative_folders SET last_sequence_number = ?, document_prefix = COALESCE(document_prefix, ?) WHERE id = ?");
                            $updateSeqStmt->bind_param("isi", $newSequence, $folderPrefix, $current_folder_id);
                            $updateSeqStmt->execute();
                            $updateSeqStmt->close();
                        } else {
                            $updateSeqStmt = $conn->prepare("UPDATE archive_folders SET last_sequence_number = ?, document_prefix = COALESCE(document_prefix, ?) WHERE id = ?");
                            $updateSeqStmt->bind_param("isi", $newSequence, $folderPrefix, $current_folder_id);
                            $updateSeqStmt->close();
                        }

                        if ($is_legislative) {
                            $stmt = $conn->prepare("INSERT INTO legislative_records (title, type, month, year, author, file_path, file_date, unique_number, version, parent_version_id, folder_id, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                            $month = $fdate ? date('F', strtotime($fdate)) : date('F');
                            $year = $fdate ? date('Y', strtotime($fdate)) : date('Y');
                            $type = $current_folder['type'];
                            $fileSize = filesize($file_path);
                            $stmt->bind_param("ssssssssiiis", $final_name, $type, $month, $year, $author, $file_path, $fdate, $unq, $version, $parent_version_id, $current_folder_id, $fileSize);
                        } else {
                            $fileSize = filesize($file_path);
                            $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path, author, file_date, unique_number, version, parent_version_id, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("issssssii", $current_folder_id, $final_name, $file_path, $author, $fdate, $unq, $version, $parent_version_id, $fileSize);
                        }

                        if ($stmt->execute()) {
                            $new_id = $conn->insert_id;
                            if ($isBlankUnq) {
                                // Generate folder-prefixed document ID
                                $unq = $folderPrefix . " - " . str_pad($newSequence, 6, '0', STR_PAD_LEFT);
                                if ($is_legislative) {
                                    $conn->query("UPDATE legislative_records SET unique_number = '$unq' WHERE id = $new_id");
                                } else {
                                    $conn->query("UPDATE archive_files SET unique_number = '$unq' WHERE id = $new_id");
                                }
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
                        $stmt->close();
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
    
    if ($action === 'search_folder') {
        header('Content-Type: application/json');
        $term = $_POST['term'] ?? $input['term'] ?? '';
        $sort = $_POST['sort'] ?? $input['sort'] ?? 'latest';
        $term = trim($term);
        $like = '%' . $conn->real_escape_string($term) . '%';
        $folders = [];
        $files = [];

        if (!$is_legislative) {
            $stmt = $conn->prepare("SELECT id, name, created_at FROM archive_folders WHERE parent_id = ? AND name LIKE ? ORDER BY created_at DESC LIMIT 20");
            $stmt->bind_param("is", $current_folder_id, $like);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $folders[] = $row;
                }
            }
            $stmt->close();
        }

        $orderClause = "ORDER BY created_at DESC";
        if ($sort === 'daily') $orderClause = "ORDER BY DATE(created_at) DESC, created_at DESC";
        if ($sort === 'monthly') $orderClause = "ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC, created_at DESC";
        if ($sort === 'yearly') $orderClause = "ORDER BY YEAR(created_at) DESC, created_at DESC";

        if ($is_legislative) {
            $type = $current_folder['type'];
            if ($type === 'Ordinance') {
                $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type IN ('Ordinance', 'Resolution') AND folder_id = ? AND (title LIKE ? OR author LIKE ? OR unique_number LIKE ?) $orderClause LIMIT 100");
                $stmt->bind_param("isss", $current_folder_id, $like, $like, $like);
            } else {
                $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type = ? AND folder_id = ? AND (title LIKE ? OR author LIKE ? OR unique_number LIKE ?) $orderClause LIMIT 100");
                $stmt->bind_param("sisss", $type, $current_folder_id, $like, $like, $like);
            }
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $files[] = $row;
                }
            }
        } else {
            $stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? AND (name LIKE ? OR author LIKE ? OR unique_number LIKE ?) $orderClause LIMIT 100");
            $stmt->bind_param("isss", $current_folder_id, $like, $like, $like);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $files[] = $row;
                }
            }
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'folders' => $folders, 'files' => $files]);
        exit();
    }

    if ($action === 'get_requests') {
        header('Content-Type: application/json');
        $file_id = $_POST['file_id'] ?? $input['file_id'] ?? null;
        $file_source = $_POST['file_source'] ?? $input['file_source'] ?? 'archive';

        $requests = [];
        if ($file_id) {
            $stmt = $conn->prepare("SELECT * FROM requests WHERE file_id = ? AND file_source = ? ORDER BY date_requested DESC");
            $stmt->bind_param("is", $file_id, $file_source);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $requests[] = $row;
            $stmt->close();
        }
        echo json_encode(['success' => true, 'requests' => $requests]);
        exit();
    }

    if ($action === 'update_request_status') {
        header('Content-Type: application/json');
        $request_id = $_POST['request_id'] ?? $input['request_id'] ?? null;
        $status = $_POST['status'] ?? $input['status'] ?? null;
        
        if (!$request_id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $date_released = ($status === 'Released') ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare("UPDATE requests SET status = ?, date_released = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $date_released, $request_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'get_files') {
        header('Content-Type: application/json');
        $sort = $_POST['sort'] ?? $input['sort'] ?? 'latest';
        $orderClause = "ORDER BY created_at DESC";
        if ($sort === 'daily') $orderClause = "ORDER BY DATE(created_at) DESC, created_at DESC";
        if ($sort === 'monthly') $orderClause = "ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC, created_at DESC";
        if ($sort === 'yearly') $orderClause = "ORDER BY YEAR(created_at) DESC, created_at DESC";

        $subfolders = [];
        $files = [];

        if (!$is_legislative) {
            $stmt = $conn->prepare("SELECT * FROM archive_folders WHERE parent_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $current_folder_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $subfolders[] = $row;
            $stmt->close();
        }

        if ($is_legislative) {
            $type = $current_folder['type'];
            if ($type === 'Ordinance') {
                $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type IN ('Ordinance', 'Resolution') AND folder_id = ? $orderClause");
                $stmt->bind_param("i", $current_folder_id);
            } else {
                $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type = ? AND folder_id = ? $orderClause");
                $stmt->bind_param("si", $type, $current_folder_id);
            }
        } else {
            $stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? $orderClause");
            $stmt->bind_param("i", $current_folder_id);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $files[] = $row;
        $stmt->close();
        
        echo json_encode(['success' => true, 'folders' => $subfolders, 'files' => $files]);
        exit();
    }
}

// Fetch subfolders and files/records
$subfolders = [];
$files = [];
$legislative_records = [];

if ($is_legislative) {
    $type = $current_folder['type'];
    if ($type === 'Ordinance') {
        $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type IN ('Ordinance', 'Resolution') AND (folder_id = ? OR folder_id IS NULL) ORDER BY created_at DESC");
        $stmt->bind_param("i", $current_folder_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM legislative_records WHERE type = ? AND (folder_id = ? OR folder_id IS NULL) ORDER BY created_at DESC");
        $stmt->bind_param("si", $type, $current_folder_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $legislative_records[] = $row;
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM archive_folders WHERE parent_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $current_folder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $subfolders[] = $row;
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? ORDER BY created_at DESC");
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

// Fetch archive folders for sidebar
$archive_folders = [];
$folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
if ($folders_result && $folders_result->num_rows > 0) {
    while ($row = $folders_result->fetch_assoc()) {
        $archive_folders[] = $row;
    }
}

$conn->close();

function formatFileSize($fileSize) {
    if ($fileSize <= 0) return 'Unknown';
    return $fileSize >= 1073741824 ? round($fileSize / 1073741824, 2) . ' GB' :
          ($fileSize >= 1048576 ? round($fileSize / 1048576, 2) . ' MB' :
          ($fileSize >= 1024 ? round($fileSize / 1024, 2) . ' KB' : $fileSize . ' B'));
}
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
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <button id="create-subfolder-btn" type="button" onclick="openCreateFolderModal()" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200">
                        <i class="bi bi-folder-plus text-blue-600 dark:text-blue-400 text-lg"></i>
                        <?php echo $is_legislative ? 'Create' : 'Create Subfolder'; ?>
                    </button>
                    <button id="upload-file-btn" type="button" onclick="openUploadModal()" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200">
                        <i class="bi bi-cloud-upload text-green-600 dark:text-green-400 text-lg"></i>
                        Upload File
                    </button>
                </div>

                <!-- Toolbar -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-slate-700 p-4 mb-6">
                    <div class="flex flex-wrap gap-3 items-center">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sort:</label>
                            <select id="sortSelect" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                                <option value="latest">Latest</option>
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px] flex gap-2">
                            <div class="relative flex-1">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input id="searchInput" type="text" placeholder="Search by filename, author, or document ID" class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                            </div>
                            <button id="searchBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                                Search
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Content List -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300"><?php echo $is_legislative ? 'Records' : 'Folder Contents'; ?></h3>
                        <span id="itemCount" class="text-xs text-gray-500 bg-gray-200 dark:bg-slate-700 px-2 py-1 rounded-full"><?php echo $is_legislative ? count($legislative_records) : count($subfolders) + count($files); ?> items</span>
                    </div>
                    
                    <div id="content-list">
                        <?php if ($is_legislative): ?>
                            <!-- Legislative Records -->
                            <?php if (empty($legislative_records)): ?>
                                <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                                    <i class="bi bi-file-earmark-text text-4xl mb-3 block opacity-50"></i>
                                    <p>No records found</p>
                                </div>
                            <?php else: ?>
                                <div id="records-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20">
                                <?php foreach ($legislative_records as $record): 
                                    $fileUrl = $record['file_path'] ?? '';
                                    $fileExt = $fileUrl ? strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION)) : '';
                                    $iconClass = 'bi-file-earmark-text text-blue-500';
                                    if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                                    elseif (in_array($fileExt, ['pdf'])) $iconClass = 'bi-file-earmark-pdf text-red-500';
                                    elseif (in_array($fileExt, ['mp4','avi','mov'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                                    elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';
                                    $fileSize = $record['file_size'] ?? (file_exists($fileUrl) ? filesize($fileUrl) : 0);
                                    $uniqueId = !empty($record['unique_number']) ? htmlspecialchars($record['unique_number']) : sprintf("DOC-%06d", $record['id']);
                                ?>
                                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="record-<?php echo $record['id']; ?>">
                                    <!-- Thumbnail Preview Area -->
                                    <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="previewFile('<?php echo htmlspecialchars($record['title']); ?>', <?php echo $record['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $record['created_at']; ?>')">
                                        <?php if (in_array($fileExt, ['jpg','jpeg','png','gif','webp']) && file_exists($fileUrl)): ?>
                                            <img src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="Preview">
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center">
                                                <i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i>
                                            </div>
                                        <?php elseif (in_array($fileExt, ['mp4','avi','mov','webm','ogg']) && file_exists($fileUrl)): ?>
                                            <video src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" muted playsinline></video>
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center">
                                                <i class="bi bi-play-circle-fill text-white opacity-0 group-hover:opacity-100 transition-opacity text-5xl drop-shadow-lg"></i>
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
                                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold"><?php echo strtoupper($fileExt ?: 'FILE'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-4 flex flex-col flex-1">
                                        <div class="flex items-start justify-between gap-2 mb-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="<?php echo htmlspecialchars($record['title']); ?>"><?php echo htmlspecialchars($record['title']); ?></div>
                                            </div>
                                            <div class="relative flex-shrink-0">
                                                <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); document.getElementById('leg-menu-<?php echo $record['id']; ?>').classList.toggle('hidden'); setTimeout(() => { document.addEventListener('click', function _close(e){ if(!e.target.closest('#leg-menu-<?php echo $record['id']; ?>') && !e.target.closest('button')){ document.getElementById('leg-menu-<?php echo $record['id']; ?>').classList.add('hidden'); document.removeEventListener('click', _close); }}); }, 10);">
                                                    <i class="bi bi-three-dots-vertical text-lg"></i>
                                                </button>
                                                <!-- Dropdown Menu -->
                                                <div id="leg-menu-<?php echo $record['id']; ?>" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                                    <button onclick="previewFile('<?php echo htmlspecialchars($record['title']); ?>', <?php echo $record['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $record['created_at']; ?>'); document.getElementById('leg-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                        <i class="bi bi-eye"></i> <span>View</span>
                                                    </button>
                                                    <a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($record['title']); ?>" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors" title="Download file" onclick="document.getElementById('leg-menu-<?php echo $record['id']; ?>').classList.add('hidden');">
                                                        <i class="bi bi-download"></i> <span>Download</span>
                                                    </a>
                                                    <button onclick="openLegislativeVersionHistory(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>'); document.getElementById('leg-menu-<?php echo $record['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors">
                                                        <i class="bi bi-clock-history"></i> <span>History</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Metadata -->
                                        <div class="space-y-2 text-xs">
                                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">Author:</span>
                                                <span class="truncate"><?php echo !empty($record['author']) ? htmlspecialchars($record['author']) : 'Unknown'; ?></span>
                                            </div>
                                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">Date:</span>
                                                <span><?php echo !empty($record['file_date']) ? date('M d, Y', strtotime($record['file_date'])) : (isset($record['month']) ? htmlspecialchars($record['month']) . ' ' . htmlspecialchars($record['year']) : date('M d, Y', strtotime($record['created_at']))); ?></span>
                                            </div>
                                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">Size:</span>
                                                <span><?php echo formatFileSize($fileSize); ?></span>
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
                        <?php else: ?>
                            <!-- Archive Folder Contents -->
                            <div id="subfolders-list" class="border-b border-gray-200 dark:border-slate-700">
                                <?php if (!empty($subfolders)): ?>
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
                                <?php endif; ?>
                            </div>
                            <!-- Files Grid -->
                            <div id="files-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20">
                            <?php if (empty($subfolders) && empty($files)): ?>
                                <div class="col-span-full p-12 text-center text-gray-500 dark:text-gray-400">
                                    <i class="bi bi-folder2-open text-4xl mb-3 block opacity-50"></i>
                                    <p>This folder is empty</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($files as $file): 
                                    $fileUrl = $file['file_path'];
                                    $fileSize = file_exists($fileUrl) ? filesize($fileUrl) : 0;
                                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                    $iconClass = 'bi-file-earmark-text text-blue-500';
                                    if (in_array($fileExt, ['jpg','jpeg','png','gif','webp'])) $iconClass = 'bi-file-earmark-image text-purple-500';
                                    elseif ($fileExt === 'pdf') $iconClass = 'bi-file-earmark-pdf text-red-500';
                                    elseif (in_array($fileExt, ['mp4','avi','mov'])) $iconClass = 'bi-file-earmark-play text-pink-500';
                                    elseif (in_array($fileExt, ['doc','docx'])) $iconClass = 'bi-file-earmark-word text-blue-700';
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
                                        <?php elseif (in_array($fileExt, ['mp4','avi','mov','webm','ogg']) && file_exists($fileUrl)): ?>
                                            <video src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" muted playsinline></video>
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center">
                                                <i class="bi bi-play-circle-fill text-white opacity-0 group-hover:opacity-100 transition-opacity text-5xl drop-shadow-lg"></i>
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
                                                    <button onclick="moveToHiddenFolder(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-3 transition-colors">
                                                        <i class="bi bi-eye-slash-fill"></i> <span>Move to Hidden Folder</span>
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
                                                <span><?php echo formatFileSize($fileSize); ?></span>
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
                            <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Unique Number (Optional)</label>
                    <input type="text" id="fileUniqueNumber" name="fileUniqueNumber" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="Enter unique number">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File</label>
                    <input type="file" id="fileInput" name="files" accept="image/*,video/*,.pdf,.doc,.docx,.txt" multiple required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
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

    <!-- Preview Modal -->
    <div id="previewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closePreviewModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
                <h3 id="previewTitle" class="text-lg font-bold text-gray-900 dark:text-gray-100">Preview</h3>
                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-100 dark:bg-slate-700 p-4" id="previewContent">
            </div>
        </div>
    </div>

    <!-- Version History Modal -->
    <div id="versionHistoryModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('versionHistoryModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Version History</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal('versionHistoryModal')">&times;</button>
                </div>
                <div id="versionHistoryTitle" class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4"></div>
                <div id="versionList" class="space-y-3 max-h-[60vh] overflow-y-auto">
                    <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No version history available yet.</div>
                </div>
            </div>
        </div>
    </div>

    <div id="requestersModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('requestersModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Requesters</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal('requestersModal')">&times;</button>
                </div>
                <div id="requestersTitle" class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4"></div>
                <div id="requestersList" class="space-y-3 max-h-[60vh] overflow-y-auto">
                    <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Loading requesters...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const isLegislative = <?php echo $is_legislative ? 'true' : 'false'; ?>;
        const highlightFileId = <?php echo isset($_GET['highlight']) ? (int)$_GET['highlight'] : 'null'; ?>;
        window.currentRecords = <?php echo json_encode($legislative_records); ?>;
        window.currentFiles = <?php echo json_encode($files); ?>;

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            modal.classList.add('hidden');
            document.getElementById('previewContent').innerHTML = '';
        }

        function handleHighlight() {
            if (!highlightFileId) return;
            
            // Wait a bit for the files to be rendered
            setTimeout(() => {
                let targetElement;
                if (isLegislative) {
                    targetElement = document.getElementById(`record-${highlightFileId}`);
                } else {
                    targetElement = document.getElementById(`file-${highlightFileId}`);
                }
                
                if (targetElement) {
                    // Scroll to the element
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Add highlight effect
                    targetElement.classList.add('ring-4', 'ring-yellow-400', 'ring-opacity-75');
                    
                    // Remove highlight after a while
                    setTimeout(() => {
                        targetElement.classList.remove('ring-4', 'ring-yellow-400', 'ring-opacity-75');
                    }, 3000);
                    
                    // Try to auto-open preview
                    const fileUrl = window.currentFiles?.find(f => f.id == highlightFileId)?.file_path;
                    const recordUrl = window.currentRecords?.find(r => r.id == highlightFileId)?.file_path;
                    const name = window.currentFiles?.find(f => f.id == highlightFileId)?.name ||
                                 window.currentRecords?.find(r => r.id == highlightFileId)?.title;
                    const size = 0;
                    const createdAt = '';
                    if (fileUrl || recordUrl) {
                        previewFile(name, highlightFileId, fileUrl || recordUrl, size, createdAt);
                    }
                }
            }, 500);
        }

        function previewFile(name, id, url, size, created_at) {
            try {
                var ext = (name || '').split('.').pop().toLowerCase();
                var t = '';
                if (['mp4','webm','ogg','avi','mov'].indexOf(ext) >= 0) t = 'video';
                else if (ext === 'pdf') t = 'pdf';
                else if (['jpg','jpeg','png','gif','webp'].indexOf(ext) >= 0) t = 'image';
                
                document.getElementById('previewTitle').textContent = name;
                const previewContent = document.getElementById('previewContent');
                
                if (t === 'image') {
                    previewContent.innerHTML = `<img src="${url}" class="max-h-[70vh] max-w-full rounded-lg shadow-lg" alt="Preview">`;
                } else if (t === 'video') {
                    previewContent.innerHTML = `<video controls class="max-h-[70vh] max-w-full rounded-lg shadow-lg" src="${url}"></video>`;
                } else if (t === 'pdf') {
                    previewContent.innerHTML = `<iframe src="${url}" class="w-full h-[70vh] rounded-lg shadow-lg" title="PDF Preview"></iframe>`;
                } else {
                    previewContent.innerHTML = `<div class="text-center">
                        <i class="bi bi-file-earmark text-6xl text-gray-500 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">Preview not available for this file type.</p>
                        <a href="${url}" download class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                            <i class="bi bi-download"></i> Download File
                        </a>
                    </div>`;
                }
                
                document.getElementById('previewModal').classList.remove('hidden');
            } catch(e) { 
                console.error(e);
                alert('Unable to preview file');
            }
        }

        function openCreateFolderModal() {
            document.getElementById('createFolderModal').classList.remove('hidden');
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').classList.add('hidden');
        }

        function createFolder() {
            const name = document.getElementById('newFolderName').value;
            if (!name.trim()) return;
            const formData = new FormData();
            formData.append('action', 'create_folder');
            formData.append('name', name);
            fetch('folder_view.php?id=<?php echo $current_folder_id; ?><?php echo $is_legislative ? '&legislative=1' : ''; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    closeCreateFolderModal();
                    showNotification('Success', 'Folder created successfully!', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showNotification('Error', data.message || 'Failed to create folder', 'error');
                }
            }).catch(e => {
                console.error(e);
                showNotification('Error', 'Failed to create folder', 'error');
            });
        }

        function openUploadModal() {
            document.getElementById('uploadFileModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadFileModal').classList.add('hidden');
        }

        function showNotification(title, message, type = 'success') {
            document.getElementById('notificationTitle').textContent = title;
            document.getElementById('notificationMessage').textContent = message;
            const icon = document.getElementById('notificationIcon');
            if (type === 'error') {
                icon.innerHTML = '<i class="bi bi-x-circle text-red-600 dark:text-red-400 text-xl"></i>';
                icon.className = 'flex-none rounded-full p-2 bg-red-100 dark:bg-red-900/30';
            } else {
                icon.innerHTML = '<i class="bi bi-check2-circle text-green-600 dark:text-green-400 text-xl"></i>';
                icon.className = 'flex-none rounded-full p-2 bg-green-100 dark:bg-green-900/30';
            }
            document.getElementById('notificationModal').classList.remove('hidden');
        }

        function closeNotification() {
            document.getElementById('notificationModal').classList.add('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function openArchiveVersionHistory(id, name) {
            document.getElementById('versionHistoryTitle').textContent = name;
            document.getElementById('versionHistoryModal').classList.remove('hidden');
        }

        function openLegislativeVersionHistory(id, name) {
            document.getElementById('versionHistoryTitle').textContent = name;
            document.getElementById('versionHistoryModal').classList.remove('hidden');
        }

        async function openRequestersModal(fileId, fileSource, fileName) {
            document.getElementById('requestersTitle').textContent = fileName;
            document.getElementById('requestersModal').classList.remove('hidden');
            
            // Fetch requesters
            const formData = new FormData();
            formData.append('action', 'get_requests');
            formData.append('file_id', fileId);
            formData.append('file_source', fileSource);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            const container = document.getElementById('requestersList');
            
            if (data.success && data.requests.length > 0) {
                container.innerHTML = data.requests.map(req => {
                    const statusColors = {
                        'Pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                        'Approved': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                        'Released': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                        'Denied': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                    };
                    
                    return `
                        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white">${escapeHtml(req.requester_name)}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">${escapeHtml(req.department)}</p>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full ${statusColors[req.status] || statusColors['Pending']}">${escapeHtml(req.status)}</span>
                            </div>
                            <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                <div><span class="font-medium">Date Requested:</span> ${escapeHtml(req.date_requested)}</div>
                                <div><span class="font-medium">Purpose:</span> ${escapeHtml(req.purpose)}</div>
                                <div><span class="font-medium">Contact:</span> ${escapeHtml(req.contact_info)}</div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                ${req.status !== 'Released' ? `
                                    <button class="px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors" onclick="updateRequestStatus(${req.id}, 'Released')">
                                        Release Copy
                                    </button>
                                ` : ''}
                                ${req.status === 'Pending' ? `
                                    <button class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors" onclick="updateRequestStatus(${req.id}, 'Approved')">
                                        Approve
                                    </button>
                                    <button class="px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors" onclick="updateRequestStatus(${req.id}, 'Denied')">
                                        Deny
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center py-10 text-gray-500 dark:text-gray-400">
                        <i class="bi bi-inbox text-4xl mb-3"></i>
                        <p>No requesters found for this file.</p>
                    </div>
                `;
            }
        }

        async function updateRequestStatus(requestId, status) {
            const formData = new FormData();
            formData.append('action', 'update_request_status');
            formData.append('request_id', requestId);
            formData.append('status', status);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                showNotification('Success', 'Request status updated!', 'success');
                // Refresh requesters list
                const title = document.getElementById('requestersTitle').textContent;
                // We need to find fileId and fileSource again - let's just close and re-open? Or better, store them
                document.getElementById('requestersModal').classList.add('hidden');
            } else {
                showNotification('Error', 'Failed to update status', 'error');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function moveToHiddenFolder(id, name) {
            // Check if user has hidden folder setup and unlocked
            fetch('confidential_vault.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'move_to_hidden_folder', file_id: id, source_type: '<?php echo $is_legislative ? "legislative" : "archive"; ?>' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Success', `File "${name}" moved to hidden folder`, 'success');
                    // Remove the file from current view
                    const fileElement = document.getElementById('<?php echo $is_legislative ? "record" : "file"; ?>-' + id);
                    if (fileElement) {
                        fileElement.style.opacity = '0.5';
                        fileElement.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            fileElement.remove();
                        }, 300);
                    }
                } else {
                    showNotification('Error', data.message || 'Failed to move file', 'error');
                }
            })
            .catch(e => {
                showNotification('Error', 'Connection error', 'error');
            });
        }

        document.getElementById('uploadBtn').addEventListener('click', function() {
            const form = document.getElementById('uploadForm');
            const fileInput = document.getElementById('fileInput');
            if (!fileInput.files.length) {
                showNotification('Error', 'Please select at least one file', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload_files_bulk');
            formData.append('fileAuthor', document.getElementById('fileAuthor').value);
            formData.append('fileDate', document.getElementById('fileDate').value);
            formData.append('fileUniqueNumber', document.getElementById('fileUniqueNumber').value);
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }

            this.disabled = true;
            document.getElementById('upload-progress').classList.remove('hidden');
            document.getElementById('upload-progress').textContent = 'Uploading...';

            fetch('folder_view.php?id=<?php echo $current_folder_id; ?><?php echo $is_legislative ? '&legislative=1' : ''; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                this.disabled = false;
                document.getElementById('upload-progress').classList.add('hidden');
                if (data.success) {
                    closeUploadModal();
                    showNotification('Success', data.files.length + ' file(s) uploaded successfully!', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showNotification('Error', data.message || 'Failed to upload files', 'error');
                }
            }).catch(e => {
                console.error(e);
                this.disabled = false;
                document.getElementById('upload-progress').classList.add('hidden');
                showNotification('Error', 'Failed to upload files', 'error');
            });
        });

        document.getElementById('sortSelect').addEventListener('change', function() {
            refreshContent();
        });

        document.getElementById('searchBtn').addEventListener('click', function() {
            refreshContent();
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') refreshContent();
        });

        function refreshContent() {
            const sort = document.getElementById('sortSelect').value;
            const search = document.getElementById('searchInput').value.trim();
            const action = search ? 'search_folder' : 'get_files';
            const formData = new FormData();
            formData.append('action', action);
            formData.append('sort', sort);
            if (search) formData.append('term', search);

            fetch('folder_view.php?id=<?php echo $current_folder_id; ?><?php echo $is_legislative ? '&legislative=1' : ''; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    renderContent(data);
                }
            }).catch(e => console.error(e));
        }

        function renderContent(data) {
            document.getElementById('itemCount').textContent = (data.folders.length + data.files.length) + ' items';
            if (isLegislative) {
                renderLegislativeRecords(data.files);
            } else {
                renderSubfolders(data.folders);
                renderFiles(data.files);
            }
        }

        function renderLegislativeRecords(records) {
            window.currentRecords = records;
            const container = document.getElementById('records-grid');
            if (!container) {
                const contentList = document.getElementById('content-list');
                if (records.length === 0) {
                    contentList.innerHTML = '<div class="p-12 text-center text-gray-500 dark:text-gray-400"><i class="bi bi-file-earmark-text text-4xl mb-3 block opacity-50"></i><p>No records found</p></div>';
                    return;
                }
                contentList.innerHTML = '<div id="records-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-slate-800/20"></div>';
            }
            const grid = document.getElementById('records-grid');
            grid.innerHTML = records.map(record => {
                const fileUrl = record.file_path || '';
                const fileExt = fileUrl ? fileUrl.split('.').pop().toLowerCase() : '';
                let iconClass = 'bi-file-earmark-text text-blue-500';
                if (['jpg','jpeg','png','gif','webp'].includes(fileExt)) iconClass = 'bi-file-earmark-image text-purple-500';
                else if (fileExt === 'pdf') iconClass = 'bi-file-earmark-pdf text-red-500';
                else if (['mp4','avi','mov','webm','ogg'].includes(fileExt)) iconClass = 'bi-file-earmark-play text-pink-500';
                else if (['doc','docx'].includes(fileExt)) iconClass = 'bi-file-earmark-word text-blue-700';
                const fileSize = record.file_size || 0;
                const uniqueId = record.unique_number || 'DOC-' + String(record.id).padStart(6, '0');
                const author = record.author || 'Unknown';
                const date = record.file_date ? new Date(record.file_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : (record.month ? record.month + ' ' + record.year : new Date(record.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
                
                let previewHtml = '';
                if (['jpg','jpeg','png','gif','webp'].includes(fileExt)) {
                    previewHtml = `<img src="${fileUrl}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="Preview"><div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center"><i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i></div>`;
                } else if (['mp4','avi','mov','webm','ogg'].includes(fileExt)) {
                    previewHtml = `<video src="${fileUrl}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" muted playsinline></video><div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center"><i class="bi bi-play-circle-fill text-white opacity-0 group-hover:opacity-100 transition-opacity text-5xl drop-shadow-lg"></i></div>`;
                } else if (fileExt === 'pdf') {
                    previewHtml = `<div class="flex flex-col items-center justify-center text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform duration-300"><i class="bi bi-file-earmark-pdf text-5xl mb-2 opacity-90"></i><span class="text-xs font-semibold">PDF Preview</span></div><div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center"><i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i></div>`;
                } else {
                    previewHtml = `<div class="flex flex-col items-center"><i class="bi ${iconClass} text-5xl opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-300"></i><span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold">${fileExt.toUpperCase()}</span></div>`;
                }

                return `<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="record-${record.id}">
                    <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="previewFile('${record.title}', ${record.id}, '${fileUrl}', ${fileSize}, '${record.created_at}')">${previewHtml}</div>
                    <div class="p-4 flex flex-col flex-1">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="min-w-0 flex-1"><div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="${record.title}">${record.title}</div></div>
                            <div class="relative flex-shrink-0">
                                <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); document.getElementById('leg-menu-${record.id}').classList.toggle('hidden');">
                                    <i class="bi bi-three-dots-vertical text-lg"></i>
                                </button>
                                <div id="leg-menu-${record.id}" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                    <button onclick="previewFile('${record.title}', ${record.id}, '${fileUrl}', ${fileSize}, '${record.created_at}'); document.getElementById('leg-menu-${record.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-eye"></i><span>View</span></button>
                                    <a href="${fileUrl}" download="${record.title}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors" onclick="document.getElementById('leg-menu-${record.id}').classList.add('hidden');"><i class="bi bi-download"></i><span>Download</span></a>
                                    <button onclick="openLegislativeVersionHistory(${record.id}, '${record.title}'); document.getElementById('leg-menu-${record.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-clock-history"></i><span>History</span></button>
                                    <hr class="my-1 border-gray-200 dark:border-slate-600">
                                    <button onclick="openRequestersModal(${record.id}, 'legislative', '${record.title.replace(/'/g, "\\'")}'); document.getElementById('leg-menu-${record.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-people"></i><span>View Requesters</span></button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Author:</span><span class="truncate">${author}</span></div>
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Date:</span><span>${date}</span></div>
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Size:</span><span>${formatFileSize(fileSize)}</span></div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-600">
                            <div class="bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800/30 text-center">
                                <div class="text-xs text-blue-700 dark:text-blue-300 font-semibold">Document ID</div>
                                <div class="text-xs font-mono text-blue-900 dark:text-blue-200 font-bold">${uniqueId}</div>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function renderSubfolders(folders) {
            const container = document.getElementById('subfolders-list');
            container.innerHTML = folders.map(folder => `<div class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group" id="folder-${folder.id}">
                <a href="folder_view.php?id=${folder.id}" class="flex items-center flex-1 min-w-0 gap-4">
                    <i class="bi bi-folder-fill text-2xl text-yellow-500"></i>
                    <div class="min-w-0"><div class="font-medium text-gray-800 dark:text-gray-200 truncate">${folder.name}</div><div class="text-xs text-gray-500 dark:text-gray-400">${new Date(folder.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div></div>
                </a>
            </div>`).join('');
        }

        function renderFiles(files) {
            window.currentFiles = files;
            const container = document.getElementById('files-grid');
            if (files.length === 0 && document.getElementById('subfolders-list').children.length === 0) {
                container.innerHTML = '<div class="col-span-full p-12 text-center text-gray-500 dark:text-gray-400"><i class="bi bi-folder2-open text-4xl mb-3 block opacity-50"></i><p>This folder is empty</p></div>';
                return;
            }

            container.innerHTML = files.map(file => {
                const fileUrl = file.file_path;
                const fileExt = file.name.split('.').pop().toLowerCase();
                let iconClass = 'bi-file-earmark-text text-blue-500';
                if (['jpg','jpeg','png','gif','webp'].includes(fileExt)) iconClass = 'bi-file-earmark-image text-purple-500';
                else if (fileExt === 'pdf') iconClass = 'bi-file-earmark-pdf text-red-500';
                else if (['mp4','avi','mov','webm','ogg'].includes(fileExt)) iconClass = 'bi-file-earmark-play text-pink-500';
                else if (['doc','docx'].includes(fileExt)) iconClass = 'bi-file-earmark-word text-blue-700';
                const fileSize = file.file_size || 0;
                const uniqueId = file.unique_number || 'DOC-' + String(file.id).padStart(6, '0');
                const author = file.author || 'Unknown';
                const date = file.file_date ? new Date(file.file_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : new Date(file.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                let previewHtml = '';
                if (['jpg','jpeg','png','gif','webp'].includes(fileExt)) {
                    previewHtml = `<img src="${fileUrl}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="Preview"><div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center"><i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i></div>`;
                } else if (['mp4','avi','mov','webm','ogg'].includes(fileExt)) {
                    previewHtml = `<video src="${fileUrl}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" muted playsinline></video><div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center"><i class="bi bi-play-circle-fill text-white opacity-0 group-hover:opacity-100 transition-opacity text-5xl drop-shadow-lg"></i></div>`;
                } else if (fileExt === 'pdf') {
                    previewHtml = `<div class="flex flex-col items-center justify-center text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform duration-300"><i class="bi bi-file-earmark-pdf text-5xl mb-2 opacity-90"></i><span class="text-xs font-semibold">PDF Preview</span></div><div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center"><i class="bi bi-eye text-white opacity-0 group-hover:opacity-100 transition-opacity text-2xl"></i></div>`;
                } else {
                    previewHtml = `<div class="flex flex-col items-center"><i class="bi ${iconClass} text-5xl opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-300"></i><span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-semibold">${fileExt.toUpperCase()}</span></div>`;
                }

                return `<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all group relative flex flex-col overflow-hidden" id="file-${file.id}">
                    <div class="h-40 bg-gray-100 dark:bg-slate-700 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer group" onclick="previewFile('${file.name}', ${file.id}, '${fileUrl}', ${fileSize}, '${file.created_at}')">${previewHtml}</div>
                    <div class="p-4 flex flex-col flex-1">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="min-w-0 flex-1"><div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate line-clamp-2" title="${file.name}">${file.name}</div></div>
                            <div class="relative flex-shrink-0">
                                <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="More options" onclick="event.stopPropagation(); document.getElementById('file-menu-${file.id}').classList.toggle('hidden');">
                                    <i class="bi bi-three-dots-vertical text-lg"></i>
                                </button>
                                <div id="file-menu-${file.id}" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-slate-700 rounded-lg shadow-xl border border-gray-200 dark:border-slate-600 z-50 py-2">
                                    <button onclick="previewFile('${file.name}', ${file.id}, '${fileUrl}', ${fileSize}, '${file.created_at}'); document.getElementById('file-menu-${file.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-eye"></i><span>View</span></button>
                                    <a href="${fileUrl}" download="${file.name}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors" onclick="document.getElementById('file-menu-${file.id}').classList.add('hidden');"><i class="bi bi-download"></i><span>Download</span></a>
                                    <button onclick="openArchiveVersionHistory(${file.id}, '${file.name}'); document.getElementById('file-menu-${file.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-clock-history"></i><span>History</span></button>
                                    <hr class="my-1 border-gray-200 dark:border-slate-600">
                                    <button onclick="openRequestersModal(${file.id}, 'archive', '${file.name.replace(/'/g, "\\'")}'); document.getElementById('file-menu-${file.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center gap-3 transition-colors"><i class="bi bi-people"></i><span>View Requesters</span></button>
                                    <hr class="my-1 border-gray-200 dark:border-slate-600">
                                    <button onclick="moveToHiddenFolder(${file.id}, '${file.name}'); document.getElementById('file-menu-${file.id}').classList.add('hidden');" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-3 transition-colors"><i class="bi bi-eye-slash-fill"></i><span>Move to Hidden Folder</span></button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Author:</span><span class="truncate">${author}</span></div>
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Date:</span><span>${date}</span></div>
                            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span class="font-medium">Size:</span><span>${formatFileSize(fileSize)}</span></div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-600">
                            <div class="bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800/30 text-center">
                                <div class="text-xs text-blue-700 dark:text-blue-300 font-semibold">Document ID</div>
                                <div class="text-xs font-mono text-blue-900 dark:text-blue-200 font-bold">${uniqueId}</div>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function formatFileSize(fileSize) {
            if (fileSize <= 0) return 'Unknown';
            return fileSize >= 1073741824 ? (fileSize / 1073741824).toFixed(2) + ' GB' :
                  (fileSize >= 1048576 ? (fileSize / 1048576).toFixed(2) + ' MB' :
                  (fileSize >= 1024 ? (fileSize / 1024).toFixed(2) + ' KB' : fileSize + ' B'));
        }

        // Handle highlight on page load
        document.addEventListener('DOMContentLoaded', function() {
            handleHighlight();
        });
    </script>
</body>
</html>
</think_never_used_51bce0c785ca2f68081bfa7d91973934>Okay, let's update the todo list now!

<seed:tool_call><function name="TodoWrite"><parameter name="todos" string="false">[{"id": "1", "content": "Explore current folder_view.php and other relevant files", "status": "completed", "priority": "high"}, {"id": "2", "content": "Enhance toolbar with sort, search, create folder, and upload file for both legislative and archive", "status": "completed", "priority": "high"}, {"id": "3", "content": "Enhance grid view of uploaded files (for both legislative and archive folders)", "status": "completed", "priority": "high"}, {"id": "4", "content": "Implement sorting and search functionality", "status": "completed", "priority": "high"}, {"id": "5", "content": "Add missing JS functions and version history modal", "status": "completed", "priority": "high"}, {"id": "6", "content": "Test everything", "status": "in_progress", "priority": "medium"}]
<?php
require 'authdatabase.php';
// session_start() removed as it is handled in authdatabase.php

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($current_folder_id === 0) {
    header("Location: storage.php");
    exit();
}

// Fetch current folder info
$stmt = $conn->prepare("SELECT * FROM archive_folders WHERE id = ?");
$stmt->bind_param("i", $current_folder_id);
$stmt->execute();
$current_folder = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_folder) {
    header("Location: storage.php");
    exit();
}

// Get parent folder for breadcrumb/back link
$parent_folder = null;
if ($current_folder['parent_id']) {
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
        if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
            $author = $_POST['fileAuthor'] ?? null;
            $fdate = $_POST['fileDate'] ?? null;
            $unq_base = $_POST['fileUniqueNumber'] ?? null;
            if ($fdate === '') $fdate = null;

            $uploadedFiles = [];
            $target_dir = "uploads/archives/" . $current_folder_id . "/";
            if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }

            $colCheck = $conn->query("SHOW COLUMNS FROM archive_files LIKE 'author'");
            if ($colCheck && $colCheck->num_rows == 0) {
                $conn->query("ALTER TABLE archive_files ADD COLUMN author VARCHAR(255) DEFAULT NULL, ADD COLUMN file_date DATE DEFAULT NULL, ADD COLUMN unique_number VARCHAR(100) DEFAULT NULL");
            }
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, time VARCHAR(20) NOT NULL, date DATE NOT NULL, content VARCHAR(255) NOT NULL, about VARCHAR(100) NOT NULL, status ENUM('unread','read') NOT NULL DEFAULT 'unread', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
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

                        $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path, author, file_date, unique_number) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssss", $current_folder_id, $final_name, $file_path, $author, $fdate, $unq);
                        if ($stmt->execute()) {
                            $new_id = $conn->insert_id;
                            if ($isBlankUnq) {
                                $unq = sprintf("DOC-%06d", $new_id);
                                $conn->query("UPDATE archive_files SET unique_number = '$unq' WHERE id = $new_id");
                            }
                            $uploadedFiles[] = ['id' => $new_id, 'name' => $final_name];
                        }
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
                echo json_encode(['success' => true, 'files' => $uploadedFiles]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload files']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No files provided']);
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

// Fetch subfolders
$subfolders = [];
$stmt = $conn->prepare("SELECT * FROM archive_folders WHERE parent_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $current_folder_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $subfolders[] = $row;

// Fetch files
$files = [];
$stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR) ORDER BY created_at DESC");
$stmt->bind_param("i", $current_folder_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $files[] = $row;

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
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-slate-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="<?php echo $parent_folder ? "folder_view.php?id=" . $parent_folder['id'] : "storage.php"; ?>" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                        <i class="bi bi-arrow-left text-xl"></i>
                        <span class="font-semibold">Back to <?php echo $parent_folder ? htmlspecialchars($parent_folder['name']) : "Main Storage"; ?></span>
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
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <button id="create-subfolder-btn" type="button" onclick="openCreateFolderModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
                <div class="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full group-hover:scale-110 transition-transform">
                    <i class="bi bi-folder-plus text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200">Create Subfolder</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Add a new folder here</div>
                </div>
            </button>
            <button id="upload-file-btn" type="button" onclick="openUploadModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
                <div class="bg-green-100 dark:bg-green-900/30 p-3 rounded-full group-hover:scale-110 transition-transform">
                    <i class="bi bi-cloud-upload text-2xl text-green-600 dark:text-green-400"></i>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200">Upload File</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Upload documents to this folder</div>
                </div>
            </button>
            
        </div>

        <!-- Content List -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="font-semibold text-gray-700 dark:text-gray-300">Folder Contents</h3>
                <span class="text-xs text-gray-500 bg-gray-200 dark:bg-slate-700 px-2 py-1 rounded-full"><?php echo count($subfolders) + count($files); ?> items</span>
            </div>
            
            <div id="content-list" class="divide-y divide-gray-100 dark:divide-slate-700">
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
                ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all group relative flex flex-col" id="file-<?php echo $file['id']; ?>">
                    <div class="h-32 bg-gray-100 dark:bg-slate-700/50 rounded-t-xl flex items-center justify-center overflow-hidden relative cursor-pointer" onclick="previewFile('<?php echo htmlspecialchars($file['name']); ?>', <?php echo $file['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $file['created_at']; ?>')">
                        <?php if (in_array($fileExt, ['jpg','jpeg','png','gif','webp']) && file_exists($fileUrl)): ?>
                            <img src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="bi <?php echo $iconClass; ?> text-5xl opacity-80 group-hover:scale-110 transition-transform"></i>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 flex items-start justify-between flex-1">
                        <div class="min-w-0 pr-2">
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate" title="<?php echo htmlspecialchars($file['name']); ?>"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                <?php echo !empty($file['author']) ? htmlspecialchars($file['author']) : 'Unknown Author'; ?> • <?php echo !empty($file['file_date']) ? date('M d, Y', strtotime($file['file_date'])) : date('M d, Y', strtotime($file['created_at'])); ?>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">
                                ID: <span class="font-mono"><?php echo !empty($file['unique_number']) ? htmlspecialchars($file['unique_number']) : sprintf("DOC-%06d", $file['id']); ?></span>
                            </div>
                        </div>
                        <div class="relative flex-shrink-0">
                            <button class="p-1 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 transition-colors" onclick="document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.toggle('hidden'); setTimeout(() => { document.addEventListener('click', function _close(e){ if(!e.target.closest('#file-menu-<?php echo $file['id']; ?>') && !e.target.closest('button')){ document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden'); document.removeEventListener('click', _close); }}); }, 10);">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <!-- Dropdown menu -->
                            <div id="file-menu-<?php echo $file['id']; ?>" class="hidden absolute right-0 mt-1 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 py-1">
                                <button onclick="previewFile('<?php echo htmlspecialchars($file['name']); ?>', <?php echo $file['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $file['created_at']; ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 flex items-center">
                                    <i class="bi bi-eye mr-2"></i> View
                                </button>
                                <button onclick="openArchiveVersionHistory(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>'); document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 flex items-center">
                                    <i class="bi bi-clock-history mr-2"></i> History
                                </button>
                                <a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($file['name']); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 flex items-center" onclick="document.getElementById('file-menu-<?php echo $file['id']; ?>').classList.add('hidden');">
                                    <i class="bi bi-download mr-2"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
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
            e.preventDefault();
            if (isUploading) return;
            isUploading = true;
            const fileInput = document.getElementById('fileInput');
            if (!fileInput.files.length) {
                openNotification('Please select a file to upload.', 'error');
                try { fileInput.focus(); fileInput.click(); } catch(_) {}
                isUploading = false;
                return;
            }
            if (fileInput.files.length > 3) {
                openNotification('Please select up to 3 files.', 'error');
                isUploading = false;
                return;
            }

            const progress = document.getElementById('upload-progress');
            if (progress) {
                progress.classList.remove('hidden');
                progress.textContent = `Uploading ${fileInput.files.length} file(s)...`;
            }

            let successCount = 0;
            const formData = new FormData();
            formData.append('action', 'upload_files_bulk');
            formData.append('fileAuthor', document.getElementById('fileAuthor')?.value || '');
            formData.append('fileDate', document.getElementById('fileDate')?.value || '');
            formData.append('fileUniqueNumber', document.getElementById('fileUniqueNumber')?.value || '');

            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }

            try {
                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST', body: formData
                });
                const data = await response.json();
                if (data.success && data.files) {
                    successCount = data.files.length;
                    closeUploadModal();
                    openNotification(`${successCount} file(s) uploaded successfully!`, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    openNotification(data.message || 'Failed to upload files', 'error');
                }
            } catch (e) {
                console.error(e);
                openNotification('Network error during upload', 'error');
            }

            if (progress) progress.classList.add('hidden');
            isUploading = false;
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
            if (type === 'error') {
                title.textContent = 'Error';
                msg.textContent = message || 'Something went wrong.';
                icon.className = 'flex-none rounded-full p-2 bg-red-100 dark:bg-red-900/30';
                icon.innerHTML = '<i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>';
            } else {
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
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

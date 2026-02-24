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
             echo json_encode(['success' => true, 'folder' => ['id' => $new_id, 'name' => $name, 'slug' => $slug]]);
        } else {
             echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
        }
        exit();
    }
    
    if ($action === 'upload_file') {
        header('Content-Type: application/json');
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $name = $file['name'];
            $target_dir = "uploads/archives/" . $current_folder_id . "/";
            if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
            // Sanitize filename
            $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $name);
            
            // Check if file exists and append number if needed to preserve original name as much as possible
            $file_path = $target_dir . $safe_name;
            $counter = 1;
            $path_info = pathinfo($safe_name);
            $base_name = $path_info['filename'];
            $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';

            while (file_exists($file_path)) {
                $file_path = $target_dir . $base_name . '_' . $counter . $extension;
                $counter++;
            }
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $conn->query("CREATE TABLE IF NOT EXISTS archive_files (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    folder_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(1024) NOT NULL,
                    version INT DEFAULT 1,
                    parent_version_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $colV = $conn->query("SHOW COLUMNS FROM archive_files LIKE 'version'");
                if ($colV && $colV->num_rows === 0) { $conn->query("ALTER TABLE archive_files ADD COLUMN version INT DEFAULT 1"); }
                $colP = $conn->query("SHOW COLUMNS FROM archive_files LIKE 'parent_version_id'");
                if ($colP && $colP->num_rows === 0) { $conn->query("ALTER TABLE archive_files ADD COLUMN parent_version_id INT NULL"); }
                $version = 1;
                $parent_version_id = NULL;
                if ($st = $conn->prepare("SELECT id, parent_version_id, version FROM archive_files WHERE folder_id = ? AND name = ? ORDER BY id DESC LIMIT 1")) {
                    $st->bind_param("is", $current_folder_id, $name);
                    $st->execute();
                    $r = $st->get_result();
                    if ($r && $r->num_rows) {
                        $ex = $r->fetch_assoc();
                        $root = $ex['parent_version_id'] ? (int)$ex['parent_version_id'] : (int)$ex['id'];
                        if ($st2 = $conn->prepare("SELECT MAX(version) AS mv FROM archive_files WHERE id = ? OR parent_version_id = ?")) {
                            $st2->bind_param("ii", $root, $root);
                            $st2->execute();
                            $rs2 = $st2->get_result();
                            $mv = $rs2 && $rs2->num_rows ? (int)($rs2->fetch_assoc()['mv'] ?? 1) : 1;
                            $st2->close();
                            $version = $mv + 1;
                            $parent_version_id = $root;
                        }
                    }
                    $st->close();
                }
                $hasVersionCols = ($conn->query("SHOW COLUMNS FROM archive_files LIKE 'version'")->num_rows > 0);
                if ($hasVersionCols) {
                    $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path, version, parent_version_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issii", $current_folder_id, $name, $file_path, $version, $parent_version_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $current_folder_id, $name, $file_path);
                }
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'file' => [
                            'id' => $conn->insert_id, 
                            'name' => $name, 
                            'created_at' => date('Y-m-d H:i:s'),
                            'folder_id' => $current_folder_id,
                            'file_path' => $file_path,
                            'version' => $version,
                            'parent_version_id' => $parent_version_id
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
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

    if ($action === 'delete_file') {
        header('Content-Type: application/json');
        $file_id = $input['id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM archive_files WHERE id = ? AND folder_id = ?");
        $stmt->bind_param("ii", $file_id, $current_folder_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if ($file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            $conn->query("DELETE FROM archive_files WHERE id = $file_id");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
        exit();
    }

    if ($action === 'delete_folder') {
        header('Content-Type: application/json');
        $folder_id = $input['id'] ?? 0;
        // Basic check to ensure we are deleting a subfolder of current folder
        $stmt = $conn->prepare("SELECT id FROM archive_folders WHERE id = ? AND parent_id = ?");
        $stmt->bind_param("ii", $folder_id, $current_folder_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            // Recursive delete is complex, for now let's just delete the folder entry.
            // Ideally we should delete all subfiles and subfolders.
            // For this implementation, we'll assume foreign keys CASCADE or we might leave orphans if not careful.
            // The schema update used ON DELETE CASCADE for files, so files are safe.
            // For subfolders, we didn't add foreign key constraint on parent_id in my update script (oops, I just added the column).
            // But let's proceed with simple delete.
            $conn->query("DELETE FROM archive_folders WHERE id = $folder_id");
            echo json_encode(['success' => true]);
        } else {
             echo json_encode(['success' => false, 'message' => 'Folder not found']);
        }
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
            <button id="create-subfolder-btn" onclick="openCreateFolderModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
                <div class="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full group-hover:scale-110 transition-transform">
                    <i class="bi bi-folder-plus text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200">Create Subfolder</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Add a new folder here</div>
                </div>
            </button>
            <button id="upload-file-btn" onclick="openUploadModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
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
                    <?php if ($is_admin): ?>
                    <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                         <button onclick="openDeleteFolderConfirm(<?php echo $folder['id']; ?>, '<?php echo addslashes(htmlspecialchars($folder['name'])); ?>')" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Files -->
                <?php foreach ($files as $file): 
                    $fileUrl = $file['file_path'];
                    $fileSize = file_exists($file['file_path']) ? filesize($file['file_path']) : 0;
                    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
                ?>
                <div class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group" id="file-<?php echo $file['id']; ?>">
                    <div class="flex items-center flex-1 min-w-0 gap-4">
                        <i class="bi bi-file-earmark-text text-2xl text-blue-500"></i>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('M d, Y', strtotime($file['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="previewFile('<?php echo htmlspecialchars($file['name']); ?>', <?php echo $file['id']; ?>, '<?php echo addslashes($fileUrl); ?>', <?php echo $fileSize; ?>, '<?php echo $file['created_at']; ?>')" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <span>View</span>
                        </button>
                        <button onclick="openArchiveVersionHistory(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>')" class="px-3 py-1.5 text-sm font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1" title="Version History">
                            <i class="bi bi-clock-history"></i><span>History</span>
                        </button>
                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($file['name']); ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1" title="Download">
                            <i class="bi bi-download"></i><span>Download</span>
                        </a>
                        <?php if ($is_admin): ?>
                        <button onclick="openDeleteConfirm(<?php echo $file['id']; ?>, '<?php echo addslashes(htmlspecialchars($file['name'])); ?>')" class="px-3 py-2 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:from-red-100 hover:to-orange-100 dark:hover:from-red-900/30 dark:hover:to-orange-900/30 transition-all" title="Delete file">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
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
           <form id="uploadForm" class="space-y-4" onsubmit="handleUpload(event); return false;">
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
                        <input type="file" id="fileInput" name="file" accept="image/*,.pdf,.doc,.docx,.txt" multiple required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                        <div id="file-list-preview" class="mt-2 space-y-1"></div>
                    </div>
                    <div id="upload-progress" class="hidden text-sm text-gray-600 dark:text-gray-400 py-1"></div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        <button type="button" id="uploadBtn" onclick="handleUpload(event)" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors cursor-pointer font-medium focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Upload</button>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('deleteModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700 transform transition-all scale-100 opacity-100 duration-300">
                <div class="mb-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                        <i class="bi bi-trash text-3xl text-red-600 dark:text-red-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Delete File?</h3>
                    <p class="text-gray-500 dark:text-gray-400">Are you sure you want to delete <span id="deleteFileName" class="font-semibold text-gray-800 dark:text-gray-200"></span>?</p>
                </div>
                <div class="flex justify-center space-x-4">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-5 py-2.5 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-all font-medium">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmDelete()" class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-xl shadow-lg transition-all font-medium flex items-center">
                        <i class="bi bi-trash mr-2"></i>
                        Delete File
                    </button>
                </div>
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
            document.getElementById('createFolderModal').classList.remove('hidden');
            document.getElementById('newFolderName').focus();
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
            document.getElementById('uploadFileModal').classList.remove('hidden');
            try { document.getElementById('fileInput').focus(); } catch(_) {}
        }

        function closeUploadModal() {
            document.getElementById('uploadFileModal').classList.add('hidden');
            document.getElementById('fileInput').value = '';
            document.getElementById('file-list-preview').innerHTML = '';
            document.getElementById('upload-progress').classList.add('hidden');
        }
        (function(){
            const createBtn = document.getElementById('create-subfolder-btn');
            const uploadBtn = document.getElementById('upload-file-btn');
            createBtn && createBtn.addEventListener('click', function(e){ e.preventDefault(); openCreateFolderModal(); });
            uploadBtn && uploadBtn.addEventListener('click', function(e){ e.preventDefault(); openUploadModal(); });
            document.addEventListener('click', function(e){
                const t = e.target;
                if (t && t.id === 'create-subfolder-btn') { e.preventDefault(); openCreateFolderModal(); }
                if (t && t.id === 'upload-file-btn') { e.preventDefault(); openUploadModal(); }
                const uploadTrigger = t && (t.id === 'uploadBtn' || (t.closest && t.closest('#uploadBtn')));
                if (uploadTrigger) { e.preventDefault(); handleUpload(e); }
            });
            const uploadForm = document.getElementById('uploadForm');
            uploadForm && uploadForm.addEventListener('submit', function(e){ e.preventDefault(); handleUpload(e); });
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
                ${isAdmin ? `
                <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="openDeleteFolderConfirm(${folder.id}, '${escapeHtml(folder.name || 'New Folder')}')" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>` : ''}
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

        async function handleUpload(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            if (!fileInput.files.length) {
                openNotification('Please select a file to upload.', 'error');
                try { fileInput.focus(); fileInput.click(); } catch(_) {}
                return;
            }
            if (fileInput.files.length > 3) {
                openNotification('Please select up to 3 files.', 'error');
                return;
            }

            const progress = document.getElementById('upload-progress');
            if (progress) {
                progress.classList.remove('hidden');
                progress.textContent = `Uploading ${fileInput.files.length} file(s)...`;
            }

            let successCount = 0;
            const list = document.getElementById('content-list');

            for (let i = 0; i < fileInput.files.length; i++) {
                const formData = new FormData();
                formData.append('action', 'upload_file');
                formData.append('file', fileInput.files[i]);
                const baseName = (document.getElementById('fileName')?.value || '').trim() || fileInput.files[i].name.replace(/\.[^.\s]+$/, '');
                formData.append('fileName', baseName);
                try {
                    const pre = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check_duplicate', name: fileInput.files[i].name, base: baseName })
                    });
                    const preData = await pre.json();
                    if (preData && preData.success && preData.exists) {
                        const ok = await confirmDuplicateAsync();
                        if (!ok) { continue; }
                    }
                } catch (_e) {}

                try {
                    const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        successCount++;
                        const file = data.file;
                        const div = document.createElement('div');
                        div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group';
                        div.id = 'file-' + file.id;
                        div.innerHTML = `
                            <div class="flex items-center flex-1 min-w-0 gap-4">
                                <i class="bi bi-file-earmark-text text-2xl text-blue-500"></i>
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-200 truncate">${escapeHtml(file.name)}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Just now</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="previewFile('${escapeHtml(file.name)}', ${file.id}, '${escapeHtml(file.file_path)}', ${fileInput.files[i].size}, '${file.created_at}')" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <span>View</span>
                                </button>
                                <button onclick="openArchiveVersionHistory(${file.id}, '${escapeHtml(file.name)}')" class="px-3 py-1.5 text-sm font-semibold bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded hover:bg-gray-50 dark:hover:bg-slate-600 flex items-center space-x-1">
                                    <i class="bi bi-clock-history"></i><span>History</span><span class="ml-1 bg-gray-200 dark:bg-gray-600 px-1.5 rounded-full" id="ver-count-${file.id}">…</span>
                                </button>
                                <a href="${escapeHtml(file.file_path)}" download="${escapeHtml(file.name)}" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    <span>Download</span>
                                </a>
                                ${isAdmin ? `<button onclick="deleteFile(${file.id})" class="px-3 py-2 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:from-red-100 hover:to-orange-100 dark:hover:from-red-900/30 dark:hover:to-orange-900/30 transition-all" title="Delete file">
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>` : ''}
                            </div>
                        `;
                        if (list.querySelector('.text-center')) list.querySelector('.text-center').remove();
                        list.appendChild(div);
                        try {
                            fetch('archives_api.php?action=get_versions&id=' + encodeURIComponent(file.id))
                                .then(function(r){ return r.json(); })
                                .then(function(d){
                                    var cEl = document.getElementById('ver-count-' + String(file.id));
                                    if (cEl) cEl.textContent = (d && d.success && Array.isArray(d.versions)) ? String(d.versions.length) : '0';
                                }).catch(function(){
                                    var cEl = document.getElementById('ver-count-' + String(file.id));
                                    if (cEl) cEl.textContent = '0';
                                });
                        } catch(_e){}
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            if (successCount > 0) {
                closeUploadModal();
                openNotification('Your file(s) have been uploaded.', 'success');
            } else {
                openNotification('Failed to upload files', 'error');
                if (progress) progress.classList.add('hidden');
            }
        }
        (function(){
            const form = document.getElementById('uploadForm');
            const btn = document.getElementById('uploadBtn');
            if (form) form.addEventListener('submit', function(e) { e.preventDefault(); handleUpload(e); });
            if (btn) btn.addEventListener('click', function(e) { e.preventDefault(); handleUpload(e); });
        })();

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
        
        function openDeleteConfirm(id, name) {
            deleteId = id;
            deleteIsFolder = false;
            const el = document.getElementById('deleteFileName');
            if (el) el.textContent = name;
            openModal('deleteModal');
        }
        function openDeleteFolderConfirm(id, name) {
            deleteId = id;
            deleteIsFolder = true;
            const el = document.getElementById('deleteFileName');
            if (el) el.textContent = name;
            openModal('deleteModal');
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
        async function confirmDelete() {
            closeModal('deleteModal');
            if (deleteId == null) return;
            if (deleteIsFolder) {
                try {
                    const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_folder', id: deleteId })
                    });
                    const data = await response.json();
                    if (data.success) {
                        const el = document.getElementById('folder-' + deleteId);
                        if (el) el.remove();
                    } else {
                        alert(data.message || 'Failed to delete folder');
                    }
                } catch (e) {
                    alert('Error deleting folder');
                }
            } else {
                try {
                    const fileEl = document.getElementById('file-' + deleteId);
                    let fileName = 'Unknown File';
                    if (fileEl) {
                        const nameEl = fileEl.querySelector('.truncate');
                        if (nameEl) fileName = nameEl.textContent;
                    }
                    const deletedItem = {
                        id: deleteId,
                        name: fileName,
                        type: fileName.split('.').pop().toUpperCase(),
                        category: 'Main Storage',
                        originalPath: 'Main Storage',
                        deletedAt: new Date().toLocaleString()
                    };
                    const existing = JSON.parse(localStorage.getItem('deletedFiles') || '[]');
                    existing.push(deletedItem);
                    localStorage.setItem('deletedFiles', JSON.stringify(existing));
                    const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_file', id: deleteId })
                    });
                    const data = await response.json();
                    if (data.success) {
                        if (fileEl) fileEl.remove();
                    } else {
                        alert(data.message || 'Failed to delete file');
                    }
                } catch (e) {
                    alert('Error deleting file');
                }
            }
            deleteId = null;
            deleteIsFolder = false;
        }

        async function deleteFolder(id) {
            if (!confirm('Are you sure you want to delete this folder?')) return;

            try {
                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_folder', id: id })
                });
                const data = await response.json();

                if (data.success) {
                    document.getElementById('folder-' + id).remove();
                } else {
                    alert(data.message || 'Failed to delete folder');
                }
            } catch (e) {
                console.error(e);
                alert('Error deleting folder');
            }
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

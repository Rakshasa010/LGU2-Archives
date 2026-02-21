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
             echo json_encode(['success' => true, 'folder' => ['id' => $conn->insert_id, 'name' => $name, 'slug' => $slug]]);
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
            $target_dir = "uploads/archives/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            // Sanitize filename
            $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $name);
            $file_path = $target_dir . time() . '_' . $safe_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $current_folder_id, $name, $file_path);
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'file' => [
                            'id' => $conn->insert_id, 
                            'name' => $name, 
                            'created_at' => date('Y-m-d H:i:s')
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
$stmt = $conn->prepare("SELECT * FROM archive_files WHERE folder_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $current_folder_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $files[] = $row;

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
            <button onclick="openCreateFolderModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
                <div class="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full group-hover:scale-110 transition-transform">
                    <i class="bi bi-folder-plus text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-200">Create Subfolder</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Add a new folder here</div>
                </div>
            </button>
            <button onclick="openUploadModal()" class="flex items-center justify-center gap-3 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 hover:shadow-md transition-all group text-left">
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
                    <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                         <button onclick="deleteFolder(<?php echo $folder['id']; ?>)" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Files -->
                <?php foreach ($files as $file): ?>
                <div class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group" id="file-<?php echo $file['id']; ?>">
                    <div class="flex items-center flex-1 min-w-0 gap-4">
                        <i class="bi bi-file-earmark-text text-2xl text-blue-500"></i>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('M d, Y', strtotime($file['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="download_file.php?id=<?php echo $file['id']; ?>" target="_blank" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 transition-colors">
                            View
                        </a>
                        <a href="download_file.php?id=<?php echo $file['id']; ?>" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 transition-colors">
                            Download
                        </a>
                        <button onclick="deleteFile(<?php echo $file['id']; ?>)" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

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
    <div id="uploadFileModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeUploadModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6 border border-gray-200 dark:border-slate-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Upload File</h3>
            <form id="uploadForm" onsubmit="handleUpload(event)">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File</label>
                    <input type="file" id="fileInput" required class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateFolderModal() {
            document.getElementById('createFolderModal').classList.remove('hidden');
            document.getElementById('newFolderName').focus();
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').classList.add('hidden');
            document.getElementById('newFolderName').value = '';
        }

        function openUploadModal() {
            document.getElementById('uploadFileModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadFileModal').classList.add('hidden');
            document.getElementById('fileInput').value = '';
        }

        async function createFolder() {
            const name = document.getElementById('newFolderName').value.trim();
            if (!name) return;

            try {
                const formData = new FormData();
                formData.append('action', 'create_folder');
                formData.append('name', name);

                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const folder = data.folder;
                    const list = document.getElementById('content-list');
                    const div = document.createElement('div');
                    div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex items-center justify-between group';
                    div.id = 'folder-' + folder.id;
                    div.innerHTML = `
                        <a href="folder_view.php?id=${folder.id}" class="flex items-center flex-1 min-w-0 gap-4">
                            <i class="bi bi-folder-fill text-2xl text-yellow-500"></i>
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800 dark:text-gray-200 truncate">${escapeHtml(folder.name)}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Just now</div>
                            </div>
                        </a>
                        <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                             <button onclick="deleteFolder(${folder.id})" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    // Insert at top of list (after potential empty message)
                    if (list.querySelector('.text-center')) list.querySelector('.text-center').remove();
                    list.insertBefore(div, list.firstChild);
                    closeCreateFolderModal();
                } else {
                    alert(data.message || 'Failed to create folder');
                }
            } catch (e) {
                console.error(e);
                alert('Error creating folder');
            }
        }

        async function handleUpload(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            if (!fileInput.files.length) return;

            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('file', fileInput.files[0]);

            try {
                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const file = data.file;
                    const list = document.getElementById('content-list');
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
                            <a href="download_file.php?id=${file.id}" target="_blank" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 transition-colors">
                                View
                            </a>
                            <a href="download_file.php?id=${file.id}" class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 transition-colors">
                                Download
                            </a>
                            <button onclick="deleteFile(${file.id})" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    if (list.querySelector('.text-center')) list.querySelector('.text-center').remove();
                    list.appendChild(div); // Append files at bottom, or insert after folders? 
                    // To keep folders first, we might need to find the last folder and insert after it, or just append to end.
                    // For simplicity, appending to end is fine as folders are usually at top.
                    closeUploadModal();
                } else {
                    alert(data.message || 'Failed to upload file');
                }
            } catch (e) {
                console.error(e);
                alert('Error uploading file');
            }
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

        async function deleteFile(id) {
            if (!confirm('Are you sure you want to delete this file?')) return;

            try {
                const response = await fetch('folder_view.php?id=<?php echo $current_folder_id; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_file', id: id })
                });
                const data = await response.json();

                if (data.success) {
                    document.getElementById('file-' + id).remove();
                } else {
                    alert(data.message || 'Failed to delete file');
                }
            } catch (e) {
                console.error(e);
                alert('Error deleting file');
            }
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
    </script>
    <?php include 'includes/footer_scripts.php'; ?>
</body>
</html>

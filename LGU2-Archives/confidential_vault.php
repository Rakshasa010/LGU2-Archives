<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'authdatabase.php';

$user_id = (int)$_SESSION['user_id'];

// Check if user's hidden folder is unlocked
$folder_unlocked = isset($_SESSION['hidden_folder_unlocked']) && $_SESSION['hidden_folder_unlocked'] === true;

// Get user's hidden folder info
$stmt = $conn->prepare("SELECT pin_hash, is_setup FROM user_hidden_folders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$folder_info = $result->fetch_assoc();
$stmt->close();

$folder_exists = !empty($folder_info);
$folder_setup = $folder_exists && $folder_info['is_setup'];

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $action = $payload['action'] ?? '';
        
        if ($action === 'setup_hidden_folder') {
            $pin = $payload['pin'] ?? '';
            
            if (strlen($pin) !== 6 || !ctype_digit($pin)) {
                echo json_encode(['success' => false, 'message' => 'PIN must be exactly 6 digits']);
                exit();
            }
            
            $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
            
            if ($folder_exists) {
                // Update existing folder
                $stmt = $conn->prepare("UPDATE user_hidden_folders SET pin_hash = ?, is_setup = TRUE WHERE user_id = ?");
                $stmt->bind_param("si", $pin_hash, $user_id);
            } else {
                // Create new folder
                $stmt = $conn->prepare("INSERT INTO user_hidden_folders (user_id, pin_hash, is_setup) VALUES (?, ?, TRUE)");
                $stmt->bind_param("is", $user_id, $pin_hash);
            }
            
            if ($stmt->execute()) {
                $_SESSION['hidden_folder_unlocked'] = true;
                echo json_encode(['success' => true, 'message' => 'Hidden folder set up successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to set up hidden folder']);
            }
            $stmt->close();
            exit();
        }
        
        if ($action === 'unlock_hidden_folder') {
            if (!$folder_setup) {
                echo json_encode(['success' => false, 'message' => 'Hidden folder not set up']);
                exit();
            }
            
            $pin = $payload['pin'] ?? '';
            
            if (password_verify($pin, $folder_info['pin_hash'])) {
                $_SESSION['hidden_folder_unlocked'] = true;
                echo json_encode(['success' => true, 'message' => 'Hidden folder unlocked']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
            }
            exit();
        }
        
        if ($action === 'lock_hidden_folder') {
            unset($_SESSION['hidden_folder_unlocked']);
            echo json_encode(['success' => true, 'message' => 'Hidden folder locked']);
            exit();
        }
        
        if ($action === 'move_to_hidden_folder') {
            if (!$folder_unlocked) {
                echo json_encode(['success' => false, 'message' => 'Hidden folder is locked']);
                exit();
            }
            
            $file_id = (int)($payload['file_id'] ?? 0);
            $source_type = $payload['source_type'] ?? '';
            
            if ($file_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
                exit();
            }
            
            // Get file info based on source type
            if ($source_type === 'archive') {
                $stmt = $conn->prepare("SELECT name, file_path FROM archive_files WHERE id = ?");
            } elseif ($source_type === 'legislative') {
                $stmt = $conn->prepare("SELECT title as name, file_path FROM legislative_records WHERE id = ?");
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid source type']);
                exit();
            }
            
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'File not found']);
                $stmt->close();
                exit();
            }
            
            $file = $result->fetch_assoc();
            $stmt->close();
            
            // Move file to user's hidden folder
            $stmt = $conn->prepare("INSERT INTO hidden_files (user_id, name, file_path, original_source, original_id, moved_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssii", $user_id, $file['name'], $file['file_path'], $source_type, $file_id, $user_id);
            
            if ($stmt->execute()) {
                // Delete from original location
                if ($source_type === 'archive') {
                    $del = $conn->prepare("DELETE FROM archive_files WHERE id = ?");
                } else {
                    $del = $conn->prepare("DELETE FROM legislative_records WHERE id = ?");
                }
                $del->bind_param("i", $file_id);
                $del->execute();
                $del->close();
                
                // Log activity
                $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    time VARCHAR(20) NOT NULL,
                    date DATE NOT NULL,
                    content VARCHAR(255) NOT NULL,
                    about VARCHAR(100) NOT NULL,
                    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = 'File moved to hidden folder: ' . $file['name'];
                $nabout = 'Hidden Folder';
                $nstatus = 'unread';
                
                $notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)");
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
                
                echo json_encode(['success' => true, 'message' => 'File moved to hidden folder']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move file']);
            }
            
            $stmt->close();
            exit();
        }
        
        if ($action === 'remove_from_hidden_folder') {
            if (!$folder_unlocked) {
                echo json_encode(['success' => false, 'message' => 'Hidden folder is locked']);
                exit();
            }
            
            $file_id = (int)($payload['file_id'] ?? 0);
            
            if ($file_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
                exit();
            }
            
            // Get file info (only files belonging to current user)
            $stmt = $conn->prepare("SELECT name, file_path FROM hidden_files WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $file_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
                $stmt->close();
                exit();
            }
            
            $file = $result->fetch_assoc();
            $stmt->close();
            
            // Delete file from disk
            if (file_exists($file['file_path'])) {
                @unlink($file['file_path']);
            }
            
            // Delete from database
            $del = $conn->prepare("DELETE FROM hidden_files WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $file_id, $user_id);
            
            if ($del->execute()) {
                // Log activity
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = 'File removed from hidden folder: ' . $file['name'];
                $nabout = 'Hidden Folder';
                $nstatus = 'unread';
                
                $notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)");
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
                
                echo json_encode(['success' => true, 'message' => 'File removed from hidden folder']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove file']);
            }
            
            $del->close();
            exit();
        }
        
        if ($action === 'get_hidden_files') {
            if (!$folder_unlocked) {
                echo json_encode(['success' => false, 'message' => 'Hidden folder is locked']);
                exit();
            }
            
            $stmt = $conn->prepare("SELECT id, name, file_path, created_at FROM hidden_files WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $files = [];
            while ($row = $result->fetch_assoc()) {
                $files[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'files' => $files]);
            exit();
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// Get user data for display
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hidden Folder - Document Management</title>
    <?php include 'includes/header_scripts.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
    
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center gap-4">
                        <a href="storage.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            <i class="bi bi-arrow-left text-xl"></i>
                        </a>
                        <div class="flex items-center gap-3">
                            <div class="bg-red-100 dark:bg-red-900/30 p-2 rounded-lg">
                                <i class="bi bi-eye-slash-fill text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-800 dark:text-gray-100">Hidden Folder</h1>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Personal secure storage</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                            <i class="bi bi-moon-fill text-gray-700 dark:text-gray-300"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <?php if (!$files_unlocked): ?>
                    <?php if (!$files_setup): ?>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-12">
                        <div class="flex flex-col items-center justify-center">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-8 rounded-full mb-6">
                                <i class="bi bi-gear-fill text-blue-600 dark:text-blue-400 text-6xl"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-3">Setup Your Hidden Folder</h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 text-center max-w-md">Create a personal 6-digit PIN to secure your hidden folder. Only you will have access to files stored here.</p>
                            <button onclick="openHiddenFolderModal('setup')" class="px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition-colors">
                                <i class="bi bi-plus-circle-fill mr-2"></i>Setup Hidden Folder
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-12">
                        <div class="flex flex-col items-center justify-center">
                            <div class="bg-red-50 dark:bg-red-900/20 p-8 rounded-full mb-6">
                                <i class="bi bi-eye-slash text-red-600 dark:text-red-400 text-6xl"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-3">Hidden Folder is Locked</h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-8">Enter your personal PIN to access your hidden files</p>
                            <button onclick="openHiddenFolderModal('unlock')" class="px-6 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold transition-colors">
                                <i class="bi bi-unlock-fill mr-2"></i>Unlock Hidden Folder
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-eye text-green-600 dark:text-green-400"></i>
                            <span class="text-sm text-green-700 dark:text-green-300 font-medium">Hidden folder is unlocked</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400" id="file-count">Loading...</span>
                            <button onclick="lockHiddenFolder()" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-medium transition-colors">
                                <i class="bi bi-eye-slash mr-2"></i>Lock Folder
                            </button>
                        </div>
                    </div>
                    
                    <div id="files-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Files will be loaded here -->
                    </div>
                    
                    <div id="empty-state" class="hidden flex flex-col items-center justify-center py-12">
                        <i class="bi bi-inbox text-gray-400 dark:text-gray-600 text-5xl mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No hidden files yet</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Move files here from other folders to keep them private</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
            </div>
        </main>
    </div>
    
    <!-- Hidden Folder PIN Modal -->
    <div id="hidden-folder-modal" class="hidden fixed inset-0 z-50">
        <div id="hidden-folder-backdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl p-6">
                <div class="text-center mb-6">
                    <div class="bg-red-100 dark:bg-red-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-eye-slash-fill text-red-600 dark:text-red-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1" id="hidden-folder-modal-title">Enter PIN</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400" id="hidden-folder-modal-subtitle">Enter your 6-digit PIN</p>
                </div>
                
                <div class="mb-4">
                    <div class="flex justify-center gap-2 mb-4">
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                        <input type="password" maxlength="1" class="hidden-folder-pin-input w-12 h-14 text-center text-2xl font-bold rounded-lg border-2 border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100 focus:border-red-500 focus:outline-none" />
                    </div>
                    <div id="hidden-folder-pin-error" class="text-xs text-red-600 dark:text-red-400 text-center hidden"></div>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button id="hidden-folder-pin-cancel" type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 text-sm font-semibold">Cancel</button>
                    <button id="hidden-folder-pin-confirm" type="button" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="toast" class="fixed right-6 bottom-6 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 transform translate-y-4 transition-all z-50 font-semibold"></div>
    
    <?php include 'includes/footer_scripts.php'; ?>
    
    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
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
        
        let hiddenFolderMode = 'unlock';
        
        function openHiddenFolderModal(mode) {
            hiddenFolderMode = mode;
            const modal = document.getElementById('hidden-folder-modal');
            const title = document.getElementById('hidden-folder-modal-title');
            const subtitle = document.getElementById('hidden-folder-modal-subtitle');
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            const error = document.getElementById('hidden-folder-pin-error');
            
            if (mode === 'setup') {
                title.textContent = 'Setup Hidden Folder PIN';
                subtitle.textContent = 'Create a 6-digit PIN to secure your folder';
            } else {
                title.textContent = 'Enter PIN';
                subtitle.textContent = 'Enter your 6-digit PIN to unlock';
            }
            
            inputs.forEach(input => input.value = '');
            error.classList.add('hidden');
            modal.classList.remove('hidden');
            setTimeout(() => inputs[0]?.focus(), 100);
        }
        
        function closeHiddenFolderModal() {
            const modal = document.getElementById('hidden-folder-modal');
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            const error = document.getElementById('hidden-folder-pin-error');
            
            modal.classList.add('hidden');
            inputs.forEach(input => input.value = '');
            error.classList.add('hidden');
        }
        
        function getHiddenFolderPin() {
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            return Array.from(inputs).map(input => input.value).join('');
        }
        
        function handleHiddenFolderPinInput(e, index) {
            const input = e.target;
            const value = input.value;
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            
            if (value && /^\d$/.test(value)) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            } else if (!value) {
                input.value = '';
            }
        }
        
        function handleHiddenFolderPinKeydown(e, index) {
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            } else if (e.key === 'Enter') {
                document.getElementById('hidden-folder-pin-confirm').click();
            }
        }
        
        function lockHiddenFolder() {
            fetch('confidential_vault.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'lock_hidden_folder' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to lock folder', 'error');
                }
            })
            .catch(e => {
                showToast('Connection error', 'error');
            });
        }
        
        <?php if ($folder_unlocked): ?>
        function loadFiles() {
            fetch('confidential_vault.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_hidden_files' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const files = data.files || [];
                    document.getElementById('file-count').textContent = files.length + (files.length === 1 ? ' file' : ' files');
                    
                    if (files.length === 0) {
                        document.getElementById('files-grid').innerHTML = '';
                        document.getElementById('empty-state').classList.remove('hidden');
                    } else {
                        document.getElementById('empty-state').classList.add('hidden');
                        renderFiles(files);
                    }
                }
            })
            .catch(e => console.error('Load failed:', e));
        }
        
        function renderFiles(files) {
            const grid = document.getElementById('files-grid');
            grid.innerHTML = files.map(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                let icon = 'bi-file-earmark';
                let color = 'text-gray-600 dark:text-gray-400';
                
                if (['pdf'].includes(ext)) {
                    icon = 'bi-file-earmark-pdf';
                    color = 'text-red-600 dark:text-red-400';
                } else if (['doc', 'docx'].includes(ext)) {
                    icon = 'bi-file-earmark-word';
                    color = 'text-blue-600 dark:text-blue-400';
                } else if (['xls', 'xlsx'].includes(ext)) {
                    icon = 'bi-file-earmark-excel';
                    color = 'text-green-600 dark:text-green-400';
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                    icon = 'bi-file-earmark-image';
                    color = 'text-purple-600 dark:text-purple-400';
                }
                
                return `
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4 hover:shadow-md transition-all group">
                        <div class="flex items-start justify-between mb-3">
                            <div class="${color} text-3xl">
                                <i class="bi ${icon}"></i>
                            </div>
                            <button onclick="removeFile(${file.id})" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="font-medium text-gray-900 dark:text-gray-100 text-sm mb-1 truncate" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${new Date(file.created_at).toLocaleDateString()}</div>
                    </div>
                `;
            }).join('');
        }
        
        function removeFile(fileId) {
            if (!confirm('Remove this file from the hidden folder? This will permanently delete the file.')) {
                return;
            }
            
            fetch('confidential_vault.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_from_hidden_folder', file_id: fileId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadFiles();
                } else {
                    showToast(data.message || 'Failed to remove file', 'error');
                }
            })
            .catch(e => {
                showToast('Connection error', 'error');
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadFiles();
        <?php endif; ?>
        
        // Setup PIN input handlers
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.hidden-folder-pin-input');
            
            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => handleHiddenFolderPinInput(e, index));
                input.addEventListener('keydown', (e) => handleHiddenFolderPinKeydown(e, index));
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = paste.replace(/\D/g, '').slice(0, 6);
                    digits.split('').forEach((digit, i) => {
                        if (inputs[i]) {
                            inputs[i].value = digit;
                        }
                    });
                    if (digits.length > 0) {
                        const lastIndex = Math.min(digits.length, inputs.length) - 1;
                        inputs[lastIndex].focus();
                    }
                });
            });
            
            document.getElementById('hidden-folder-pin-cancel')?.addEventListener('click', closeHiddenFolderModal);
            document.getElementById('hidden-folder-backdrop')?.addEventListener('click', closeHiddenFolderModal);
            
            document.getElementById('hidden-folder-pin-confirm')?.addEventListener('click', () => {
                const pin = getHiddenFolderPin();
                const error = document.getElementById('hidden-folder-pin-error');
                
                if (!/^\d{6}$/.test(pin)) {
                    error.textContent = 'Please enter all 6 digits';
                    error.classList.remove('hidden');
                    return;
                }
                
                const action = hiddenFolderMode === 'setup' ? 'setup_hidden_folder' : 'unlock_hidden_folder';
                
                fetch('confidential_vault.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, pin: pin })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeHiddenFolderModal();
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        error.textContent = data.message || 'Operation failed';
                        error.classList.remove('hidden');
                    }
                })
                .catch(e => {
                    error.textContent = 'Connection error';
                    error.classList.remove('hidden');
                });
            });
        });
    </script>
</body>
</html>

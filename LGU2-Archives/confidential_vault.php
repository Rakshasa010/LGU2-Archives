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

// Check if vault is unlocked
$vault_unlocked = isset($_SESSION['vault_unlocked']) && $_SESSION['vault_unlocked'] === true;

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $action = $payload['action'] ?? '';
        
        if ($action === 'move_to_vault') {
            if (!$vault_unlocked) {
                echo json_encode(['success' => false, 'message' => 'Vault is locked']);
                exit();
            }
            
            $file_id = (int)($payload['file_id'] ?? 0);
            $source_type = $payload['source_type'] ?? ''; // 'archive' or 'legislative'
            
            if ($file_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
                exit();
            }
            
            $uid = (int)$_SESSION['user_id'];
            
            // Get file info based on source type
            if ($source_type === 'archive') {
                $stmt = $conn->prepare("SELECT name, file_path FROM archive_files WHERE id = ?");
            } elseif ($source_type === 'legislative') {
                $stmt = $conn->prepare("SELECT title as name, file_path FROM legislative_records WHERE id = ?");
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid source type']);
                exit();
            }
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
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
        
        // Move file to vault
        $ins = $conn->prepare("INSERT INTO confidential_files (name, file_path, moved_by) VALUES (?, ?, ?)");
        $ins->bind_param("ssi", $file['name'], $file['file_path'], $uid);
        
        if ($ins->execute()) {
            // Delete from original location
            if ($source_type === 'archive') {
                $del = $conn->prepare("DELETE FROM archive_files WHERE id = ?");
                $del->bind_param("i", $file_id);
                $del->execute();
                $del->close();
            } elseif ($source_type === 'legislative') {
                $del = $conn->prepare("DELETE FROM legislative_records WHERE id = ?");
                $del->bind_param("i", $file_id);
                $del->execute();
                $del->close();
            }
            
            // Log to audit logs / notifications
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
            $ncontent = 'File moved to vault: ' . $file['name'] . ' by user #' . $uid;
            $nabout = 'Vault';
            $nstatus = 'unread';
            
            if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
            }
            
            // Also log to analytics_events if table exists
            $check_analytics = $conn->query("SHOW TABLES LIKE 'analytics_events'");
            if ($check_analytics && $check_analytics->num_rows > 0) {
                $event_type = 'vault_move';
                $record_title = $file['name'];
                $record_type = 'confidential';
                
                if ($analytics = $conn->prepare("INSERT INTO analytics_events (event_type, user_id, record_title, record_type) VALUES (?, ?, ?, ?)")) {
                    $analytics->bind_param('siss', $event_type, $uid, $record_title, $record_type);
                    $analytics->execute();
                    $analytics->close();
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'File moved to vault']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move file']);
        }
        
        $ins->close();
        exit();
    }
    
    if ($action === 'remove_from_vault') {
        if (!$vault_unlocked) {
            echo json_encode(['success' => false, 'message' => 'Vault is locked']);
            exit();
        }
        
        $file_id = (int)($payload['file_id'] ?? 0);
        
        if ($file_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit();
        }
        
        $uid = (int)$_SESSION['user_id'];
        
        // Get file info
        $stmt = $conn->prepare("SELECT name, file_path FROM confidential_files WHERE id = ?");
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
        
        // Delete file from disk
        $file_path = $file['file_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        // Delete from database
        $del = $conn->prepare("DELETE FROM confidential_files WHERE id = ?");
        $del->bind_param("i", $file_id);
        
        if ($del->execute()) {
            // Log to audit logs / notifications
            $ntime = date('h:i A');
            $ndate = date('Y-m-d');
            $ncontent = 'File removed from vault: ' . $file['name'] . ' by user #' . $uid;
            $nabout = 'Vault';
            $nstatus = 'unread';
            
            if ($notif = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)")) {
                $notif->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $notif->execute();
                $notif->close();
            }
            
            // Also log to analytics_events if table exists
            $check_analytics = $conn->query("SHOW TABLES LIKE 'analytics_events'");
            if ($check_analytics && $check_analytics->num_rows > 0) {
                $event_type = 'vault_remove';
                $record_title = $file['name'];
                $record_type = 'confidential';
                
                if ($analytics = $conn->prepare("INSERT INTO analytics_events (event_type, user_id, record_title, record_type) VALUES (?, ?, ?, ?)")) {
                    $analytics->bind_param('siss', $event_type, $uid, $record_title, $record_type);
                    $analytics->execute();
                    $analytics->close();
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'File removed from vault']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove file']);
        }
        
        $del->close();
        exit();
    }
    
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$user_data = null;

$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
}
$stmt->close();

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confidential Vault - Document Management</title>
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
                                <i class="bi bi-shield-lock-fill text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-800 dark:text-gray-100">Confidential Vault</h1>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Secure file storage</p>
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
                <?php if (!$vault_unlocked): ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-12">
                    <div class="flex flex-col items-center justify-center">
                        <div class="bg-red-50 dark:bg-red-900/20 p-8 rounded-full mb-6">
                            <i class="bi bi-shield-lock text-red-600 dark:text-red-400 text-6xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-3">Vault is Locked</h2>
                        <p class="text-gray-600 dark:text-gray-400 mb-8">Please unlock the vault from the storage page to access confidential files</p>
                        <a href="storage.php" class="px-6 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold transition-colors">
                            <i class="bi bi-arrow-left mr-2"></i>Back to Storage
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-shield-check text-green-600 dark:text-green-400"></i>
                            <span class="text-sm text-green-700 dark:text-green-300 font-medium">Vault is unlocked</span>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400" id="file-count">Loading...</span>
                    </div>
                    
                    <div id="files-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Files will be loaded here -->
                    </div>
                    
                    <div id="empty-state" class="hidden flex flex-col items-center justify-center py-12">
                        <i class="bi bi-inbox text-gray-400 dark:text-gray-600 text-5xl mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No confidential files yet</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Move files here from other folders to keep them secure</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
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
        
        <?php if ($vault_unlocked): ?>
        function loadFiles() {
            fetch('storage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'vault_get_files' })
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
            if (!confirm('Remove this file from the vault? This will permanently delete the file.')) {
                return;
            }
            
            fetch('confidential_vault.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_from_vault', file_id: fileId })
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
    </script>
</body>
</html>

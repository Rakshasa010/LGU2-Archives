<?php
require 'authdatabase.php';
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'list_folders') {
    $rows = [];
    $q = $conn->query("SELECT id, name, created_at FROM archive_folders ORDER BY created_at DESC LIMIT 100");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $rows[] = ['id'=>(int)$r['id'],'name'=>$r['name'],'created_at'=>$r['created_at']];
        }
    }
    echo json_encode(['success'=>true,'folders'=>$rows]); exit;
}
if ($action === 'get_files') {
    $folder_id = (int)($_GET['folder_id'] ?? $_POST['folder_id'] ?? 0);
    if ($folder_id<=0) { echo json_encode(['success'=>false,'message'=>'Invalid folder']); exit; }
    $conn->query("CREATE TABLE IF NOT EXISTS archive_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        file_path VARCHAR(1024) NOT NULL,
        version INT DEFAULT 1,
        parent_version_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $rows = [];
    if ($st = $conn->prepare("SELECT id, name, file_path, version, parent_version_id, created_at FROM archive_files WHERE folder_id = ? ORDER BY created_at DESC")) {
        $st->bind_param("i", $folder_id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'=>(int)$r['id'],
                'title'=>$r['name'],
                'file_path'=>$r['file_path'],
                'version'=> (int)($r['version'] ?? 1),
                'parent_version_id'=> isset($r['parent_version_id']) ? (int)$r['parent_version_id'] : null,
                'created_at'=>$r['created_at']
            ];
        }
        $st->close();
    }
    echo json_encode(['success'=>true,'files'=>$rows]); exit;
}
if ($action === 'get_versions') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }
    $conn->query("CREATE TABLE IF NOT EXISTS archive_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        file_path VARCHAR(1024) NOT NULL,
        version INT DEFAULT 1,
        parent_version_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $root = $id;
    if ($st = $conn->prepare("SELECT parent_version_id FROM archive_files WHERE id = ?")) {
        $st->bind_param("i", $id);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $root = $row['parent_version_id'] ? (int)$row['parent_version_id'] : $id;
        }
        $st->close();
    }
    $versions = [];
    if ($st2 = $conn->prepare("SELECT id, name, version, created_at FROM archive_files WHERE id = ? OR parent_version_id = ? ORDER BY version DESC, id DESC")) {
        $st2->bind_param("ii", $root, $root);
        $st2->execute();
        $res2 = $st2->get_result();
        while ($v = $res2->fetch_assoc()) {
            $versions[] = [
                'id'=>(int)$v['id'],
                'title'=>$v['name'],
                'version'=>(int)($v['version'] ?? 1),
                'created_at'=>$v['created_at'],
            ];
        }
        $st2->close();
    }
    echo json_encode(['success'=>true,'versions'=>$versions]); exit;
}

if ($action === 'restore_version') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }
    
    // Get the file info
    if ($st = $conn->prepare("SELECT id, name, file_path, folder_id FROM archive_files WHERE id = ?")) {
        $st->bind_param("i", $id);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows > 0) {
            $file = $res->fetch_assoc();
            
            // Check if file exists on disk
            if (file_exists($file['file_path'])) {
                // File is already available, just log the restoration
                $conn->query("CREATE TABLE IF NOT EXISTS restore_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    file_id INT NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    restored_by INT NOT NULL,
                    restored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $restored_by = (int)$_SESSION['user_id'];
                if ($log = $conn->prepare("INSERT INTO restore_logs (file_id, file_name, restored_by) VALUES (?, ?, ?)")) {
                    $log->bind_param("isi", $id, $file['name'], $restored_by);
                    $log->execute();
                    $log->close();
                }
                
                echo json_encode([
                    'success'=>true, 
                    'message'=>'File restored successfully',
                    'file'=>$file
                ]);
            } else {
                echo json_encode(['success'=>false,'message'=>'File not found on disk']);
            }
        } else {
            echo json_encode(['success'=>false,'message'=>'File record not found']);
        }
        $st->close();
    } else {
        echo json_encode(['success'=>false,'message'=>'Query error']);
    }
    exit;
}

if ($action === 'get_restore_candidates') {
    // Get files that have multiple versions
    $candidates = [];
    if ($st = $conn->prepare("SELECT id, name, file_path, folder_id, version, parent_version_id, created_at FROM archive_files ORDER BY created_at DESC")) {
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $candidates[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'file_path' => $row['file_path'],
                'folder_id' => (int)$row['folder_id'],
                'version' => (int)($row['version'] ?? 1),
                'parent_version_id' => $row['parent_version_id'],
                'created_at' => $row['created_at'],
                'exists' => file_exists($row['file_path'])
            ];
        }
        $st->close();
    }
    echo json_encode(['success'=>true,'files'=>$candidates]); exit;
}

if ($action === 'restore') {
    // Check if admin
    $user_id = (int)$_SESSION['user_id'];
    if ($st = $conn->prepare("SELECT role FROM users WHERE id = ?")) {
        $st->bind_param("i", $user_id);
        $st->execute();
        $res = $st->get_result();
        $user = $res->fetch_assoc();
        $st->close();
        
        if (!$user || strtolower($user['role'] ?? '') !== 'admin') {
            echo json_encode(['success'=>false,'message'=>'Admin access required']);
            exit;
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Authorization failed']);
        exit;
    }
    
    // Check file upload
    if (!isset($_FILES['backupFile']) || $_FILES['backupFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success'=>false,'message'=>'No file uploaded or upload error']);
        exit;
    }
    
    $uploadedFile = $_FILES['backupFile'];
    $fileName = basename($uploadedFile['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $tmpPath = $uploadedFile['tmp_name'];
    
    // Validate file extension
    if (!in_array($fileExt, ['sql', 'zip', 'gz'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid file type. Only .sql, .zip, and .gz are supported']);
        exit;
    }
    
    // Create temp directory if not exists
    $tempDir = __DIR__ . '/temp_restore';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    try {
        $sqlFile = null;
        
        if ($fileExt === 'sql') {
            $sqlFile = $tmpPath;
        } elseif ($fileExt === 'gz') {
            // Decompress gzip
            $sqlFile = $tempDir . '/backup_' . time() . '.sql';
            $fp = gzopen($tmpPath, 'rb');
            if (!$fp) throw new Exception('Could not open gzip file');
            
            $out = fopen($sqlFile, 'wb');
            if (!$out) throw new Exception('Could not create output file');
            
            while (!gzeof($fp)) {
                fwrite($out, gzread($fp, 4096));
            }
            fclose($fp);
            fclose($out);
        } elseif ($fileExt === 'zip') {
            // Extract zip
            $zip = zip_open($tmpPath);
            if (!is_resource($zip)) throw new Exception('Could not open zip file');
            
            while ($zipEntry = zip_read($zip)) {
                $name = zip_entry_name($zipEntry);
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'sql') {
                    $sqlFile = $tempDir . '/backup_' . time() . '.sql';
                    $fp = fopen($sqlFile, 'wb');
                    if ($fp) {
                        fwrite($fp, zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)));
                        fclose($fp);
                    }
                    break;
                }
            }
            zip_close($zip);
        }
        
        if (!$sqlFile || !file_exists($sqlFile)) {
            throw new Exception('Could not locate or process SQL file');
        }
        
        // Read SQL file
        $sqlContent = file_get_contents($sqlFile);
        if ($sqlContent === false) {
            throw new Exception('Could not read SQL file');
        }
        
        // Execute SQL - split by semicolon for multi-statement execution
        $statements = array_filter(
            array_map('trim', preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $sqlContent)),
            fn($s) => !empty($s) && !preg_match('/^\s*--/', $s)
        );
        
        $executed = 0;
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                if (!$conn->query($statement)) {
                    throw new Exception('SQL execution error: ' . $conn->error);
                }
                $executed++;
            }
        }
        
        // Log restoration
        $conn->query("CREATE TABLE IF NOT EXISTS restore_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_id INT,
            file_name VARCHAR(255) NOT NULL,
            restored_by INT NOT NULL,
            restored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        if ($log = $conn->prepare("INSERT INTO restore_logs (file_name, restored_by) VALUES (?, ?)")) {
            $log->bind_param("si", $fileName, $user_id);
            $log->execute();
            $log->close();
        }
        
        // Clean up temp files
        if ($sqlFile && file_exists($sqlFile) && strpos($sqlFile, $tempDir) === 0) {
            unlink($sqlFile);
        }
        
        echo json_encode([
            'success'=>true,
            'message'=>'Database restored successfully from ' . htmlspecialchars($fileName),
            'statements_executed'=>$executed
        ]);
        
    } catch (Exception $e) {
        // Clean up temp files on error
        if (isset($sqlFile) && $sqlFile && file_exists($sqlFile) && strpos($sqlFile, $tempDir) === 0) {
            unlink($sqlFile);
        }
        echo json_encode(['success'=>false,'message'=>'Restoration error: ' . $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['success'=>false,'message'=>'Invalid action']); exit;
?>

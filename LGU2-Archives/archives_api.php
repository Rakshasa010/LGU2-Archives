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
echo json_encode(['success'=>false,'message'=>'Invalid action']); exit;
?>

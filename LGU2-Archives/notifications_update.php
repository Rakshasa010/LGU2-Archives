<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}
require 'authdatabase.php';
$idsStr = isset($_POST['ids']) ? $_POST['ids'] : '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$all = isset($_POST['all']) ? intval($_POST['all']) : 0;

if (!in_array($status, ['read', 'unread'], true)) {
    echo json_encode(['success' => false]);
    exit();
}

if ($all && $status === 'read') {
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE status = 'unread'");
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    $stmt->close();
    exit();
}

if ($idsStr !== '') {
    $ids = array_filter(array_map('intval', explode(',', $idsStr)), function($n) { return $n > 0; });
    if (!empty($ids)) {
        $qStrs = rtrim(str_repeat('?,', count($ids)), ',');
        $types = 's' . str_repeat('i', count($ids));
        $params = array_merge([$status], $ids);
        
        $stmt = $conn->prepare("UPDATE notifications SET status = ? WHERE id IN ($qStrs)");
        // bind dynamically
        $bindArgs = [$types];
        foreach ($params as $k => $v) { $bindArgs[] = &$params[$k]; }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        
        $ok = $stmt->execute();
        echo json_encode(['success' => $ok]);
        $stmt->close();
        exit();
    }
} elseif ($id > 0) {
    $stmt = $conn->prepare('UPDATE notifications SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok ? true : false]);
    exit();
}
echo json_encode(['success' => false]);

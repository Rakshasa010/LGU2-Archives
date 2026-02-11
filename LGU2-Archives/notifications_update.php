<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}
require 'authdatabase.php';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
if ($id <= 0 || !in_array($status, ['read', 'unread'], true)) {
    echo json_encode(['success' => false]);
    exit();
}
$stmt = $conn->prepare('UPDATE notifications SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success' => $ok ? true : false]);

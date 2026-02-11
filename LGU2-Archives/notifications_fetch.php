<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit();
}
require 'authdatabase.php';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$about = isset($_GET['about']) ? $_GET['about'] : '';
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 10;
if ($page < 1) $page = 1;
if ($page_size < 1) $page_size = 10;
if ($page_size > 100) $page_size = 100;
$where = [];
$params = [];
$types = '';
if ($status === 'read' || $status === 'unread') {
    $where[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($about !== '') {
    $where[] = 'about = ?';
    $params[] = $about;
    $types .= 's';
}
if ($from !== '') {
    $where[] = 'date >= ?';
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $where[] = 'date <= ?';
    $params[] = $to;
    $types .= 's';
}
$sql_count = 'SELECT COUNT(*) AS cnt FROM notifications' . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
$stmt = $conn->prepare($sql_count);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
if ($res && ($row = $res->fetch_assoc())) $total = intval($row['cnt']);
$stmt->close();
$offset = ($page - 1) * $page_size;
$sql_items = 'SELECT id, time, date, content, about, status FROM notifications' . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY date DESC, id DESC LIMIT ?, ?';
$params_items = $params;
$types_items = $types . 'ii';
$params_items[] = $offset;
$params_items[] = $page_size;
$stmt = $conn->prepare($sql_items);
$stmt->bind_param($types_items, ...$params_items);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($res && ($row = $res->fetch_assoc())) $items[] = $row;
$stmt->close();
$about_options = [];
$r = $conn->query('SELECT DISTINCT about FROM notifications ORDER BY about ASC');
if ($r) {
    while ($a = $r->fetch_assoc()) $about_options[] = $a['about'];
}
echo json_encode([
    'success' => true,
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'page_size' => $page_size,
    'about_options' => $about_options
]);

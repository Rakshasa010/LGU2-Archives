<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }
require 'authdatabase.php';

$user_id = intval($_SESSION['user_id']);
$user_role = null;

// get current user's role for scoping
$rs = $conn->prepare("SELECT role FROM users WHERE id=?");
$rs->bind_param("i", $user_id);
$rs->execute();
$rr = $rs->get_result();
if ($rr && $row = $rr->fetch_assoc()) { $user_role = $row['role']; }
$rs->close();

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$about = isset($_GET['about']) ? trim($_GET['about']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 10;
if ($page < 1) $page = 1;
if ($page_size < 1) $page_size = 10;
if ($page_size > 100) $page_size = 100;
$offset = ($page - 1) * $page_size;

$where = [];
$params = [];
$types = '';

// scope: include global (user_id IS NULL) and user-specific (user_id = current) and role-specific
$where[] = "(user_id IS NULL OR user_id = ?)";
$params[] = $user_id;
$types .= 'i';
if ($user_role !== null && $user_role !== '') {
    $where[] = "(role IS NULL OR role = ?)";
    $params[] = $user_role;
    $types .= 's';
}

if ($status === 'read' || $status === 'unread') {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($about !== '') {
    $where[] = "about = ?";
    $params[] = $about;
    $types .= 's';
}
if ($from !== '') {
    $where[] = "date >= ?";
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $where[] = "date <= ?";
    $params[] = $to;
    $types .= 's';
}
$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// total count (used for unread badge)
$total = 0;
$count_sql = "SELECT COUNT(*) AS c FROM notifications $where_sql";
$cnt = $conn->prepare($count_sql);
if ($types !== '') { $cnt->bind_param($types, ...$params); }
$cnt->execute();
$cres = $cnt->get_result();
if ($cres && $crow = $cres->fetch_assoc()) { $total = intval($crow['c']); }
$cnt->close();

// about options distinct list
$about_options = [];
$about_sql = "SELECT DISTINCT about FROM notifications";
$ares = $conn->query($about_sql);
if ($ares) {
    while ($r = $ares->fetch_assoc()) { if (!empty($r['about'])) $about_options[] = $r['about']; }
}

// items with pagination
$items = [];
$list_sql = "SELECT id, time, date, content, about, status, link FROM notifications $where_sql ORDER BY date DESC, id DESC LIMIT ? OFFSET ?";
$params_list = $params;
$types_list = $types . 'ii';
$params_list[] = $page_size;
$params_list[] = $offset;
$stmt = $conn->prepare($list_sql);
$stmt->bind_param($types_list, ...$params_list);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => intval($row['id']),
            'time' => $row['time'],
            'date' => $row['date'],
            'content' => $row['content'],
            'about' => $row['about'],
            'status' => $row['status'],
            'link' => $row['link']
        ];
    }
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'items' => $items,
    'total' => $total,
    'about_options' => $about_options,
    'page' => $page,
    'page_size' => $page_size
]);
?>

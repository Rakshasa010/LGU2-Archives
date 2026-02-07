<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }
require 'authdatabase.php';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
if ($id <= 0 || ($status !== 'read' && $status !== 'unread')) { echo json_encode(['success'=>false,'message'=>'Invalid']); exit(); }
$stmt = $conn->prepare("UPDATE notifications SET status=? WHERE id=?");
$stmt->bind_param("si", $status, $id);
$ok = $stmt->execute();
$stmt->close();
$event = $status === 'read' ? 'notification_mark_read' : 'notification_mark_unread';
$user_id = intval($_SESSION['user_id']);
$rid = null;
$rec = $conn->prepare("SELECT record_id FROM notifications WHERE id=?");
$rec->bind_param("i", $id);
$rec->execute();
$res = $rec->get_result();
if ($res && $row = $res->fetch_assoc()) { $rid = $row['record_id'] ? intval($row['record_id']) : null; }
$rec->close();
$ae = $conn->prepare("INSERT INTO analytics_events (event_type, user_id, record_id) VALUES (?, ?, ?)");
$ae->bind_param("sii", $event, $user_id, $rid);
$ae->execute();
$ae->close();
$conn->close();
echo json_encode(['success'=>$ok]);

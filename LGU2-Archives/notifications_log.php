<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit(); }
require 'authdatabase.php';
$event = isset($_POST['event_type']) ? $_POST['event_type'] : '';
$ids = [];
if (isset($_POST['ids'])) {
  if (is_array($_POST['ids'])) $ids = $_POST['ids'];
  else {
    $decoded = json_decode($_POST['ids'], true);
    if (is_array($decoded)) $ids = $decoded;
  }
}
if ($event === '') { echo json_encode(['success'=>false]); exit(); }
$user_id = intval($_SESSION['user_id']);
foreach ($ids as $nid) {
  $rid = null;
  $link = null;
  $q = $conn->prepare("SELECT record_id, link FROM notifications WHERE id=?");
  $nid_int = intval($nid);
  $q->bind_param("i", $nid_int);
  $q->execute();
  $res = $q->get_result();
  if ($res && $row = $res->fetch_assoc()) {
    $rid = $row['record_id'] ? intval($row['record_id']) : null;
    $link = $row['link'] ? $row['link'] : null;
  }
  $q->close();
  $ae = $conn->prepare("INSERT INTO analytics_events (event_type, user_id, record_id, record_title) VALUES (?, ?, ?, ?)");
  $ae->bind_param("siis", $event, $user_id, $rid, $link);
  $ae->execute();
  $ae->close();
}
$conn->close();
echo json_encode(['success'=>true]);

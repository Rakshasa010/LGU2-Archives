<?php
session_start();
require 'authdatabase.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc();
$stmt->close();

if (!isset($me['role']) || strtolower($me['role']) !== 'admin') {
    die("Access denied. Admin privileges required.");
}

// Log Backup Event
$ntime = date('h:i A');
$ndate = date('Y-m-d');
$ncontent = "Full Database backup generated.";
$nabout = 'System Administration';
$nstatus = 'unread';
// Get admin name for notification
$userNameForNotif = null;
if ($userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?")) {
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    if ($userRes = $userStmt->get_result()) {
        if ($urow = $userRes->fetch_assoc()) {
            $userNameForNotif = trim($urow['full_name'] ?? '');
        }
    }
    $userStmt->close();
}
if ($ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status) VALUES (?,?,?,?,?,?)")) {
    $ins->bind_param('ssssss', $ntime, $ndate, $ncontent, $nabout, $userNameForNotif, $nstatus);
    $ins->execute(); $ins->close();
}

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "-- Archives Management System SQL Dump\n";
$sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    // Structure
    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();
    $sqlScript .= "\n-- Structure for table `$table`\n";
    $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
    $sqlScript .= $row[1] . ";\n\n";

    // Data
    $result = $conn->query("SELECT * FROM $table");
    $columnCount = $result->field_count;
    if ($result->num_rows > 0) {
        $sqlScript .= "-- Data for table `$table`\n";
        $sqlScript .= "INSERT INTO `$table` VALUES";
        $records = [];
        while ($row = $result->fetch_row()) {
            $values = [];
            for ($i = 0; $i < $columnCount; $i++) {
                if (!isset($row[$i])) {
                    $values[] = "NULL";
                } elseif (is_numeric($row[$i])) {
                    $values[] = $row[$i];
                } else {
                    $values[] = "'" . $conn->real_escape_string($row[$i]) . "'";
                }
            }
            $records[] = "(" . implode(',', $values) . ")";
        }
        $sqlScript .= "\n" . implode(",\n", $records) . ";\n";
    }
}

$filename = 'backup_archives_db_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename=' . $filename);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $sqlScript;
exit();
?>

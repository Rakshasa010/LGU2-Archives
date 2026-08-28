<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Log logout for monitored users before destroying session
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/authdatabase.php';
        require_once __DIR__ . '/monitoring_helper.php';
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        log_monitored_user_action($conn, $_SESSION['user_id'], 'Logout', 'User logged out');
        // Log logout in audit logs (all users)
        $uid = (int)$_SESSION['user_id'];
        $userName = null;
        if ($u = $conn->prepare("SELECT full_name FROM users WHERE id = ?")) {
            $u->bind_param("i", $uid);
            $u->execute();
            $r = $u->get_result();
            if ($r && $row = $r->fetch_assoc()) $userName = trim($row['full_name'] ?? '');
            $u->close();
        }
        $t = date('h:i A'); $d = date('Y-m-d'); $s = 'unread'; $c = 'User logged out'; $a = 'Logout';
        $ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($ins) { $ins->bind_param('ssssss', $t, $d, $c, $a, $userName, $s); $ins->execute(); $ins->close(); }
        $conn->close();
    }
    session_destroy();
    header("Location: login.php");
    exit();
} else {
    header("Location: archives-landing.php");
    exit();
}
?>

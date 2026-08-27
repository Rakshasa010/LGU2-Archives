<?php
/**
 * Monitoring Helper - Logs actions for monitored users to the audit logs.
 * 
 * When a user has is_monitored = 1 in the users table, all their actions
 * will be logged to the notifications table with a special "Monitored Activity"
 * category and the user's name.
 */

/**
 * Log an action for a monitored user.
 *
 * @param mysqli $conn Database connection
 * @param int $user_id The user performing the action
 * @param string $action The action type (e.g., "Folder Open", "File Preview", "File Download", "Login")
 * @param string $content The notification content/description
 * @return bool True if logged, false if user is not monitored
 */
function log_monitored_user_action($conn, $user_id, $action, $content) {
    if (!$user_id || $user_id <= 0) {
        return false;
    }
    
    // Check if user is monitored
    $stmt = $conn->prepare("SELECT is_monitored, full_name FROM users WHERE id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user || !$user['is_monitored']) {
        return false; // User is not monitored, skip logging
    }
    
    // Insert notification with the monitored activity structure
    $time = date('h:i A');
    $date = date('Y-m-d');
    $user_name = $user['full_name'];
    $about = 'Monitored User Activity';
    $status = 'unread';
    
    $ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if ($ins) {
        $ins->bind_param('ssssss', $time, $date, $content, $about, $user_name, $status);
        $result = $ins->execute();
        $ins->close();
        return $result;
    }
    
    return false;
}

/**
 * Get the current user ID from session.
 *
 * @return int User ID or 0 if not logged in
 */
function get_current_user_id() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}
?>

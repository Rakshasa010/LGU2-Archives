<?php
require_once 'authdatabase.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

if (isset($data['dark_mode'])) {
    $dark_mode = $data['dark_mode'] ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $dark_mode, $user_id);
        if ($stmt->execute()) {
            $_SESSION['dark_mode'] = $dark_mode; // Save to session for fast loading
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Prepare statement failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing dark_mode payload']);
}
?>

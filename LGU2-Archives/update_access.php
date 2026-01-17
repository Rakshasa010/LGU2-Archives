<?php
header('Content-Type: application/json');

// Include database connection
include 'authdatabase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_id'])) {
    $record_id = intval($_POST['record_id']);

    if ($record_id > 0) {
        // Update the last_accessed timestamp
        $sql = "UPDATE legislative_records SET last_accessed = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Access timestamp updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update access timestamp']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
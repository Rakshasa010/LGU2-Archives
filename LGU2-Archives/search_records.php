<?php
header('Content-Type: application/json');

// Include database connection
include 'authdatabase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchTerm = trim($_POST['search']);

    if (empty($searchTerm)) {
        echo json_encode(['results' => []]);
        exit;
    }

    // Prepare search query with LIKE for partial matches
    $searchTerm = "%" . $conn->real_escape_string($searchTerm) . "%";

    $sql = "SELECT id, title, type, month, year, author FROM legislative_records
            WHERE title LIKE ? OR type LIKE ? OR month LIKE ? OR year LIKE ? OR author LIKE ?
            ORDER BY year DESC, month DESC, title ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $records = [];

        while ($row = $result->fetch_assoc()) {
            $records[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'type' => $row['type'],
                'month' => $row['month'],
                'year' => $row['year'],
                'author' => $row['author']
            ];
        }

        echo json_encode(['results' => $records]);
    } else {
        echo json_encode(['error' => 'Database query failed']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}

$conn->close();
?>
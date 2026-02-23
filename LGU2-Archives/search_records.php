<?php
header('Content-Type: application/json');
include 'authdatabase.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $raw = trim($_POST['search']);
    if ($raw === '') {
        echo json_encode(['results' => [], 'related' => []]);
        $conn->close();
        exit;
    }
    $like = "%" . $conn->real_escape_string($raw) . "%";
    $letters = preg_replace('/\s+/', '', $raw);
    $wide = '%' . implode('%', preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY)) . '%';
    $results = [];
    $related = [];
    $sql = "SELECT id, title, type, month, year, author FROM legislative_records
            WHERE title LIKE ? OR type LIKE ? OR month LIKE ? OR year LIKE ? OR author LIKE ? OR title LIKE ?
            ORDER BY year DESC, month DESC, title ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $wide);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'type' => $row['type'],
                'month' => $row['month'],
                'year' => $row['year'],
                'author' => $row['author'],
                'source' => 'legislative'
            ];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, name, created_at FROM archive_folders WHERE name LIKE ? OR name LIKE ? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("ss", $like, $wide);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['name'],
                'type' => 'Archive Folder',
                'month' => date('M', strtotime($row['created_at'])),
                'year' => date('Y', strtotime($row['created_at'])),
                'author' => '',
                'source' => 'archive',
                'kind' => 'folder',
                'folder_id' => (int)$row['id'],
                'folder_name' => $row['name']
            ];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT f.id AS folder_id, f.name AS folder_name, af.id, af.name, af.created_at 
                            FROM archive_files af 
                            INNER JOIN archive_folders f ON af.folder_id = f.id 
                            WHERE af.created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR) 
                              AND (af.name LIKE ? OR af.name LIKE ? OR f.name LIKE ?) 
                            ORDER BY af.created_at DESC LIMIT 50");
    $stmt->bind_param("sss", $like, $wide, $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['name'],
                'type' => 'Archive File',
                'month' => date('M', strtotime($row['created_at'])),
                'year' => date('Y', strtotime($row['created_at'])),
                'author' => '',
                'source' => 'archive',
                'kind' => 'file',
                'folder_id' => (int)$row['folder_id'],
                'folder_name' => $row['folder_name']
            ];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT DISTINCT type FROM legislative_records WHERE type IS NOT NULL AND type <> '' AND type LIKE ? LIMIT 6");
    $stmt->bind_param("s", $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $related[] = ['label' => $row['type'], 'category' => 'Type', 'query' => $row['type']];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT DISTINCT author FROM legislative_records WHERE author IS NOT NULL AND author <> '' AND author LIKE ? LIMIT 6");
    $stmt->bind_param("s", $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $related[] = ['label' => $row['author'], 'category' => 'Author', 'query' => $row['author']];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT DISTINCT name FROM archive_folders WHERE name LIKE ? LIMIT 6");
    $stmt->bind_param("s", $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $related[] = ['label' => $row['name'], 'category' => 'Folder', 'query' => $row['name']];
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT UPPER(SUBSTRING_INDEX(af.name, '.', -1)) AS ext FROM archive_files af WHERE af.created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR) AND af.name LIKE ? GROUP BY ext ORDER BY COUNT(*) DESC LIMIT 6");
    $stmt->bind_param("s", $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ext = $row['ext'];
            if ($ext) {
                $related[] = ['label' => $ext, 'category' => 'File Type', 'query' => $ext];
            }
        }
    }
    $stmt->close();
    echo json_encode(['results' => $results, 'related' => $related]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
$conn->close();
?>

<?php
// Test database connection and data
$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "MySQL connection successful\n";

$conn->select_db('lgu2_archives');

$result = $conn->query('SELECT COUNT(*) as count FROM legislative_records');
if ($result) {
    $row = $result->fetch_assoc();
    echo 'Records in database: ' . $row['count'] . "\n";

    // Show a sample record
    $sample = $conn->query('SELECT * FROM legislative_records LIMIT 1');
    if ($sample && $sample->num_rows > 0) {
        $record = $sample->fetch_assoc();
        echo "Sample record:\n";
        echo "- ID: " . $record['id'] . "\n";
        echo "- Title: " . $record['title'] . "\n";
        echo "- Type: " . $record['type'] . "\n";
        echo "- Month: " . $record['month'] . "\n";
        echo "- Year: " . $record['year'] . "\n";
        echo "- Author: " . $record['author'] . "\n";
    }
} else {
    echo 'No table found or query failed: ' . $conn->error . "\n";
}

$conn->close();
?>
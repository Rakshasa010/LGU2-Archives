<?php
// Test search functionality
$data = http_build_query(['search' => 'ordinance']);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $data
    ]
]);
$result = file_get_contents('http://localhost/LGU2-Archives/search_records.php', false, $context);

echo "Search Results for 'ordinance':\n";
echo $result . "\n\n";

// Test with empty search
$data2 = http_build_query(['search' => '']);
$context2 = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $data2
    ]
]);
$result2 = file_get_contents('http://localhost/LGU2-Archives/search_records.php', false, $context2);

echo "Search Results for empty string:\n";
echo $result2 . "\n";
?>
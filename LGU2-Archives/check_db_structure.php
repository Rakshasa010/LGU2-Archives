<?php
error_reporting(E_ALL);
require 'authdatabase.php';
echo "Connected to " . $dbname . "\n";

echo "--- Notifications Table ---\n";
$res = $conn->query("DESCRIBE notifications");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['Field'] . " (" . $r['Type'] . ")\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}

echo "--- Archive Files Table ---\n";
$res = $conn->query("DESCRIBE archive_files");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['Field'] . " (" . $r['Type'] . ")\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}

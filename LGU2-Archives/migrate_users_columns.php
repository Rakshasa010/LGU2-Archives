<?php
require __DIR__ . '/authdatabase.php';
$columns = [
  'nickname' => 'VARCHAR(100) NULL',
  'birthplace' => 'VARCHAR(255) NULL',
  'birthdate' => 'DATE NULL',
  'address' => 'VARCHAR(255) NULL'
];
$existing = [];
$res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $existing[] = $r['COLUMN_NAME'];
  }
}
$added = [];
$skipped = [];
foreach ($columns as $name => $def) {
  if (in_array($name, $existing)) {
    $skipped[] = $name;
    continue;
  }
  $sql = "ALTER TABLE users ADD COLUMN $name $def";
  if ($conn->query($sql)) {
    $added[] = $name;
  } else {
    echo "ERROR adding $name: ".$conn->error.PHP_EOL;
  }
}
echo "Added: ".(empty($added) ? 'none' : implode(', ', $added)).PHP_EOL;
echo "Already existed: ".(empty($skipped) ? 'none' : implode(', ', $skipped)).PHP_EOL;

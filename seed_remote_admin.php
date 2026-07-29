<?php
function load_env($path) {
    $vars = [];
    if (!file_exists($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value, " \t\n\r\0\"'");
    }
    return $vars;
}
$env = load_env(__DIR__ . '/.env');

$host = $env['MYSQL_HOST'] ?? 'localhost';
$user = $env['MYSQL_USER'] ?? 'root';
$pass = $env['MYSQL_PASSWORD'] ?? '';
$db   = $env['MYSQL_DATABASE'] ?? 'las_lgu2_archives';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die('DB connection failed: ' . $conn->connect_error);

// Ensure basic users table exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    dark_mode TINYINT(1) NOT NULL DEFAULT 0
)");

$accounts = [
    ['admin', 'admin123', 'admin@lgu.gov.ph', 'Admin User', 'admin'],
    ['user', 'user123', 'user@lgu.gov.ph', 'Regular User', 'user'],
];

foreach ($accounts as $a) {
    $hash = password_hash($a[1], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, dark_mode) VALUES (?, ?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE password=VALUES(password), full_name=VALUES(full_name), role=VALUES(role)");
    $stmt->bind_param('sssss', $a[0], $hash, $a[2], $a[3], $a[4]);
    $stmt->execute();
    $stmt->close();
}

echo "Seeded/updated admin and user accounts.\n";
$conn->close();

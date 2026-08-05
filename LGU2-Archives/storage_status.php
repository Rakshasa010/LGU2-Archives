<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit();
}

function dir_size($path) {
    $size = 0;
    if (!is_dir($path)) return 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
    } catch (Exception $e) {
        // Ignore unreadable directories/files
    }
    return $size;
}

// Configure total storage capacity (in bytes)
// Default: 50 GB
$total_bytes = 50 * 1024 * 1024 * 1024;

// Calculate used bytes from uploads directory
$uploads_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$used_bytes = dir_size($uploads_path);
if ($used_bytes < 0) $used_bytes = 0;
if ($used_bytes > $total_bytes) $used_bytes = $total_bytes;

$percent = $total_bytes > 0 ? round(($used_bytes / $total_bytes) * 100) : 0;

function format_bytes($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $e = floor(log($bytes, 1024));
    $e = max(0, min($e, count($units) - 1));
    return round($bytes / pow(1024, $e), $e >= 3 ? 1 : 0) . ' ' . $units[$e];
}

echo json_encode([
    'success' => true,
    'used_bytes' => $used_bytes,
    'total_bytes' => $total_bytes,
    'percent' => $percent,
    'used_human' => format_bytes($used_bytes),
    'total_human' => format_bytes($total_bytes),
]);
?>

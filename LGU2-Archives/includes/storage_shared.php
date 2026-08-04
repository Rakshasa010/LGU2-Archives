<?php
// Shared storage helpers for consistent metrics across pages.

if (!function_exists('storage_format_bytes')) {
    function storage_format_bytes($bytes) {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}

if (!function_exists('storage_dir_metrics')) {
    function storage_dir_metrics($path, $capacityBytes = null) {
        $bytes = 0;
        $fileCount = 0;

        if (is_dir($path)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                    $fileCount++;
                }
            }
        }

        if ($capacityBytes === null) {
            $capacityBytes = 50 * 1024 * 1024 * 1024; // 50 GB default
        }

        $pct = ($capacityBytes > 0) ? min(100, round(($bytes / $capacityBytes) * 100, 2)) : 0;

        return [
            'bytes' => $bytes,
            'fileCount' => $fileCount,
            'capacityBytes' => $capacityBytes,
            'pct' => $pct,
            'usedText' => storage_format_bytes($bytes),
            'totalText' => storage_format_bytes($capacityBytes)
        ];
    }
}

// DB-tracked storage metrics: counts only files that have records in
// legislative_records and archive_files (ALL rows, including every version),
// resolving relative file_path values against the project web root. Skips
// profile pictures and orphan files that exist on disk but are not tracked.
if (!function_exists('storage_db_metrics')) {
    function storage_db_metrics($conn, $capacityBytes = null) {
        if ($capacityBytes === null) {
            $capacityBytes = 50 * 1024 * 1024 * 1024; // 50 GB default
        }

        $base = dirname(__DIR__); // project web root
        $totalBytes = 0;
        $fileCount = 0;
        $storageTop = [];

        $tables = [
            ['table' => 'legislative_records', 'src' => 'Legislative'],
            ['table' => 'archive_files', 'src' => 'Archive']
        ];

        foreach ($tables as $tbl) {
            $res = $conn->query("SELECT file_path FROM {$tbl['table']} WHERE file_path IS NOT NULL AND file_path <> ''");
            if (!$res) continue;
            while ($row = $res->fetch_assoc()) {
                $p = trim($row['file_path']);
                if ($p === '') continue;
                $full = $p;
                if (!preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $p)) {
                    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
                }
                if (@file_exists($full)) {
                    $size = @filesize($full);
                    $totalBytes += $size;
                    $fileCount++;
                    $storageTop[] = ['name' => basename($full), 'path' => $full, 'src' => $tbl['src'], 'size' => $size];
                }
            }
        }

        usort($storageTop, function($a, $b) { return $b['size'] - $a['size']; });
        $storageTop = array_slice($storageTop, 0, 15);

        $pct = ($capacityBytes > 0) ? min(100, round(($totalBytes / $capacityBytes) * 100, 2)) : 0;

        return [
            'pct' => $pct,
            'totalBytes' => $totalBytes,
            'capacityBytes' => $capacityBytes,
            'fileCount' => $fileCount,
            'storageTop' => $storageTop,
            'usedText' => storage_format_bytes($totalBytes),
            'totalText' => storage_format_bytes($capacityBytes)
        ];
    }
}

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

        $pct = ($capacityBytes > 0) ? min(100, round(($bytes / $capacityBytes) * 100, 1)) : 0;

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

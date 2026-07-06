<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'authdatabase.php';
require_once __DIR__ . '/includes/storage_shared.php';

$user_id = $_SESSION['user_id'];
$user_data = null;
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) $user_data = $res->fetch_assoc();
$stmt->close();

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;

// Fetch stats from DB
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM legislative_records");
$stats['total_records'] = ($result && $row = $result->fetch_assoc()) ? (int)$row['total'] : 0;

$result = $conn->query("SELECT type, COUNT(*) as cnt FROM legislative_records GROUP BY type");
$stats['by_type'] = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $stats['by_type'][$r['type']] = (int)$r['cnt'];
}
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare("SELECT role FROM users WHERE id = ?");
    if ($st) {
        $st->bind_param("i", $uid);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && $rs->num_rows === 1) {
            $r = $rs->fetch_assoc();
            $is_admin = isset($r['role']) && strtolower($r['role']) === 'admin';
        }
        $st->close();
    }
}
// Filters
$q_start = isset($_GET['start']) ? $_GET['start'] : null;
$q_end = isset($_GET['end']) ? $_GET['end'] : null;
$q_type = isset($_GET['type']) ? $_GET['type'] : null;
$q_format = isset($_GET['format']) ? $_GET['format'] : null;
$q_event = isset($_GET['event']) ? $_GET['event'] : null;
$f_start = null;
$f_end = null;
if ($q_start) {
    $d = DateTime::createFromFormat('Y-m-d', $q_start);
    if ($d) $f_start = $d->format('Y-m-d');
}
if ($q_end) {
    $d = DateTime::createFromFormat('Y-m-d', $q_end);
    if ($d) $f_end = $d->format('Y-m-d');
}
$safe_type = $q_type ? $conn->real_escape_string($q_type) : null;
$safe_format = $q_format ? $conn->real_escape_string(strtolower($q_format)) : null;
$safe_event = $q_event ? $conn->real_escape_string(strtolower($q_event)) : null;
$dl_where = "event_type='download'";
if ($f_start) $dl_where .= " AND created_at >= '".$conn->real_escape_string($f_start)." 00:00:00'";
if ($f_end) $dl_where .= " AND created_at <= '".$conn->real_escape_string($f_end)." 23:59:59'";
if ($safe_type) $dl_where .= " AND record_type = '".$safe_type."'";
if ($safe_format) $dl_where .= " AND download_format = '".$safe_format."'";
$act_where = "1=1";
if ($safe_event) $act_where .= " AND event_type = '".$safe_event."'";
if ($f_start) $act_where .= " AND created_at >= '".$conn->real_escape_string($f_start)." 00:00:00'";
if ($f_end) $act_where .= " AND created_at <= '".$conn->real_escape_string($f_end)." 23:59:59'";
if ($safe_type) $act_where .= " AND record_type = '".$safe_type."'";
if ($safe_format) $act_where .= " AND download_format = '".$safe_format."'";

// Downloads + Activity (prefer analytics_events if available; fallback to legacy last_accessed)
$stats['downloads'] = 0;
$stats['downloads_by_type'] = [];
$stats['downloads_by_format'] = [];
$stats['recent_activity'] = [];
$stats['recent_downloads'] = []; // legacy fallback table
$stats['has_user_attr'] = false;

$ae_count = $conn->query("SELECT COUNT(*) AS cnt FROM analytics_events WHERE $dl_where");
if ($ae_count && ($row = $ae_count->fetch_assoc())) {
    $stats['downloads'] = (int)$row['cnt'];

    $ae_by_type = $conn->query("SELECT COALESCE(record_type,'Unknown') AS k, COUNT(*) AS cnt
                                FROM analytics_events
                                WHERE $dl_where
                                GROUP BY COALESCE(record_type,'Unknown')");
    if ($ae_by_type) while ($r = $ae_by_type->fetch_assoc()) $stats['downloads_by_type'][$r['k']] = (int)$r['cnt'];

    $ae_by_format = $conn->query("SELECT COALESCE(download_format,'unknown') AS k, COUNT(*) AS cnt
                                  FROM analytics_events
                                  WHERE $dl_where
                                  GROUP BY COALESCE(download_format,'unknown')");
    if ($ae_by_format) while ($r = $ae_by_format->fetch_assoc()) $stats['downloads_by_format'][strtoupper($r['k'])] = (int)$r['cnt'];

    $col = $conn->query("SHOW COLUMNS FROM analytics_events LIKE 'user_id'");
    if ($col && $col->num_rows > 0) {
        $stats['has_user_attr'] = true;
        $ae_recent = $conn->query("SELECT ae.id, ae.event_type, ae.record_id, ae.record_title, ae.record_type, ae.download_format, ae.bytes, ae.created_at, ae.user_id, u.full_name AS user_name
                                   FROM analytics_events ae
                                   LEFT JOIN users u ON u.id = ae.user_id
                                   WHERE $act_where
                                   ORDER BY ae.created_at DESC
                                   LIMIT 15");
        if ($ae_recent) while ($r = $ae_recent->fetch_assoc()) $stats['recent_activity'][] = $r;
    } else {
        $ae_recent = $conn->query("SELECT event_type, record_title, record_type, download_format, bytes, created_at
                                   FROM analytics_events
                                   WHERE $act_where
                                   ORDER BY created_at DESC
                                   LIMIT 15");
        if ($ae_recent) while ($r = $ae_recent->fetch_assoc()) $stats['recent_activity'][] = $r;
    }
} else {
    // Legacy fallback: counts "records that have been accessed at least once"
    $result = $conn->query("SELECT COUNT(*) as downloads FROM legislative_records WHERE last_accessed IS NOT NULL");
    $stats['downloads'] = ($result && $row = $result->fetch_assoc()) ? (int)$row['downloads'] : 0;

    $result = $conn->query("SELECT type, COUNT(*) as cnt FROM legislative_records WHERE last_accessed IS NOT NULL GROUP BY type");
    if ($result) while ($r = $result->fetch_assoc()) $stats['downloads_by_type'][$r['type']] = (int)$r['cnt'];

    $result = $conn->query("SELECT id, title, type, author, last_accessed FROM legislative_records WHERE last_accessed IS NOT NULL ORDER BY last_accessed DESC LIMIT 10");
    if ($result) while ($r = $result->fetch_assoc()) $stats['recent_downloads'][] = $r;
}

$result = $conn->query("SELECT id, title, type, author, created_at FROM legislative_records ORDER BY created_at DESC LIMIT 10");
$stats['recent_added'] = [];
if ($result) while ($r = $result->fetch_assoc()) $stats['recent_added'][] = $r;

// Fetch folder types with file counts (for new MVP display)
$stats['folder_types'] = [];
$stats['folder_types_detailed'] = [];
$folder_query = $conn->query("
    SELECT 
        af.id,
        af.name,
        COUNT(afi.id) as file_count,
        af.created_at,
        u.full_name as created_by_name
    FROM archive_folders af
    LEFT JOIN archive_files afi ON af.id = afi.folder_id
    LEFT JOIN users u ON af.created_by = u.id
    WHERE af.parent_id IS NULL
    GROUP BY af.id, af.name, af.created_at, u.full_name
    ORDER BY af.created_at DESC
");
if ($folder_query) {
    while ($row = $folder_query->fetch_assoc()) {
        $stats['folder_types_detailed'][] = $row;
        if (!isset($stats['folder_types'][$row['name']])) {
            $stats['folder_types'][$row['name']] = 0;
        }
        $stats['folder_types'][$row['name']] += (int)$row['file_count'];
    }
}

// Calculate uploads directory size (will show profile pictures and any other uploads)
$uploads_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$uploads_metrics = storage_dir_metrics($uploads_path);
$uploads_bytes = $uploads_metrics['bytes'];

$exportQuery = $_GET;
$exportQuery['export'] = 'csv';
$exportUrl = 'report_analytics.php?' . http_build_query($exportQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action'])) {
    $action = $_POST['export_action'];
    $password = $_POST['confirm_password'] ?? '';
    $uid = (int)$_SESSION['user_id'];
    $ok = false;
    $stmt = $conn->prepare("SELECT password, username FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if (password_verify($password, $row['password'])) $ok = true;
        }
        $stmt->close();
    }
    if ($ok) {
        $conn->query("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            time VARCHAR(20) NOT NULL,
            date DATE NOT NULL,
            content VARCHAR(255) NOT NULL,
            about VARCHAR(100) NOT NULL,
            status ENUM('unread','read') NOT NULL DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $ntime = date('h:i A');
        $ndate = date('Y-m-d');
        $ncontent = 'Export requested: '.strtoupper($action).' by user #'.$uid;
        $nabout = 'Export';
        $nstatus = 'unread';
        $ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?,?,?,?,?)");
        if ($ins) { $ins->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus); $ins->execute(); $ins->close(); }
        if ($action === 'txt') {
            $filename = 'report_analytics_' . date('Ymd_His') . '.txt';
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo "Reports & Analytics\n";
            echo "Generated At: " . date('Y-m-d H:i:s') . "\n\n";
            echo "Summary\n";
            echo "Total Records: " . (string)($stats['total_records'] ?? 0) . "\n";
            echo "Total Downloads: " . (string)($stats['downloads'] ?? 0) . "\n";
            echo "Uploads Folder Size: " . storage_format_bytes($uploads_bytes) . "\n\n";
            echo "Downloads by Type\n";
            foreach (($stats['downloads_by_type'] ?? []) as $k => $v) echo $k . ": " . (string)$v . "\n";
            echo "\nDownloads by Format\n";
            foreach (($stats['downloads_by_format'] ?? []) as $k => $v) echo $k . ": " . (string)$v . "\n";
            echo "\nRecent Activity\n";
            if (!empty($stats['recent_activity'])) {
                foreach ($stats['recent_activity'] as $row) {
                    echo ($row['created_at'] ?? '') . " | " . ($row['event_type'] ?? '') . " | " . ($row['record_title'] ?? '') . " | " . ($row['record_type'] ?? '') . " | " . strtoupper($row['download_format'] ?? '') . "\n";
                }
            } elseif (!empty($stats['recent_downloads'])) {
                foreach ($stats['recent_downloads'] as $row) {
                    echo ($row['last_accessed'] ?? '') . " | " . ($row['title'] ?? '') . " | " . ($row['type'] ?? '') . " | " . ($row['author'] ?? '') . "\n";
                }
            } else {
                echo "No recent activity available\n";
            }
            exit();
        } elseif ($action === 'pdf') {
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>Reports & Analytics</title><style>body{font-family:Arial,sans-serif;color:#111}h1{font-size:20px;margin:0 0 8px}h2{font-size:16px;margin:16px 0 8px}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #ddd;padding:6px}th{background:#f3f4f6;text-align:left}</style></head><body>';
            $html .= '<h1>Reports & Analytics</h1><div>Generated At: '.date('Y-m-d H:i:s').'</div>';
            $html .= '<h2>Summary</h2><table><tr><th>Metric</th><th>Value</th></tr>';
            $html .= '<tr><td>Total Records</td><td>'.(int)($stats['total_records'] ?? 0).'</td></tr>';
            $html .= '<tr><td>Total Downloads</td><td>'.(int)($stats['downloads'] ?? 0).'</td></tr>';
            $html .= '<tr><td>Uploads Folder Size</td><td>'.htmlspecialchars(storage_format_bytes($uploads_bytes)).'</td></tr></table>';
            $html .= '<h2>Downloads by Type</h2><table><tr><th>Type</th><th>Count</th></tr>';
            foreach (($stats['downloads_by_type'] ?? []) as $k=>$v) $html .= '<tr><td>'.htmlspecialchars($k).'</td><td>'.(int)$v.'</td></tr>';
            $html .= '</table><h2>Downloads by Format</h2><table><tr><th>Format</th><th>Count</th></tr>';
            foreach (($stats['downloads_by_format'] ?? []) as $k=>$v) $html .= '<tr><td>'.htmlspecialchars($k).'</td><td>'.(int)$v.'</td></tr>';
            $html .= '</table><h2>Recent Activity</h2>';
            if (!empty($stats['recent_activity'])) {
                $html .= '<table><tr><th>When</th><th>Event</th><th>Title</th><th>Type</th><th>Format</th></tr>';
                foreach ($stats['recent_activity'] as $r) {
                    $html .= '<tr><td>'.htmlspecialchars($r['created_at']).'</td><td>'.htmlspecialchars($r['event_type']).'</td><td>'.htmlspecialchars($r['record_title'] ?? '').'</td><td>'.htmlspecialchars($r['record_type'] ?? '').'</td><td>'.htmlspecialchars(strtoupper($r['download_format'] ?? '')).'</td></tr>';
                }
                $html .= '</table>';
            } elseif (!empty($stats['recent_downloads'])) {
                $html .= '<table><tr><th>When</th><th>Title</th><th>Type</th><th>Author</th></tr>';
                foreach ($stats['recent_downloads'] as $r) {
                    $html .= '<tr><td>'.htmlspecialchars($r['last_accessed'] ?? '').'</td><td>'.htmlspecialchars($r['title'] ?? '').'</td><td>'.htmlspecialchars($r['type'] ?? '').'</td><td>'.htmlspecialchars($r['author'] ?? '').'</td></tr>';
                }
                $html .= '</table>';
            } else {
            $html .= <<<HTML

<div>No recent activity available</div>
<script>window.print && setTimeout(function(){window.print();},250)</script>
<script>
    (function() {
        function fetchAndUpdateStorage() {
            fetch("archives-landing.php?action=get_storage_data")
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const pct = data.percentage;
                        const usedText = data.usedText;
                        const totalText = data.totalText;
                        const fileCount = data.fileCount;

                        ["mobile", "desktop"].forEach(prefix => {
                            const bar = document.getElementById(prefix + '-storage-bar');
                            const pctEl = document.getElementById(prefix + '-storage-pct');
                            const textEl = document.getElementById(prefix + '-storage-text');
                            const filesEl = document.getElementById(prefix + '-storage-files');
                            
                            if (bar) bar.style.width = pct + '%';
                            if (pctEl) pctEl.textContent = pct + '%';
                            if (textEl) textEl.textContent = usedText + ' of ' + totalText;
                            if (filesEl) filesEl.textContent = fileCount + ' files tracked';
                        });
                    }
                }).catch(err => console.warn('Storage fetch error:', err));
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fetchAndUpdateStorage);
        } else {
            fetchAndUpdateStorage();
        }
        setInterval(fetchAndUpdateStorage, 60000);
    })();
</script>
</body></html>
HTML;
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit();
        }
        }
    } else {
        $export_error = 'Invalid password for export.';
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'report_analytics_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report', 'Generated At']);
    fputcsv($out, ['Reports & Analytics', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Total Records', (string)($stats['total_records'] ?? 0)]);
    fputcsv($out, ['Total Downloads', (string)($stats['downloads'] ?? 0)]);
    fputcsv($out, ['Uploads Folder Size', storage_format_bytes($uploads_bytes)]);
    fputcsv($out, []);
    fputcsv($out, ['Downloads by Type']);
    fputcsv($out, ['Type', 'Count']);
    if (!empty($stats['downloads_by_type'])) {
        foreach ($stats['downloads_by_type'] as $type => $count) {
            fputcsv($out, [$type, (string)$count]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['Downloads by Format']);
    fputcsv($out, ['Format', 'Count']);
    if (!empty($stats['downloads_by_format'])) {
        foreach ($stats['downloads_by_format'] as $format => $count) {
            fputcsv($out, [$format, (string)$count]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['Recent Activity']);
    if (!empty($stats['recent_activity'])) {
        fputcsv($out, ['Event', 'Title', 'Type', 'Format', 'Bytes', 'Date']);
        foreach ($stats['recent_activity'] as $row) {
            fputcsv($out, [
                $row['event_type'] ?? '',
                $row['record_title'] ?? '',
                $row['record_type'] ?? '',
                $row['download_format'] ?? '',
                isset($row['bytes']) ? (string)$row['bytes'] : '',
                $row['created_at'] ?? '',
            ]);
        }
    } elseif (!empty($stats['recent_downloads'])) {
        fputcsv($out, ['Title', 'Type', 'Author', 'Date']);
        foreach ($stats['recent_downloads'] as $row) {
            fputcsv($out, [
                $row['title'] ?? '',
                $row['type'] ?? '',
                $row['author'] ?? '',
                $row['last_accessed'] ?? '',
            ]);
        }
    } else {
        fputcsv($out, ['No recent activity available']);
    }
    fclose($out);
    exit();
}

// Time series data (last 30 days)
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = 0;
}
$series_downloads = $days;
$series_records = $days;
$q_dl_series = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM analytics_events WHERE event_type='download' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY d");
if ($q_dl_series) {
    while ($r = $q_dl_series->fetch_assoc()) {
        $key = $r['d'];
        if (isset($series_downloads[$key])) $series_downloads[$key] = (int)$r['cnt'];
    }
}
$q_rec_series = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM legislative_records WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY d");
if ($q_rec_series) {
    while ($r = $q_rec_series->fetch_assoc()) {
        $key = $r['d'];
        if (isset($series_records[$key])) $series_records[$key] = (int)$r['cnt'];
    }
}
$series_labels = array_keys($days);
$series_downloads_values = array_values($series_downloads);
$series_records_values = array_values($series_records);

// Funnel and active users
$views_by_type = [];
$funnel_types = [];
$has_ae = $conn->query("SHOW TABLES LIKE 'analytics_events'");
if ($has_ae && $has_ae->num_rows > 0) {
    $vw_where = "event_type='view'";
    if ($f_start) $vw_where .= " AND created_at >= '".$conn->real_escape_string($f_start)." 00:00:00'";
    if ($f_end) $vw_where .= " AND created_at <= '".$conn->real_escape_string($f_end)." 23:59:59'";
    if ($safe_type) $vw_where .= " AND record_type = '".$safe_type."'";
    $qe = $conn->query("SELECT COALESCE(record_type,'Unknown') AS k, COUNT(*) AS c FROM analytics_events WHERE $vw_where GROUP BY COALESCE(record_type,'Unknown')");
    if ($qe) { while ($r = $qe->fetch_assoc()) $views_by_type[$r['k']] = (int)$r['c']; }
    $funnel_types = array_unique(array_merge(array_keys($views_by_type), array_keys($stats['downloads_by_type'] ?? [])));
    $dau = 0; $wau = 0; $mau = 0;
    $col = $conn->query("SHOW COLUMNS FROM analytics_events LIKE 'user_id'");
    if ($col && $col->num_rows > 0) {
        $q1 = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM analytics_events WHERE created_at >= CURDATE()");
        if ($q1 && ($r=$q1->fetch_assoc())) $dau = (int)$r['c'];
        $q2 = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM analytics_events WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        if ($q2 && ($r=$q2->fetch_assoc())) $wau = (int)$r['c'];
        $q3 = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM analytics_events WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        if ($q3 && ($r=$q3->fetch_assoc())) $mau = (int)$r['c'];
    }
    $top_downloaders = [];
    $qd = $conn->query("SELECT ae.user_id, 
                        COALESCE(NULLIF(u.full_name,''), 
                                 NULLIF(u.username,''), 
                                 NULLIF(u.email,''), 
                                 CONCAT('User #', ae.user_id)) AS name, 
                        COUNT(*) AS c
                        FROM analytics_events ae
                        LEFT JOIN users u ON u.id = ae.user_id
                        WHERE ae.event_type='download' AND ae.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY ae.user_id, name
                        ORDER BY c DESC
                        LIMIT 10");
    if ($qd) { while ($r = $qd->fetch_assoc()) $top_downloaders[] = $r; }
} else {
    $funnel_types = array_keys($stats['downloads_by_type'] ?? []);
    $dau = $wau = $mau = 0;
    $top_downloaders = [];
}
$funnel_types = array_values($funnel_types);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reports & Analytics - Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/archives-landing-head.js"></script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="stylesheet" href="assets/css/skeletons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/ui-enhancements.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .card { border-radius: 0.75rem; }
        .skeleton { position: relative; overflow: hidden; }
        .skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: translateX(-100%);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
        .skeleton-block { height: 160px; border-radius: 0.5rem; background-color: #f3f4f6; }
        .skeleton-line { height: 12px; border-radius: 0.5rem; background-color: #f3f4f6; }
        .dark .skeleton-block, body.dark .skeleton-block,
        .dark .skeleton-line, body.dark .skeleton-line { background-color: rgba(100,116,139,0.35); }
    </style>
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
</head>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <!-- Left Side: Toggle buttons and Logo -->
                        <div class="flex items-center">
                            <!-- Sidebar Toggle Button (Desktop) -->

                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Report & Analytics</h2>
                            </div>
                        </div>
                        
                        <!-- Right Side Actions -->
                        <div class="flex items-center space-x-1 md:space-x-4">
                            <!-- Dark Mode Toggle -->
                            <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                                <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </button>
                        
                            <!-- Notification Dropdown (placed beside theme toggle) -->
                            <div class="relative">
                                <button id="notification-btn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors relative" title="Notifications">
                                    <i class="bi bi-bell-fill text-xl text-gray-700 dark:text-gray-300"></i>
                                    <span id="notif-count" class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-red-600 bg-red-100 rounded-full">3</span>
                                </button>

                                <div id="notification-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                    <div class="p-4">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Notifications</div>
                                        <div id="notif-list" class="space-y-2">
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Loading notifications...</div>
                                        </div>
                                    </div>

                                    <div class="px-4 py-2 border-t border-gray-200 dark:border-slate-700">
                                        <a href="audit-logs.php" class="block text-center text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            View All Notifications
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- User Profile Dropdown (moved right of notification) -->
                             <div class="relative">
                                <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                <?php if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php elseif ($profile_picture && file_exists($profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php else: ?>
                                    <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden sm:block text-left">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($display_name); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $is_admin ? 'Administrator' : 'User'; ?></p>
                                </div>
                                <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                            </button>
                            
                                <!-- Profile Dropdown -->
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <i class="bi bi-gear mr-2"></i>Account Settings
                                        </a>
                                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer">
                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900 p-4 sm:p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    <div class="card bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl transition-all duration-300 p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                            <div>
                                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">Reports & Analytics</h1>
                                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Quick overview of records, downloads, and recent activity</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <div class="flex items-center gap-2">
                                    <button id="export-pdf-btn" class="px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Export PDF</button>
                                    <button id="export-txt-btn" class="px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Export Plain</button>
                                    <a href="archives-landing.php" class="px-3 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Back</a>
                                    <div class="relative">
                                        <button id="more-actions-btn" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200" title="More options">
                                            <i class="bi bi-three-dots-vertical text-lg"></i>
                                        </button>
                                        <div id="more-actions-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                            <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Export CSV</a>
                                            <button id="refresh-analytics" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Refresh Data</button>
                                            <a href="audit-logs.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">View Audit Logs</a>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($export_error)): ?>
                                    <div class="text-xs text-red-600 dark:text-red-400"><?php echo htmlspecialchars($export_error); ?></div>
                                <?php endif; ?>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">Download location: your browsers default Downloads folder.</div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300 flex items-center justify-center"><i class="bi bi-file-earmark-text"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Total Records</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['total_records']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 flex items-center justify-center"><i class="bi bi-download"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Total Downloads</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo $stats['downloads']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-300 flex items-center justify-center"><i class="bi bi-hdd"></i></div>
                                    <div>
                                        <div class="text-xs text-gray-500">Uploads Folder Size</div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo storage_format_bytes($uploads_bytes); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card p-4 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:-translate-y-1 hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Archive Types</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="text-lg font-bold text-gray-800 dark:text-gray-100"><?php echo count($stats['folder_types'] ?? []); ?></div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">types</span>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <button id="types-dropdown-btn" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors text-gray-600 dark:text-gray-400" title="View all types">
                                            <i class="bi bi-three-dots-vertical text-lg"></i>
                                        </button>
                                        <div id="types-dropdown-menu" class="hidden absolute right-0 top-full mt-2 w-64 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 overflow-hidden">
                                            <div class="p-3 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50">
                                                <h4 class="font-semibold text-sm text-gray-800 dark:text-gray-100">Archive Types</h4>
                                            </div>
                                            <div class="max-h-64 overflow-y-auto">
                                                <?php if (!empty($stats['folder_types_detailed'])): ?>
                                                    <?php foreach ($stats['folder_types_detailed'] as $folder): ?>
                                                        <div class="px-3 py-2 border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                                            <div class="flex items-center justify-between mb-1">
                                                                <span class="font-medium text-sm text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($folder['name']); ?></span>
                                                                <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-semibold"><?php echo (int)$folder['file_count']; ?></span>
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                Created <?php echo date('M j, Y', strtotime($folder['created_at'])); ?>
                                                                <?php if ($folder['created_by_name']): ?>by <?php echo htmlspecialchars($folder['created_by_name']); ?><?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                        <p>No types available</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?php if (!empty($stats['folder_types'])): ?>
                                        <?php $type_count = 0; foreach ($stats['folder_types'] as $name => $count): if ($type_count < 3): ?>
                                            <span class="inline-block px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-xs font-medium whitespace-nowrap">
                                                <?php echo htmlspecialchars($name); ?> (<?php echo (int)$count; ?>)
                                            </span>
                                        <?php $type_count++; endif; endforeach; ?>
                                        <?php if (count($stats['folder_types']) > 3): ?>
                                            <span class="inline-block px-2 py-1 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded text-xs font-medium">
                                                +<?php echo count($stats['folder_types']) - 3; ?> more
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-500 dark:text-gray-400 text-xs">No types yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="text-xs text-gray-600 dark:text-gray-400">From</label>
                                <input id="filter-from" type="date" value="<?php echo htmlspecialchars($f_start ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <label class="text-xs text-gray-600 dark:text-gray-400 ml-2">To</label>
                                <input id="filter-to" type="date" value="<?php echo htmlspecialchars($f_end ?? ''); ?>" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                <select id="filter-type" class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                                    <option value="">All Types</option>
                                    <?php foreach (($stats['by_type'] ?? []) as $k=>$v): ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($safe_type === $k ? 'selected' : ''); ?>><?php echo htmlspecialchars($k); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex md:justify-end">
                                <button id="apply-filters" aria-pressed="false" class="px-3 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white">Apply</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="col-span-2 card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Records by Type</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo count($stats['by_type'] ?? []); ?> types</span>
                            </div>
                            <div id="sk-records" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <div class="relative w-full h-64 md:h-72 flex justify-center">
                                <canvas id="recordsTypeChart"></canvas>
                            </div>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Downloads by Type</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo array_sum($stats['downloads_by_type'] ?? []); ?></span>
                            </div>
                            <div id="sk-downloads-type" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="downloadsTypeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="col-span-2 card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Conversion Funnel (Views ? Downloads)</h3>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="funnelChart"></canvas>
                            </div>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="p-4 rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-800 dark:to-slate-700/40 border border-gray-200/50 dark:border-slate-600/50 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="text-xs text-gray-500">DAU</div>
                                    <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo (int)$dau; ?></div>
                                </div>
                                <div class="p-4 rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-800 dark:to-slate-700/40 border border-gray-200/50 dark:border-slate-600/50 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="text-xs text-gray-500">WAU</div>
                                    <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo (int)$wau; ?></div>
                                </div>
                                <div class="p-4 rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-800 dark:to-slate-700/40 border border-gray-200/50 dark:border-slate-600/50 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="text-xs text-gray-500">MAU</div>
                                    <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo (int)$mau; ?></div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-100">Top Downloaders (30d)</div>
                                <?php if (!empty($top_downloaders)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500"><tr><th class="py-1 pr-3">User</th><th class="py-1 pr-3">Downloads</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                            <?php foreach ($top_downloaders as $u): ?>
                                            <tr><td class="py-1 pr-3 truncate"><?php echo htmlspecialchars($u['name']); ?></td><td class="py-1 pr-3"><?php echo (int)$u['c']; ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-xs text-gray-500">No data</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Downloads Over Time (30 days)</h3>
                            </div>
                            <div id="sk-downloads-time" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="downloadsTimeChart"></canvas>
                            </div>
                        </div>
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Records Over Time (30 days)</h3>
                            </div>
                            <div id="sk-records-time" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <div class="relative w-full h-64 md:h-72">
                                <canvas id="recordsTimeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Downloads by Format</h3>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo array_sum($stats['downloads_by_format'] ?? []); ?></span>
                            </div>
                            <div id="sk-downloads-format" class="skeleton mb-2">
                                <div class="skeleton-block"></div>
                            </div>
                            <div class="relative w-full h-64 md:h-72 flex justify-center">
                                <canvas id="downloadsFormatChart"></canvas>
                            </div>
                        </div>
                        <?php if ($is_admin): ?>
                            <div class="lg:col-span-2 card p-4 sm:p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Recent Activity</h3>
                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200"><?php echo count($stats['recent_activity'] ?? []); ?></span>
                                </div>
                                <div id="sk-recent-activity" class="skeleton space-y-2 mb-2">
                                    <div class="skeleton-line w-2/3"></div>
                                    <div class="skeleton-line w-full"></div>
                                    <div class="skeleton-line w-5/6"></div>
                                    <div class="skeleton-line w-full"></div>
                                    <div class="skeleton-line w-4/5"></div>
                                    <div class="skeleton-line w-full"></div>
                                </div>
                                <?php if (!empty($stats['recent_activity'])): ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-xs text-gray-500">
                                                <tr>
                                                    <?php if ($stats['has_user_attr']): ?><th class="py-2 pr-3">User / App</th><?php endif; ?>
                                                    <th class="py-2 pr-3">Date &amp; time</th>
                                                    <th class="py-2 pr-3">Event</th>
                                                    <th class="py-2 pr-3">Event ID</th>
                                                    <th class="py-2 pr-3">Status</th>
                                                    <th class="py-2 pr-3">Entity type</th>
                                                    <th class="py-2 pr-3">Entity name</th>
                                                    <th class="py-2 pr-3">Entity ID</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                            <?php foreach ($stats['recent_activity'] as $a): ?>
                                                <tr class="even:bg-gray-50 dark:even:bg-slate-800/60">
                                                    <?php if ($stats['has_user_attr']): ?><td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars(($a['user_name'] ?? '') ?: 'Unknown'); ?></td><?php endif; ?>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['created_at']); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['event_type']); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo isset($a['id']) ? (int)$a['id'] : ''; ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo 'Succeeded'; ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($a['record_type'] ?? ''); ?></td>
                                                    <td class="py-2 pr-3"><?php echo htmlspecialchars($a['record_title'] ?? ''); ?></td>
                                                    <td class="py-2 pr-3 whitespace-nowrap"><?php echo isset($a['record_id']) ? (int)$a['record_id'] : ''; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-500">No tracked activity yet. Downloads will appear here after using the download modal.</div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="lg:col-span-2 card p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl transition-all duration-300 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                                        <i class="bi bi-lock"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">Recent Activity</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Visible to administrators only.</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl transition-all duration-300">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Recent Downloads</h3>
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <?php if (!empty($stats['recent_downloads'])): ?>
                                    <table class="w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500">
                                            <tr><th>Title</th><th>Type</th><th>Author</th><th>When</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_downloads'] as $row): ?>
                                                <tr class="border-t"><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo htmlspecialchars($row['type']); ?></td><td><?php echo htmlspecialchars($row['author']); ?></td><td><?php echo htmlspecialchars($row['last_accessed']); ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php elseif (!empty($stats['downloads_by_type'])): ?>
                                    <ul class="list-disc pl-5">
                                        <?php foreach ($stats['downloads_by_type'] as $type => $count): ?>
                                            <li><?php echo htmlspecialchars($type); ?>  <?php echo (int)$count; ?> downloads</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-gray-500">No downloads yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card p-6 bg-white dark:bg-slate-800 shadow-lg rounded-2xl border border-gray-100 dark:border-slate-700/60 ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl transition-all duration-300">
                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-100">Recently Added</h3>
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <?php if (empty($stats['recent_added'])): ?>
                                    <div class="text-gray-500">No recent records.</div>
                                <?php else: ?>
                                    <ul class="list-disc pl-5">
                                        <?php foreach ($stats['recent_added'] as $r): ?>
                                            <li><?php echo htmlspecialchars($r['title'].'  '.$r['type'].'  '.date('M j, Y', strtotime($r['created_at']))); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="export-modal" class="hidden fixed inset-0 z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-sm w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="mb-4">
                    <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Confirm Export</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Enter your password to continue.</div>
                </div>
                <form id="export-form" method="post" class="space-y-3">
                    <input type="hidden" name="export_action" id="export-action" value="">
                    <div>
                        <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-100">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" id="export-cancel" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white">Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality (mobile + desktop)
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('sidebar-collapsed'));
        });

        mobileMenuBtn?.addEventListener('click', () => {
            mobileSidebar?.classList.remove('-translate-x-full');
            sidebarOverlay?.classList.remove('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.add('opacity-100', 'pointer-events-auto');
        });

        closeMobileSidebar?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });

        sidebarOverlay?.addEventListener('click', () => {
            mobileSidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
            sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        });

        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notification-btn');
        const notifDropdown = document.getElementById('notification-dropdown');
        const notifCount = document.getElementById('notif-count');
        const moreBtn = document.getElementById('more-actions-btn');
        const moreDropdown = document.getElementById('more-actions-dropdown');
        const refreshBtn = document.getElementById('refresh-analytics');

        profileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown?.classList.add('hidden');
            moreDropdown?.classList.add('hidden');
            profileDropdown?.classList.toggle('hidden');
        });

        notifBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            moreDropdown?.classList.add('hidden');
            notifDropdown?.classList.toggle('hidden');
            try {
                var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
                if (ids.length > 0) {
                    fetch('notifications_log.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
                    }).then(function(){});
                }
            } catch(e){}
        });

        moreBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.add('hidden');
            notifDropdown?.classList.add('hidden');
            moreDropdown?.classList.toggle('hidden');
        });

        // Types Dropdown Handler
        const typesDropdownBtn = document.getElementById('types-dropdown-btn');
        const typesDropdownMenu = document.getElementById('types-dropdown-menu');
        
        typesDropdownBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            typesDropdownMenu?.classList.toggle('hidden');
        });
        
        document.addEventListener('click', (e) => {
            if (!typesDropdownBtn?.contains(e.target) && !typesDropdownMenu?.contains(e.target)) {
                typesDropdownMenu?.classList.add('hidden');
            }
        });

        refreshBtn?.addEventListener('click', () => {
            moreDropdown?.classList.add('hidden');
            location.reload();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
                profileDropdown?.classList.add('hidden');
            }
            if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
                notifDropdown?.classList.add('hidden');
            }
            if (!e.target.closest || !e.target.closest('#more-actions-dropdown')) {
                moreDropdown?.classList.add('hidden');
            }
        });

        (function(){
            function renderNotifList(items){
                var container = document.getElementById('notif-list');
                if (!container) return;
                if (!items || items.length === 0) {
                    container.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No notifications</div>';
                    return;
                }
                var html = items.map(function(n){
                    var href = n.link ? n.link : ('audit-logs.php?id='+encodeURIComponent(n.id));
                    var badge = '';
                    var textWeight = (n.status === 'unread') ? 'font-semibold' : 'font-medium';
                    if (n.status === 'unread') badge = ' ring-2 ring-red-200';
                    return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
                           '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
                           '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
                           '<div class="flex-1 min-w-0">'+
                           '<p class="text-sm '+textWeight+' text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
                           '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
                           '</div></a>';
                }).join('');
                container.innerHTML = html;
            }
            function escapeHtml(s){
                if (typeof s !== 'string') return '';
                return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); });
            }
            function fetchLatest(){
                fetch('notifications_fetch.php?page_size=5&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (d && d.success) renderNotifList(d.items||[]);
                }).catch(function(){});
            }
            function fetchUnread(){
                fetch('notifications_fetch.php?status=unread&page_size=1&page=1').then(function(r){ return r.json(); }).then(function(d){
                    if (!notifCount) return;
                    var total = (d && d.success) ? (d.total||0) : 0;
                    notifCount.textContent = String(total);
                    notifCount.style.display = total > 0 ? 'inline-flex' : 'none';
                }).catch(function(){});
            }
            function refresh(){
                fetchLatest();
                fetchUnread();
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', refresh);
            } else {
                refresh();
            }
            window.addEventListener('focus', refresh);
        })();

        if (localStorage.getItem('sidebarCollapsed') === 'true') sidebar?.classList.add('sidebar-collapsed');

        // Charts
        const byType = <?php echo json_encode($stats['by_type']); ?>;
        const downloadsByType = <?php echo json_encode($stats['downloads_by_type']); ?>;
        const downloadsByFormat = <?php echo json_encode($stats['downloads_by_format']); ?>;
        const seriesLabels = <?php echo json_encode($series_labels); ?>;
        const seriesDownloads = <?php echo json_encode($series_downloads_values); ?>;
        const seriesRecords = <?php echo json_encode($series_records_values); ?>;
        function labelsAndData(obj) { const labels = Object.keys(obj); const data = Object.values(obj); return { labels, data }; }
        const rt = labelsAndData(byType);
        const dt = labelsAndData(downloadsByType);
        const df = labelsAndData(downloadsByFormat);
        const hideSk = (id) => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); };
        const recordsCtx = document.getElementById('recordsTypeChart')?.getContext('2d');
        if (recordsCtx) { new Chart(recordsCtx, { type: 'pie', data: { labels: rt.labels, datasets: [{ data: rt.data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); hideSk('sk-records'); }
        const downloadsCtx = document.getElementById('downloadsTypeChart')?.getContext('2d');
        if (downloadsCtx) { new Chart(downloadsCtx, { type: 'bar', data: { labels: dt.labels, datasets: [{ label: 'Downloads', data: dt.data, backgroundColor: '#2563eb' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, precision:0 } } } }); hideSk('sk-downloads-type'); }
        const downloadsFormatCtx = document.getElementById('downloadsFormatChart')?.getContext('2d');
        if (downloadsFormatCtx) { new Chart(downloadsFormatCtx, { type: 'doughnut', data: { labels: df.labels, datasets: [{ data: df.data, backgroundColor: ['#dc2626','#3b82f6','#10b981','#6b7280'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); hideSk('sk-downloads-format'); }
        hideSk('sk-recent-activity');
        const downloadsTimeCtx = document.getElementById('downloadsTimeChart')?.getContext('2d');
        if (downloadsTimeCtx) { new Chart(downloadsTimeCtx, { type: 'line', data: { labels: seriesLabels, datasets: [{ label: 'Downloads', data: seriesDownloads, tension: 0.3, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.2)', fill: true }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxRotation: 0, autoSkip: true } }, y: { beginAtZero: true, precision: 0 } } } }); hideSk('sk-downloads-time'); }
        const recordsTimeCtx = document.getElementById('recordsTimeChart')?.getContext('2d');
        if (recordsTimeCtx) { new Chart(recordsTimeCtx, { type: 'line', data: { labels: seriesLabels, datasets: [{ label: 'Records', data: seriesRecords, tension: 0.3, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.2)', fill: true }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxRotation: 0, autoSkip: true } }, y: { beginAtZero: true, precision: 0 } } } }); hideSk('sk-records-time'); }
        const applyBtn = document.getElementById('apply-filters');
        let filtersApplied = false;
        function updateApplyBtn() {
            if (!applyBtn) return;
            applyBtn.classList.remove('bg-red-600','bg-red-700');
            applyBtn.classList.add(filtersApplied ? 'bg-red-700' : 'bg-red-600');
            applyBtn.setAttribute('aria-pressed', String(filtersApplied));
        }
        function currentFilters() {
            const from = document.getElementById('filter-from')?.value || '';
            const to = document.getElementById('filter-to')?.value || '';
            const type = document.getElementById('filter-type')?.value || '';
            const params = new URLSearchParams();
            if (from) params.set('start', from);
            if (to) params.set('end', to);
            if (type) params.set('type', type);
            return params;
        }
        function initFiltersApplied() {
            const qs = new URLSearchParams(window.location.search);
            filtersApplied = qs.has('start') || qs.has('end') || qs.has('type') || qs.has('format') || qs.has('event');
            updateApplyBtn();
        }
        applyBtn?.addEventListener('click', () => {
            const params = currentFilters();
            const url = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            window.location.assign(url);
        });
        initFiltersApplied();

    </script>
    <script src="assets/js/theme-toggle.js"></script>
    <script>
        (function(){
            var funnelLabels = <?php echo json_encode($funnel_types); ?>;
            var viewsByType = <?php echo json_encode($views_by_type); ?>;
            var downloadsByType = <?php echo json_encode($stats['downloads_by_type'] ?? []); ?>;
            var v = funnelLabels.map(function(k){ return viewsByType[k] || 0; });
            var d = funnelLabels.map(function(k){ return downloadsByType[k] || 0; });
            var ctx = document.getElementById('funnelChart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'bar',
                    data: { labels: funnelLabels, datasets: [
                        { label: 'Views', data: v, backgroundColor: '#3b82f6' },
                        { label: 'Downloads', data: d, backgroundColor: '#dc2626' }
                    ]},
                    options: { responsive: true, maintainAspectRatio: false, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true, precision:0 } } }
                });
            }
        })();
    </script>
    <script>
        const exportModal = document.getElementById('export-modal');
        const exportAction = document.getElementById('export-action');
        const exportCancel = document.getElementById('export-cancel');
        const exportPdfBtn = document.getElementById('export-pdf-btn');
        const exportTxtBtn = document.getElementById('export-txt-btn');
        function openExport(which){ exportAction.value = which; exportModal.classList.remove('hidden'); }
        function closeExport(){ exportModal.classList.add('hidden'); exportAction.value = ''; }
        exportPdfBtn?.addEventListener('click', () => openExport('pdf'));
        exportTxtBtn?.addEventListener('click', () => openExport('txt'));
        exportCancel?.addEventListener('click', closeExport);
        exportModal?.addEventListener('click', (e) => { if (e.target === exportModal) closeExport(); });
    </script>

    <script>
        (function() {
            function fetchAndUpdateStorage() {
                fetch("archives-landing.php?action=get_storage_data")
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const pct = data.percentage;
                            const usedText = data.usedText;
                            const totalText = data.totalText;
                            const fileCount = data.fileCount;

                            ["mobile", "desktop"].forEach(prefix => {
                                const bar = document.getElementById(prefix + \'-storage-bar\');
                                const pctEl = document.getElementById(prefix + \'-storage-pct\');
                                const textEl = document.getElementById(prefix + \'-storage-text\');
                                const filesEl = document.getElementById(prefix + \'-storage-files\');
                                
                                if (bar) bar.style.width = pct + \'%\';
                                if (pctEl) pctEl.textContent = pct + \'%\';
                                if (textEl) textEl.textContent = usedText + \' of \' + totalText;
                                if (filesEl) filesEl.textContent = fileCount + \' files tracked\';
                            });
                        }
                    }).catch(err => console.warn('Storage fetch error:', err));
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fetchAndUpdateStorage);
            } else {
                fetchAndUpdateStorage();
            }
            setInterval(fetchAndUpdateStorage, 60000);
        })();
    </script>
\n</body>
</html>
HTML;

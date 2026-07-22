<?php
/**
 * Check Database Files - Quick Diagnostic
 */
session_start();
$_SESSION['user_id'] = 1; // Bypass auth for testing

require 'authdatabase.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Files Check</title>
    <style>
        body { font-family: system-ui; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .section { margin-bottom: 40px; }
        .count { font-size: 24px; font-weight: bold; color: #2196F3; }
        .error { color: #f44336; }
        .success { color: #4CAF50; }
    </style>
</head>
<body>
    <h1>📊 Database Files Check</h1>

    <!-- Archive Files -->
    <div class="section">
        <h2>📁 Archive Files</h2>
        <?php
        $archiveResult = $conn->query("SELECT COUNT(*) as cnt FROM archive_files");
        $archiveCount = $archiveResult->fetch_assoc()['cnt'];
        ?>
        <p class="count">Total: <?php echo $archiveCount; ?> files</p>
        
        <?php if ($archiveCount > 0): ?>
            <p class="success">✅ Archive files found</p>
            <h3>Sample Files (First 10):</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>File Path</th>
                    <th>Size</th>
                    <th>Folder ID</th>
                    <th>File Exists?</th>
                </tr>
                <?php
                $files = $conn->query("SELECT id, name, file_path, file_size, folder_id FROM archive_files LIMIT 10");
                while ($f = $files->fetch_assoc()) {
                    $filePath = $f['file_path'];
                    $exists = file_exists($filePath) || file_exists('../' . $filePath) || file_exists('../uploads/' . $filePath);
                    $existsText = $exists ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>';
                    echo "<tr>";
                    echo "<td>{$f['id']}</td>";
                    echo "<td>" . htmlspecialchars($f['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($f['file_path']) . "</td>";
                    echo "<td>" . number_format($f['file_size']) . " bytes</td>";
                    echo "<td>{$f['folder_id']}</td>";
                    echo "<td>{$existsText}</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        <?php else: ?>
            <p class="error">⚠️ No archive files in database!</p>
        <?php endif; ?>
    </div>

    <!-- Legislative Files -->
    <div class="section">
        <h2>📜 Legislative Records</h2>
        <?php
        $legResult = $conn->query("SELECT COUNT(*) as cnt FROM legislative_records WHERE parent_version_id IS NULL");
        $legCount = $legResult->fetch_assoc()['cnt'];
        ?>
        <p class="count">Total: <?php echo $legCount; ?> records</p>
        
        <?php if ($legCount > 0): ?>
            <p class="success">✅ Legislative records found</p>
            <h3>Sample Records (First 10):</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>File Path</th>
                    <th>File Exists?</th>
                </tr>
                <?php
                $records = $conn->query("SELECT id, title, type, file_path FROM legislative_records WHERE parent_version_id IS NULL LIMIT 10");
                while ($r = $records->fetch_assoc()) {
                    $filePath = $r['file_path'];
                    $exists = file_exists($filePath) || file_exists('../' . $filePath);
                    $existsText = $exists ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>';
                    echo "<tr>";
                    echo "<td>{$r['id']}</td>";
                    echo "<td>" . htmlspecialchars($r['title']) . "</td>";
                    echo "<td>{$r['type']}</td>";
                    echo "<td>" . htmlspecialchars($r['file_path']) . "</td>";
                    echo "<td>{$existsText}</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        <?php else: ?>
            <p class="error">⚠️ No legislative records in database!</p>
        <?php endif; ?>
    </div>

    <!-- Archive Folders -->
    <div class="section">
        <h2>📂 Archive Folders</h2>
        <?php
        $folderResult = $conn->query("SELECT COUNT(*) as cnt FROM archive_folders");
        $folderCount = $folderResult->fetch_assoc()['cnt'];
        ?>
        <p class="count">Total: <?php echo $folderCount; ?> folders</p>
        
        <?php if ($folderCount > 0): ?>
            <p class="success">✅ Archive folders found</p>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Files Count</th>
                </tr>
                <?php
                $folders = $conn->query("SELECT id, name FROM archive_folders");
                while ($folder = $folders->fetch_assoc()) {
                    $fileCount = $conn->query("SELECT COUNT(*) as cnt FROM archive_files WHERE folder_id = {$folder['id']}")->fetch_assoc()['cnt'];
                    echo "<tr>";
                    echo "<td>{$folder['id']}</td>";
                    echo "<td>" . htmlspecialchars($folder['name']) . "</td>";
                    echo "<td>{$fileCount} files</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        <?php else: ?>
            <p class="error">⚠️ No archive folders in database!</p>
        <?php endif; ?>
    </div>

    <!-- Legislative Folders -->
    <div class="section">
        <h2>📂 Legislative Folders</h2>
        <?php
        $legFolderResult = $conn->query("SELECT COUNT(*) as cnt FROM legislative_folders WHERE parent_id IS NULL");
        $legFolderCount = $legFolderResult->fetch_assoc()['cnt'];
        ?>
        <p class="count">Total: <?php echo $legFolderCount; ?> folders</p>
        
        <?php if ($legFolderCount > 0): ?>
            <p class="success">✅ Legislative folders found</p>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Records Count</th>
                </tr>
                <?php
                $legFolders = $conn->query("SELECT id, name, type FROM legislative_folders WHERE parent_id IS NULL");
                while ($folder = $legFolders->fetch_assoc()) {
                    $recordCount = $conn->query("SELECT COUNT(*) as cnt FROM legislative_records WHERE type = '{$folder['type']}' AND parent_version_id IS NULL")->fetch_assoc()['cnt'];
                    echo "<tr>";
                    echo "<td>{$folder['id']}</td>";
                    echo "<td>" . htmlspecialchars($folder['name']) . "</td>";
                    echo "<td>{$folder['type']}</td>";
                    echo "<td>{$recordCount} records</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        <?php else: ?>
            <p class="error">⚠️ No legislative folders in database!</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔗 API Test Links</h2>
        <ul>
            <li><a href="api/fetch-storage-files.php?page=1" target="_blank">Test API: All Folders</a></li>
            <li><a href="api/fetch-storage-files.php?page=1&search=2024" target="_blank">Test API: Search "2024"</a></li>
        </ul>
    </div>
</body>
</html>

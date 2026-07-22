<?php
/**
 * Test Storage API - Direct Database Test
 * This tests if files are being fetched correctly from the database
 */

session_start();
// Bypass authentication for testing
$_SESSION['user_id'] = 1; // Set a test user ID

require 'authdatabase.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage API Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { padding: 20px; font-family: system-ui; }
        .section { margin-bottom: 30px; padding: 20px; background: #f9fafb; border-radius: 8px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        pre { background: #1f2937; color: #10b981; padding: 16px; border-radius: 8px; overflow-x: auto; }
        .test-card { 
            background: white; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            padding: 16px; 
            margin-bottom: 12px;
            max-width: 400px;
        }
        .test-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
        }
        .test-button:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <h1 style="font-size: 28px; font-weight: bold; margin-bottom: 20px;">🔬 Storage API & Database Test</h1>

    <!-- Test 1: Database Connection -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">1. Database Connection</h2>
        <?php
        if ($conn && $conn->ping()) {
            echo '<p class="success">✅ Database connected successfully</p>';
        } else {
            echo '<p class="error">❌ Database connection failed</p>';
            exit;
        }
        ?>
    </div>

    <!-- Test 2: Archive Files Count -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">2. Archive Files</h2>
        <?php
        $archiveCount = $conn->query("SELECT COUNT(*) as cnt FROM archive_files")->fetch_assoc()['cnt'];
        echo "<p><strong>Total archive files:</strong> {$archiveCount}</p>";
        
        if ($archiveCount > 0) {
            echo '<p class="success">✅ Archive files found</p>';
            $sampleFiles = $conn->query("SELECT id, name, file_path, file_size FROM archive_files LIMIT 3");
            echo '<p style="margin-top: 8px;"><strong>Sample files:</strong></p><ul style="margin-left: 20px;">';
            while ($f = $sampleFiles->fetch_assoc()) {
                echo '<li>' . htmlspecialchars($f['name']) . ' (' . $f['file_size'] . ' bytes)</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="error">⚠️ No archive files in database</p>';
        }
        ?>
    </div>

    <!-- Test 3: Legislative Files Count -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">3. Legislative Records</h2>
        <?php
        $legCount = $conn->query("SELECT COUNT(*) as cnt FROM legislative_records WHERE parent_version_id IS NULL")->fetch_assoc()['cnt'];
        echo "<p><strong>Total legislative records:</strong> {$legCount}</p>";
        
        if ($legCount > 0) {
            echo '<p class="success">✅ Legislative records found</p>';
            $sampleLeg = $conn->query("SELECT id, title, file_path, type FROM legislative_records WHERE parent_version_id IS NULL LIMIT 3");
            echo '<p style="margin-top: 8px;"><strong>Sample records:</strong></p><ul style="margin-left: 20px;">';
            while ($l = $sampleLeg->fetch_assoc()) {
                echo '<li>' . htmlspecialchars($l['title']) . ' (' . $l['type'] . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="error">⚠️ No legislative records in database</p>';
        }
        ?>
    </div>

    <!-- Test 4: API Response Test -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">4. API Response Test</h2>
        <p style="margin-bottom: 12px;">Testing: <code>api/fetch-storage-files.php?page=1</code></p>
        <button onclick="testAPI()" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">
            🧪 Test API Call
        </button>
        <div id="api-result" style="margin-top: 12px;"></div>
    </div>

    <!-- Test 5: Render Files with Clickable Buttons -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">5. Render Test Files (Like Storage Modal)</h2>
        <p style="margin-bottom: 12px;">These cards simulate the storage modal layout:</p>
        <button onclick="renderFiles()" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
            📄 Load & Render Files
        </button>
        <div id="files-container" style="margin-top: 16px;"></div>
    </div>

    <!-- Console Log -->
    <div class="section">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 12px;">Console Log</h2>
        <pre id="console-log">Waiting for actions...</pre>
    </div>

    <script>
        const consoleLog = document.getElementById('console-log');
        
        function log(msg) {
            const timestamp = new Date().toLocaleTimeString();
            consoleLog.textContent += `\n[${timestamp}] ${msg}`;
            consoleLog.scrollTop = consoleLog.scrollHeight;
            console.log(msg);
        }

        function testAPI() {
            log('Testing API call...');
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<p style="color: #6b7280;">Loading...</p>';
            
            fetch('api/fetch-storage-files.php?page=1')
                .then(response => {
                    log(`API Response Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    log(`API Success: ${data.success}`);
                    log(`Folders: ${data.data?.folders?.length || 0}`);
                    log(`Files: ${data.data?.files?.length || 0}`);
                    
                    resultDiv.innerHTML = `
                        <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;">
                            <p><strong>Success:</strong> ${data.success}</p>
                            <p><strong>Folders:</strong> ${data.data?.folders?.length || 0}</p>
                            <p><strong>Files:</strong> ${data.data?.files?.length || 0}</p>
                            <details style="margin-top: 8px;">
                                <summary style="cursor: pointer; font-weight: 600;">View JSON Response</summary>
                                <pre style="font-size: 11px; margin-top: 8px;">${JSON.stringify(data, null, 2)}</pre>
                            </details>
                        </div>
                    `;
                })
                .catch(error => {
                    log(`API Error: ${error.message}`);
                    resultDiv.innerHTML = `<p style="color: #ef4444;">Error: ${error.message}</p>`;
                });
        }

        function renderFiles() {
            log('Fetching files from API...');
            const container = document.getElementById('files-container');
            container.innerHTML = '<p style="color: #6b7280;">Loading...</p>';
            
            fetch('api/fetch-storage-files.php?page=1')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'API failed');
                    }
                    
                    const files = data.data?.files || [];
                    log(`Rendering ${files.length} files...`);
                    
                    if (files.length === 0) {
                        container.innerHTML = '<p style="color: #ef4444;">No files found. Check database.</p>';
                        return;
                    }
                    
                    // Render files in grid
                    container.innerHTML = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;"></div>';
                    const grid = container.querySelector('div');
                    
                    files.slice(0, 6).forEach((file, index) => {
                        const card = document.createElement('div');
                        card.className = 'test-card';
                        card.innerHTML = `
                            <div style="margin-bottom: 8px;">
                                <p style="font-weight: 600; margin-bottom: 4px;">📄 ${escapeHtml(file.name)}</p>
                                <p style="font-size: 12px; color: #6b7280;">${file.size_formatted || 'Unknown size'}</p>
                            </div>
                            <button 
                                class="test-button"
                                onclick="handleClick('${escapeHtml(file.id)}', '${escapeHtml(file.name)}')"
                                style="z-index: 10; pointer-events: auto;">
                                <i class="bi bi-files"></i>
                                <span>Make a Copy</span>
                            </button>
                        `;
                        grid.appendChild(card);
                        log(`Card ${index + 1} rendered: ${file.name}`);
                    });
                    
                    log(`✅ All files rendered successfully`);
                })
                .catch(error => {
                    log(`❌ Error: ${error.message}`);
                    container.innerHTML = `<p style="color: #ef4444;">Error: ${error.message}</p>`;
                });
        }

        function handleClick(fileId, fileName) {
            log(`🎯 BUTTON CLICKED! File: ${fileName} | ID: ${fileId}`);
            alert(`Success!\n\nFile: ${fileName}\nID: ${fileId}\n\nThe button is working!`);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        log('Page loaded. Ready for testing.');
    </script>
</body>
</html>

<?php
/**
 * test-pinata.php — Pinata Cloud (IPFS) integration test page.
 *
 * Lets an authenticated user verify the Pinata connection and run a live
 * upload → gateway retrieval → unpin round trip against their Pinata account.
 *
 * Endpoints (same file, JSON responses):
 *   GET  test-pinata.php                     -> page
 *   POST test-pinata.php?action=test_auth    -> auth check
 *   POST test-pinata.php?action=upload       -> multipart file upload (field "file")
 *   POST test-pinata.php?action=fetch&cid=.. -> fetch a CID from the gateway
 *   POST test-pinata.php?action=unpin&cid=.. -> unpin a CID
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'authdatabase.php';
require_once __DIR__ . '/includes/pinata.php';

header('Content-Type: text/html; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---------------------------------------------------------------- JSON actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($action === 'test_auth') {
        $cfg = pinata_config();
        if (empty($cfg['jwt'])) {
            echo json_encode(['success' => false, 'error' => 'PINATA_JWT is not configured in .env']);
            exit;
        }
        $ch = curl_init('https://api.pinata.cloud/data/testAuthentication');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt']],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $ok = $status >= 200 && $status < 300;
        echo json_encode([
            'success' => $ok,
            'status'  => $status,
            'curl_error' => $err ?: null,
            'message' => $ok ? 'Connected to Pinata API successfully.' : 'Pinata API rejected the credentials.',
            'response' => json_decode((string)$body, true) ?: (string)$body,
        ]);
        exit;
    }

    if ($action === 'upload') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $code]);
            exit;
        }
        $maxSize = 50 * 1024 * 1024;
        if ((int)$_FILES['file']['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'File exceeds 50MB limit']);
            exit;
        }

        // Work on a temp copy so test uploads never pollute the archive.
        $tmpPath = tempnam(sys_get_temp_dir(), 'pinata_test_');
        $name = $_FILES['file']['name'];
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to stage upload']);
            exit;
        }

        // Optional group membership, mirroring what the app does for folders.
        $groupName = trim($_POST['group_name'] ?? '');
        $groupId = null;
        if ($groupName !== '') {
            $g = pinata_ensure_group($groupName);
            if ($g['success']) {
                $groupId = $g['id'];
            } else {
                @unlink($tmpPath);
                echo json_encode(['success' => false, 'error' => 'Group setup failed: ' . ($g['error'] ?? 'unknown error')]);
                exit;
            }
        }

        $result = pinata_upload_file($tmpPath, basename($name), ['record' => 'test', 'source' => 'test-pinata'], $groupId);
        @unlink($tmpPath);

        echo json_encode([
            'success' => $result['success'],
            'error'   => $result['error'] ?? null,
            'cid'     => $result['cid'] ?? null,
            'gateway_url' => isset($result['cid']) ? pinata_gateway_url($result['cid']) : null,
            'group'   => $result['group'] ?? null,
            'group_name' => $groupName !== '' ? $groupName : null,
            'data'    => $result['data'] ?? null,
        ]);
        exit;
    }

    if ($action === 'list_groups') {
        $cfg = pinata_config();
        if (empty($cfg['jwt'])) {
            echo json_encode(['success' => false, 'error' => 'PINATA_JWT is not configured']);
            exit;
        }
        $groups = [];
        $pageToken = '';
        do {
            $url = 'https://api.pinata.cloud/v3/groups/public?limit=100' . ($pageToken !== '' ? '&pageToken=' . urlencode($pageToken) : '');
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt']],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status < 200 || $status >= 300) {
                echo json_encode(['success' => false, 'error' => 'List groups failed (HTTP ' . $status . '): ' . substr((string)$body, 0, 300)]);
                exit;
            }
            $d = json_decode((string)$body, true);
            foreach (($d['data']['groups'] ?? []) as $g) {
                $groups[] = ['id' => $g['id'] ?? '', 'name' => $g['name'] ?? '', 'created_at' => $g['created_at'] ?? ''];
            }
            $pageToken = $d['data']['next_page_token'] ?? '';
        } while ($pageToken !== '');
        echo json_encode(['success' => true, 'groups' => $groups]);
        exit;
    }

    if ($action === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Missing group name']);
            exit;
        }
        $res = pinata_ensure_group($name);
        echo json_encode($res + ['name' => $name]);
        exit;
    }

    if ($action === 'fetch') {
        $cid = trim($_GET['cid'] ?? $_POST['cid'] ?? '');
        if ($cid === '') {
            echo json_encode(['success' => false, 'error' => 'Missing CID']);
            exit;
        }
        $url = pinata_gateway_url($cid);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $size = strlen((string)$body);
        curl_close($ch);

        $previewable = $contentType && strpos($contentType, 'image/') === 0;
        echo json_encode([
            'success' => $status >= 200 && $status < 300 && $err === '',
            'status'  => $status,
            'curl_error' => $err ?: null,
            'content_type' => $contentType,
            'bytes'   => $size,
            'url'     => $url,
            'preview' => $previewable ? 'data:' . $contentType . ';base64,' . base64_encode($body) : null,
            'text_preview' => !$previewable ? substr($body, 0, 2000) : null,
        ]);
        exit;
    }

    if ($action === 'unpin') {
        $cid = trim($_GET['cid'] ?? $_POST['cid'] ?? '');
        if ($cid === '') {
            echo json_encode(['success' => false, 'error' => 'Missing CID']);
            exit;
        }
        echo json_encode(['success' => pinata_delete_cid($cid), 'cid' => $cid]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ---------------------------------------------------------------- Configuration status
$cfg = pinata_config();
$jwtSet = !empty($cfg['jwt']);
$jwtMasked = $jwtSet ? substr($cfg['jwt'], 0, 12) . '…' . substr($cfg['jwt'], -8) : '';
$gatewaySet = !empty($cfg['gateway']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinata IPFS Test — LAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        pre { white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-slate-900 text-gray-800 dark:text-gray-100 font-sans antialiased min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="bi bi-cloud-arrow-up text-indigo-500"></i> Pinata IPFS Test
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Verify the Pinata connection and run a live upload / retrieval round trip.</p>
            </div>
            <a href="storage.php" class="text-sm px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                <i class="bi bi-arrow-left"></i> Back to Storage
            </a>
        </div>

        <!-- 1. Configuration status -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">1. Configuration (.env)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg border border-gray-200 dark:border-slate-600 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="bi bi-key"></i>
                        <span class="font-semibold">PINATA_JWT</span>
                    </div>
                    <?php if ($jwtSet): ?>
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">✔ Set</span>
                        <div class="mt-1 text-gray-500 dark:text-gray-400 break-all"><?php echo htmlspecialchars($jwtMasked); ?></div>
                    <?php else: ?>
                        <span class="text-red-600 dark:text-red-400 font-medium">✖ Not set</span>
                        <div class="mt-1 text-gray-500 dark:text-gray-400">Uploads will be stored locally only.</div>
                    <?php endif; ?>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-slate-600 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="bi bi-globe"></i>
                        <span class="font-semibold">PINATA_GATEWAY</span>
                    </div>
                    <?php if ($gatewaySet): ?>
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">✔ Set</span>
                        <div class="mt-1 text-gray-500 dark:text-gray-400 break-all"><?php echo htmlspecialchars($cfg['gateway']); ?></div>
                    <?php else: ?>
                        <span class="text-red-600 dark:text-red-400 font-medium">✖ Not set</span>
                        <div class="mt-1 text-gray-500 dark:text-gray-400">Falls back to the public gateway.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. Connection test -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">2. Connection Test</h2>
                <button id="btn-auth" onclick="runAuth()" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                    <i class="bi bi-plug"></i> Test Connection
                </button>
            </div>
            <div id="auth-result" class="text-sm text-gray-500 dark:text-gray-400">Click "Test Connection" to verify your Pinata credentials.</div>
        </div>

        <!-- 3. Upload test -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">3. Upload Test</h2>
            <form id="upload-form" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2" for="file-input">Choose a file to pin to IPFS</label>
                    <input id="file-input" type="file" name="file" required
                           class="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/40 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/60 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2" for="group-name-input">Pinata group (optional — creates it if missing)</label>
                    <input id="group-name-input" type="text" name="group_name" placeholder="e.g. LAS/Ordinances"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors">
                    <i class="bi bi-cloud-arrow-up"></i> Upload &amp; Pin
                </button>
            </form>

            <div id="upload-result" class="mt-4"></div>
        </div>

        <!-- 4. Groups -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">4. Groups</h2>
                <button onclick="loadGroups()" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                    <i class="bi bi-folder2-open"></i> List Groups
                </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <input id="new-group-name" type="text" placeholder="New group name, e.g. LAS/Test Group"
                       class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button onclick="createGroup()" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors">
                    <i class="bi bi-folder-plus"></i> Create Group
                </button>
            </div>
            <div id="groups-result" class="text-sm text-gray-500 dark:text-gray-400">Click "List Groups" to see the groups in your Pinata account. Files uploaded with a group name in step 3 are added to that group.</div>
        </div>

        <!-- 5. Gateway fetch test -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">5. Gateway Fetch Test</h2>
            <div class="flex flex-col sm:flex-row gap-3">
                <input id="cid-input" type="text" placeholder="Paste an IPFS CID (e.g. bafkreig…)" 
                       class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button onclick="runFetch()" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors">
                    <i class="bi bi-download"></i> Fetch from Gateway
                </button>
            </div>
            <div id="fetch-result" class="mt-4"></div>
        </div>

        <!-- 6. Recent pins (cleanup) -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold mb-4">6. Tested Pins</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">CIs pinned during this session (in this browser tab). Unpin them to keep your Pinata account clean.</p>
            <div id="pins-list" class="space-y-2"></div>
        </div>
    </div>

    <script>
        const uploadResult = document.getElementById('upload-result');
        const pinsList = document.getElementById('pins-list');
        const pinnedCids = new Set();

        function renderPins() {
            if (pinnedCids.size === 0) {
                pinsList.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">No pins in this session yet.</div>';
                return;
            }
            pinsList.innerHTML = '';
            pinnedCids.forEach((cid, idx) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-slate-600 p-3';
                row.innerHTML = `
                    <div class="min-w-0">
                        <div class="text-xs font-mono text-emerald-600 dark:text-emerald-400 break-all">${cid}</div>
                    </div>
                    <button onclick="unpin('${cid}', this)" class="flex-none px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors">
                        Unpin
                    </button>
                `;
                pinsList.appendChild(row);
            });
        }

        function setResult(el, type, html) {
            const colors = { info: 'bg-gray-50 dark:bg-slate-900 text-gray-700 dark:text-gray-300',
                             ok: 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300',
                             err: 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300' };
            el.className = 'rounded-lg border border-gray-200 dark:border-slate-600 p-4 text-sm ' + (colors[type] || colors.info);
            el.innerHTML = html;
        }

        async function loadGroups() {
            const el = document.getElementById('groups-result');
            setResult(el, 'info', 'Loading groups…');
            try {
                const fd = new FormData();
                fd.append('action', 'list_groups');
                const r = await fetch('test-pinata.php?action=list_groups', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) {
                    setResult(el, 'err', '<div class="font-semibold">Failed to list groups</div><pre class="mt-2">' + escapeHtml(JSON.stringify(d, null, 2)) + '</pre>');
                    return;
                }
                if (!d.groups.length) {
                    setResult(el, 'info', 'No groups yet. Create one above or upload a file with a group name.');
                    return;
                }
                const rows = d.groups.map(g =>
                    `<div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-slate-600 p-3">
                        <div class="min-w-0">
                            <div class="font-medium text-sm break-all">${escapeHtml(g.name)}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono break-all">${escapeHtml(g.id)}</div>
                        </div>
                        <button onclick="copyText('${g.id}')" class="flex-none px-3 py-1.5 rounded-lg bg-gray-600 hover:bg-gray-700 text-white text-xs font-semibold transition-colors">Copy ID</button>
                    </div>`).join('');
                setResult(el, 'ok', `<div class="font-semibold mb-3">${d.groups.length} group(s) in your Pinata account</div>${rows}`);
            } catch (e) {
                setResult(el, 'err', 'Error: ' + escapeHtml(e.message));
            }
        }

        async function createGroup() {
            const el = document.getElementById('groups-result');
            const name = document.getElementById('new-group-name').value.trim();
            if (!name) return;
            setResult(el, 'info', 'Creating group…');
            try {
                const fd = new FormData();
                fd.append('action', 'create_group');
                fd.append('name', name);
                const r = await fetch('test-pinata.php?action=create_group', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    setResult(el, 'ok',
                        `<div class="flex items-center gap-2 font-semibold"><i class="bi bi-check-circle-fill"></i> ${d.created ? 'Created' : 'Already exists'}: ${escapeHtml(d.name)}</div>
                         <div class="mt-1 text-xs font-mono text-emerald-600 dark:text-emerald-400 break-all">${escapeHtml(d.id)}</div>
                         <pre class="mt-2">${escapeHtml(JSON.stringify(d, null, 2))}</pre>`);
                } else {
                    setResult(el, 'err', '<div class="font-semibold">Create failed</div><pre class="mt-2">' + escapeHtml(JSON.stringify(d, null, 2)) + '</pre>');
                }
            } catch (e) {
                setResult(el, 'err', 'Error: ' + escapeHtml(e.message));
            }
        }

        function copyText(text) {
            navigator.clipboard.writeText(text).catch(() => {});
        }

        async function runAuth() {
            const el = document.getElementById('auth-result');
            setResult(el, 'info', 'Testing connection…');
            try {
                const fd = new FormData();
                fd.append('action', 'test_auth');
                const r = await fetch('test-pinata.php?action=test_auth', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    setResult(el, 'ok',
                        `<div class="flex items-center gap-2 font-semibold"><i class="bi bi-check-circle-fill"></i> ${escapeHtml(d.message)} (HTTP ${d.status})</div>
                         <pre class="mt-2">${escapeHtml(JSON.stringify(d.response, null, 2))}</pre>`);
                } else {
                    setResult(el, 'err',
                        `<div class="font-semibold">Connection failed (HTTP ${d.status || '—'})${d.curl_error ? ' — ' + escapeHtml(d.curl_error) : ''}</div>
                         <pre class="mt-2">${escapeHtml(JSON.stringify(d, null, 2))}</pre>`);
                }
            } catch (e) {
                setResult(el, 'err', 'Error: ' + escapeHtml(e.message));
            }
        }

        document.getElementById('upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fileInput = document.getElementById('file-input');
            if (!fileInput.files.length) return;

            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('file', fileInput.files[0]);
            const groupInput = document.getElementById('group-name-input');
            if (groupInput.value.trim()) fd.append('group_name', groupInput.value.trim());

            setResult(uploadResult, 'info', 'Uploading & pinning to Pinata…');
            try {
                const r = await fetch('test-pinata.php?action=upload', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    pinnedCids.add(d.cid);
                    renderPins();
                    const groupLine = d.group_name
                        ? `<div><span class="font-semibold">Group:</span> ${escapeHtml(d.group_name)} ${d.group?.success ? '<span class="text-emerald-600 dark:text-emerald-400">(added)</span>' : '<span class="text-amber-600 dark:text-amber-400">(add failed)</span>'}</div>`
                        : '';
                    setResult(uploadResult, 'ok',
                        `<div class="flex items-center gap-2 font-semibold"><i class="bi bi-check-circle-fill"></i> Pinned successfully</div>
                         <div class="mt-2 grid grid-cols-1 gap-2">
                            <div><span class="font-semibold">CID:</span> <span class="font-mono text-emerald-600 dark:text-emerald-400">${escapeHtml(d.cid)}</span></div>
                            <div class="break-all"><span class="font-semibold">Gateway:</span> <a href="${escapeHtml(d.gateway_url)}" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 underline">${escapeHtml(d.gateway_url)}</a></div>
                            <div><span class="font-semibold">Name:</span> ${escapeHtml(d.data?.name || '')} · <span class="font-semibold">Size:</span> ${escapeHtml(d.data?.size || '')} bytes · <span class="font-semibold">MIME:</span> ${escapeHtml(d.data?.mime_type || '')}</div>
                            ${groupLine}
                         </div>
                         <div class="mt-3 flex gap-2">
                            <a href="test-pinata.php?action=fetch&cid=${encodeURIComponent(d.cid)}" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold">Open in new tab</a>
                            <button onclick="document.getElementById('cid-input').value='${encodeURIComponent(d.cid)}'; runFetch();" class="px-3 py-1.5 rounded-lg bg-gray-600 hover:bg-gray-700 text-white text-xs font-semibold">Fetch here</button>
                            <button onclick="unpin('${d.cid}', this)" class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">Unpin</button>
                         </div>
                         <details class="mt-3"><summary class="cursor-pointer font-semibold">View raw response</summary>
                         <pre class="mt-2">${escapeHtml(JSON.stringify(d, null, 2))}</pre></details>`);
                } else {
                    setResult(uploadResult, 'err', '<div class="font-semibold">Upload failed</div><pre class="mt-2">' + escapeHtml(JSON.stringify(d, null, 2)) + '</pre>');
                }
            } catch (e) {
                setResult(uploadResult, 'err', 'Error: ' + escapeHtml(e.message));
            }
        });

        async function runFetch() {
            const el = document.getElementById('fetch-result');
            const cid = document.getElementById('cid-input').value.trim();
            if (!cid) return;

            setResult(el, 'info', 'Fetching from gateway…');
            try {
                const fd = new FormData();
                fd.append('action', 'fetch');
                fd.append('cid', cid);
                const r = await fetch('test-pinata.php?action=fetch', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    let preview = '';
                    if (d.preview) {
                        preview = `<img src="${d.preview}" alt="Preview" class="mt-3 max-h-72 rounded-lg border border-gray-200 dark:border-slate-600">`;
                    } else if (d.text_preview !== null) {
                        preview = `<pre class="mt-3 bg-gray-50 dark:bg-slate-900 rounded-lg p-3 border border-gray-200 dark:border-slate-600">${escapeHtml(d.text_preview)}${d.bytes > 2000 ? '\n…' : ''}</pre>`;
                    }
                    setResult(el, 'ok',
                        `<div class="flex items-center gap-2 font-semibold"><i class="bi bi-check-circle-fill"></i> Retrieved ${escapeHtml(d.bytes)} bytes (HTTP ${d.status})</div>
                         <div class="mt-2"><span class="font-semibold">Content-Type:</span> ${escapeHtml(d.content_type || 'unknown')}</div>
                         <div class="mt-1 break-all"><span class="font-semibold">URL:</span> <a href="${escapeHtml(d.url)}" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 underline">${escapeHtml(d.url)}</a></div>
                         ${preview}`);
                } else {
                    setResult(el, 'err',
                        `<div class="font-semibold">Fetch failed (HTTP ${d.status || '—'})${d.curl_error ? ' — ' + escapeHtml(d.curl_error) : ''}</div>
                         <pre class="mt-2">${escapeHtml(JSON.stringify(d, null, 2))}</pre>`);
                }
            } catch (e) {
                setResult(el, 'err', 'Error: ' + escapeHtml(e.message));
            }
        }

        async function unpin(cid, btn) {
            if (btn) { btn.disabled = true; btn.textContent = '…'; }
            try {
                const fd = new FormData();
                fd.append('action', 'unpin');
                fd.append('cid', cid);
                const r = await fetch('test-pinata.php?action=unpin', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    pinnedCids.delete(cid);
                    renderPins();
                    if (btn) btn.textContent = 'Unpinned';
                } else if (btn) {
                    btn.textContent = 'Failed';
                }
            } catch (e) {
                if (btn) btn.textContent = 'Error';
            }
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
    </script>
</body>
</html>

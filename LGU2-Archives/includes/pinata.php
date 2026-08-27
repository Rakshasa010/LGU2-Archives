<?php
/**
 * Pinata Cloud (IPFS) integration helper for LAS.
 *
 * Configured via PINATA_JWT and PINATA_GATEWAY in the project .env file.
 * Uses the Pinata V3 Files API (https://uploads.pinata.cloud/v3/files)
 * for uploading/pinning files and the dedicated gateway for retrieval.
 *
 * Public API:
 *   pinata_is_configured()                          : bool
 *   pinata_config()                                 : array{jwt, gateway}
 *   pinata_upload_file($path, $name, $keyvalues, $groupId)
 *                                                   : array{success, cid, group, ...}
 *   pinata_gateway_url($cid)                        : string
 *   pinata_redirect($cid, $filename = null)         : void (302 redirect)
 *   pinata_stream_cid($cid, $inline = true)         : void (streams via gateway)
 *   pinata_delete_cid($cid)                         : bool
 *   pinata_group_create($name)                      : array{success, id, ...}
 *   pinata_group_find_by_name($name)                : string|null (group id)
 *   pinata_ensure_group($name)                      : array{success, id, created, ...}
 *   pinata_group_add_file($groupId, $fileId)        : array{success, ...}
 *   pinata_ensure_folder_group($conn, $table, $folderId, $folderName)
 *                                                   : array{success, id, ...}
 */

function pinata_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $env = [];
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\"'");
        }
    }

    $config = [
        'jwt'     => $env['PINATA_JWT'] ?? (getenv('PINATA_JWT') ?: ''),
        'gateway' => $env['PINATA_GATEWAY'] ?? (getenv('PINATA_GATEWAY') ?: ''),
    ];
    return $config;
}

function pinata_is_configured() {
    $cfg = pinata_config();
    return !empty($cfg['jwt']);
}

/**
 * Upload / pin a local file to Pinata IPFS.
 *
 * @param string $filePath Absolute or relative path to the file on disk.
 * @param string|null $name Human-friendly filename stored on Pinata.
 * @param array $keyvalues Optional key/value metadata attached to the pin.
 * @param string|null $groupId Optional Pinata group id to add the file to after pinning.
 * @return array{success: bool, cid?: string, error?: string, status?: int, group?: array}
 */
function pinata_upload_file($filePath, $name = null, array $keyvalues = [], $groupId = null) {
    $cfg = pinata_config();
    if (empty($cfg['jwt'])) {
        return ['success' => false, 'error' => 'PINATA_JWT is not configured'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'The PHP cURL extension is not enabled'];
    }
    if (!is_file($filePath) || !is_readable($filePath)) {
        return ['success' => false, 'error' => 'File not found or not readable: ' . $filePath];
    }

    $name = ($name !== null && $name !== '') ? $name : basename($filePath);

    $post = [
        'file'    => new CURLFile($filePath),
        'name'    => $name,
        'network' => 'public',
    ];
    if (!empty($keyvalues)) {
        $post['keyvalues'] = json_encode($keyvalues);
    }

    $ch = curl_init('https://uploads.pinata.cloud/v3/files');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt']],
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        return ['success' => false, 'error' => 'Pinata upload failed: ' . $err];
    }

    $data = json_decode((string)$body, true);
    if ($status >= 200 && $status < 300 && isset($data['data']['cid'])) {
        $result = [
            'success' => true,
            'cid'     => $data['data']['cid'],
            'data'    => $data['data'],
            'status'  => $status,
        ];
        // Best-effort: add the file to the requested group after a successful pin.
        if (!empty($groupId) && !empty($data['data']['id'])) {
            $result['group'] = pinata_group_add_file($groupId, $data['data']['id']);
        }
        return $result;
    }

    return [
        'success' => false,
        'status'  => $status,
        'error'   => 'Pinata upload failed (HTTP ' . $status . '): ' . substr((string)$body, 0, 500),
    ];
}

/**
 * Build a content URL for a CID served through the Pinata dedicated gateway.
 *
 * @param string $cid The IPFS Content Identifier.
 * @return string e.g. https://<PINATA_GATEWAY>/ipfs/<CID>
 */
function pinata_gateway_url($cid) {
    $cid = trim((string)$cid);
    if ($cid === '') {
        return '';
    }
    $cfg = pinata_config();
    $gateway = trim($cfg['gateway']);
    if ($gateway === '') {
        // Fall back to Pinata's public gateway when no dedicated gateway is configured.
        $gateway = 'https://gateway.pinata.cloud';
    } elseif (!preg_match('#^https?://#i', $gateway)) {
        $gateway = 'https://' . $gateway;
    }
    return rtrim($gateway, '/') . '/ipfs/' . rawurlencode($cid);
}

/**
 * 302-redirect to the Pinata dedicated gateway for a CID.
 */
function pinata_redirect($cid, $filename = null) {
    $url = pinata_gateway_url($cid);
    if ($url === '') {
        http_response_code(404);
        exit('IPFS CID not available');
    }
    if ($filename !== null && $filename !== '') {
        $sep = strpos($url, '?') === false ? '?' : '&';
        $url .= $sep . http_build_query(['download' => $filename]);
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Stream a CID's content through the Pinata dedicated gateway.
 *
 * @param string $cid CID to fetch from the gateway.
 * @param bool $inline true => Content-Disposition inline, false => attachment.
 */
function pinata_stream_cid($cid, $inline = true, $filename = null) {
    $url = pinata_gateway_url($cid);
    if ($url === '') {
        http_response_code(404);
        exit('IPFS CID not available');
    }

    // Probe response headers first so we can forward accurate Content-Type/Length.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY          => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 60,
        CURLOPT_CONNECTTIMEOUT  => 30,
    ]);
    curl_exec($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $size        = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);

    if ($httpCode >= 400) {
        http_response_code(502);
        exit('Failed to retrieve file from IPFS gateway (HTTP ' . $httpCode . ')');
    }

    $name = ($filename !== null && $filename !== '') ? basename($filename) : ($cid . '.bin');
    header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    if ($size > 0) {
        header('Content-Length: ' . $size);
    }
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

/**
 * Unpin a CID from Pinata (removes the pin; content may remain on the IPFS network).
 */
function pinata_delete_cid($cid) {
    $cfg = pinata_config();
    if (empty($cfg['jwt'])) {
        return false;
    }
    $ch = curl_init('https://api.pinata.cloud/pinning/unpin/' . rawurlencode($cid));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt']],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

/**
 * Create a Pinata group (on the public IPFS network).
 *
 * @param string $name Display name for the group.
 * @return array{success: bool, id?: string, error?: string, status?: int}
 */
function pinata_group_create($name) {
    $cfg = pinata_config();
    if (empty($cfg['jwt'])) {
        return ['success' => false, 'error' => 'PINATA_JWT is not configured'];
    }
    $ch = curl_init('https://api.pinata.cloud/v3/groups/public');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode(['name' => (string)$name]),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt'], 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        return ['success' => false, 'error' => 'Pinata group create failed: ' . $err];
    }
    $data = json_decode((string)$body, true);
    if ($status >= 200 && $status < 300 && isset($data['data']['id'])) {
        return ['success' => true, 'id' => $data['data']['id'], 'status' => $status];
    }
    return ['success' => false, 'status' => $status, 'error' => 'Pinata group create failed (HTTP ' . $status . '): ' . substr((string)$body, 0, 500)];
}

/**
 * Find a Pinata group by its exact name on the public network.
 *
 * @param string $name Group name to look up.
 * @return string|null Group id, or null when not found / API failure.
 */
function pinata_group_find_by_name($name) {
    $cfg = pinata_config();
    if (empty($cfg['jwt'])) {
        return null;
    }
    $ch = curl_init('https://api.pinata.cloud/v3/groups/public?name=' . urlencode((string)$name));
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
        return null;
    }
    $data = json_decode((string)$body, true);
    foreach (($data['data']['groups'] ?? []) as $group) {
        if (isset($group['name']) && $group['name'] === (string)$name) {
            return $group['id'];
        }
    }
    return null;
}

/**
 * Find or create a Pinata group by name (public network).
 *
 * @param string $name Group name.
 * @return array{success: bool, id?: string, created?: bool, error?: string}
 */
function pinata_ensure_group($name) {
    $id = pinata_group_find_by_name($name);
    if ($id !== null) {
        return ['success' => true, 'id' => $id, 'created' => false];
    }
    $res = pinata_group_create($name);
    if ($res['success']) {
        $res['created'] = true;
    }
    return $res;
}

/**
 * Add a file to a Pinata group on the public network.
 *
 * @param string $groupId The Pinata group id.
 * @param string $fileId  The Pinata file id (returned in the upload response as data.id).
 * @return array{success: bool, error?: string, status?: int}
 */
function pinata_group_add_file($groupId, $fileId) {
    $cfg = pinata_config();
    if (empty($cfg['jwt'])) {
        return ['success' => false, 'error' => 'PINATA_JWT is not configured'];
    }
    $url = 'https://api.pinata.cloud/v3/groups/public/' . rawurlencode($groupId) . '/ids/' . rawurlencode($fileId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $cfg['jwt']],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        return ['success' => false, 'error' => 'Pinata group add failed: ' . $err];
    }
    if ($status >= 200 && $status < 300) {
        return ['success' => true, 'status' => $status];
    }
    return ['success' => false, 'status' => $status, 'error' => 'Pinata group add failed (HTTP ' . $status . '): ' . substr((string)$body, 0, 500)];
}

/**
 * Resolve the Pinata group id for an archive/legislative folder, creating the
 * group (named "LAS/<folder name>") on first use and caching the id on the folder row.
 *
 * Best-effort: returns success=false on any failure so callers never block uploads.
 *
 * @param mysqli $conn       Database connection.
 * @param string $table      'archive_folders' or 'legislative_folders'.
 * @param int    $folderId   Folder row id.
 * @param string $folderName Folder display name (used to name the group).
 * @return array{success: bool, id?: string, error?: string, created?: bool}
 */
function pinata_ensure_folder_group($conn, $table, $folderId, $folderName) {
    static $cache = [];

    if (!in_array($table, ['archive_folders', 'legislative_folders'], true)) {
        return ['success' => false, 'error' => 'Invalid folder table'];
    }
    $folderId = (int)$folderId;
    $name = trim((string)$folderName);
    if ($folderId <= 0 || $name === '') {
        return ['success' => false, 'error' => 'Missing folder context'];
    }
    $cacheKey = $table . ':' . $folderId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Reuse an existing mapping stored on the folder row.
    $st = $conn->prepare("SELECT pinata_group_id FROM $table WHERE id = ?");
    $st->bind_param("i", $folderId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if ($row && !empty($row['pinata_group_id'])) {
        $cache[$cacheKey] = ['success' => true, 'id' => $row['pinata_group_id']];
        return $cache[$cacheKey];
    }

    $res = pinata_ensure_group('LAS/' . $name);
    if (!$res['success']) {
        $cache[$cacheKey] = $res;
        return $res;
    }

    $upd = $conn->prepare("UPDATE $table SET pinata_group_id = ? WHERE id = ?");
    $gid = $res['id'];
    $upd->bind_param("si", $gid, $folderId);
    $upd->execute();
    $upd->close();

    $cache[$cacheKey] = $res;
    return $res;
}

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
 *   pinata_upload_file($path, $name, $keyvalues)    : array{success, cid, ...}
 *   pinata_gateway_url($cid)                        : string
 *   pinata_redirect($cid, $filename = null)         : void (302 redirect)
 *   pinata_stream_cid($cid, $inline = true)         : void (streams via gateway)
 *   pinata_delete_cid($cid)                         : bool
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
 * @return array{success: bool, cid?: string, error?: string, status?: int}
 */
function pinata_upload_file($filePath, $name = null, array $keyvalues = []) {
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
        $post['keyvalues'] = json_encode(['keyvalues' => $keyvalues]);
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
        return [
            'success' => true,
            'cid'     => $data['data']['cid'],
            'data'    => $data['data'],
            'status'  => $status,
        ];
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

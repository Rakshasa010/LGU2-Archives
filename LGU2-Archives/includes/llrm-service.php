<?php
/**
 * LLRM Service Class
 * 
 * Wraps all LLRM API calls using cURL.
 * Handles: list, search, get, create, update, delete, download, stats, types,
 *          and push (receive_document) from LAS to LLRM.
 * 
 * Usage:
 *   $llrm = new LLRMService();
 *   $stats = $llrm->getStats();
 *   $docs = $llrm->listDocuments(['type' => 'ordinance', 'page' => 1]);
 *   $llrm->pushDocument('/path/to/file.pdf', 'Title', 'ordinance', ['external_id' => 'LAS-001']);
 */

class LLRMService {

    private $config;
    private $cache = [];

    public function __construct() {
        $this->config = require __DIR__ . '/llrm-config.php';
    }

    /**
     * Get config value
     */
    public function getConfig($key = null) {
        if ($key === null) return $this->config;
        return $this->config[$key] ?? null;
    }

    // ─── Core cURL wrapper ───────────────────────────────────────────

    /**
     * Make an API request to LLRM
     */
    private function request($url, $method = 'GET', $data = null, $isMultipart = false, $rawResponse = false) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->buildHeaders($isMultipart),
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($data !== null) {
            if ($isMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error'   => 'cURL error: ' . $error,
                'http_code' => 0,
            ];
        }

        if ($rawResponse) {
            return [
                'success'  => $httpCode >= 200 && $httpCode < 300,
                'data'     => $response,
                'http_code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'success'   => false,
                'error'     => 'Invalid JSON response from LLRM',
                'raw'       => substr($response, 0, 500),
                'http_code' => $httpCode,
            ];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    /**
     * Build HTTP headers for API requests
     */
    private function buildHeaders($isMultipart = false) {
        $headers = [
            'X-API-Key: ' . $this->config['api_key'],
        ];
        if (!$isMultipart) {
            $headers[] = 'Content-Type: application/json';
        }
        return $headers;
    }

    /**
     * Build query string from params
     */
    private function buildQuery($params) {
        return http_build_query(array_filter($params, function($v) {
            return $v !== '' && $v !== null;
        }));
    }

    // ─── Cache helpers ────────────────────────────────────────────────

    private function getCache($key) {
        if ($this->config['cache_ttl'] <= 0) return null;

        $cacheFile = $this->config['cache_dir'] . '/' . md5($key) . '.json';
        if (!file_exists($cacheFile)) return null;

        $age = time() - filemtime($cacheFile);
        if ($age > $this->config['cache_ttl']) return null;

        $data = file_get_contents($cacheFile);
        return $data ? json_decode($data, true) : null;
    }

    private function setCache($key, $data) {
        if ($this->config['cache_ttl'] <= 0) return;

        $dir = $this->config['cache_dir'];
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/' . md5($key) . '.json', json_encode($data));
    }

    // ─── Archive API: Read endpoints ──────────────────────────────────

    /**
     * API Health Check
     */
    public function healthCheck() {
        $url = $this->config['archive_api_url'];
        return $this->request($url);
    }

    /**
     * List documents (paginated + filterable)
     */
    public function listDocuments($params = []) {
        $defaults = [
            'page'    => 1,
            'per_page' => 20,
        ];
        $params = array_merge($defaults, $params);
        $url = $this->config['archive_api_url'] . '?action=list&' . $this->buildQuery($params);
        return $this->request($url);
    }

    /**
     * Search documents
     */
    public function searchDocuments($query, $params = []) {
        $params['q'] = $query;
        $defaults = ['page' => 1, 'per_page' => 20];
        $params = array_merge($defaults, $params);
        $url = $this->config['archive_api_url'] . '?action=search&' . $this->buildQuery($params);
        return $this->request($url);
    }

    /**
     * Get single document with versions, related docs, activity
     */
    public function getDocument($id) {
        $url = $this->config['archive_api_url'] . '?action=get&id=' . (int)$id;
        return $this->request($url);
    }

    /**
     * Download document file (returns raw binary data)
     */
    public function downloadDocument($id) {
        $url = $this->config['archive_api_url'] . '?action=download&id=' . (int)$id;
        return $this->request($url, 'GET', null, false, true);
    }

    /**
     * Get archive statistics (cached)
     */
    public function getStats() {
        $cacheKey = 'llrm_stats';
        $cached = $this->getCache($cacheKey);
        if ($cached) return $cached;

        $url = $this->config['archive_api_url'] . '?action=stats';
        $result = $this->request($url);

        if (isset($result['success']) && $result['success']) {
            $this->setCache($cacheKey, $result);
        }
        return $result;
    }

    /**
     * Get document types and statuses (cached)
     */
    public function getTypes() {
        $cacheKey = 'llrm_types';
        $cached = $this->getCache($cacheKey);
        if ($cached) return $cached;

        $url = $this->config['archive_api_url'] . '?action=types';
        $result = $this->request($url);

        if (isset($result['success']) && $result['success']) {
            $this->setCache($cacheKey, $result);
        }
        return $result;
    }

    // ─── Archive API: Write endpoints ─────────────────────────────────

    /**
     * Create / Upload document to LLRM
     * 
     * @param string $filePath Absolute path to file
     * @param string $title Document title
     * @param string $documentType ordinance, resolution, session, etc.
     * @param array $options document_date, status, description, tags, reference_number
     */
    public function createDocument($filePath, $title, $documentType, $options = []) {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found: ' . $filePath];
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->config['max_file_size']) {
            return ['success' => false, 'error' => 'File exceeds 50MB limit'];
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config['allowed_file_types'])) {
            return ['success' => false, 'error' => 'File type not allowed: ' . $ext];
        }

        $postData = [
            'file' => new CURLFile($filePath),
            'title' => $title,
            'document_type' => $documentType,
        ];

        // Optional fields
        $optional = ['document_date', 'status', 'description', 'tags', 'reference_number'];
        foreach ($optional as $field) {
            if (isset($options[$field])) {
                $postData[$field] = $options[$field];
            }
        }

        $url = $this->config['archive_api_url'] . '?action=create';
        return $this->request($url, 'POST', $postData, true);
    }

    /**
     * Update document metadata
     */
    public function updateDocument($id, $data) {
        $url = $this->config['archive_api_url'] . '?action=update&id=' . (int)$id;
        return $this->request($url, 'PUT', $data);
    }

    /**
     * Delete document (soft delete)
     */
    public function deleteDocument($id) {
        $url = $this->config['archive_api_url'] . '?action=delete&id=' . (int)$id;
        return $this->request($url, 'DELETE');
    }

    // ─── Push to LLRM (receive_document.php) ──────────────────────────

    /**
     * Push a document from LAS to LLRM
     * 
     * @param string $filePath Absolute path to the file
     * @param string $title Document title
     * @param string $documentType ordinance, resolution, session, etc.
     * @param array $options external_id, document_date, description, tags
     */
    public function pushDocument($filePath, $title, $documentType, $options = []) {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found: ' . $filePath];
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->config['max_file_size']) {
            return ['success' => false, 'error' => 'File exceeds 50MB limit'];
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config['allowed_file_types'])) {
            return ['success' => false, 'error' => 'File type not allowed: ' . $ext];
        }

        $postData = [
            'file'          => new CURLFile($filePath),
            'title'         => $title,
            'document_type' => $documentType,
            'source_system' => $this->config['source_system'],
        ];

        // Optional fields
        if (isset($options['external_id'])) {
            $postData['external_id'] = $options['external_id'];
        }
        if (isset($options['document_date'])) {
            $postData['document_date'] = $options['document_date'];
        }
        if (isset($options['description'])) {
            $postData['description'] = $options['description'];
        }
        if (isset($options['tags'])) {
            $postData['tags'] = $options['tags'];
        }

        return $this->request($this->config['receive_url'], 'POST', $postData, true);
    }

    /**
     * Push a legislative record from LAS database to LLRM
     * 
     * @param mysqli $conn Database connection
     * @param int $recordId legislative_records.id
     */
    public function pushRecordById($conn, $recordId) {
        $stmt = $conn->prepare("SELECT id, title, type, month, year, author, file_path, created_at FROM legislative_records WHERE id = ?");
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $res = $stmt->get_result();
        $record = $res->fetch_assoc();
        $stmt->close();

        if (!$record) {
            return ['success' => false, 'error' => 'Record not found: ' . $recordId];
        }

        if (empty($record['file_path'])) {
            return ['success' => false, 'error' => 'Record has no file to push'];
        }

        $filePath = __DIR__ . '/../' . $record['file_path'];
        if (!file_exists($filePath)) {
            $filePath = $record['file_path'];
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File not found on disk: ' . $record['file_path']];
            }
        }

        // Map LAS type to LLRM document_type
        $typeMap = [
            'Ordinance'      => 'ordinance',
            'Resolution'     => 'resolution',
            'Meeting'        => 'session',
            'Public Hearing' => 'hearing',
        ];
        $docType = $typeMap[$record['type']] ?? 'archive';

        // Build date from month/year
        $monthNum = date_parse($record['month'])['month'] ?? 1;
        $documentDate = $record['year'] . '-' . str_pad($monthNum, 2, '0', STR_PAD_LEFT) . '-01';

        $options = [
            'external_id'  => 'LAS-' . $record['id'],
            'document_date' => $documentDate,
            'description'   => 'Archived from LAS by ' . $record['author'],
            'tags'          => strtolower($record['type']) . ',' . strtolower($record['author']),
        ];

        return $this->pushDocument($filePath, $record['title'], $docType, $options);
    }

    /**
     * Push an archive file from LAS archive_files table to LLRM
     * 
     * @param mysqli $conn Database connection
     * @param int $fileId archive_files.id
     */
    public function pushArchiveFileById($conn, $fileId) {
        $stmt = $conn->prepare("SELECT f.id, f.name, f.file_path, f.author, f.file_date, f.created_at, fo.name as folder_name FROM archive_files f JOIN archive_folders fo ON f.folder_id = fo.id WHERE f.id = ?");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $res = $stmt->get_result();
        $file = $res->fetch_assoc();
        $stmt->close();

        if (!$file) {
            return ['success' => false, 'error' => 'Archive file not found: ' . $fileId];
        }

        if (empty($file['file_path'])) {
            return ['success' => false, 'error' => 'File has no file_path'];
        }

        $filePath = __DIR__ . '/../' . $file['file_path'];
        if (!file_exists($filePath)) {
            $filePath = $file['file_path'];
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File not found on disk: ' . $file['file_path']];
            }
        }

        $documentDate = !empty($file['file_date']) ? $file['file_date'] : date('Y-m-d', strtotime($file['created_at']));

        $options = [
            'external_id'  => 'LAS-ARCH-' . $file['id'],
            'document_date' => $documentDate,
            'description'   => 'Archive file from LAS folder: ' . ($file['folder_name'] ?? 'Unknown'),
            'tags'          => 'archive,' . strtolower($file['folder_name'] ?? ''),
        ];

        return $this->pushDocument($filePath, $file['name'], 'archive', $options);
    }

    // ─── Sync helpers ─────────────────────────────────────────────────

    /**
     * Map an LLRM document type to a canonical LAS legislative type + main-storage folder.
     * Unknown types fall back to an "Other / External Documents" folder so intake/pull
     * always route to a valid main-storage folder.
     *
     * @return array ['type' => string, 'folder_name' => string]
     */
    public function mapToMainStorageType($llrmType) {
        $map = [
            'ordinance'              => ['type' => 'Ordinance',      'folder_name' => 'Ordinances & Resolutions'],
            'resolution'             => ['type' => 'Resolution',     'folder_name' => 'Ordinances & Resolutions'],
            'motion'                 => ['type' => 'Resolution',     'folder_name' => 'Ordinances & Resolutions'],
            'hearing'                => ['type' => 'Public Hearing', 'folder_name' => 'Public Hearings'],
            'public hearing'         => ['type' => 'Public Hearing', 'folder_name' => 'Public Hearings'],
            'session'                => ['type' => 'Meeting',        'folder_name' => 'Meeting Records'],
            'meeting'                => ['type' => 'Meeting',        'folder_name' => 'Meeting Records'],
            'minutes'                => ['type' => 'Meeting',        'folder_name' => 'Meeting Records'],
            'minutes of the meeting' => ['type' => 'Meeting',        'folder_name' => 'Meeting Records'],
        ];

        $key = strtolower(trim((string)$llrmType));
        $key = str_replace('_', ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);

        if (isset($map[$key])) {
            return $map[$key];
        }

        return ['type' => 'Other', 'folder_name' => 'Other / External Documents'];
    }

    /**
     * Find or create a top-level legislative folder by canonical type.
     *
     * @param mysqli $conn Database connection
     * @param string $type Canonical legislative folder type
     * @param string $name Folder display name
     * @return int folder id
     */
    public function ensureLegislativeFolder($conn, $type, $name) {
        $stmt = $conn->prepare("SELECT id FROM legislative_folders WHERE type = ? AND parent_id IS NULL LIMIT 1");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            return (int)$row['id'];
        }

        $prefix = null;
        if (function_exists('generate_document_prefix')) {
            $customPrefixes = [
                'Ordinances & Resolutions' => 'Ordinance-Resolution',
                'Meeting Records'          => 'Meeting-Records',
            ];
            $prefix = isset($customPrefixes[$name]) ? $customPrefixes[$name] : generate_document_prefix($name);
        }

        $insert = $conn->prepare("INSERT INTO legislative_folders (name, type, parent_id, document_prefix) VALUES (?, ?, NULL, ?)");
        $insert->bind_param("sss", $name, $type, $prefix);
        $insert->execute();
        $id = (int)$conn->insert_id;
        $insert->close();

        return $id;
    }

    /**
     * Pull documents from LLRM and optionally save to LAS main storage.
     * 
     * @param array $params List parameters (type, status, page, per_page, etc.)
     * @param bool $saveToDb If true, insert pulled documents into legislative_records
     * @param mysqli $conn Database connection (required if $saveToDb is true)
     */
    public function pullDocuments($params = [], $saveToDb = false, $conn = null) {
        $result = $this->listDocuments($params);

        if (!isset($result['success']) || !$result['success']) {
            return $result;
        }

        if ($saveToDb && $conn) {
            $summary = ['saved_count' => 0, 'skipped_count' => 0, 'error_count' => 0, 'errors' => []];
            foreach ($result['documents'] ?? [] as $doc) {
                $out = $this->savePulledDocument($conn, $doc);
                if ($out['status'] === 'saved') {
                    $summary['saved_count']++;
                } elseif ($out['status'] === 'skipped') {
                    $summary['skipped_count']++;
                } else {
                    $summary['error_count']++;
                    $summary['errors'][] = ['title' => $doc['title'] ?? '', 'error' => $out['error'] ?? 'Unknown error'];
                }
            }
            $result['save_summary'] = $summary;
        }

        return $result;
    }

    /**
     * Save a pulled LLRM document into LAS main storage (legislative_records).
     * Routes to the correct main-storage folder by document type, downloads the
     * actual file locally, and skips records that already exist by title + type.
     *
     * @return array ['status' => 'saved'|'skipped'|'error', 'id' => int|null, 'error' => string|null]
     */
    private function savePulledDocument($conn, $doc) {
        $routing = $this->mapToMainStorageType($doc['document_type'] ?? '');
        $type = $routing['type'];
        $folderName = $routing['folder_name'];

        $folderId = $this->ensureLegislativeFolder($conn, $type, $folderName);

        $title = trim((string)($doc['title'] ?? ''));
        if ($title === '') {
            return ['status' => 'error', 'error' => 'Document has no title'];
        }

        // Skip duplicates by title + canonical type
        $check = $conn->prepare("SELECT id FROM legislative_records WHERE title = ? AND type = ? LIMIT 1");
        $check->bind_param("ss", $title, $type);
        $check->execute();
        $res = $check->get_result();
        if ($res->fetch_assoc()) {
            $check->close();
            return ['status' => 'skipped'];
        }
        $check->close();

        // Build date fields
        $dateRaw = $doc['document_date'] ?? null;
        $month = $dateRaw ? date('F', strtotime($dateRaw)) : date('F');
        $year = $dateRaw ? date('Y', strtotime($dateRaw)) : date('Y');
        $author = $doc['uploaded_by_name'] ?? 'LLRM Import';

        // Download the file from LLRM and store it locally under the folder
        $filePath = null;
        $fileSize = null;
        $mimeType = null;
        $llrmId = (int)($doc['id'] ?? 0);
        if ($llrmId > 0) {
            $dl = $this->downloadDocument($llrmId);
            if ($dl['success'] && !empty($dl['data'])) {
                $ext = strtolower(pathinfo((string)($doc['file_name'] ?? ''), PATHINFO_EXTENSION));
                if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
                    $ext = 'bin';
                }

                $targetDir = __DIR__ . '/../uploads/legislative/' . $folderId . '/' . $year . '/';
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0777, true);
                }

                $safeName = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', (string)($doc['file_name'] ?? ('llrm_' . $llrmId . '.' . $ext)));
                if ($safeName === '' || $safeName === null) {
                    $safeName = 'llrm_' . $llrmId . '.' . $ext;
                }
                $filename = 'v1_' . $safeName;
                if (preg_match('/\.' . preg_quote($ext, '/') . '$/i', $filename)) {
                    $filename = 'v1_' . $safeName;
                } else {
                    $filename = 'v1_' . $safeName . '.' . $ext;
                }
                $targetPath = $targetDir . $filename;
                $counter = 1;
                while (file_exists($targetPath)) {
                    $filename = 'v1_' . $counter . '_' . $safeName;
                    $targetPath = $targetDir . $filename;
                    $counter++;
                }

                if (file_put_contents($targetPath, $dl['data']) !== false) {
                    $filePath = 'uploads/legislative/' . $folderId . '/' . $year . '/' . $filename;
                    $fileSize = filesize($targetPath);
                    $mimeType = function_exists('mime_content_type') ? mime_content_type($targetPath) : null;
                    if (!$mimeType) {
                        $mimeType = 'application/octet-stream';
                    }
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO legislative_records (title, type, month, year, author, file_path, folder_id, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssiss", $title, $type, $month, $year, $author, $filePath, $folderId, $fileSize, $mimeType);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $newId = (int)$conn->insert_id;
            $stmt->close();
            return ['status' => 'saved', 'id' => $newId];
        }
        $stmt->close();
        return ['status' => 'error', 'error' => 'Database insert failed: ' . $conn->error];
    }

    /**
     * Batch push all LAS records to LLRM
     * 
     * @param mysqli $conn Database connection
     * @param int $batchSize Number of records to process
     * @return array Results with success/failure counts
     */
    public function batchPushToLLRM($conn, $batchSize = 50) {
        $results = ['success_count' => 0, 'error_count' => 0, 'errors' => [], 'pushed' => []];

        $stmt = $conn->prepare("SELECT id FROM legislative_records WHERE file_path IS NOT NULL AND file_path != '' AND parent_version_id IS NULL ORDER BY id ASC LIMIT ?");
        $limit = (int)$batchSize;
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        $stmt->close();

        foreach ($ids as $id) {
            $result = $this->pushRecordById($conn, $id);
            if (isset($result['success']) && $result['success']) {
                $results['success_count']++;
                $results['pushed'][] = ['las_id' => $id, 'llrm_response' => $result];
            } else {
                $results['error_count']++;
                $results['errors'][] = ['las_id' => $id, 'error' => $result['error'] ?? 'Unknown error'];
            }
        }

        return $results;
    }

    /**
     * Clear cached data
     */
    public function clearCache() {
        $dir = $this->config['cache_dir'];
        if (!is_dir($dir)) return;
        $files = glob($dir . '/*.json');
        foreach ($files as $f) {
            @unlink($f);
        }
    }
}

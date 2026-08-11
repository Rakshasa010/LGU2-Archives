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
            'Billing'        => 'ordinance',
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
     * Pull documents from LLRM and optionally save to LAS database
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
            foreach ($result['documents'] ?? [] as $doc) {
                $this->savePulledDocument($conn, $doc);
            }
        }

        return $result;
    }

    /**
     * Save a pulled LLRM document into the External Documents queue (pending).
     * Documents are staged here and must be manually routed by a user.
     */
    private function savePulledDocument($conn, $doc) {
        $title = trim($doc['title'] ?? '');
        if ($title === '') return;

        require_once __DIR__ . '/llrm-intake.php';

        // Try to download the actual file so the routed record has a local copy.
        $fileSpec = null;
        $docId = (int)($doc['id'] ?? 0);
        if ($docId > 0) {
            $dl = $this->downloadDocument($docId);
            if (!empty($dl['success']) && !empty($dl['data'])) {
                $tmp = @tempnam(sys_get_temp_dir(), 'llrm_');
                if ($tmp !== false) {
                    $bytes = @file_put_contents($tmp, $dl['data']);
                    if ($bytes !== false && $bytes > 0) {
                        $origName = $doc['file_name'] ?? ($title . '.pdf');
                        $fileSpec = ['tmp_path' => $tmp, 'orig_name' => $origName, 'copy' => false];
                    } else {
                        @unlink($tmp);
                    }
                }
            }
        }

        $routeDoc = [
            'title'            => $title,
            'type'             => $doc['document_type'] ?? 'archive',
            'author'           => $doc['uploaded_by_name'] ?? 'LLRM Import',
            'document_date'    => $doc['document_date'] ?? null,
            'source_system'    => 'LLRM',
            'source_record_id' => $docId ?: null,
            'reference_number' => $doc['reference_number'] ?? null,
            'external_id'      => $docId ? 'LLRM-' . $docId : null,
            'description'      => $doc['description'] ?? null,
            'tags'             => $doc['tags'] ?? null,
        ];

        // Stage into the External Documents queue (manual routing is required).
        $result = llrm_intake_stage($conn, $routeDoc, $fileSpec, ['notification_prefix' => 'LLRM Pull']);

        // Clean up the temp download if the router left a copy behind.
        if ($fileSpec && !empty($fileSpec['tmp_path']) && is_file($fileSpec['tmp_path'])) {
            @unlink($fileSpec['tmp_path']);
        }

        // Duplicates are expected when re-running a pull; fail silently for those.
        if (!empty($result['error']) && empty($result['duplicate'])) {
            error_log('LLRM pull save failed for "' . $title . '": ' . $result['error']);
        }
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

<?php
/**
 * MongoDB Atlas Data API integration helper.
 * PHP constants for SSL verification may not be defined in all environments.
 * We define them here if missing to ensure cURL works correctly.
 */
if (!defined('CURLOPT_SSL_VERIFYPEER')) {
    define('CURLOPT_SSL_VERIFYPEER', 1);
}
if (!defined('CURLOPT_SSL_VERIFYHOST')) {
    define('CURLOPT_SSL_VERIFYHOST', 2);
}

/**
 * MongoDB Atlas Data API integration helper.
 *
 * Connects to MongoDB Atlas via the REST Data API using cURL.
 * Configuration is read from the project .env file.
 *
 * Supported operations:
 *   - insertOne: Insert a single document into a collection
 *   - findOne:   Find a single document matching a filter
 *   - find:      Find multiple documents matching a filter
 *
 * @method insertOne($collection, $document) - Insert document, returns new _id
 * @method findOne($collection, $filter)   - Find single document, returns doc or null
 */
class MongoDBAtlas {

	private $baseUrl;
	private $dataApiKey;
	private $connectionString;
	private $dbName;
	private $collectionName;
	private $httpHeaders = [];

	public function __construct() {
		$this->loadConfig();
	}

	public function getDataApiKey() {
		return $this->dataApiKey;
	}

	public function getBaseUrl() {
		return $this->baseUrl;
	}

	public function getDbName() {
		return $this->dbName;
	}

	public function getCollectionName() {
		return $this->collectionName;
	}

	private function loadConfig() {
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

		$this->connectionString = $env['MONGO_CONNECTION_STRING'] ?? '';
		$this->dbName           = $env['MONGO_DB_NAME'] ?? '';
		$this->collectionName   = $env['MONGO_COLLECTION'] ?? 'document_metadata';
		$this->dataApiKey       = $env['MONGO_DATA_API_KEY'] ?? '';

		// Parse connection string for cluster host
		// Format: mongodb+srv://username:password@cluster0.example.mongodb.net
		if (!empty($this->connectionString)) {
			preg_match('#mongodb\+srv://[^@]+@([^/]+)#', $this->connectionString, $matches);
			if (!empty($matches[1])) {
				$this->baseUrl = 'https://' . $matches[1];
			} else {
				$this->baseUrl = 'https://data.mongodb-api.com/action';
			}
		} else {
			$this->baseUrl = 'https://data.mongodb-api.com/action';
		}
	}

	/**
	 * Set custom HTTP headers (e.g., Data API key).
	 */
	public function setHttpHeader($name, $value) {
		$this->httpHeaders[$name] = $value;
	}

	/**
	 * Perform a cURL request to MongoDB Atlas.
	 *
	 * @param string $method  HTTP method (GET, POST)
	 * @param string $endpoint API endpoint path (relative to base URL)
	 * @param mixed    $body   JSON body or null
	 * @return array{success: bool, data: mixed, error?: string}
	 */
	private function curlRequest($method, $endpoint, $body = null) {
		$url = $this->baseUrl . '/' . ltrim($endpoint, '/');

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_HTTPHEADER     => $this->httpHeaders,
			// Allow self-signed certs for dev environments
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($this->httpHeaders, [
				'Content-Type: application/json',
			]));
		}

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($curlError) {
			return [
				'success' => false,
				'error' => 'cURL error: ' . $curlError,
			];
		}

		if ($httpCode >= 500) {
			return [
				'success' => false,
				'error' => 'Atlas HTTP ' . $httpCode . ': ' . $response,
			];
		}

		$data = json_decode($response, true);
		if ($data === null && $response !== '') {
			return [
				'success' => false,
				'error' => 'Invalid JSON response: ' . $response,
			];
		}

		return [
			'success' => $httpCode >= 200 && $httpCode < 300,
			'data'    => $data ?? $response,
			'httpCode' => $httpCode,
		];
	}

	/**
	 * Insert a single document into the configured collection.
	 * The document should not include _id (Atlas will generate it).
	 *
	 * @param array $document Document data to insert
	 * @return array{success: bool, new_id?: string, error?: string}
	 */
	public function insertOne(array $document) {
		$endpoint = $this->dbName . '/' . $this->collectionName . '/insertOne';

		$result = $this->curlRequest('POST', $endpoint, $document);

		if (!$result['success']) {
			return $result;
		}

		$data = $result['data'] ?? [];

		// Common response patterns from Atlas insertOne:
		// { "insertedId": "..." }
		// { "id": "..." }
		// { "result": { "n": 1, "ok": 1 } }
		$newId = null;
		if (isset($data['insertedId'])) {
			$newId = (string)$data['insertedId'];
		} elseif (isset($data['id'])) {
			$newId = (string)$data['id'];
		} elseif (isset($data['_id'])) {
			$newId = (string)$data['_id'];
		}

		return [
			'success' => $result['success'],
			'new_id'  => $newId,
			'error'   => $result['error'] ?? null,
			'raw'     => $data,
		];
	}

	/**
	 * Find a single document matching the filter.
	 * Returns the document or null if not found.
	 * The filter typically uses mysql_id or mongo_id fields.
	 *
	 * @param array $filter Filter criteria (e.g., ['mysql_id' => 101])
	 * @return array{success: bool, document?: array, error?: string}
	 */
	public function findOne(array $filter = []) {
		$endpoint = $this->dbName . '/' . $this->collectionName . '/findOne';

		$result = $this->curlRequest('POST', $endpoint, $filter);

		if (!$result['success']) {
			return $result;
		}

		$data = $result['data'] ?? [];

		// Atlas findOne may return the document directly or nested
		$doc = $data ?? null;

		return [
			'success' => $result['success'],
			'document' => $doc,
			'error'   => $result['error'] ?? null,
		];
	}

	/**
	 * Find multiple documents matching the filter.
	 *
	 * @param array $filter Filter criteria
	 * @return array{success: bool, documents?: array, error?: string}
	 */
	public function find(array $filter = []) {
		$endpoint = $this->dbName . '/' . $this->collectionName . '/find';

		$result = $this->curlRequest('POST', $endpoint, $filter);

		if (!$result['success']) {
			return $result;
		}

		$data = $result['data'] ?? [];

		// Atlas find may return { documents: [...] } or just [...]
		$docs = $data['documents'] ?? ($data ?? []);

		return [
			'success' => $result['success'],
			'documents' => $docs,
			'error'   => $result['error'] ?? null,
		];
	}
}
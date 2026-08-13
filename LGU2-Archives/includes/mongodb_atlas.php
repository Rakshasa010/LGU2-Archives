<?php
/**
 * MongoDB Atlas integration helper using the native MongoDB PHP driver.
 *
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

	private $connectionString;
	private $dbName;
	private $collectionName;
	private $dataApiKey;
	private $manager;

	public function __construct() {
		$this->loadConfig();
	}

	public function getDataApiKey() {
		return $this->dataApiKey;
	}

	public function getBaseUrl() {
		return $this->connectionString;
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

		if (!class_exists('MongoDB\Driver\Manager')) {
			throw new RuntimeException('MongoDB PHP extension (ext-mongodb) is not installed or enabled.');
		}
	}

	/**
	 * Lazy-get the MongoDB driver Manager instance.
	 */
	private function getManager() {
		if ($this->manager === null) {
			$this->manager = new MongoDB\Driver\Manager($this->connectionString, [
				'serverSelectionTimeoutMS' => 15000,
			]);
		}
		return $this->manager;
	}

	/**
	 * Convert a MongoDB document into a plain associative array.
	 * Handles BSON ObjectId, UTCDateTime and nested values.
	 */
	private function bsonToArray($doc) {
		if ($doc instanceof stdClass) {
			$doc = get_object_vars($doc);
		}
		if (!is_array($doc)) {
			return $doc;
		}
		$out = [];
		foreach ($doc as $k => $v) {
			if ($v instanceof MongoDB\BSON\ObjectId) {
				$out[$k] = (string)$v;
			} elseif ($v instanceof MongoDB\BSON\UTCDateTime) {
				$out[$k] = $v->toDateTime()->format('c');
			} elseif ($v instanceof stdClass) {
				$out[$k] = $this->bsonToArray($v);
			} elseif (is_array($v)) {
				$out[$k] = $this->bsonToArray($v);
			} else {
				$out[$k] = $v;
			}
		}
		return $out;
	}

	/**
	 * Insert a single document into the configured collection.
	 * The document should not include _id (Atlas will generate it).
	 *
	 * @param array $document Document data to insert
	 * @return array{success: bool, new_id?: string, error?: string}
	 */
	public function insertOne(array $document) {
		try {
			$bulk = new MongoDB\Driver\BulkWrite();
			$insertedId = $bulk->insert($document);
			$this->getManager()->executeBulkWrite($this->dbName . '.' . $this->collectionName, $bulk);

			return [
				'success' => true,
				'new_id'  => (string)$insertedId,
				'error'   => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
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
		try {
			$query = new MongoDB\Driver\Query($filter, ['limit' => 1]);
			$cursor = $this->getManager()->executeQuery($this->dbName . '.' . $this->collectionName, $query);
			$doc = null;
			foreach ($cursor as $d) {
				$doc = $d;
				break;
			}

			return [
				'success'  => true,
				'document' => $doc ? $this->bsonToArray($doc) : null,
				'error'    => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'document' => null,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Find multiple documents matching the filter.
	 *
	 * @param array $filter Filter criteria
	 * @return array{success: bool, documents?: array, error?: string}
	 */
	public function find(array $filter = []) {
		try {
			$query = new MongoDB\Driver\Query($filter);
			$cursor = $this->getManager()->executeQuery($this->dbName . '.' . $this->collectionName, $query);

			$docs = [];
			foreach ($cursor as $doc) {
				$docs[] = $this->bsonToArray($doc);
			}

			return [
				'success'  => true,
				'documents' => $docs,
				'error'    => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'documents' => [],
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Delete documents matching the filter.
	 *
	 * @param array $filter Filter criteria (e.g., ['mysql_id' => 101])
	 * @return array{success: bool, deleted_count?: int, error?: string}
	 */
	public function deleteMany(array $filter = []) {
		try {
			$bulk = new MongoDB\Driver\BulkWrite();
			$bulk->delete($filter);
			$result = $this->getManager()->executeBulkWrite($this->dbName . '.' . $this->collectionName, $bulk);

			return [
				'success'       => true,
				'deleted_count' => $result->getDeletedCount(),
				'error'         => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'deleted_count' => 0,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Sanitize a folder name into a valid MongoDB database name.
	 * MongoDB db names may not contain /\. "$*<>:|? and are capped at 64 bytes.
	 */
	public static function sanitizeDbName($folderName) {
		$name = strtolower(trim((string)$folderName));
		// Replace any character that is not a-z0-9 with a single dash
		$name = preg_replace('/[^a-z0-9]+/', '-', $name);
		$name = trim($name, '-');
		// MongoDB db names must not start with a digit (avoid confusion) — prefix if needed
		if ($name === '') {
			$name = 'folder';
		} elseif (preg_match('/^[0-9]/', $name)) {
			$name = 'folder-' . $name;
		}
		return substr($name, 0, 60);
	}

	/**
	 * Registry collection lives inside the default database and maps
	 * MySQL folder_id -> MongoDB database name.
	 */
	private function registryNamespace() {
		return $this->dbName . '.folder_registry';
	}

	/**
	 * Ensure a MongoDB database exists for a system folder.
	 * If one is already registered for this folder_id it is reused.
	 * A marker document is inserted so the database physically appears in Atlas.
	 *
	 * @param int    $folderId   MySQL folder id (archive_folders / legislative_folders)
	 * @param string $folderKind 'archive' or 'legislative'
	 * @param string $folderName Folder display name
	 * @return array{success: bool, db_name?: string, error?: string}
	 */
	public function ensureFolderDatabase($folderId, $folderKind, $folderName) {
		try {
			$folderId = (int)$folderId;
			$folderKind = $folderKind === 'legislative' ? 'legislative' : 'archive';
			$baseName = self::sanitizeDbName($folderName);

			// Already registered for this folder?
			$existing = $this->findOneInDb($this->dbName, 'folder_registry', ['folder_id' => $folderId]);
			if ($existing['success'] && $existing['document']) {
				return ['success' => true, 'db_name' => $existing['document']['db_name']];
			}

			// If the sanitized name is taken by a different folder, append the id for uniqueness
			$candidate = $baseName;
			$taken = $this->findOneInDb($this->dbName, 'folder_registry', ['db_name' => $candidate]);
			if ($taken['success'] && $taken['document'] && (int)$taken['document']['folder_id'] !== $folderId) {
				$candidate = $baseName . '-' . $folderId;
				$candidate = substr($candidate, 0, 60);
			}

			// Register the mapping
			$regDoc = [
				'folder_id'    => $folderId,
				'folder_kind'  => $folderKind,
				'folder_name'  => (string)$folderName,
				'db_name'      => $candidate,
				'collection'   => 'files',
				'created_at'   => date('c'),
			];
			$this->insertOneInDb($this->dbName, 'folder_registry', $regDoc);

			// Insert a marker doc so the folder's database physically shows up in Atlas
			$this->insertOneInDb($candidate, 'files', [
				'_folder_marker' => true,
				'folder_id'      => $folderId,
				'folder_kind'    => $folderKind,
				'folder_name'    => (string)$folderName,
				'created_at'     => date('c'),
			]);

			return ['success' => true, 'db_name' => $candidate];
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Look up the MongoDB database name registered for a folder, if any.
	 *
	 * @param int $folderId MySQL folder id
	 * @return array{success: bool, db_name?: string, document?: array, error?: string}
	 */
	public function getFolderDatabase($folderId) {
		try {
			$existing = $this->findOneInDb($this->dbName, 'folder_registry', ['folder_id' => (int)$folderId]);
			if ($existing['success'] && $existing['document']) {
				return ['success' => true, 'db_name' => $existing['document']['db_name'], 'document' => $existing['document']];
			}
			return ['success' => true, 'db_name' => null, 'document' => null];
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Drop a folder's database (used when a system folder is deleted).
	 *
	 * @param string $dbName MongoDB database name to drop
	 * @return array{success: bool, error?: string}
	 */
	public function dropDatabase($dbName) {
		try {
			$cmd = new MongoDB\Driver\Command(['dropDatabase' => 1]);
			$this->getManager()->executeCommand($dbName, $cmd);
			return ['success' => true];
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Insert a document into an arbitrary database/collection.
	 */
	public function insertOneInDb($dbName, $collection, array $document) {
		try {
			$bulk = new MongoDB\Driver\BulkWrite();
			$insertedId = $bulk->insert($document);
			$this->getManager()->executeBulkWrite($dbName . '.' . $collection, $bulk);
			return [
				'success' => true,
				'new_id'  => (string)$insertedId,
				'error'   => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Find a single document in an arbitrary database/collection.
	 */
	public function findOneInDb($dbName, $collection, array $filter = []) {
		try {
			$query = new MongoDB\Driver\Query($filter, ['limit' => 1]);
			$cursor = $this->getManager()->executeQuery($dbName . '.' . $collection, $query);
			$doc = null;
			foreach ($cursor as $d) {
				$doc = $d;
				break;
			}
			return [
				'success'  => true,
				'document' => $doc ? $this->bsonToArray($doc) : null,
				'error'    => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'document' => null,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Delete documents from an arbitrary database/collection.
	 */
	public function deleteManyInDb($dbName, $collection, array $filter = []) {
		try {
			$bulk = new MongoDB\Driver\BulkWrite();
			$bulk->delete($filter);
			$result = $this->getManager()->executeBulkWrite($dbName . '.' . $collection, $bulk);
			return [
				'success'       => true,
				'deleted_count' => $result->getDeletedCount(),
				'error'         => null,
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'deleted_count' => 0,
				'error'   => $e->getMessage(),
			];
		}
	}
}

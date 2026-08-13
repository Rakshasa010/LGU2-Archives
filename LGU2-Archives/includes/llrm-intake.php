<?php
/**
 * LLRM Intake Router
 *
 * Auto-registers an LLRM document into the LAS archive (Main Storage) with
 * automatic routing to the correct folder based on document type.
 *
 * Routing rules (LLRM document_type / LAS record type):
 *   ordinance, resolution, billing      -> legislative_folders (type "Ordinance")      -> "Ordinances & Resolutions"
 *   hearing / public hearing            -> legislative_folders (type "Public Hearing") -> "Public Hearings"
 *   meeting / session / minutes         -> legislative_folders (type "Meeting")        -> "Meeting Records"
 *   anything else                       -> archive_folders "Imported" + archive_files
 *
 * Shared by:
 *   - api/intake-from-llrm.php                    (external receive endpoint)
 *   - archive.php (action=create)                 (LLRM push / receive_document endpoint)
 *   - includes/llrm-service.php::savePulledDocument (pull-and-save flow)
 *
 * Depends on: $conn (mysqli), generate_document_prefix() (authdatabase.php),
 *             pinata_*() helpers (includes/pinata.php).
 */

require_once __DIR__ . '/pinata.php';
require_once __DIR__ . '/mongodb_atlas.php';

if (!function_exists('generate_document_prefix')) {
    // Minimal fallback so this file is safe standalone; authdatabase.php defines the real one.
    function generate_document_prefix($folder_name) {
        $clean = preg_replace('/[^\w\s-]/', '', $folder_name);
        $clean = preg_replace('/[\s-]+/', '-', $clean);
        $clean = trim($clean, '-');
        return $clean ?: 'Documents';
    }
}

if (!function_exists('llrm_intake_normalize_type')) {

    /**
     * Map an LLRM document type to a canonical LAS folder target.
     *
     * @param string $documentType Raw type from LLRM (ordinance, resolution,
     *                             session, hearing, Public Hearing, ...).
     * @return array{kind:string, leg_type:?string, folder_name:string}
     */
    function llrm_intake_normalize_type($documentType) {
        $key = strtolower((string)$documentType);
        $key = preg_replace('/[^a-z0-9]/', '', $key);

        switch ($key) {
            case 'ordinance':
            case 'resolution':
            case 'billing':
            case 'ordinanceresolution':
                return ['kind' => 'legislative', 'leg_type' => 'Ordinance', 'folder_name' => 'Ordinances & Resolutions'];
            case 'hearing':
            case 'publichearing':
                return ['kind' => 'legislative', 'leg_type' => 'Public Hearing', 'folder_name' => 'Public Hearings'];
            case 'meeting':
            case 'session':
            case 'meetingrecord':
            case 'meetingrecords':
            case 'minutes':
                return ['kind' => 'legislative', 'leg_type' => 'Meeting', 'folder_name' => 'Meeting Records'];
            default:
                return ['kind' => 'archive', 'leg_type' => null, 'folder_name' => 'Imported'];
        }
    }

    /**
     * Resolve (find or create) the target folder for an intake document.
     *
     * When an explicit target is provided (manual routing), the folder is looked
     * up by id and verified to exist - it is never created automatically.
     *
     * @param array $explicit Optional: ['kind'=>'archive'|'legislative', 'id'=>int]
     * @return array{id:int, kind:string, name:string, document_prefix:?string, last_sequence_number:int}|null
     */
    function llrm_intake_resolve_folder($conn, $normalized, $explicit = null) {
        if (empty($normalized['kind']) && empty($explicit)) return null;

        if (!empty($explicit) && !empty($explicit['kind']) && !empty($explicit['id'])) {
            $table = $explicit['kind'] === 'legislative' ? 'legislative_folders' : 'archive_folders';
            $typeCol = $explicit['kind'] === 'legislative' ? ', type' : '';
            $st = $conn->prepare("SELECT id, name, document_prefix, last_sequence_number, parent_id $typeCol FROM $table WHERE id = ? LIMIT 1");
            $st->bind_param("i", $explicit['id']);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$row) return null;

            return [
                'id'                   => (int)$row['id'],
                'kind'                 => $explicit['kind'],
                'name'                 => $row['name'],
                'document_prefix'      => $row['document_prefix'],
                'last_sequence_number' => (int)$row['last_sequence_number'],
                'type'                 => $row['type'] ?? null,
            ];
        }

        $name = $normalized['folder_name'] ?: 'Imported';

        if ($normalized['kind'] === 'legislative') {
            $type = $normalized['leg_type'] ?? 'Ordinance';
            $st = $conn->prepare("SELECT id, document_prefix, last_sequence_number FROM legislative_folders WHERE type = ? AND parent_id IS NULL LIMIT 1");
            $st->bind_param("s", $type);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();

            if ($row) {
                return [
                    'id'                   => (int)$row['id'],
                    'kind'                 => 'legislative',
                    'name'                 => $name,
                    'document_prefix'      => $row['document_prefix'],
                    'last_sequence_number' => (int)$row['last_sequence_number'],
                ];
            }

            $prefix = generate_document_prefix($name);
            $st = $conn->prepare("INSERT INTO legislative_folders (name, type, parent_id, document_prefix) VALUES (?, ?, NULL, ?)");
            $st->bind_param("sss", $name, $type, $prefix);
            $st->execute();
            $id = (int)$conn->insert_id;
            $st->close();

            llrm_intake_ensure_folder_mongo($id, 'legislative', $name);

            return ['id' => $id, 'kind' => 'legislative', 'name' => $name, 'document_prefix' => $prefix, 'last_sequence_number' => 0];
        }

        // Archive fallback -> "Imported" folder
        $st = $conn->prepare("SELECT id, document_prefix, last_sequence_number FROM archive_folders WHERE name = ? LIMIT 1");
        $st->bind_param("s", $name);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row) {
            return [
                'id'                   => (int)$row['id'],
                'kind'                 => 'archive',
                'name'                 => $name,
                'document_prefix'      => $row['document_prefix'],
                'last_sequence_number' => (int)$row['last_sequence_number'],
            ];
        }

        $base = strtolower(generate_document_prefix($name)) ?: 'imported';
        $slug = $base;
        $n = 1;
        while (true) {
            $chk = $conn->prepare("SELECT id FROM archive_folders WHERE slug = ? LIMIT 1");
            $chk->bind_param("s", $slug);
            $chk->execute();
            $taken = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$taken) break;
            $n++;
            $slug = $base . '-' . $n;
        }

        $prefix = generate_document_prefix($name);
        $st = $conn->prepare("INSERT INTO archive_folders (name, slug, created_by, document_prefix) VALUES (?, ?, NULL, ?)");
        $st->bind_param("sss", $name, $slug, $prefix);
        $st->execute();
        $id = (int)$conn->insert_id;
        $st->close();

        llrm_intake_ensure_folder_mongo($id, 'archive', $name);

        return ['id' => $id, 'kind' => 'archive', 'name' => $name, 'document_prefix' => $prefix, 'last_sequence_number' => 0];
    }

    /**
     * Ensure a MongoDB database exists for a folder (created alongside the folder).
     */
    function llrm_intake_ensure_folder_mongo($folderId, $kind, $folderName) {
        if (!class_exists('MongoDBAtlas')) return;
        try {
            $atlas = new MongoDBAtlas();
            $res = $atlas->ensureFolderDatabase((int)$folderId, $kind, $folderName);
            if (!$res['success']) {
                error_log('MongoDB folder db creation failed for folder #'.$folderId.' : ' . $res['error']);
            }
        } catch (Exception $e) {
            error_log('MongoDB folder db error for folder #'.$folderId.' : ' . $e->getMessage());
        }
    }

    /**
     * Increment the folder sequence and build the folder-prefixed unique number.
     */
    function llrm_intake_next_unique_number($conn, $folder) {
        $table = $folder['kind'] === 'legislative' ? 'legislative_folders' : 'archive_folders';
        $prefix = !empty($folder['document_prefix']) ? $folder['document_prefix'] : generate_document_prefix($folder['name']);
        $seq = (int)$folder['last_sequence_number'] + 1;

        $upd = $conn->prepare("UPDATE $table SET last_sequence_number = ?, document_prefix = COALESCE(document_prefix, ?) WHERE id = ?");
        $upd->bind_param("isi", $seq, $prefix, $folder['id']);
        $upd->execute();
        $upd->close();

        return $prefix . ' - ' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Write the intake file into the routed folder directory.
     *
     * $fileSpec keys:
     *   base64    string base64-encoded content (optional)
     *   tmp_path  absolute path to an existing local file (optional)
     *   copy      bool  true  = copy the source file (keep the original)
     *                    false = move the source file (default)
     *   orig_name string original filename
     *
     * @return array{final_name:string, target_path:string, file_size:int, mime_type:string}|array{error:string}
     */
    function llrm_intake_write_file($targetDir, $folder, $fileSpec) {
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9\-\._]/', '_', $fileSpec['orig_name'] ?? 'document');
        if ($safeName === '') $safeName = 'document';
        if (empty(pathinfo($safeName, PATHINFO_EXTENSION))) {
            $safeName .= '.pdf';
        }

        $prefix = $folder['kind'] === 'legislative' ? 'v1_' : time() . '_';
        $finalName = $prefix . $safeName;
        $targetPath = $targetDir . $finalName;

        $counter = 1;
        while (file_exists($targetPath)) {
            $finalName = $prefix . $counter . '_' . $safeName;
            $targetPath = $targetDir . $finalName;
            $counter++;
        }

        if (!empty($fileSpec['base64'])) {
            $data = base64_decode($fileSpec['base64'], true);
            if ($data === false) return ['error' => 'Invalid base64 file content'];
            if (@file_put_contents($targetPath, $data) === false) return ['error' => 'Failed to save file to disk'];
        } elseif (!empty($fileSpec['tmp_path']) && is_file($fileSpec['tmp_path'])) {
            $ok = false;
            if (!empty($fileSpec['copy'])) {
                $ok = @copy($fileSpec['tmp_path'], $targetPath);
            } else {
                if (@rename($fileSpec['tmp_path'], $targetPath)) {
                    $ok = true;
                } elseif (@copy($fileSpec['tmp_path'], $targetPath)) {
                    $ok = true;
                }
            }
            if (!$ok) return ['error' => 'Failed to save file to disk'];
        } else {
            return ['error' => 'No file content provided'];
        }

        $mimeType = function_exists('mime_content_type') ? mime_content_type($targetPath) : null;
        if (!$mimeType) $mimeType = 'application/octet-stream';
        $fileSize = @filesize($targetPath) ?: 0;

        return [
            'final_name'  => $finalName,
            'target_path' => $targetPath,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType,
        ];
    }

    /**
     * Route an LLRM document into the archive (Main Storage).
     *
     * @param mysqli $conn   Database connection.
     * @param array  $doc    Document data: title, type/document_type, author/uploaded_by_name,
     *                       document_date/file_date, source_system, source_record_id.
     *                       Manual routing: target_folder_kind ('archive'|'legislative')
     *                       + target_folder_id (int).
     * @param array|null $fileSpec  File source spec (see llrm_intake_write_file). Null = no file.
     * @param array  $opts   Options: notification_prefix.
     * @return array Result array with success/error/record_id, folder info, unique_number, ipfs data.
     */
    function llrm_intake_route($conn, $doc, $fileSpec = null, $opts = []) {
        $title = trim((string)($doc['title'] ?? ''));
        $rawType = $doc['type'] ?? $doc['document_type'] ?? 'archive';
        $author = $doc['author'] ?? $doc['uploaded_by_name'] ?? 'LLRM Import';
        $sourceSystem = $doc['source_system'] ?? 'LLRM';
        $sourceRecordId = isset($doc['source_record_id']) ? (int)$doc['source_record_id'] : null;
        $fileDate = $doc['document_date'] ?? $doc['file_date'] ?? null;

        if ($title === '') {
            return ['success' => false, 'error' => 'Missing required field: title'];
        }

        $explicit = null;
        if (!empty($doc['target_folder_kind']) && !empty($doc['target_folder_id'])) {
            $explicit = [
                'kind' => $doc['target_folder_kind'],
                'id'   => (int)$doc['target_folder_id'],
            ];
        }

        $normalized = llrm_intake_normalize_type($rawType);
        $folder = llrm_intake_resolve_folder($conn, $normalized, $explicit);
        if (!$folder) {
            return ['success' => false, 'error' => $explicit ? 'Target folder not found' : 'Failed to resolve target folder'];
        }

        $type = $folder['kind'] === 'legislative' ? ($normalized['leg_type'] ?? $folder['type'] ?? $rawType) : $rawType;

        // Duplicate guard
        if ($folder['kind'] === 'legislative') {
            $chk = $conn->prepare("SELECT id FROM legislative_records WHERE title = ? AND type = ? LIMIT 1");
            $chk->bind_param("ss", $title, $type);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();
        } else {
            $chk = $conn->prepare("SELECT id FROM archive_files WHERE name = ? AND folder_id = ? LIMIT 1");
            $chk->bind_param("si", $title, $folder['id']);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();
        }
        if ($dup) {
            return [
                'success'     => false,
                'duplicate'   => true,
                'error'       => 'Duplicate record already exists',
                'existing_id' => (int)$dup['id'],
            ];
        }

        // Month / year from document date (or now)
        $ts = $fileDate ? strtotime($fileDate) : time();
        if ($ts === false) $ts = time();
        $month = date('F', $ts);
        $year = date('Y', $ts);

        // Storage dir: uploads/legislative/{Type}/{Year}/ or uploads/archive/{Folder}/{Year}/
        $dirPart = $folder['kind'] === 'legislative' ? $type : $folder['name'];
        $cleanDirPart = preg_replace('/[^a-zA-Z0-9]/', '', $dirPart);
        if ($cleanDirPart === '') $cleanDirPart = $folder['kind'] === 'legislative' ? 'Document' : 'Imported';
        $subDir = ($folder['kind'] === 'legislative' ? 'legislative/' : 'archive/') . $cleanDirPart . '/' . $year . '/';
        $targetDir = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subDir);

        $fileResult = ['file_path' => null, 'file_size' => 0, 'mime_type' => null, 'final_name' => null, 'target_path' => null];
        if ($fileSpec) {
            $written = llrm_intake_write_file($targetDir, $folder, $fileSpec);
            if (isset($written['error'])) {
                return ['success' => false, 'error' => $written['error']];
            }
            $fileResult = [
                'file_path'   => 'uploads/' . $subDir . $written['final_name'],
                'file_size'   => $written['file_size'],
                'mime_type'   => $written['mime_type'],
                'final_name'  => $written['final_name'],
                'target_path' => $written['target_path'],
            ];
        }

        // Pin to Pinata (best-effort; the local copy is always kept)
        $ipfsCid = null;
        if (!empty($fileResult['target_path']) && is_file($fileResult['target_path'])) {
            $pinataGroupId = null;
            $pinataTable = $folder['kind'] === 'legislative' ? 'legislative_folders' : 'archive_folders';
            if (function_exists('pinata_ensure_folder_group')) {
                $groupInfo = pinata_ensure_folder_group($conn, $pinataTable, $folder['id'], $folder['name']);
                if (!empty($groupInfo['success'])) $pinataGroupId = $groupInfo['id'];
            }
            if (function_exists('pinata_upload_file')) {
                $pinataResult = pinata_upload_file(
                    $fileResult['target_path'],
                    $fileResult['final_name'],
                    ['record' => $folder['kind'], 'source_system' => $sourceSystem, 'llrm_intake' => '1'],
                    $pinataGroupId
                );
                if (!empty($pinataResult['success'])) $ipfsCid = $pinataResult['cid'];
            }
        }

        // Unique number from the folder's document prefix + sequence
        $uniqueNumber = llrm_intake_next_unique_number($conn, $folder);

        // Insert record
        if ($folder['kind'] === 'legislative') {
            $stmt = $conn->prepare("INSERT INTO legislative_records (title, type, month, year, author, file_path, file_date, unique_number, version, parent_version_id, folder_id, file_size, created_at, ipfs_cid, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("ssssssssiiss",
                $title,
                $type,
                $month,
                $year,
                $author,
                $fileResult['file_path'],
                $fileDate,
                $uniqueNumber,
                $folder['id'],
                $fileResult['file_size'],
                $ipfsCid,
                $fileResult['mime_type']
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO archive_files (folder_id, name, file_path, author, file_date, unique_number, version, parent_version_id, file_size, ipfs_cid, mime_type) VALUES (?, ?, ?, ?, ?, ?, 1, NULL, ?, ?, ?)");
            $stmt->bind_param("isssssiss",
                $folder['id'],
                $title,
                $fileResult['file_path'],
                $author,
                $fileDate,
                $uniqueNumber,
                $fileResult['file_size'],
                $ipfsCid,
                $fileResult['mime_type']
            );
        }

        if (!$stmt->execute()) {
            $err = $conn->error;
            $stmt->close();
            return ['success' => false, 'error' => 'Database error: ' . $err];
        }
        $newId = (int)$conn->insert_id;
        $stmt->close();

        // --- MongoDB Atlas Step: Insert heavy file metadata into the folder's database ---
        try {
            $atlas = new MongoDBAtlas();
            $mongoDoc = [
                'mysql_id'    => $newId,
                'ipfs_cid'    => $ipfsCid,
                'file_name'   => $fileResult['final_name'] ?? $title,
                'file_size'   => $fileResult['file_size'] ?? 0,
                'mime_type'   => $fileResult['mime_type'] ?? 'application/octet-stream',
                'created_at'  => date('c'),
            ];
            // Ensure the folder's Mongo database exists and get its name
            $folderDb = $atlas->ensureFolderDatabase((int)$folder['id'], $folder['kind'], $folder['name']);
            if (!$folderDb['success'] || empty($folderDb['db_name'])) {
                $mongoResult = $atlas->insertOne($mongoDoc);
            } else {
                $mongoResult = $atlas->insertOneInDb($folderDb['db_name'], 'files', $mongoDoc);
            }
            if ($mongoResult['success'] && !empty($mongoResult['new_id'])) {
                // Update MySQL record's mongo_id column with the MongoDB _id string
                $table = $folder['kind'] === 'legislative' ? 'legislative_records' : 'archive_files';
                $upd = $conn->prepare("UPDATE {$table} SET mongo_id = ? WHERE id = ?");
                if ($upd) {
                    $mid = (string)$mongoResult['new_id'];
                    $upd->bind_param("si", $mid, $newId);
                    $upd->execute();
                    $upd->close();
                }
            } else {
                error_log('MongoDB Atlas insert failed for intake record #'.$newId.' : ' . ($mongoResult['error'] ?? 'unknown error'));
            }
        } catch (Exception $e) {
            error_log('MongoDB Atlas intake error: ' . $e->getMessage());
        }
        // ---------------------------------------------------

        // Log the intake
        $logSql = "INSERT INTO analytics_events (event_type, record_id, record_title, record_type, created_at) VALUES ('external_intake', ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("iss", $newId, $title, $type);
        $logStmt->execute();
        $logStmt->close();

        // Create notification for admins
        $notifPrefix = $opts['notification_prefix'] ?? $sourceSystem;
        $notifContent = "New archived record from {$notifPrefix}: {$title}";
        $notifSql = "INSERT INTO notifications (time, date, content, about, status, created_at) VALUES (?, CURDATE(), ?, 'External Intake', 'unread', NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $timeStr = date('h:i A');
        $notifStmt->bind_param("ss", $timeStr, $notifContent);
        $notifStmt->execute();
        $notifStmt->close();

        return [
            'success'          => true,
            'record_id'        => $newId,
            'id'               => $newId,
            'kind'             => $folder['kind'],
            'folder_id'        => $folder['id'],
            'folder_name'      => $folder['name'],
            'type'             => $type,
            'file_path'        => $fileResult['file_path'],
            'unique_number'    => $uniqueNumber,
            'ipfs_cid'         => $ipfsCid,
            'ipfs_url'         => ($ipfsCid && function_exists('pinata_gateway_url')) ? pinata_gateway_url($ipfsCid) : null,
            'source_system'    => $sourceSystem,
            'source_record_id' => $sourceRecordId,
        ];
    }

    /**
     * Stage an externally-received document into the intake queue
     * (external_documents table, status "pending").
     *
     * The document is NOT auto-routed into main storage - an administrator
     * manually routes it to a folder from the External Documents page.
     *
     * @param mysqli $conn   Database connection.
     * @param array  $doc    Document data: title, type/document_type, author/uploaded_by_name,
     *                       document_date/file_date, source_system, source_record_id,
     *                       reference_number.
     * @param array|null $fileSpec  File source spec (see llrm_intake_write_file). Null = no file.
     * @param array  $opts   Options: notification_prefix.
     * @return array Result with success/error/id (external_documents.id).
     */
    function llrm_intake_stage($conn, $doc, $fileSpec = null, $opts = []) {
        $title = trim((string)($doc['title'] ?? ''));
        $type = $doc['type'] ?? $doc['document_type'] ?? 'archive';
        $author = $doc['author'] ?? $doc['uploaded_by_name'] ?? 'LLRM Import';
        $sourceSystem = $doc['source_system'] ?? 'LLRM';
        $sourceRecordId = $doc['source_record_id'] ?? null;
        $externalId = $doc['external_id'] ?? (is_numeric($sourceRecordId) ? (string)$sourceRecordId : null);
        $referenceNumber = $doc['reference_number'] ?? null;
        $description = $doc['description'] ?? null;
        $tags = is_array($doc['tags'] ?? null) ? implode(',', $doc['tags']) : ($doc['tags'] ?? null);
        $fileDate = $doc['document_date'] ?? $doc['file_date'] ?? null;

        if ($title === '') {
            return ['success' => false, 'error' => 'Missing required field: title'];
        }

        $ts = $fileDate ? strtotime($fileDate) : time();
        if ($ts === false || $ts <= 0) $ts = time();
        $documentDate = date('Y-m-d', $ts);

        // Ensure queue table exists
        $conn->query("CREATE TABLE IF NOT EXISTS external_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            document_type VARCHAR(50) NOT NULL DEFAULT 'archive',
            document_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            description TEXT NULL,
            tags VARCHAR(500) NULL,
            reference_number VARCHAR(100) NULL,
            file_path VARCHAR(500) NULL,
            file_name VARCHAR(255) NULL,
            file_size BIGINT DEFAULT 0,
            file_type VARCHAR(100) NULL,
            ipfs_cid VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            source_system VARCHAR(50) NOT NULL DEFAULT 'llrm',
            external_id VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ref (reference_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Duplicate guard against existing queue records with the same title reference
        if (!empty($referenceNumber)) {
            $chk = $conn->prepare("SELECT id FROM external_documents WHERE reference_number = ? LIMIT 1");
            $chk->bind_param("s", $referenceNumber);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($dup) {
                return ['success' => false, 'duplicate' => true, 'error' => 'Duplicate record already exists', 'existing_id' => (int)$dup['id']];
            }
        } else {
            $chk = $conn->prepare("SELECT id FROM external_documents WHERE title = ? AND source_system = ? LIMIT 1");
            $chk->bind_param("ss", $title, $sourceSystem);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($dup) {
                return ['success' => false, 'duplicate' => true, 'error' => 'Duplicate record already exists', 'existing_id' => (int)$dup['id']];
            }
        }

        // Write file into the staging area: uploads/external/{Y-m}/
        $subDir = 'external/' . date('Y-m', $ts) . '/';
        $targetDir = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subDir);
        $origName = $fileSpec['orig_name'] ?? ($title . '.pdf');

        $fileResult = ['file_path' => null, 'file_size' => 0, 'mime_type' => null, 'file_name' => null];
        if ($fileSpec) {
            $written = llrm_intake_write_file($targetDir, ['kind' => 'external'], ['base64' => $fileSpec['base64'] ?? null, 'tmp_path' => $fileSpec['tmp_path'] ?? null, 'orig_name' => $origName]);
            if (isset($written['error'])) {
                return ['success' => false, 'error' => $written['error']];
            }
            $fileResult = [
                'file_path'  => 'uploads/' . $subDir . $written['final_name'],
                'file_size'  => $written['file_size'],
                'mime_type'  => $written['mime_type'],
                'file_name'  => $written['final_name'],
            ];
        }

        // Clean up temporary source if the file was moved
        if (!empty($fileSpec['tmp_path']) && !empty($fileResult['file_path']) && is_file($fileSpec['tmp_path'])) {
            @unlink($fileSpec['tmp_path']);
        }

        // Pin to Pinata (best-effort; local copy is always kept)
        $ipfsCid = null;
        if (!empty($fileResult['file_path'])) {
            $absFile = rtrim(dirname(__DIR__), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileResult['file_path']);
            if (is_file($absFile) && function_exists('pinata_upload_file')) {
                $pinataGroupId = null;
                if (function_exists('pinata_ensure_group')) {
                    $groupInfo = pinata_ensure_group('LAS/External Documents');
                    if (!empty($groupInfo['success'])) $pinataGroupId = $groupInfo['id'];
                }
                $pinataResult = pinata_upload_file($absFile, $fileResult['file_name'], ['record' => 'external_document', 'source_system' => $sourceSystem], $pinataGroupId);
                if (!empty($pinataResult['success'])) $ipfsCid = $pinataResult['cid'];
            }
        }

        // Insert into external_documents (pending queue)
        $stmt = $conn->prepare("INSERT INTO external_documents (title, document_type, document_date, status, description, tags, reference_number, file_path, file_name, file_size, file_type, ipfs_cid, mime_type, source_system, external_id, created_at) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $i_file_path = $fileResult['file_path'];
        $i_file_name = $fileResult['file_name'] ?: $origName;
        $i_file_size = $fileResult['file_size'];
        $i_mime_type = $fileResult['mime_type'];
        $stmt->bind_param("ssssssssisssss",
            $title,
            $type,
            $documentDate,
            $description,
            $tags,
            $referenceNumber,
            $i_file_path,
            $i_file_name,
            $i_file_size,
            $i_mime_type,
            $ipfsCid,
            $i_mime_type,
            $sourceSystem,
            $externalId
        );

        if (!$stmt->execute()) {
            $err = $conn->error;
            $stmt->close();
            return ['success' => false, 'error' => 'Database error: ' . $err];
        }
        $newId = (int)$conn->insert_id;
        $stmt->close();

        // Log the intake
        $logSql = "INSERT INTO analytics_events (event_type, record_id, record_title, record_type, created_at) VALUES ('external_intake', ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("iss", $newId, $title, $type);
        $logStmt->execute();
        $logStmt->close();

        // Create notification for admins
        $notifPrefix = $opts['notification_prefix'] ?? $sourceSystem;
        $notifContent = "New document received from {$notifPrefix}: {$title} (awaiting manual routing)";
        $notifSql = "INSERT INTO notifications (time, date, content, about, status, created_at) VALUES (?, CURDATE(), ?, 'External Intake', 'unread', NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $timeStr = date('h:i A');
        $notifStmt->bind_param("ss", $timeStr, $notifContent);
        $notifStmt->execute();
        $notifStmt->close();

        return [
            'success'           => true,
            'id'                => $newId,
            'record_id'         => $newId,
            'status'            => 'pending',
            'file_path'         => $fileResult['file_path'],
            'file_name'         => $fileResult['file_name'],
            'file_size'         => $fileResult['file_size'],
            'mime_type'         => $fileResult['mime_type'],
            'ipfs_cid'          => $ipfsCid,
            'source_system'     => $sourceSystem,
            'source_record_id'  => $sourceRecordId,
            'external_id'       => $externalId,
        ];
    }
}

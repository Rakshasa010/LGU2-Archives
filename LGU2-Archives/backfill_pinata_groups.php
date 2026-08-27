<?php
/**
 * backfill_pinata_groups.php — Add existing folders and their already-pinned
 * files to matching Pinata groups.
 *
 * Purpose:
 *   - Phase A: creates a Pinata group ("LAS/<folder name>") for every row in
 *     archive_folders and legislative_folders, caching the group id in the new
 *     pinata_group_id column (via pinata_ensure_folder_group).
 *   - Phase B: for every record that already has an ipfs_cid, resolves the
 *     file's Pinata id by CID and adds it to its folder's group (or to the
 *     "LAS/External Documents" group for external documents and folderless
 *     records). Never uploads new files — already-pinned files only.
 *
 * Idempotent & resumable: progress is recorded in the pinata_file_id /
 * pinata_grouped columns (0 = pending, 1 = done, 2 = CID gone from Pinata),
 * so re-running picks up where it left off.
 *
 * CLI usage (run on the server where the production DB lives):
 *   php backfill_pinata_groups.php                # run everything
 *   php backfill_pinata_groups.php --dry-run      # plan only, no API/DB writes
 *   php backfill_pinata_groups.php --phase=folders
 *   php backfill_pinata_groups.php --phase=files
 *   php backfill_pinata_groups.php --limit=100
 *   php backfill_pinata_groups.php --sleep=800    # ms between API calls
 *   php backfill_pinata_groups.php --verbose      # one line per record
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

// ---- argument parsing -------------------------------------------------------
$opts = ['dry_run' => false, 'limit' => 0, 'sleep_ms' => 400, 'phase' => 'all', 'verbose' => false];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $opts['dry_run'] = true; continue; }
    if ($arg === '--verbose') { $opts['verbose'] = true; continue; }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) { $opts['limit'] = (int)$m[1]; continue; }
    if (preg_match('/^--sleep=(\d+)$/', $arg, $m)) { $opts['sleep_ms'] = max(0, (int)$m[1]); continue; }
    if (preg_match('/^--phase=(folders|files|all)$/', $arg, $m)) { $opts['phase'] = $m[1]; continue; }
    fwrite(STDERR, "Unknown argument: $arg\n");
    exit(1);
}

require_once __DIR__ . '/authdatabase.php';
require_once __DIR__ . '/includes/pinata.php';

if (!pinata_is_configured()) {
    fwrite(STDERR, "PINATA_JWT is not configured.\n");
    exit(1);
}

// ---- helpers ----------------------------------------------------------------
function p($msg) {
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

function throttle($ms) {
    if ($ms > 0) usleep($ms * 1000);
}

/**
 * Resolve a file's Pinata record by its CID (public network).
 * @return array|null File data (id, group_id, ...) or null when not found.
 */
function pinata_find_file_by_cid($cid) {
    $cfg = pinata_config();
    $ch = curl_init('https://api.pinata.cloud/v3/files/public?cid=' . urlencode($cid));
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
        return ['__error' => 'HTTP ' . $status . ': ' . substr((string)$body, 0, 200)];
    }
    $data = json_decode((string)$body, true);
    $files = $data['data']['files'] ?? [];
    return $files ? $files[0] : null;
}

/**
 * Resolve (create if needed) the Pinata group id for a folder row.
 * @return string|null Group id or null on failure.
 */
function backfill_folder_group($conn, $table, $folderId) {
    $folderId = (int)$folderId;
    if ($folderId <= 0) return null;
    $folderName = null;
    $st = $conn->prepare("SELECT name FROM $table WHERE id = ?");
    $st->bind_param('i', $folderId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) $folderName = $row['name'];
    $res = pinata_ensure_folder_group($conn, $table, $folderId, $folderName ?? '');
    return $res['success'] ? $res['id'] : null;
}

function mark_record($conn, $table, $id, $grouped, $fileId = null) {
    $id = (int)$id;
    $g = (int)$grouped;
    if ($fileId !== null) {
        $st = $conn->prepare("UPDATE $table SET pinata_file_id = ?, pinata_grouped = ? WHERE id = ?");
        $st->bind_param('sii', $fileId, $g, $id);
        $st->execute();
        $st->close();
    } else {
        $st = $conn->prepare("UPDATE $table SET pinata_grouped = ? WHERE id = ?");
        $st->bind_param('ii', $g, $id);
        $st->execute();
        $st->close();
    }
}

// ---- Phase A: folders -> groups ---------------------------------------------
function phase_folders($conn, $opts) {
    $summary = ['found' => 0, 'created' => 0, 'failed' => 0];
    foreach ([['archive_folders', 'archive'], ['legislative_folders', 'legislative']] as [$table, $label]) {
        $res = $conn->query("SELECT id, name, pinata_group_id FROM $table");
        while ($row = $res->fetch_assoc()) {
            $summary['found']++;
            $groupName = 'LAS/' . $row['name'];
            if ($opts['dry_run']) {
                p("[$label] folder #{$row['id']} \"{$row['name']}\" -> group \"$groupName\"" . ($row['pinata_group_id'] ? ' (already mapped)' : ' (will create)'));
                continue;
            }
            $grp = pinata_ensure_folder_group($conn, $table, (int)$row['id'], $row['name']);
            if ($grp['success']) {
                if (!empty($grp['created'])) $summary['created']++;
                if ($opts['verbose']) p("[$label] folder #{$row['id']} \"{$row['name']}\" -> group {$grp['id']}" . (!empty($grp['created']) ? ' (created)' : ' (found)'));
            } else {
                $summary['failed']++;
                p("!! [$label] folder #{$row['id']} \"{$row['name']}\" failed: " . ($grp['error'] ?? 'unknown'));
            }
        }
    }
    return $summary;
}

// ---- Phase B: pinned files -> folder groups ---------------------------------
function phase_files($conn, $opts) {
    $summary = ['seen' => 0, 'added' => 0, 'already' => 0, 'missing' => 0, 'failed' => 0];

    // The catch-all group for external documents and folderless records.
    $externalGroupId = null;
    if ($opts['dry_run']) {
        $externalGroupId = '__external';
    } else {
        $g = pinata_ensure_group('LAS/External Documents');
        if (!$g['success']) {
            p('!! Could not ensure "LAS/External Documents" group: ' . ($g['error'] ?? 'unknown'));
            $externalGroupId = null;
        } else {
            $externalGroupId = $g['id'];
        }
    }

    $sets = [
        ['archive_files', 'archive_folders', 'archive'],
        ['legislative_records', 'legislative_folders', 'legislative'],
        ['external_documents', null, 'external'],
    ];

    foreach ($sets as [$table, $folderTable, $label]) {
        $folderExpr = ($table === 'external_documents') ? 'NULL AS folder_id' : 'folder_id';
        $sql = "SELECT id, $folderExpr, ipfs_cid FROM $table
                WHERE ipfs_cid IS NOT NULL AND ipfs_cid <> '' AND pinata_grouped = 0";
        if ($opts['limit'] > 0) $sql .= " LIMIT " . $opts['limit'];
        $res = $conn->query($sql);
        if (!$res) { p("!! [$label] query failed: " . $conn->error); continue; }

        while ($row = $res->fetch_assoc()) {
            $summary['seen']++;

            // Target group id.
            $targetGroupId = null;
            if ($folderTable !== null && !empty($row['folder_id'])) {
                if ($opts['dry_run']) {
                    $targetGroupId = '__folder_' . $row['folder_id'];
                } else {
                    $targetGroupId = backfill_folder_group($conn, $folderTable, (int)$row['folder_id']);
                    throttle($opts['sleep_ms']);
                }
            } elseif (!empty($externalGroupId)) {
                $targetGroupId = $externalGroupId;
            }

            if (empty($targetGroupId)) {
                $summary['failed']++;
                p("!! [$label] #{$row['id']} no target group (folder {$row['folder_id']}), skipped");
                continue;
            }

            if ($opts['dry_run']) {
                if ($opts['verbose']) p("[$label] #{$row['id']} cid {$row['ipfs_cid']} -> group $targetGroupId (dry-run)");
                continue;
            }

            $file = pinata_find_file_by_cid($row['ipfs_cid']);
            if (!is_array($file)) {
                $summary['failed']++;
                p("!! [$label] #{$row['id']} cid lookup returned no response");
                throttle($opts['sleep_ms']);
                continue;
            }
            if (isset($file['__error'])) {
                $summary['failed']++;
                p("!! [$label] #{$row['id']} cid lookup failed: " . $file['__error']);
                throttle($opts['sleep_ms']);
                continue;
            }
            if (!isset($file['id'])) {
                // CID exists but not found in this account -> unpinned/gone.
                mark_record($conn, $table, (int)$row['id'], 2);
                $summary['missing']++;
                if ($opts['verbose']) p("[$label] #{$row['id']} cid {$row['ipfs_cid']} not found on Pinata (marked gone)");
                throttle($opts['sleep_ms']);
                continue;
            }

            if (isset($file['group_id']) && $file['group_id'] === $targetGroupId) {
                mark_record($conn, $table, (int)$row['id'], 1, $file['id']);
                $summary['already']++;
                if ($opts['verbose']) p("[$label] #{$row['id']} already in group $targetGroupId");
                throttle($opts['sleep_ms']);
                continue;
            }

            $add = pinata_group_add_file($targetGroupId, $file['id']);
            throttle($opts['sleep_ms']);
            if ($add['success']) {
                mark_record($conn, $table, (int)$row['id'], 1, $file['id']);
                $summary['added']++;
                if ($opts['verbose']) p("[$label] #{$row['id']} cid {$row['ipfs_cid']} added to group $targetGroupId");
            } else {
                $summary['failed']++;
                p("!! [$label] #{$row['id']} add to group $targetGroupId failed: " . ($add['error'] ?? 'unknown'));
            }
        }
    }
    return $summary;
}

// ---- main -------------------------------------------------------------------
p('Pinata groups backfill — dry run: ' . ($opts['dry_run'] ? 'YES' : 'no') . ' | phase: ' . $opts['phase']);

if ($opts['phase'] === 'all' || $opts['phase'] === 'folders') {
    p('Phase A: folders -> groups');
    $fa = phase_folders($conn, $opts);
    p('Phase A done: ' . json_encode($fa));
}

if ($opts['phase'] === 'all' || $opts['phase'] === 'files') {
    p('Phase B: pinned files -> folder groups');
    $fb = phase_files($conn, $opts);
    p('Phase B done: ' . json_encode($fb));
}

p('Backfill finished.');

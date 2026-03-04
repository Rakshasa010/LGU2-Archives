<?php
// auto_backup.php
// This script automatically backs up a MySQL database, compresses the dump,
// uploads it to a Google Drive folder via a service account, and cleans up
// old backups both locally and on Drive. Designed for Windows + XAMPP + PHP 8.x.

// Configuration -------------------------------------------------------------
$databaseName   = 'my_database';
$backupDir      = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
$serviceAccount = __DIR__ . DIRECTORY_SEPARATOR . 'credentials.json';
$driveFolderId  = '1b0KwBZbLytwJ3vA1Xx7trybl_BI4K-4U'; // supplied by user

// alert email (leave empty to disable)
$alertEmail     = 'johnpauldeluna06@gmail.com';
// log file path (kept in backups folder)
$logFile        = $backupDir . DIRECTORY_SEPARATOR . 'backup.log';

// Path to mysqldump executable (XAMPP default). Adjust if installed elsewhere.
$mysqldumpPath  = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';

// ---------------------------------------------------------------------------

// ensure backup directory exists
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        die("Failed to create backup directory: $backupDir\n");
    }
}

// helper logger
define('LOG_FILE', $logFile);
function logMsg($msg) {
    $line = '['.date('Y-m-d H:i:s').'] ' . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function alert($subject, $body) {
    global $alertEmail;
    if ($alertEmail !== '') {
        @mail($alertEmail, $subject, $body);
    }
}

logMsg('Backup script started.');

// build filenames
$date      = date('Y-m-d_H-i-s');
$dumpFile  = "$backupDir/{$databaseName}_$date.sql";
$gzFile    = "$dumpFile.gz";

// perform mysqldump
// note: relying on default XAMPP credentials (root without password)
$command = "$mysqldumpPath --user=root --password= --databases $databaseName > \"$dumpFile\"";
exec($command, $output, $returnVar);
if ($returnVar !== 0) {
    $msg = 'mysqldump failed: ' . implode("\n", $output);
    logMsg($msg);
    alert('Backup Error: mysqldump', $msg);
    die("Database backup failed.\n");
}
logMsg("Database dump created: $dumpFile");

// compress the SQL dump
$fp_in  = fopen($dumpFile, 'rb');
$fp_out = gzopen($gzFile, 'wb9');
if (!$fp_in || !$fp_out) {
    $msg = 'Failed to open files for compression.';
    logMsg($msg);
    alert('Backup Error: compression', $msg);
    die("Compression error.\n");
}
while (!feof($fp_in)) {
    gzwrite($fp_out, fread($fp_in, 1024 * 512));
}
fclose($fp_in);
gzclose($fp_out);

// remove the uncompressed dump
unlink($dumpFile);

// load Google Client
require_once __DIR__ . '/vendor/autoload.php';

try {
    $client = new Google_Client();
    $client->setAuthConfig($serviceAccount);
    $client->addScope(Google_Service_Drive::DRIVE_FILE);
    $drive = new Google_Service_Drive($client);

    // upload file
    $file = new Google_Service_Drive_DriveFile();
    $file->setName(basename($gzFile));
    $file->setParents([$driveFolderId]);

    $content = file_get_contents($gzFile);
    $result  = $drive->files->create($file, [
        'data'       => $content,
        'mimeType'   => 'application/gzip',
        'uploadType' => 'multipart'
    ]);

    // after successful upload, delete local file
    if (isset($result->id)) {
        unlink($gzFile);
        logMsg('Uploaded to Drive, remote ID: ' . $result->id);
    } else {
        throw new Exception('Upload returned no ID.');
    }

    // keep only last 7 backups in Drive folder
    $query = sprintf("'%s' in parents and mimeType='application/gzip' and trashed = false", $driveFolderId);

    $files = [];
    $pageToken = null;
    do {
        $params = [
            'q' => $query,
            'fields' => 'nextPageToken, files(id, name, createdTime)',
            'orderBy' => 'createdTime desc',
            'pageToken' => $pageToken
        ];
        $response = $drive->files->listFiles($params);
        foreach ($response->getFiles() as $f) {
            $files[] = $f;
        }
        $pageToken = $response->getNextPageToken();
    } while ($pageToken);

    // delete older ones beyond 7
    if (count($files) > 7) {
        $toDelete = array_slice($files, 7);
        foreach ($toDelete as $old) {
            try {
                $drive->files->delete($old->getId());
            } catch (Exception $e) {
                error_log("Failed to delete old backup {$old->getName()}: " . $e->getMessage());
            }
        }
    }

    echo "Backup complete and uploaded successfully to Google Drive.\n";
    logMsg('Process completed successfully.');
} catch (Exception $e) {
    $msg = 'Error during Google Drive operation: ' . $e->getMessage();
    logMsg($msg);
    alert('Backup Error: Drive', $msg);
    die("Upload failed: " . $e->getMessage() . "\n");
}

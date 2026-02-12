<?php
declare(strict_types=1);

$config = [
    'xampp_dir' => 'C:\\xampp',
    'mysqldump' => 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'REPLACE_ME_DB_NAME',
    'db_user' => 'REPLACE_ME_DB_USER',
    'db_pass' => 'REPLACE_ME_DB_PASSWORD',
    'backup_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'backups',
    'log_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'logs',
    'retention_days' => 7,
    'ftp_host' => 'REPLACE_ME_FTP_HOST',
    'ftp_port' => 21,
    'ftp_user' => 'REPLACE_ME_FTP_USER',
    'ftp_pass' => 'REPLACE_ME_FTP_PASS',
    'ftp_remote_dir' => '/REPLACE_ME_REMOTE_PATH',
    'ftp_passive' => true
];

date_default_timezone_set('UTC');
$ts = date('Ymd_His');
$sqlFile = $config['backup_dir'] . DIRECTORY_SEPARATOR . $config['db_name'] . '_' . $ts . '.sql';
$zipFile = $config['backup_dir'] . DIRECTORY_SEPARATOR . $config['db_name'] . '_' . $ts . '.zip';
$logFile = $config['log_dir'] . DIRECTORY_SEPARATOR . 'backup.log';

function ensureDir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function appendLog(string $file, string $line): void {
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

ensureDir($config['backup_dir']);
ensureDir($config['log_dir']);
appendLog($logFile, 'START');

if (!is_file($config['mysqldump'])) {
    appendLog($logFile, 'ERROR mysqldump not found at ' . $config['mysqldump']);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mysqldump not found']);
    exit;
}

$cmd = '"' . $config['mysqldump'] . '"'
    . ' --host=' . $config['db_host']
    . ' --port=' . (int)$config['db_port']
    . ' --user=' . $config['db_user']
    . ' --password=' . $config['db_pass']
    . ' --routines --events --single-transaction --quick --set-gtid-purged=OFF '
    . $config['db_name']
    . ' --result-file="' . $sqlFile . '"';
$out = [];
$code = 0;
exec($cmd, $out, $code);
if ($code !== 0 || !is_file($sqlFile)) {
    appendLog($logFile, 'ERROR mysqldump failed code=' . $code);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mysqldump failed']);
    exit;
}
appendLog($logFile, 'Dumped ' . basename($sqlFile));

$zip = new ZipArchive();
$opened = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($opened !== true) {
    appendLog($logFile, 'ERROR zip open failed code=' . (string)$opened);
    @unlink($sqlFile);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'zip open failed']);
    exit;
}
$zip->addFile($sqlFile, basename($sqlFile));
$zip->setArchiveComment('MySQL backup ' . $config['db_name'] . ' ' . $ts);
$zip->close();
@unlink($sqlFile);
appendLog($logFile, 'Compressed ' . basename($zipFile));

$ftpOk = false;
$conn = @ftp_connect($config['ftp_host'], (int)$config['ftp_port'], 30);
if ($conn) {
    $login = @ftp_login($conn, $config['ftp_user'], $config['ftp_pass']);
    if ($login) {
        @ftp_pasv($conn, (bool)$config['ftp_passive']);
        $cwdOk = @ftp_chdir($conn, $config['ftp_remote_dir']);
        if ($cwdOk) {
            $putOk = @ftp_put($conn, basename($zipFile), $zipFile, FTP_BINARY);
            if ($putOk) {
                $ftpOk = true;
                appendLog($logFile, 'Uploaded ' . basename($zipFile) . ' to ' . $config['ftp_remote_dir']);
            } else {
                appendLog($logFile, 'ERROR ftp_put failed');
            }
        } else {
            appendLog($logFile, 'ERROR ftp_chdir failed');
        }
    } else {
        appendLog($logFile, 'ERROR ftp_login failed');
    }
    @ftp_close($conn);
} else {
    appendLog($logFile, 'ERROR ftp_connect failed');
}

$retention = (int)$config['retention_days'];
if ($retention > 0) {
    $threshold = time() - ($retention * 86400);
    $files = glob($config['backup_dir'] . DIRECTORY_SEPARATOR . '*.zip') ?: [];
    foreach ($files as $f) {
        $mtime = @filemtime($f);
        if ($mtime !== false && $mtime < $threshold) {
            @unlink($f);
        }
    }
    appendLog($logFile, 'Pruned older than ' . $retention . ' days');
}

appendLog($logFile, 'DONE');
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'zip' => basename($zipFile),
    'uploaded' => $ftpOk,
    'retention_days' => $retention
]);

<?php
include 'authdatabase.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$record = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, title, author, last_accessed, file_path FROM legislative_records WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $record = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}
$filePath = '';
if ($record && !empty($record['file_path'])) {
    $filePath = $record['file_path'];
} elseif (!empty($path)) {
    $filePath = $path;
}
$meta = [
    'title' => $record['title'] ?? null,
    'authors' => $record['author'] ?? null,
    'size' => null,
    'contentType' => null,
    'createdAt' => null,
    'lastSaved' => $record['last_accessed'] ?? null,
    'fileType' => null
];
if ($filePath) {
    $root = realpath(__DIR__);
    $rp = realpath($filePath);
    if ($rp && strpos($rp, $root) === 0 && is_file($rp)) {
        $meta['size'] = filesize($rp);
        $meta['createdAt'] = date('c', filemtime($rp));
        $ext = strtolower(pathinfo($rp, PATHINFO_EXTENSION));
        $meta['fileType'] = $ext;
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $meta['contentType'] = $finfo ? finfo_file($finfo, $rp) : null;
        if ($finfo) finfo_close($finfo);
        if (in_array($ext, ['docx','pptx','xlsx'])) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($rp) === true) {
                    $coreXml = $zip->getFromName('docProps/core.xml');
                    if ($coreXml) {
                        $dom = new DOMDocument();
                        if (@$dom->loadXML($coreXml)) {
                            $xpath = new DOMXPath($dom);
                            $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
                            $xpath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');
                            $tNode = $xpath->query('//dc:title')->item(0);
                            $cNode = $xpath->query('//dc:creator')->item(0);
                            $mNode = $xpath->query('//dcterms:modified')->item(0);
                            $titleXml = $tNode ? trim($tNode->textContent) : null;
                            $creatorXml = $cNode ? trim($cNode->textContent) : null;
                            $modifiedXml = $mNode ? trim($mNode->textContent) : null;
                            if ($titleXml) $meta['title'] = $titleXml;
                            if ($creatorXml) $meta['authors'] = $creatorXml;
                            if ($modifiedXml) $meta['lastSaved'] = $modifiedXml;
                        }
                    }
                    $zip->close();
                }
            }
        } elseif ($ext === 'pdf') {
            $meta['contentType'] = $meta['contentType'] ?: 'application/pdf';
        }
    }
}
echo json_encode($meta, JSON_UNESCAPED_UNICODE);
?>

<?php
/**
 * MongoDB Atlas Mock Uploader Tester
 *
 * Upload mock data into MongoDB Atlas (las_archiving -> resolutions).
 * MongoDB auto-creates the database and collection on first insert.
 */

require __DIR__ . '/LGU2-Archives/includes/mongodb_atlas.php';

$atlas = new MongoDBAtlas();
$dbName = $atlas->getDbName();
$collectionName = $atlas->getCollectionName();

$flash = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'insert';

    if ($action === 'insert') {
        $uploadedFile = $_FILES['file'] ?? null;

        $fileSize = 0;
        $fileName = '';
        $mimeType = 'application/pdf';

        if ($uploadedFile && !empty($uploadedFile['tmp_name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($uploadedFile['name']);
            $fileSize = (int)$uploadedFile['size'];
            $mimeType = $uploadedFile['type'] ?: 'application/pdf';
        }

        $doc = [
            'mysql_id'     => (int)($_POST['mysql_id'] ?? rand(1000, 999999)),
            'title'        => trim($_POST['title'] ?? ''),
            'document_no'  => trim($_POST['document_no'] ?? ''),
            'resolution_no'=> trim($_POST['resolution_no'] ?? ''),
            'author'       => trim($_POST['author'] ?? ''),
            'year'         => trim($_POST['year'] ?? ''),
            'month'        => trim($_POST['month'] ?? ''),
            'file_name'    => $fileName ?: trim($_POST['file_name'] ?? 'mock_document.pdf'),
            'file_size'    => $fileSize,
            'mime_type'    => $mimeType,
            'description'  => trim($_POST['description'] ?? ''),
            'ipfs_cid'     => trim($_POST['ipfs_cid'] ?? ''),
            'created_at'   => date('c'),
        ];

        // Remove empty fields so the doc is clean
        foreach ($doc as $k => $v) {
            if ($v === '' || $v === null) { unset($doc[$k]); }
        }

        $result = $atlas->insertOne($doc);
        if ($result['success']) {
            $flash = ['type' => 'success', 'text' => "Inserted into <strong>{$dbName}.{$collectionName}</strong> with _id <code>{$result['new_id']}</code>"];
        } else {
            $flash = ['type' => 'error', 'text' => 'Insert failed: ' . htmlspecialchars($result['error'] ?? 'unknown error')];
        }
    } elseif ($action === 'delete') {
        $oid = trim($_POST['mongo_id'] ?? '');
        if (preg_match('/^[a-f0-9]{24}$/i', $oid)) {
            try {
                $idObj = new MongoDB\BSON\ObjectId($oid);
                $result = $atlas->deleteMany(['_id' => $idObj]);
                $flash = ['type' => 'success', 'text' => "Deleted {$result['deleted_count']} document from {$dbName}.{$collectionName}"];
            } catch (Exception $e) {
                $flash = ['type' => 'error', 'text' => 'Delete failed: ' . htmlspecialchars($e->getMessage())];
            }
        } else {
            $flash = ['type' => 'error', 'text' => 'Invalid _id format'];
        }
    } elseif ($action === 'clear_all') {
        $result = $atlas->deleteMany([]);
        $flash = ['type' => 'success', 'text' => "Cleared {$result['deleted_count']} documents from {$dbName}.{$collectionName}"];
    }
}

// Load current documents for the table
$findResult = $atlas->find([]);
$docs = $findResult['success'] ? $findResult['documents'] : [];
$error = $findResult['success'] ? null : $findResult['error'];

// Sort by created_at desc (newest first)
usort($docs, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MongoDB Atlas Mock Uploader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .code { font-family: monospace; font-size: .8em; }
        .badge-db { background: #10b981; color: #fff; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">MongoDB Atlas Mock Uploader</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to App</a>
    </div>

    <div class="mb-4 p-3 bg-dark text-white rounded">
        <span class="me-3"><span class="badge badge-db">DB</span> <span class="code"><?= htmlspecialchars($dbName) ?></span></span>
        <span class="me-3"><span class="badge badge-db">Collection</span> <span class="code"><?= htmlspecialchars($collectionName) ?></span></span>
        <span><span class="badge badge-db">Docs</span> <span class="code"><?= count($docs) ?></span></span>
        <div class="small text-white-50 mt-2">
            MongoDB auto-creates the database &amp; collection on first insert.
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $flash['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">Connection/query error: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Upload Mock Data</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="insert">
                        <div class="mb-3">
                            <label class="form-label">File (optional — its name/size/type will be used)</label>
                            <input type="file" name="file" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">MySQL ID</label>
                                <input type="number" name="mysql_id" class="form-control" placeholder="auto">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" placeholder="Resolution No. 01-2026">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Resolution No.</label>
                                <input type="text" name="resolution_no" class="form-control" placeholder="01-2026">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Document No.</label>
                                <input type="text" name="document_no" class="form-control" placeholder="DOC-2026-001">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Author</label>
                                <input type="text" name="author" class="form-control" placeholder="Sangguniang Bayan">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Year</label>
                                <input type="text" name="year" class="form-control" placeholder="2026">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select">
                                <option value="">— select —</option>
                                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                                    <option><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File Name (if no file uploaded)</label>
                            <input type="text" name="file_name" class="form-control" placeholder="mock_document.pdf">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">IPFS CID (optional)</label>
                            <input type="text" name="ipfs_cid" class="form-control" placeholder="Qm...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="A mock resolution document"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Insert into MongoDB Atlas</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Documents in <?= htmlspecialchars($dbName) ?> . <?= htmlspecialchars($collectionName) ?></span>
                    <?php if ($docs): ?>
                        <form method="post" onsubmit="return confirm('Delete ALL <?= count($docs) ?> documents?')">
                            <input type="hidden" name="action" value="clear_all">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Clear All</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!$docs): ?>
                        <div class="p-4 text-center text-muted">
                            No documents yet. Upload mock data on the left — MongoDB will create the database &amp; collection automatically.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>_id</th>
                                        <th>Title</th>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Created</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($docs as $doc): ?>
                                    <tr>
                                        <td class="code" title="<?= htmlspecialchars(json_encode($doc)) ?>"><?= htmlspecialchars(mb_substr($doc['_id'] ?? '', 0, 10)) ?>…</td>
                                        <td>
                                            <?= htmlspecialchars($doc['title'] ?? ($doc['file_name'] ?? 'untitled')) ?>
                                            <?php if (!empty($doc['resolution_no'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars($doc['resolution_no']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="code small"><?= htmlspecialchars($doc['file_name'] ?? '—') ?></td>
                                        <td><?= isset($doc['file_size']) ? number_format($doc['file_size']) : '—' ?></td>
                                        <td class="small"><?= htmlspecialchars(mb_substr($doc['created_at'] ?? '', 0, 10)) ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Delete this document?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="mongo_id" value="<?= htmlspecialchars($doc['_id'] ?? '') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

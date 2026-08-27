<?php
// Include database connection
include 'authdatabase.php';
require_once __DIR__ . '/includes/mongodb_atlas.php';
require_once __DIR__ . '/includes/pinata.php';
require_once __DIR__ . '/monitoring_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function log_analytics_event(mysqli $conn, array $event): void {
    // Best-effort logging. If table doesn't exist / permissions fail, ignore.
    $sql = "INSERT INTO analytics_events (event_type, user_id, record_id, record_title, record_type, download_format, bytes)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;

    $event_type = (string)($event['event_type'] ?? '');
    $user_id = isset($event['user_id']) ? (int)$event['user_id'] : null;
    $record_id = isset($event['record_id']) ? (int)$event['record_id'] : null;
    $record_title = isset($event['record_title']) ? (string)$event['record_title'] : null;
    $record_type = isset($event['record_type']) ? (string)$event['record_type'] : null;
    $download_format = isset($event['download_format']) ? (string)$event['download_format'] : null;
    $bytes = isset($event['bytes']) ? (int)$event['bytes'] : null;

    // bind_param does not accept null with strict types cleanly; cast to variables
    $stmt->bind_param(
        "siisssi",
        $event_type,
        $user_id,
        $record_id,
        $record_title,
        $record_type,
        $download_format,
        $bytes
    );
    $stmt->execute();
    $stmt->close();
}


function log_download_audit(mysqli $conn, string $about, string $content): void {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) return;
    $userName = null;
    if ($u = $conn->prepare("SELECT full_name FROM users WHERE id = ?")) {
        $u->bind_param("i", $uid);
        $u->execute();
        $r = $u->get_result();
        if ($r && $row = $r->fetch_assoc()) $userName = trim($row['full_name'] ?? '');
        $u->close();
    }
    $t = date('h:i A'); $d = date('Y-m-d'); $s = 'unread';
    $ins = $conn->prepare("INSERT INTO notifications (time, date, content, about, user_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if ($ins) {
        $ins->bind_param('ssssss', $t, $d, $content, $about, $userName, $s);
        $ins->execute();
        $ins->close();
    }
}

function get_legislative_file_info(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT file_path, ipfs_cid, title, type, month, year, author, mongo_id, folder_id FROM legislative_records WHERE id = ?");
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function resolve_local_path(string $path): ?string {
    if ($path === '') return null;
    if (file_exists($path)) return $path;
    $absolute = __DIR__ . DIRECTORY_SEPARATOR . $path;
    if (file_exists($absolute)) return $absolute;
    return null;
}

function build_absolute_url(string $relative): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $base = $scheme . '://' . $host . ($dir ? $dir . '/' : '/');
    if (strpos($relative, '/') === 0) {
        return $scheme . '://' . $host . $relative;
    }
    return $base . $relative;
}


// Check if it's a GET request (show modal) or POST request (download file)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check for view action
    if (isset($_GET['action']) && $_GET['action'] === 'view') {
        if (!isset($_GET['id'], $_GET['title'], $_GET['type'], $_GET['month'], $_GET['year'], $_GET['author'])) {
            die('Invalid request');
        }

        $record = [
            'id' => $_GET['id'],
            'title' => $_GET['title'],
            'type' => $_GET['type'],
            'month' => $_GET['month'],
            'year' => $_GET['year'],
            'author' => $_GET['author']
        ];
        
        // Log view event
        log_analytics_event($conn, [
            'event_type' => 'view',
            'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'record_id' => (int)$record['id'] > 0 ? (int)$record['id'] : null,
            'record_title' => $record['title'],
            'record_type' => $record['type'],
            'download_format' => 'html',
            'bytes' => null
        ]);

        // Log file preview for monitored users
        log_monitored_user_action(
            $conn,
            $_SESSION['user_id'] ?? 0,
            'File Preview',
            'Previewed file "' . htmlspecialchars($record['title']) . '" in folder "' . htmlspecialchars($record['type']) . '"'
        );

        // Log file preview in audit logs (all users)
        log_download_audit($conn, 'File Preview', 'Previewed file "' . htmlspecialchars($record['title']) . '" in folder "' . htmlspecialchars($record['type']) . '"');

        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $record['title']) . '_' . $record['id'];
        generatePDF($record, $filename, 'inline');
        $conn->close();
        exit;
    } elseif (isset($_GET['action']) && $_GET['action'] === 'view_json') {
        if (!isset($_GET['id'], $_GET['title'], $_GET['type'], $_GET['month'], $_GET['year'], $_GET['author'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            $conn->close();
            exit;
        }
        $record = [
            'id' => $_GET['id'],
            'title' => $_GET['title'],
            'type' => $_GET['type'],
            'month' => $_GET['month'],
            'year' => $_GET['year'],
            'author' => $_GET['author']
        ];
        log_analytics_event($conn, [
            'event_type' => 'view_api',
            'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'record_id' => (int)$record['id'] > 0 ? (int)$record['id'] : null,
            'record_title' => $record['title'],
            'record_type' => $record['type'],
            'download_format' => 'json',
            'bytes' => null
        ]);
        $viewerHtml = '';
        $fileInfo = null;
        $idInt = (int)$record['id'];
        if ($idInt > 0) {
            $fileInfo = get_legislative_file_info($conn, $idInt);
        }
        // --- MongoDB Atlas Step: Query supplementary metadata ---
        $mongoMetadata = null;
        if ($idInt > 0) {
            $atlas = new MongoDBAtlas();
            // Resolve the per-folder database first (routed files live there)
            $folderDbName = null;
            if ($fileInfo && !empty($fileInfo['folder_id'])) {
                $folderReg = $atlas->getFolderDatabase((int)$fileInfo['folder_id']);
                if ($folderReg['success'] && !empty($folderReg['db_name'])) {
                    $folderDbName = $folderReg['db_name'];
                    $mongoResult = $atlas->findOneInDb($folderDbName, 'files', ['mysql_id' => $idInt]);
                } else {
                    $mongoResult = ['success' => false];
                }
            } else {
                // Try querying by mysql_id first (legacy/default db)
                $mongoResult = $atlas->findOne(['mysql_id' => $idInt]);
            }
            if ($mongoResult['success'] && $mongoResult['document']) {
                $mongoMetadata = $mongoResult['document'];
            } elseif ($fileInfo && !empty($fileInfo['mongo_id'])) {
                // Fallback: query by MongoDB _id using the value stored in MySQL's mongo_id column
                try {
                    $mongoId = new MongoDB\BSON\ObjectId($fileInfo['mongo_id']);
                    if ($folderDbName) {
                        $mongoResult2 = $atlas->findOneInDb($folderDbName, 'files', ['_id' => $mongoId]);
                    } else {
                        $mongoResult2 = $atlas->findOne(['_id' => $mongoId]);
                    }
                    if ($mongoResult2['success'] && $mongoResult2['document']) {
                        $mongoMetadata = $mongoResult2['document'];
                    }
                } catch (Exception $e) {
                    error_log('MongoDB fallback lookup failed: ' . $e->getMessage());
                }
            }
        }
        // Merge MongoDB metadata with file info (MongoDB values take precedence where available)
        if ($fileInfo && $mongoMetadata) {
            // Use MongoDB file_size and mime_type if available, otherwise fall back to MySQL
            if (isset($mongoMetadata['file_size'])) $fileInfo['file_size'] = $mongoMetadata['file_size'];
            if (isset($mongoMetadata['mime_type'])) $fileInfo['mime_type'] = $mongoMetadata['mime_type'];
        }
        // ---------------------------------------------------
        if ($fileInfo && isset($fileInfo['file_path'])) {
            $path = resolve_local_path($fileInfo['file_path']);
            $ipfsCid = $fileInfo['ipfs_cid'] ?? null;
            // When the file only exists on IPFS, keep rendering the preview —
            // view_file below serves it through the Pinata gateway.
            if (!$path && !empty($ipfsCid) && pinata_is_configured()) {
                $path = $fileInfo['file_path'];
            }
            if ($path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $viewUrl = 'download.php?action=view_file&id=' . urlencode((string)$idInt);
                $viewUrlAbs = build_absolute_url($viewUrl);
                if (in_array($ext, ['pdf'])) {
                    $viewerHtml = '<div class="space-y-4">'
                                . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                . '<iframe src="' . $viewUrl . '" class="w-full h-[70vh] rounded-lg border"></iframe>'
                                . '</div>';
                } elseif (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'])) {
                    $viewerHtml = '<div class="space-y-4 text-center">'
                                . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                . '<img src="' . $viewUrl . '" class="max-h-[70vh] w-auto inline-block rounded-lg border" alt="Preview" />'
                                . '</div>';
                } elseif (in_array($ext, ['txt','csv','json','xml','md','log'])) {
                    $viewerHtml = '<div class="space-y-4">'
                                . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                . '<iframe src="' . $viewUrl . '" class="w-full h-[70vh] rounded-lg border bg-white"></iframe>'
                                . '</div>';
                } elseif ($ext === 'docx') {
                    require_once __DIR__ . '/includes/docx_preview.php';
                    $docxText = docx_extract_text($path);
                    if ($docxText !== null && trim($docxText) !== '') {
                        $viewerHtml = '<div class="space-y-4">'
                                    . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                    . '<pre class="w-full max-h-[70vh] overflow-auto rounded-lg border bg-white dark:bg-slate-900 text-gray-800 dark:text-gray-100 p-4 text-sm leading-relaxed whitespace-pre-wrap font-sans">' . htmlspecialchars($docxText) . '</pre>'
                                    . '</div>';
                    } else {
                        $gview = 'https://docs.google.com/gview?embedded=true&url=' . urlencode($viewUrlAbs);
                        $viewerHtml = '<div class="space-y-4">'
                                    . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                    . '<iframe src="' . $gview . '" class="w-full h-[70vh] rounded-lg border bg-white"></iframe>'
                                    . '<div class="text-xs text-gray-500 dark:text-gray-400">If the preview fails, use Open to view in a new tab.</div>'
                                    . '<div class="flex justify-end"><a href="' . $viewUrl . '" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">Open</a></div>'
                                    . '</div>';
                    }
                } elseif (in_array($ext, ['doc','xls','xlsx','ppt','pptx'])) {
                    $gview = 'https://docs.google.com/gview?embedded=true&url=' . urlencode($viewUrlAbs);
                    $viewerHtml = '<div class="space-y-4">'
                                . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                . '<iframe src="' . $gview . '" class="w-full h-[70vh] rounded-lg border bg-white"></iframe>'
                                . '<div class="text-xs text-gray-500 dark:text-gray-400">If the preview fails, use Open to view in a new tab.</div>'
                                . '<div class="flex justify-end"><a href="' . $viewUrl . '" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">Open</a></div>'
                                . '</div>';
                } else {
                    $viewerHtml = '<div class="space-y-4">'
                                . '<div class="border-b pb-2 text-xl font-semibold">' . htmlspecialchars($record['title']) . '</div>'
                                . '<div class="text-sm text-gray-600 dark:text-gray-400">Preview not available for this file type.</div>'
                                . '<div class="flex justify-end">'
                                . '<a href="' . $viewUrl . '" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">Open</a>'
                                . '</div>'
                                . '</div>';
                }
            } else {
                $viewerHtml = '<div class="text-sm text-red-600 dark:text-red-400">File not found on server.</div>';
            }
        } else {
            $viewerHtml = generateDocumentHTML($record);
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'metadata' => $record,
            'html' => $viewerHtml
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    } elseif (isset($_GET['action']) && $_GET['action'] === 'view_file') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid ID';
            exit;
        }
        $info = get_legislative_file_info($conn, $id);
        if (!$info || !isset($info['file_path'])) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        // Log file view in audit logs (all users)
        $viewTitle = $info['title'] ?? ('Record #' . $id);
        log_download_audit($conn, 'File Preview', 'Viewed file "' . htmlspecialchars($viewTitle) . '"');
        // Serve via the Pinata dedicated gateway when an IPFS CID is stored.
        $ipfsCid = $info['ipfs_cid'] ?? null;
        if (!empty($ipfsCid) && pinata_is_configured()) {
            pinata_stream_cid($ipfsCid, true, basename((string)$info['file_path']));
        }
        $path = resolve_local_path((string)$info['file_path']);
        if (!$path) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($path);
        exit;
    }

    // Show download modal
    if (!isset($_GET['id'], $_GET['title'], $_GET['type'], $_GET['month'], $_GET['year'], $_GET['author'])) {
        die('Invalid request');
    }

    $record = [
        'id' => $_GET['id'],
        'title' => $_GET['title'],
        'type' => $_GET['type'],
        'month' => $_GET['month'],
        'year' => $_GET['year'],
        'author' => $_GET['author']
    ];
    

    // Output HTML for modal
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Download Document</title>
        <?php include 'includes/header_scripts.php'; ?>
        <style>
            /* Ensure full-height for proper centering and fallback backdrop */
            html, body { height: 100%; }
            .backdrop-blur-fallback { background-color: rgba(0,0,0,0.45); }
        </style>
        <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
        <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    </head>
    <body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200 flex items-center justify-center">
        <!-- Download Modal (improved) -->
        <div id="downloadModal" class="fixed inset-0 z-50 flex items-center justify-center" aria-modal="true" role="dialog">
            <div class="absolute inset-0 backdrop-blur-sm bg-black/40 backdrop-blur-fallback"></div>

            <div id="modalCard" class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-lg w-full m-auto mx-4 transform transition-all duration-300 scale-95 opacity-0 max-h-[90vh] overflow-auto">
                <div class="p-6 sm:p-8 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex-none bg-gradient-to-tr from-red-500 to-pink-500 text-white rounded-xl p-3 shadow-md">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-3-3m3 3l3-3M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Download Document</h3>
                                 <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Securely download a copy of the document below.</p>
                            </div>
                        </div>
                        <button id="closeX" aria-label="Close" onclick="window.close();" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4">
                        <div class="rounded-md border border-gray-100 dark:border-slate-700 p-4 bg-gray-50 dark:bg-slate-900/50">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate" id="modalTitle"><?php echo htmlspecialchars($record['title']); ?></h4>
                            <div class="mt-3 text-sm text-gray-500 dark:text-gray-400 grid grid-cols-2 gap-2">
                                <div><span class="font-semibold text-gray-600 dark:text-gray-300">Type:</span> <?php echo htmlspecialchars($record['type']); ?></div>
                                <div><span class="font-semibold text-gray-600 dark:text-gray-300">Date:</span> <?php echo htmlspecialchars($record['month'] . ' ' . $record['year']); ?></div>
                                <div class="col-span-2"><span class="font-semibold text-gray-600 dark:text-gray-300">Author:</span> <?php echo htmlspecialchars($record['author']); ?></div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <form action="download.php" method="post" target="_blank" class="flex-1" id="dl-pdf-form">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($record['title']); ?>">
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($record['type']); ?>">
                                <input type="hidden" name="month" value="<?php echo htmlspecialchars($record['month']); ?>">
                                <input type="hidden" name="year" value="<?php echo htmlspecialchars($record['year']); ?>">
                                <input type="hidden" name="author" value="<?php echo htmlspecialchars($record['author']); ?>">
                                <input type="hidden" name="format" value="pdf">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold shadow hover:from-red-600 hover:to-pink-600 transition dark:shadow-red-900/20">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-3-3m3 3l3-3M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7"></path></svg>
                                    PDF
                                </button>
                            </form>
                            <form action="download.php" method="post" target="_blank" class="flex-1" id="dl-docx-form">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($record['title']); ?>">
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($record['type']); ?>">
                                <input type="hidden" name="month" value="<?php echo htmlspecialchars($record['month']); ?>">
                                <input type="hidden" name="year" value="<?php echo htmlspecialchars($record['year']); ?>">
                                <input type="hidden" name="author" value="<?php echo htmlspecialchars($record['author']); ?>">
                                <input type="hidden" name="format" value="docx">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-gradient-to-r from-blue-500 to-cyan-500 text-white font-semibold shadow hover:from-blue-600 hover:to-cyan-600 transition dark:shadow-blue-900/20">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5"></path></svg>
                                    DOCX
                                </button>
                            </form>
                        </div>

                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-lg px-3 py-2">
                            <i class="bi bi-shield-lock"></i>
                            <span>A one-time code will be sent to your email to verify this download.</span>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button id="cancelDownload" onclick="window.close();" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script id="download-record" type="application/json"><?php echo json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
        <script src="assets/js/folder-otp.js"></script>
        <script>
            // Simple animation
            document.addEventListener('DOMContentLoaded', () => {
                const card = document.getElementById('modalCard');
                if (card) {
                    setTimeout(() => {
                        card.classList.remove('scale-95', 'opacity-0');
                        card.classList.add('scale-100', 'opacity-100');
                    }, 50);
                }
            });

            // OTP gate: PDF/DOCX download only proceeds after a verified OTP code.
            (function () {
                var formIds = ['dl-pdf-form', 'dl-docx-form'];
                formIds.forEach(function (id) {
                    var form = document.getElementById(id);
                    if (!form) return;
                    form.addEventListener('submit', function (e) {
                        if (form.dataset.otpVerified === '1') return;
                        e.preventDefault();
                        window.folderOTP.guard(null, function () {
                            form.dataset.otpVerified = '1';
                            form.submit();
                        });
                    });
                });
            })();
        </script>
        <?php include 'includes/footer_scripts.php'; ?>
    </body>
    </html>
    <?php
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file download
    // Check if all required POST data is present
    if (!isset($_POST['id'], $_POST['title'], $_POST['type'], $_POST['month'], $_POST['year'], $_POST['author'], $_POST['format'])) {
        die('Invalid request');
    }

    // Downloads require a fresh OTP verification (set by api/verify-folder-otp.php).
    $otpVerified = isset($_SESSION['folder_otp_verified'])
        && (time() - (int)$_SESSION['folder_otp_verified']) <= 300;
    if (!$otpVerified) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Download Blocked</title></head>'
            . '<body style="font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#fef2f2;">'
            . '<div style="text-align:center;background:#fff;padding:32px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:420px;">'
            . '<div style="font-size:40px;">&#128274;</div>'
            . '<h2 style="margin:12px 0 8px;color:#111827;">Download blocked</h2>'
            . '<p style="color:#6b7280;font-size:14px;margin-bottom:20px;">This download requires a one-time verification code. Please go back, choose PDF or DOCX, and complete the code sent to your email.</p>'
            . '<button onclick="window.close()" style="background:#dc2626;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-size:14px;cursor:pointer;">Close window</button>'
            . '</div></body></html>';
        $conn->close();
        exit;
    }

    $record = [
        'id' => $_POST['id'],
        'title' => $_POST['title'],
        'type' => $_POST['type'],
        'month' => $_POST['month'],
        'year' => $_POST['year'],
        'author' => $_POST['author']
    ];

    $format = strtolower($_POST['format']);

    // Update last_accessed timestamp only when ID is a real record id (>0)
    $record_id_int = (int)$record['id'];
    if ($record_id_int > 0) {
        $update_sql = "UPDATE legislative_records SET last_accessed = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param("i", $record_id_int);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Log download event for Reports & Analytics (best-effort)
    log_analytics_event($conn, [
        'event_type' => 'download',
        'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'record_id' => $record_id_int > 0 ? $record_id_int : null,
        'record_title' => $record['title'] ?? null,
        'record_type' => $record['type'] ?? null,
        'download_format' => $format,
        'bytes' => null
    ]);

    // Log file download for monitored users
    log_monitored_user_action(
        $conn,
        $_SESSION['user_id'] ?? 0,
        'File Download',
        'Downloaded "' . htmlspecialchars($record['title']) . '" as ' . strtoupper($format) . ' from folder "' . htmlspecialchars($record['type']) . '"'
    );

    // Log file download in audit logs (all users)
    log_download_audit($conn, 'File Download', 'Downloaded "' . htmlspecialchars($record['title']) . '" as ' . strtoupper($format) . ' from folder "' . htmlspecialchars($record['type']) . '"');
    

    // Generate filename
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $record['title']) . '_' . $record['id'];

    switch ($format) {
        case 'pdf':
            generatePDF($record, $filename);
            break;
        case 'doc':
        case 'docx':
            generateWord($record, $filename);
            break;
        default:
            die('Invalid format');
    }

    $conn->close();
    exit;
} else {
    die('Invalid request method');
}

function generatePDF($record, $filename, $disposition = 'attachment') {
    // Output HTML formatted for PDF printing
    header('Content-Type: text/html');
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '_print.html"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $html = '<!DOCTYPE html>';
    $html .= '<html><head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<title>' . htmlspecialchars($record['title']) . '</title>';
    $html .= '<style>';
    $html .= '@media print { body { font-family: "Times New Roman", serif; font-size: 12pt; margin: 1in; } }';
    $html .= '@media screen { body { font-family: Arial, sans-serif; font-size: 14px; margin: 0; padding: 20px; background-color: #fff; } }';
    $html .= 'body { line-height: 1.5; color: #333; }';
    $html .= 'h1 { text-align: center; margin-bottom: 30pt; text-decoration: underline; font-size: 18pt; }';
    $html .= '.metadata { margin: 20pt 0; padding: 15pt; background-color: #f9f9f9; border-left: 5px solid #007acc; border-radius: 4px; }';
    $html .= '.content { margin-top: 30pt; }';
    $html .= 'table.meta-table { width: 100%; border-collapse: collapse; }';
    $html .= 'table.meta-table td { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #eee; }';
    $html .= 'table.meta-table tr:last-child td { border-bottom: none; }';
    $html .= 'table.meta-table td.label { font-weight: bold; color: #555; width: 140px; }';
    $html .= '</style>';
    $html .= '</head><body>';

    $html .= generateDocumentHTML($record);

    $html .= '</body></html>';

    echo $html;
}

function generateWord($record, $filename) {
    $html = generateDocumentHTML($record);

    // Set headers for Word document download
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output HTML that Word can open
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><title>' . htmlspecialchars($record['title']) . '</title>';
    echo '<meta charset="utf-8">';
    echo '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>90</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 40px; }';
    echo 'h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }';
    echo '.metadata { background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #007acc; }';
    echo '.content { line-height: 1.6; margin-top: 30px; }';
    echo '</style>';
    echo '</head><body>';
    echo $html;
    echo '</body></html>';
}

function generateXML($record, $filename) {
    // Set headers for XML download
    header('Content-Type: text/xml');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Generate XML structure
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $root = $xml->createElement('document');
    $xml->appendChild($root);

    $metadata = $xml->createElement('metadata');
    $root->appendChild($metadata);

    $metadata->appendChild($xml->createElement('id', $record['id']));
    $metadata->appendChild($xml->createElement('title', $record['title']));
    $metadata->appendChild($xml->createElement('type', $record['type']));
    $metadata->appendChild($xml->createElement('month', $record['month']));
    $metadata->appendChild($xml->createElement('year', $record['year']));
    $metadata->appendChild($xml->createElement('author', $record['author']));
    $metadata->appendChild($xml->createElement('created_at', date('Y-m-d H:i:s')));

    $content = $xml->createElement('content');
    $root->appendChild($content);

    // Add basic content structure
    $content->appendChild($xml->createElement('header', $record['title']));
    $content->appendChild($xml->createElement('body', 'Document content for ' . $record['type'] . ' - ' . $record['title']));

    echo $xml->saveXML();
}

function generateDocumentHTML($record) {
    // Determine container style based on context (this is a simple static HTML generation, so we rely on media queries for outer body, but here we can set max-width)
    $html = '<div style="font-family: inherit; max-width: 800px; margin: 0 auto;">';

    // Header
    $html .= '<h1 style="color: #333; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 30px; font-size: 24px; text-align: center;">';
    $html .= htmlspecialchars($record['title']);
    $html .= '</h1>';

    // Metadata
    $html .= '<div class="metadata">';
    $html .= '<h3 style="margin-top: 0; margin-bottom: 15px; color: #007acc; font-size: 18px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">Document Information</h3>';
    
    // Use table for precise alignment
    $html .= '<table class="meta-table">';
    
    $html .= '<tr>';
    $html .= '<td class="label">Type:</td>';
    $html .= '<td>' . htmlspecialchars($record['type']) . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label">Month/Year:</td>';
    $html .= '<td>' . htmlspecialchars($record['month'] . ' ' . $record['year']) . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label">Author:</td>';
    $html .= '<td>' . htmlspecialchars($record['author']) . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label">Generated on:</td>';
    $html .= '<td>' . date('F j, Y \a\t g:i A') . '</td>';
    $html .= '</tr>';

    $html .= '</table>';
    $html .= '</div>';

    // Content placeholder
    $html .= '<div class="content" style="line-height: 1.8; color: #444;">';
    $html .= '<h2 style="font-size: 20px; color: #222; margin-bottom: 15px;">Document Content</h2>';
    $html .= '<p style="margin-bottom: 15px;">This is a generated document for the <strong>' . htmlspecialchars($record['type']) . '</strong> titled "<em>' . htmlspecialchars($record['title']) . '</em>".</p>';
    $html .= '<p style="margin-bottom: 15px;">In a full implementation, this would contain the actual document content, sections, and detailed information extracted from the source file or database.</p>';
    $html .= '<p style="margin-top: 30px; padding-top: 20px; border-top: 1px dashed #ccc; font-size: 12px; color: #888;">Document ID: ' . htmlspecialchars($record['id']) . ' | System Generated</p>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}
?>

<?php
// Include database connection
include 'authdatabase.php';

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

        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $record['title']) . '_' . $record['id'];
        generatePDF($record, $filename, 'inline');
        $conn->close();
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
                            <form action="download.php" method="post" target="_blank" class="flex-1">
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
                            <form action="download.php" method="post" target="_blank" class="flex-1">
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
                            <form action="download.php" method="post" target="_blank" class="flex-1">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($record['title']); ?>">
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($record['type']); ?>">
                                <input type="hidden" name="month" value="<?php echo htmlspecialchars($record['month']); ?>">
                                <input type="hidden" name="year" value="<?php echo htmlspecialchars($record['year']); ?>">
                                <input type="hidden" name="author" value="<?php echo htmlspecialchars($record['author']); ?>">
                                <input type="hidden" name="format" value="xml">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition border border-gray-200 dark:border-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7L9 18l-5-5"></path></svg>
                                    XML
                                </button>
                            </form>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button id="cancelDownload" onclick="window.close();" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script id="download-record" type="application/json"><?php echo json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
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
    

    // Generate filename
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $record['title']) . '_' . $record['id'];

    switch ($format) {
        case 'pdf':
            generatePDF($record, $filename);
            break;
        case 'docx':
            generateWord($record, $filename);
            break;
        case 'xml':
            generateXML($record, $filename);
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

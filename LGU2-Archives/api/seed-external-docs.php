<?php
/**
 * Seed external_documents table with real PDFs from real-docus/ folder.
 * Copies RES (Resolutions) and ORD (Ordinances) into uploads/external/2026-09/
 * and inserts DB records so they appear on the External Documents page.
 *
 * Run once on deployment: php seed-external-docs.php
 * Then delete this file.
 */
session_start();
require_once __DIR__ . '/../authdatabase.php';

$baseDir = __DIR__ . '/../real-docus';
$uploadDir = __DIR__ . '/../uploads/external/2026-09';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

function cleanTitle($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['  '], [' '], $name);
    return trim($name);
}

function guessDocType($folder, $title) {
    $lower = strtolower($title);
    $folderUpper = strtoupper($folder);
    if ($folderUpper === 'RES') return 'resolution';
    if ($folderUpper === 'ORD') return 'ordinance';
    if (strpos($lower, 'ordinance') !== false) return 'ordinance';
    if (strpos($lower, 'resolution') !== false) return 'resolution';
    if (strpos($lower, 'executive order') !== false) return 'executive order';
    if (strpos($lower, 'memorandum') !== false) return 'memorandum';
    return 'archive';
}

function guessDate($title) {
    if (preg_match('/20\d{2}/', $title, $m)) {
        return $m[0] . '-01-01';
    }
    return date('Y-m-d');
}

function makeRef($folder, $index) {
    $prefix = strtoupper($folder) === 'RES' ? 'SP-RES' : 'SP-ORD';
    return $prefix . '-2025-' . str_pad($index, 4, '0', STR_PAD_LEFT);
}

$inserted = 0;
$skipped = 0;

foreach (['RES', 'ORD'] as $folder) {
    $dir = $baseDir . '/' . $folder;
    if (!is_dir($dir)) {
        echo "SKIP: Directory not found: {$dir}\n";
        continue;
    }

    $files = glob($dir . '/*.pdf');
    $index = 1;

    foreach ($files as $filePath) {
        $fileName = basename($filePath);
        $title = cleanTitle($fileName);
        $docType = guessDocType($folder, $title);
        $docDate = guessDate($title);
        $ref = makeRef($folder, $index);
        $tags = strtolower($docType) . ',' . strtolower($folder);
        $description = $title;

        // Check if already exists by reference_number
        $chk = $conn->prepare("SELECT id FROM external_documents WHERE reference_number = ?");
        $chk->bind_param('s', $ref);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo "SKIP: '{$ref}' already exists.\n";
            $skipped++;
            $chk->close();
            $index++;
            continue;
        }
        $chk->close();

        // Copy file to uploads
        $destPath = $uploadDir . '/' . $fileName;
        if (!copy($filePath, $destPath)) {
            echo "ERROR: Failed to copy {$fileName}\n";
            continue;
        }

        $relativePath = 'uploads/external/2026-09/' . $fileName;
        $fileSize = filesize($destPath);
        $fileType = 'application/pdf';
        $status = 'pending';

        $stmt = $conn->prepare("INSERT INTO external_documents
            (title, document_type, document_date, status, description, tags,
             reference_number, file_path, file_name, file_size, file_type,
             mime_type, source_system, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $sourceSystem = 'llrm';
        $stmt->bind_param('sssssssssisss',
            $title, $docType, $docDate, $status, $description, $tags,
            $ref, $relativePath, $fileName, $fileSize, $fileType, $fileType, $sourceSystem
        );
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo "OK: ID={$id} | {$ref} | [{$folder}] {$title}\n";
        $inserted++;
        $index++;
    }
}

echo "\nDone! Inserted: {$inserted}, Skipped: {$skipped}\n";
$conn->close();

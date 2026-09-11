<?php
/**
 * Generate a mock PDF resolution and insert it into external_documents.
 * Run once: php seed-mock-pdf.php
 * Then delete this file.
 */
session_start();
require_once __DIR__ . '/../authdatabase.php';

// ── Generate a real PDF with municipal document text ─────────────────
$lines = [
    'Republic of the Philippines',
    'Province of Bulacan',
    'City of Valenzuela',
    'SANGGUNIANG PANLUNGSOD',
    '',
    'EXCERPTS FROM THE MINUTES OF THE REGULAR SESSION',
    'OF THE SANGGUNIANG PANLUNGSOD OF VALENZUELA',
    'HELD ON SEPTEMBER 8, 2026 AT THE CITY HALL',
    '',
    'Present:',
    'Hon. Mayor WEXIE Z. CHUA',
    'Hon. Vice Mayor LORY N. BORJA',
    'Hon. Counsel FRANCIS E. JAVIER',
    'Hon. Councilor RODRIGO L. DELA CRUZ',
    'Hon. Councilor MARIA T. SANTOS',
    'Hon. Councilor JOSEPH B. REYES',
    'Hon. Councilor ANNA L. FERNANDEZ',
    '',
    'RESOLUTION NO. 2026-0189',
    'Series of 2026',
    '',
    '"A RESOLUTION AUTHORIZING THE CITY MAYOR TO ENTER INTO',
    'A MEMORANDUM OF AGREEMENT (MOA) WITH THE DEPARTMENT OF',
    'EDUCATION (DepEd) REGION III FOR THE IMPLEMENTATION OF',
    'THE 2026 COMMUNITY-LED SCHOOL IMPROVEMENT PROGRAM"',
    '',
    'WHEREAS, Republic Act No. 9155, otherwise known as the',
    'Governance of Basic Education Act of 2001, provides for',
    'the decentralization of basic education governance;',
    '',
    'WHEREAS, the City Government of Valenzuela has consistently',
    'supported educational programs aimed at improving the quality',
    'of basic education in the city;',
    '',
    'WHEREAS, the Department of Education Region III has proposed',
    'the 2026 Community-Led School Improvement Program which',
    'seeks to enhance school facilities and learning outcomes;',
    '',
    'WHEREAS, the City Mayor has requested authorization from',
    'the Sangguniang Panlungsod to enter into a Memorandum of',
    'Agreement with DepEd Region III for the said program;',
    '',
    'WHEREAS, the program will benefit an estimated 15,400',
    'students across 23 public elementary and secondary schools;',
    '',
    'NOW, THEREFORE, BE IT RESOLVED, as it is hereby resolved,',
    'by the Sangguniang Panlungsod of Valenzuela in regular',
    'session assembled, to authorize the City Mayor to enter',
    'into a Memorandum of Agreement with the Department of',
    'Education Region III for the implementation of the 2026',
    'Community-Led School Improvement Program.',
    '',
    'RESOLVED FURTHER, that the MOA shall cover the period',
    'from October 1, 2026 to September 30, 2027, with a total',
    'project budget of Forty-Five Million Pesos (Php 45,000,000.00),',
    'to be sourced from the Special Education Fund (SEF) of the',
    'City Government of Valenzuela.',
    '',
    'RESOLVED FINALLY, that copies of this resolution be furnished',
    'to the Office of the City Mayor, the Department of Education',
    'Region III, the City Budget Office, and the City Accounting',
    'Office for their information and guidance.',
    '',
    'Unanimously approved this 8th day of September 2026.',
    '',
    'Certified Correct:',
    '',
    'ATTY. CRISTINA N. GARCIA',
    'City Secretary',
    '',
    'Approved:',
    '',
    'HON. WEXIE Z. CHUA',
    'City Mayor',
];

// ── Build minimal valid PDF ──────────────────────────────────────────
$pdfLines = [];
foreach ($lines as $line) {
    $pdfLines[] = '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ') Tj';
}

$content = "BT\n/F1 11 Tf\n72 720 Td\n" . implode("\n0 -16 Td\n", $pdfLines) . "\nET";

$objects = [];
$objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
$objects[4] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
$objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

$pdf = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $num => $obj) {
    $offsets[$num] = strlen($pdf);
    $pdf .= $obj;
}

$xref = strlen($pdf);
$pdf .= "xref\n";
$pdf .= "0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
foreach ($objects as $num => $obj) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
}

$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n{$xref}\n%%EOF";

// ── Save to disk ─────────────────────────────────────────────────────
$dir = __DIR__ . '/../uploads/external/2026-09';
if (!is_dir($dir)) mkdir($dir, 0775, true);

$fileName = '1757568000_mock_resolution_SP-2026-0189.pdf';
$filePath = $dir . '/' . $fileName;
file_put_contents($filePath, $pdf);

$relativePath = 'uploads/external/2026-09/' . $fileName;
$fileSize = strlen($pdf);

echo "PDF created: {$filePath} ({$fileSize} bytes)\n";

// ── Insert into database ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM external_documents WHERE reference_number = ?");
$ref = 'SP-2026-0189-RES';
$stmt->bind_param('s', $ref);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo "Record with reference_number '{$ref}' already exists. Skipping insert.\n";
    $stmt->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare("INSERT INTO external_documents
    (title, document_type, document_date, status, description, tags,
     reference_number, file_path, file_name, file_size, file_type,
     mime_type, source_system, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$title       = 'Resolution No. 2026-0189 - School Improvement Program';
$docType     = 'resolution';
$docDate     = '2026-09-08';
$status      = 'pending';
$description = 'Resolution authorizing the City Mayor to enter into a MOA with DepEd Region III for the 2026 Community-Led School Improvement Program.';
$tags        = 'resolution,education,deped,school improvement';
$fileType    = 'application/pdf';

$stmt->bind_param('sssssssssisss',
    $title, $docType, $docDate, $status, $description, $tags,
    $ref, $relativePath, $fileName, $fileSize, $fileType, $fileType, $docType
);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

echo "Inserted record ID: {$newId}\n";
echo "Reference: {$ref}\n";
echo "File: {$relativePath}\n";
echo "\nYou can now test the AI scan on this document (ID: {$newId}).\n";

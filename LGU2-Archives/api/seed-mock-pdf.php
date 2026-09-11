<?php
/**
 * Generate 5 mock PDFs and insert them into external_documents.
 * Includes two versions of the same resolution for version-comparison testing.
 * Run once: php seed-mock-pdf.php
 * Then delete this file.
 */
session_start();
require_once __DIR__ . '/../authdatabase.php';

$dir = __DIR__ . '/../uploads/external/2026-09';
if (!is_dir($dir)) mkdir($dir, 0775, true);

// ── Helper: build a minimal valid PDF from lines ─────────────────────
function buildPdf(array $lines) {
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
    return $pdf;
}

// ── Helper: save PDF + insert DB record ──────────────────────────────
function saveAndInsert($conn, $fileName, $lines, $title, $docType, $docDate, $ref, $description, $tags) {
    global $dir;
    $pdf = buildPdf($lines);
    $filePath = $dir . '/' . $fileName;
    file_put_contents($filePath, $pdf);
    $relativePath = 'uploads/external/2026-09/' . $fileName;
    $fileSize = strlen($pdf);

    // Skip if already exists
    $chk = $conn->prepare("SELECT id FROM external_documents WHERE reference_number = ?");
    $chk->bind_param('s', $ref);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo "SKIP: '{$ref}' already exists.\n";
        $chk->close();
        return;
    }
    $chk->close();

    $fileType = 'application/pdf';
    $stmt = $conn->prepare("INSERT INTO external_documents
        (title, document_type, document_date, status, description, tags,
         reference_number, file_path, file_name, file_size, file_type,
         mime_type, source_system, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $status = 'pending';
    $stmt->bind_param('sssssssssisss',
        $title, $docType, $docDate, $status, $description, $tags,
        $ref, $relativePath, $fileName, $fileSize, $fileType, $fileType, $docType
    );
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    echo "OK: ID={$id} | {$ref} | {$title}\n";
}

// ══════════════════════════════════════════════════════════════════════
// DOCUMENT 1: Traffic Management Resolution (Version 1)
// ══════════════════════════════════════════════════════════════════════
saveAndInsert($conn, '1757600001_traffic_resolution_v1.pdf', [
    'Republic of the Philippines',
    'Province of Bulacan',
    'City of Valenzuela',
    'SANGGUNIANG PANLUNGSOD',
    '',
    'RESOLUTION NO. 2026-0201',
    'Series of 2026',
    '',
    '"A RESOLUTION APPROVING THE REVISED TRAFFIC',
    'MANAGEMENT PLAN FOR THE VALENZUELA CITY CENTRAL',
    'BUSINESS DISTRICT"',
    '',
    'WHEREAS, the City Government of Valenzuela has identified',
    'increasing traffic congestion in the Central Business District;',
    '',
    'WHEREAS, a traffic study conducted in June 2026 revealed that',
    'average vehicle speed alongMcArthur Highway has dropped to',
    '12 kilometers per hour during peak hours;',
    '',
    'WHEREAS, the City Traffic Management Office (CTMO) has',
    'proposed a revised traffic management plan to address',
    'the congestion issues;',
    '',
    'WHEREAS, the plan includes the implementation of a one-way',
    'road system along P. Santos Street and Maysan Road, and the',
    'installation of 15 new traffic lights at key intersections;',
    '',
    'NOW, THEREFORE, BE IT RESOLVED, to approve the Revised',
    'Traffic Management Plan for the Valenzuela City Central',
    'Business District, as submitted by the CTMO.',
    '',
    'RESOLVED FURTHER, that the City Mayor is authorized to',
    'allocate Six Million Pesos (Php 6,000,000.00) from the',
    '2026 General Fund for the implementation of the plan.',
    '',
    'Approved this 15th day of September 2026.',
    '',
    'Certified Correct:',
    'ATTY. CRISTINA N. GARCIA',
    'City Secretary',
    '',
    'Approved:',
    'HON. WEXIE Z. CHUA',
    'City Mayor',
], 'Resolution No. 2026-0201 - Traffic Management Plan', 'resolution',
   '2026-09-15', 'SP-2026-0201-RES',
   'Resolution approving the revised traffic management plan for the Valenzuela City Central Business District.',
   'resolution,traffic,infrastructure');

// ══════════════════════════════════════════════════════════════════════
// DOCUMENT 2: Traffic Management Resolution (Version 2 — amended)
// Same document with modifications for version-comparison testing.
// ══════════════════════════════════════════════════════════════════════
saveAndInsert($conn, '1757600002_traffic_resolution_v2.pdf', [
    'Republic of the Philippines',
    'Province of Bulacan',
    'City of Valenzuela',
    'SANGGUNIANG PANLUNGSOD',
    '',
    'RESOLUTION NO. 2026-0201',
    'Series of 2026',
    '',
    '"A RESOLUTION APPROVING THE REVISED TRAFFIC',
    'MANAGEMENT PLAN FOR THE VALENZUELA CITY CENTRAL',
    'BUSINESS DISTRICT"',
    '',
    'WHEREAS, the City Government of Valenzuela has identified',
    'increasing traffic congestion in the Central Business District;',
    '',
    'WHEREAS, a traffic study conducted in June 2026 revealed that',
    'average vehicle speed along McArthur Highway has dropped to',
    '12 kilometers per hour during peak hours;',
    '',
    'WHEREAS, the City Traffic Management Office (CTMO) has',
    'proposed a revised traffic management plan to address',
    'the congestion issues;',
    '',
    'WHEREAS, the plan includes the implementation of a one-way',
    'road system along P. Santos Street and Maysan Road, the',
    'installation of 20 new traffic lights at key intersections,',
    'and the creation of dedicated bicycle lanes;',
    '',
    'WHEREAS, the Sangguniang Panlungsod has reviewed and',
    'amended the original proposal to include provisions for',
    'pedestrian safety and accessibility for persons with',
    'disabilities (PWDs);',
    '',
    'NOW, THEREFORE, BE IT RESOLVED, to approve the Revised',
    'Traffic Management Plan for the Valenzuela City Central',
    'Business District, as amended by the Sangguniang Panlungsod.',
    '',
    'RESOLVED FURTHER, that the City Mayor is authorized to',
    'allocate Eight Million Pesos (Php 8,000,000.00) from the',
    '2026 General Fund for the implementation of the plan.',
    '',
    'RESOLVED FINALLY, that the CTMO shall submit quarterly',
    'progress reports to the Sangguniang Panlungsod starting',
    'January 2027.',
    '',
    'Approved this 22nd day of October 2026.',
    '',
    'Certified Correct:',
    'ATTY. CRISTINA N. GARCIA',
    'City Secretary',
    '',
    'Approved:',
    'HON. WEXIE Z. CHUA',
    'City Mayor',
], 'Resolution No. 2026-0201 (Amended) - Traffic Management Plan', 'resolution',
   '2026-10-22', 'SP-2026-0201-RES-V2',
   'Amended resolution approving the revised traffic management plan with added bicycle lanes and PWD provisions.',
   'resolution,traffic,infrastructure,amended');

// ══════════════════════════════════════════════════════════════════════
// DOCUMENT 3: Ordinance on Waste Segregation
// ══════════════════════════════════════════════════════════════════════
saveAndInsert($conn, '1757600003_waste_ordinance.pdf', [
    'Republic of the Philippines',
    'Province of Bulacan',
    'City of Valenzuela',
    'SANGGUNIANG PANLUNGSOD',
    '',
    'ORDINANCE NO. 08, Series of 2026',
    '',
    '"AN ORDINANCE MANDATING STRICT WASTE SEGREGATION',
    'IN ALL HOUSEHOLDS AND ESTABLISHMENTS IN THE CITY',
    'OF VALENZUELA, AND IMPOSING PENALTIES FOR',
    'VIOLATIONS THEREOF"',
    '',
    'WHEREAS, Republic Act No. 9003, the Ecological Solid',
    'Waste Management Act of 2000, mandates all local',
    'government units to implement solid waste management;',
    '',
    'WHEREAS, the City of Valenzuela has been experiencing',
    'difficulty in waste disposal due to non-segregation',
    'at source by households and commercial establishments;',
    '',
    'WHEREAS, this ordinance seeks to enforce strict waste',
    'segregation into biodegradable, non-biodegradable,',
    'and hazardous categories;',
    '',
    'NOW, THEREFORE, be it ordained by the Sangguniang',
    'Panlungsod of Valenzuela as follows:',
    '',
    'SECTION 1. All households and establishments shall',
    'segregate their waste into three categories:',
    '(a) Biodegradable (green bin)',
    '(b) Non-biodegradable (blue bin)',
    '(c) Hazardous (red bin)',
    '',
    'SECTION 2. First offense: Written warning.',
    'Second offense: Fine of One Thousand Pesos (Php 1,000).',
    'Third offense: Fine of Three Thousand Pesos (Php 3,000)',
    'and/or community service of eight (8) hours.',
    '',
    'SECTION 3. This ordinance shall take effect fifteen (15)',
    'days after its publication in the Official Gazette.',
    '',
    'Enacted this 10th day of September 2026.',
    '',
    'Certified Correct:',
    'ATTY. CRISTINA N. GARCIA',
    'City Secretary',
], 'Ordinance No. 08 - Mandatory Waste Segregation', 'ordinance',
   '2026-09-10', 'SP-2026-08-ORD',
   'Ordinance mandating strict waste segregation in all households and establishments with penalty provisions.',
   'ordinance,waste,environment,segregation');

// ══════════════════════════════════════════════════════════════════════
// DOCUMENT 4: Executive Order on Emergency Response
// ══════════════════════════════════════════════════════════════════════
saveAndInsert($conn, '1757600004_emergency_exec_order.pdf', [
    'Republic of the Philippines',
    'Province of Bulacan',
    'Office of the City Mayor',
    'City of Valenzuela',
    '',
    'EXECUTIVE ORDER NO. 2026-042',
    '',
    '"CREATING THE VALENZUELA CITY INTER-AGENCY',
    'DISASTER PREPAREDNESS TASK FORCE AND',
    'DEFINING ITS FUNCTIONS AND RESPONSIBILITIES"',
    '',
    'WHEREAS, the City of Valenzuela is prone to flooding',
    'and other natural calamities due to its geographic',
    'location along the Marikina River Basin;',
    '',
    'WHEREAS, the Philippine Disaster Risk Reduction and',
    'Management Act of 2010 (RA 10121) requires all LGUs',
    'to establish local disaster risk reduction and',
    'management councils;',
    '',
    'WHEREAS, recent flooding events in 2025 displaced',
    'over 3,200 families across 12 barangays;',
    '',
    'NOW, THEREFORE, I, WEXIE Z. CHUA, City Mayor of',
    'Valenzuela, do hereby order:',
    '',
    'SECTION 1. There is hereby created the Valenzuela',
    'City Inter-Agency Disaster Preparedness Task Force.',
    '',
    'SECTION 2. The Task Force shall be composed of:',
    'Chairperson: City Mayor',
    'Vice Chairperson: City Administrator',
    'Members: City Health Officer, City Engineer,',
    'Chief of BFP-Valenzuela, Chief of PNP-Valenzuela,',
    'MDRRMO Head, DSWD City Coordinator.',
    '',
    'SECTION 3. The Task Force shall:',
    '(a) Develop and update the City DRRMP annually;',
    '(b) Conduct quarterly disaster simulation exercises;',
    '(c) Maintain pre-positioned relief supplies for 5,000 families;',
    '(d) Establish evacuation centers in all 32 barangays.',
    '',
    'SECTION 4. This Executive Order shall take effect',
    'immediately upon issuance.',
    '',
    'Issued this 1st day of October 2026.',
    '',
    'WEXIE Z. CHUA',
    'City Mayor',
], 'Executive Order No. 2026-042 - Disaster Preparedness Task Force', 'executive order',
   '2026-10-01', 'EO-2026-042',
   'Executive order creating the Valenzuela City Inter-Agency Disaster Preparedness Task Force.',
   'executive order,disaster,emergency,preparedness');

// ══════════════════════════════════════════════════════════════════════
// DOCUMENT 5: Memorandum on Employee Training
// ══════════════════════════════════════════════════════════════════════
saveAndInsert($conn, '1757600005_training_memorandum.pdf', [
    'Republic of the Philippines',
    'Province of Bulacan',
    'City of Valenzuela',
    'OFFICE OF THE CITY MAYOR',
    '',
    'MEMORANDUM NO. 2026-087',
    '',
    'TO: All Department Heads and Section Chiefs',
    'FROM: Office of the City Mayor',
    'DATE: September 20, 2026',
    'SUBJECT: MANDATORY CYBERSECURITY AWARENESS',
    'TRAINING FOR ALL CITY GOVERNMENT EMPLOYEES',
    '',
    '1. PURPOSE',
    'This memorandum requires all City Government employees',
    'to complete the Mandatory Cybersecurity Awareness Training',
    'program in compliance with DICT Memorandum Circular',
    'No. 2026-003.',
    '',
    '2. SCOPE',
    'All permanent, casual, and contractual employees of the',
    'City Government of Valenzuela, totaling approximately',
    '1,450 personnel across all departments.',
    '',
    '3. TRAINING DETAILS',
    'Period: October 1-31, 2026',
    'Platform: DICT CyberLearn Online Portal',
    'Duration: 4 hours (self-paced)',
    'Modules: (a) Phishing Awareness',
    '(b) Password Management',
    '(c) Data Protection and Privacy',
    '(d) Incident Reporting Procedures',
    '',
    '4. COMPLIANCE',
    'Department Heads shall submit a consolidated list of',
    'employees who have completed the training to the HRMO',
    'on or before November 7, 2026. Non-compliant employees',
    'may face administrative sanctions per Civil Service',
    'Commission Resolution No. 26-001.',
    '',
    '5. FUNDS',
    'The City Budget Office is directed to allocate One',
    'Hundred Fifty Thousand Pesos (Php 150,000.00) for',
    'training platform licensing and coordination.',
    '',
    'WEXIE Z. CHUA',
    'City Mayor',
], 'Memorandum No. 2026-087 - Cybersecurity Training', 'memorandum',
   '2026-09-20', 'MEMO-2026-087',
   'Memorandum requiring mandatory cybersecurity awareness training for all city government employees.',
   'memorandum,training,cybersecurity,employees');

echo "\nDone! 5 mock PDFs created and inserted.\n";

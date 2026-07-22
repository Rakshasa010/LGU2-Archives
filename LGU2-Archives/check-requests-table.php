<?php
/**
 * Check Requests Table Structure
 * Verifies if all required columns exist
 */

session_start();
require 'authdatabase.php';

header('Content-Type: application/json');

try {
    // Check if requests table exists
    $result = $conn->query("SHOW TABLES LIKE 'requests'");
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Table "requests" does not exist',
            'fix' => 'Run the database migration to create the table'
        ]);
        exit;
    }
    
    // Get all columns in requests table
    $columns = $conn->query("DESCRIBE requests");
    
    $existingColumns = [];
    while ($row = $columns->fetch_assoc()) {
        $existingColumns[$row['Field']] = $row['Type'];
    }
    
    // Required columns for export fulfillment
    $requiredColumns = [
        'id' => 'int',
        'staged_file_id' => 'varchar',
        'staged_file_name' => 'varchar',
        'staged_file_size' => 'int',
        'status' => 'varchar',
        'fulfilled_at' => 'datetime'
    ];
    
    $missingColumns = [];
    $presentColumns = [];
    
    foreach ($requiredColumns as $colName => $colType) {
        if (isset($existingColumns[$colName])) {
            $presentColumns[] = [
                'name' => $colName,
                'type' => $existingColumns[$colName],
                'status' => '✅ EXISTS'
            ];
        } else {
            $missingColumns[] = [
                'name' => $colName,
                'type' => $colType,
                'status' => '❌ MISSING'
            ];
        }
    }
    
    $needsFix = count($missingColumns) > 0;
    
    echo json_encode([
        'success' => !$needsFix,
        'table_exists' => true,
        'total_columns' => count($existingColumns),
        'present_columns' => $presentColumns,
        'missing_columns' => $missingColumns,
        'needs_fix' => $needsFix,
        'fix_sql' => $needsFix ? generateFixSQL($missingColumns) : null,
        'all_columns' => $existingColumns
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateFixSQL($missingColumns) {
    $sql = "ALTER TABLE requests\n";
    $alterStatements = [];
    
    foreach ($missingColumns as $col) {
        switch ($col['name']) {
            case 'staged_file_id':
                $alterStatements[] = "ADD COLUMN staged_file_id VARCHAR(100) NULL";
                break;
            case 'staged_file_name':
                $alterStatements[] = "ADD COLUMN staged_file_name VARCHAR(255) NULL";
                break;
            case 'staged_file_size':
                $alterStatements[] = "ADD COLUMN staged_file_size INT NULL";
                break;
            case 'fulfilled_at':
                $alterStatements[] = "ADD COLUMN fulfilled_at DATETIME NULL";
                break;
        }
    }
    
    $sql .= implode(",\n", $alterStatements) . ";";
    return $sql;
}
?>

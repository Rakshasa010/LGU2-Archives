-- Export Request Fulfillment Flow Database Setup
-- Run this migration to ensure all required tables and columns exist

-- ==================== REQUESTS TABLE UPDATES ====================
-- Add staging columns if they don't exist
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_name VARCHAR(255) DEFAULT NULL;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_size INT DEFAULT NULL;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS fulfilled_at TIMESTAMP NULL DEFAULT NULL;

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_staged ON requests(staged_file_id);
CREATE INDEX IF NOT EXISTS idx_requests_fulfilled ON requests(fulfilled_at);

-- ==================== ARCHIVE FILES TABLE ====================
-- Ensure archive_files table exists with required columns
CREATE TABLE IF NOT EXISTS archive_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    file_path VARCHAR(500) NOT NULL,
    archive_folder_id INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    version VARCHAR(50) DEFAULT '1.0',
    created_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_archive_files_folder (archive_folder_id),
    INDEX idx_archive_files_name (file_name),
    INDEX idx_archive_files_type (file_type)
);

-- ==================== ARCHIVE FOLDERS TABLE ====================
-- Ensure archive_folders table exists
CREATE TABLE IF NOT EXISTS archive_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    parent_folder_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_archive_folders_slug (slug),
    INDEX idx_archive_folders_parent (parent_folder_id)
);

-- ==================== AUDIT LOGS TABLE ====================
-- Create comprehensive audit logging table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    file_id INT,
    request_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_request (request_id),
    INDEX idx_audit_timestamp (timestamp)
);

-- ==================== STAGING DIRECTORY PERMISSIONS ====================
-- Note: Run these commands in shell, not in SQL
-- mkdir -p storage/temp_exports
-- chmod 755 storage/temp_exports
-- chown www-data:www-data storage/temp_exports (on Linux)

-- ==================== SAMPLE DATA (Optional) ====================
-- Uncomment to add test data

-- INSERT INTO archive_folders (name, slug, description) VALUES
-- ('Ordinances', 'ordinances', 'City ordinances and resolutions'),
-- ('Meeting Records', 'meeting-records', 'Council meeting minutes and records'),
-- ('Public Hearings', 'public-hearings', 'Public hearing records'),
-- ('Billing', 'billing', 'Billing and budget documents');

-- INSERT INTO archive_files (file_name, file_type, file_size, file_path, archive_folder_id, version) VALUES
-- ('Zoning_Ordinance_2023.pdf', 'application/pdf', 2048576, 'ordinances/zoning_2023.pdf', 1, '2.1'),
-- ('Council_Minutes_2026_07.pdf', 'application/pdf', 1500000, 'meeting-records/2026_07_minutes.pdf', 2, '1.0');

-- ==================== VERIFICATION QUERIES ====================
-- Run these to verify setup

-- Check requests table columns
-- DESCRIBE requests;
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='requests' AND COLUMN_NAME IN ('staged_file_id', 'staged_file_name', 'staged_file_size', 'fulfilled_at');

-- Check archive_files table
-- SELECT COUNT(*) as file_count FROM archive_files;

-- Check archive_folders table
-- SELECT COUNT(*) as folder_count FROM archive_folders;

-- Check audit_logs table
-- SELECT COUNT(*) as audit_count FROM audit_logs;

-- Verify temp_exports directory exists
-- (Check manually: ls -la storage/temp_exports/)

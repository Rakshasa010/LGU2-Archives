-- LGU2 Archives Database Setup
-- Generated from authdatabase.php
-- Date: December 17, 2025

-- Create database
CREATE DATABASE IF NOT EXISTS las_lgu2_archives;

-- Use the database
USE las_lgu2_archives;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Create legislative_records table
CREATE TABLE legislative_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year YEAR NOT NULL,
    author VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL
);

-- Create analytics_events table (for Reports & Analytics activity tracking)
CREATE TABLE IF NOT EXISTS analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(32) NOT NULL,
    user_id INT(11) NULL,
    record_id INT(11) NULL,
    record_title VARCHAR(255) NULL,
    record_type VARCHAR(50) NULL,
    download_format VARCHAR(16) NULL,
    bytes BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Insert mock data
INSERT INTO legislative_records (title, type, month, year, author) VALUES
-- Existing Legislative Records
('Ordinance No. 2025-001 - Zoning Regulations Update', 'Ordinance', 'January', '2025', 'Councilor Maria Santos'),
('Resolution No. 2025-042 - Budget Allocation for Infrastructure', 'Resolution', 'January', '2025', 'Councilor John Dela Cruz'),
('Regular Session - January 15, 2025', 'Legislative Session', 'January', '2025', 'City Council'),
('Public Hearing - Environmental Impact Assessment', 'Public Hearing', 'January', '2025', 'Environmental Committee'),
('Ordinance No. 2025-015 - Business Permit Requirements', 'Ordinance', 'February', '2025', 'Councilor Robert Tan'),
('Special Session - Emergency Response Planning', 'Legislative Session', 'February', '2025', 'City Council'),
('Resolution No. 2024-156 - Community Development Fund', 'Resolution', 'December', '2024', 'Councilor Maria Santos'),
('Ordinance No. 2024-089 - Traffic Management System', 'Ordinance', 'December', '2024', 'Councilor Anna Garcia'),
('Public Hearing - Urban Planning Proposal', 'Public Hearing', 'December', '2024', 'Planning Committee'),
('Regular Session - December 10, 2024', 'Legislative Session', 'December', '2024', 'City Council'),
('Resolution No. 2024-142 - Education Support Program', 'Resolution', 'November', '2024', 'Councilor John Dela Cruz'),
('Ordinance No. 2024-075 - Waste Management Policy', 'Ordinance', 'November', '2024', 'Councilor Robert Tan'),
('Public Hearing - Housing Development Project', 'Public Hearing', 'November', '2024', 'Housing Committee'),
('Regular Session - November 20, 2024', 'Legislative Session', 'November', '2024', 'City Council'),
('Resolution No. 2024-098 - Health Services Enhancement', 'Resolution', 'October', '2024', 'Councilor Maria Santos'),
('Ordinance No. 2024-068 - Building Code Amendments', 'Ordinance', 'November', '2024', 'Councilor Anna Garcia'),
('Special Meeting - Budget Review', 'Meeting', 'October', '2024', 'Finance Committee'),
('Public Hearing - Transportation Network', 'Public Hearing', 'October', '2024', 'Transportation Committee'),

-- Additional Billing Documents
('Billing Statement - January 2025 Utilities', 'Billing', 'January', '2025', 'Finance Department'),
('Monthly Revenue Report - December 2024', 'Billing', 'December', '2024', 'Treasurer\'s Office'),
('Property Tax Assessment - Q4 2024', 'Billing', 'December', '2024', 'Assessor\'s Office'),
('Business License Fees - Annual Report 2024', 'Billing', 'December', '2024', 'Business Permits Division'),
('Water & Sewer Billing - November 2024', 'Billing', 'November', '2024', 'Public Works Department'),
('Parking Violation Fines - October 2024', 'Billing', 'October', '2024', 'Traffic Enforcement'),

-- Additional Public Hearings
('Public Hearing - Budget Proposal 2025', 'Public Hearing', 'November', '2024', 'Finance Committee'),
('Public Hearing - Zoning Variance Request #2024-045', 'Public Hearing', 'October', '2024', 'Planning & Zoning Board'),
('Public Hearing - Liquor License Application - Downtown Bar', 'Public Hearing', 'September', '2024', 'Licensing Board'),
('Public Hearing - Street Improvement Project', 'Public Hearing', 'September', '2024', 'Public Works Committee'),
('Public Hearing - School Bond Referendum', 'Public Hearing', 'August', '2024', 'Education Committee'),

-- Additional Meeting Records
('City Council Regular Meeting - September 18, 2024', 'Meeting', 'September', '2024', 'City Clerk'),
('Planning Commission Meeting - August 22, 2024', 'Meeting', 'August', '2024', 'Planning Department'),
('Finance Committee Special Meeting - July 15, 2024', 'Meeting', 'July', '2024', 'Finance Director'),
('Public Safety Committee Meeting - June 10, 2024', 'Meeting', 'June', '2024', 'Police Chief'),
('Economic Development Board Meeting - May 28, 2024', 'Meeting', 'May', '2024', 'Economic Development Director'),

-- Additional Ordinances
('Ordinance No. 2024-055 - Noise Control Regulations', 'Ordinance', 'September', '2024', 'Councilor Lisa Wong'),
('Ordinance No. 2024-042 - Historic Preservation Guidelines', 'Ordinance', 'August', '2024', 'Councilor David Chen'),
('Ordinance No. 2024-038 - Stormwater Management', 'Ordinance', 'July', '2024', 'Councilor Sarah Johnson'),
('Ordinance No. 2024-025 - Animal Control Ordinance', 'Ordinance', 'June', '2024', 'Councilor Michael Brown'),

-- Additional Resolutions
('Resolution No. 2024-089 - Sister City Agreement with Manila', 'Resolution', 'September', '2024', 'Councilor Maria Santos'),
('Resolution No. 2024-076 - Climate Action Plan Adoption', 'Resolution', 'August', '2024', 'Councilor Anna Garcia'),
('Resolution No. 2024-063 - Veterans Memorial Dedication', 'Resolution', 'July', '2024', 'Councilor John Dela Cruz'),
('Resolution No. 2024-051 - Youth Council Establishment', 'Resolution', 'June', '2024', 'Councilor Robert Tan');

-- Set character set for better Unicode support
ALTER TABLE legislative_records CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create indexes for better search performance
CREATE INDEX idx_title ON legislative_records(title);
CREATE INDEX idx_type ON legislative_records(type);
CREATE INDEX idx_month_year ON legislative_records(month, year);
CREATE INDEX idx_author ON legislative_records(author);
CREATE INDEX idx_created_at ON legislative_records(created_at);
CREATE INDEX idx_last_accessed ON legislative_records(last_accessed);
CREATE INDEX idx_file_path ON legislative_records(file_path);

-- Indexes for analytics_events
CREATE INDEX idx_event_type_created ON analytics_events(event_type, created_at);
CREATE INDEX idx_ae_created_at ON analytics_events(created_at);
CREATE INDEX idx_ae_record_type ON analytics_events(record_type);
CREATE INDEX idx_ae_download_format ON analytics_events(download_format);

-- Display completion message
SELECT 'Database setup completed successfully!' as status;

-- Notifications table for Audit Logs
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    content VARCHAR(255) NOT NULL,
    about VARCHAR(100) NOT NULL,
    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
    user_id INT NULL,
    role VARCHAR(20) NULL,
    record_id INT NULL,
    link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed mock notifications (if table is empty)
INSERT INTO notifications (time, date, content, about, status)
SELECT * FROM (
    SELECT '10:00 AM' AS time, '2026-01-19' AS date, 'New document uploaded: Ordinance No. 123' AS content, 'Document Upload' AS about, 'unread' AS status UNION ALL
    SELECT '11:00 AM', '2026-01-19', 'System update completed', 'System Maintenance', 'read' UNION ALL
    SELECT '11:30 AM', '2026-01-19', 'New user registered: Juan Dela Cruz', 'User Registration', 'unread' UNION ALL
    SELECT '12:15 PM', '2026-01-19', 'Document approved: Resolution No. 456', 'Approval', 'read' UNION ALL
    SELECT '01:02 PM', '2026-01-19', 'Profile picture updated for Maria', 'Profile Update', 'unread' UNION ALL
    SELECT '02:20 PM', '2026-01-18', 'User permissions changed for user #34', 'Permissions', 'read' UNION ALL
    SELECT '03:45 PM', '2026-01-17', 'New comment on Ordinance No. 78', 'Comment', 'read' UNION ALL
    SELECT '04:10 PM', '2026-01-16', 'Scheduled backup completed', 'Backup', 'read' UNION ALL
    SELECT '08:00 AM', '2026-01-15', 'Batch import finished (25 records)', 'Import', 'unread' UNION ALL
    SELECT '09:30 AM', '2026-01-14', 'Access revoked for user #12', 'Security', 'read' UNION ALL
    SELECT '10:15 AM', '2026-01-13', 'Tagging updated for 3 documents', 'Metadata', 'read' UNION ALL
    SELECT '11:50 AM', '2026-01-12', 'New message from admin', 'Message', 'unread'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM notifications LIMIT 1);

CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
CREATE INDEX IF NOT EXISTS idx_notifications_date ON notifications(date);
CREATE INDEX IF NOT EXISTS idx_notifications_about ON notifications(about);
CREATE INDEX IF NOT EXISTS idx_notifications_user_role ON notifications(user_id, role);

CREATE TABLE IF NOT EXISTS analytics_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    record_id INT NULL,
    record_title VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

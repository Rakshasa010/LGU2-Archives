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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    dark_mode TINYINT(1) NOT NULL DEFAULT 0
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
INSERT INTO notifications (time, date, content, about, user_name, status)
SELECT * FROM (
    SELECT '10:00 AM' AS time, '2026-01-19' AS date, 'New document uploaded: Ordinance No. 123' AS content, 'Document Upload' AS about, NULL AS user_name, 'unread' AS status UNION ALL
    SELECT '11:00 AM', '2026-01-19', 'System update completed', 'System Maintenance', NULL, 'read' UNION ALL
    SELECT '11:30 AM', '2026-01-19', 'New user registered: Juan Dela Cruz', 'User Registration', NULL, 'unread' UNION ALL
    SELECT '12:15 PM', '2026-01-19', 'Document approved: Resolution No. 456', 'Approval', NULL, 'read' UNION ALL
    SELECT '01:02 PM', '2026-01-19', 'Profile picture updated for Maria', 'Profile Update', NULL, 'unread' UNION ALL
    SELECT '02:20 PM', '2026-01-18', 'User permissions changed for user #34', 'Permissions', NULL, 'read' UNION ALL
    SELECT '03:45 PM', '2026-01-17', 'New comment on Ordinance No. 78', 'Comment', NULL, 'read' UNION ALL
    SELECT '04:10 PM', '2026-01-16', 'Scheduled backup completed', 'Backup', NULL, 'read' UNION ALL
    SELECT '08:00 AM', '2026-01-15', 'Batch import finished (25 records)', 'Import', NULL, 'unread' UNION ALL
    SELECT '09:30 AM', '2026-01-14', 'Access revoked for user #12', 'Security', NULL, 'read' UNION ALL
    SELECT '10:15 AM', '2026-01-13', 'Tagging updated for 3 documents', 'Metadata', NULL, 'read' UNION ALL
    SELECT '11:50 AM', '2026-01-12', 'New message from admin', 'Message', NULL, 'unread'
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

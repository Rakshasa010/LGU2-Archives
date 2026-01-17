-- LGU2 Archives Database Setup
-- Generated from authdatabase.php
-- Date: December 17, 2025

-- Create database
CREATE DATABASE IF NOT EXISTS lgu2_archives;

-- Use the database
USE lgu2_archives;

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

-- Display completion message
SELECT 'Database setup completed successfully!' as status;
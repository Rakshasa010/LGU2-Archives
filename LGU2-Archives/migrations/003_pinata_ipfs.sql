-- =============================================================================
-- 003_pinata_ipfs.sql — Pinata Cloud (IPFS) integration
-- Adds the Pinata CID + MIME type columns to the archive document tables.
--
-- NOTE: authdatabase.php auto-migrates these columns idempotently at runtime,
-- so applying this file manually is optional. Run it only if you prefer
-- applying schema changes via SQL:
--   mysql -u <user> -p las_lgu2_archives < 003_pinata_ipfs.sql
--
-- This file targets plain MySQL syntax (no "IF NOT EXISTS", which is
-- MariaDB-only). If a column already exists the ALTER will simply error, which
-- is safe to ignore.
-- =============================================================================

USE las_lgu2_archives;

ALTER TABLE archive_files
    ADD COLUMN ipfs_cid VARCHAR(255) DEFAULT NULL,
    ADD COLUMN mime_type VARCHAR(100) DEFAULT NULL;

ALTER TABLE legislative_records
    ADD COLUMN ipfs_cid VARCHAR(255) DEFAULT NULL,
    ADD COLUMN mime_type VARCHAR(100) DEFAULT NULL;

ALTER TABLE external_documents
    ADD COLUMN ipfs_cid VARCHAR(255) DEFAULT NULL,
    ADD COLUMN mime_type VARCHAR(100) DEFAULT NULL;

-- Indexes for fast lookups by IPFS CID
CREATE INDEX idx_archive_files_ipfs_cid ON archive_files(ipfs_cid);
CREATE INDEX idx_legislative_records_ipfs_cid ON legislative_records(ipfs_cid);
CREATE INDEX idx_external_documents_ipfs_cid ON external_documents(ipfs_cid);

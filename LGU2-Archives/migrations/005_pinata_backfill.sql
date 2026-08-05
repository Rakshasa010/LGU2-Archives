-- =============================================================================
-- 005_pinata_backfill.sql — Pinata groups backfill support columns
-- Tracks which records have already been added to their Pinata group so the
-- backfill script (backfill_pinata_groups.php) is idempotent and resumable.
--
--   pinata_file_id : the Pinata file id (data.id) resolved for this record's CID
--   pinata_grouped : 0 = pending, 1 = done (added to its folder group),
--                    2 = CID no longer exists on Pinata (checked, skipped)
--
-- NOTE: authdatabase.php auto-migrates these columns idempotently at runtime,
-- so applying this file manually is optional. Run it only if you prefer
-- applying schema changes via SQL:
--   mysql -u <user> -p las_lgu2_archives < 005_pinata_backfill.sql
--
-- This file targets plain MySQL syntax (no "IF NOT EXISTS", which is
-- MariaDB-only). If a column already exists the ALTER will simply error, which
-- is safe to ignore.
-- =============================================================================

USE las_lgu2_archives;

ALTER TABLE archive_files
    ADD COLUMN pinata_file_id VARCHAR(64) DEFAULT NULL,
    ADD COLUMN pinata_grouped TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE legislative_records
    ADD COLUMN pinata_file_id VARCHAR(64) DEFAULT NULL,
    ADD COLUMN pinata_grouped TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE external_documents
    ADD COLUMN pinata_file_id VARCHAR(64) DEFAULT NULL,
    ADD COLUMN pinata_grouped TINYINT(1) NOT NULL DEFAULT 0;

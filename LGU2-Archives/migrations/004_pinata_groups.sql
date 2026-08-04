-- =============================================================================
-- 004_pinata_groups.sql — Per-folder Pinata groups
-- Caches the Pinata group id on each folder row so uploads can auto-add files
-- to a matching "LAS/<folder name>" group without re-searching by name.
--
-- NOTE: authdatabase.php auto-migrates these columns idempotently at runtime,
-- so applying this file manually is optional. Run it only if you prefer
-- applying schema changes via SQL:
--   mysql -u <user> -p las_lgu2_archives < 004_pinata_groups.sql
--
-- This file targets plain MySQL syntax (no "IF NOT EXISTS", which is
-- MariaDB-only). If a column already exists the ALTER will simply error, which
-- is safe to ignore.
-- =============================================================================

USE las_lgu2_archives;

ALTER TABLE archive_folders
    ADD COLUMN pinata_group_id VARCHAR(64) DEFAULT NULL;

ALTER TABLE legislative_folders
    ADD COLUMN pinata_group_id VARCHAR(64) DEFAULT NULL;

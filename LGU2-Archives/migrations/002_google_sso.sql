-- =============================================================================
-- Migration 002: Google OAuth (Sign in with Google)
-- Adds columns to the `users` table for storing Google identity data.
-- Idempotent: safe to run multiple times.
--
-- How to run:
--   mysql -u root -p las_lgu2_archives < 002_google_sso.sql
--   (authdatabase.php also auto-creates these columns on every request,
--    so this file is optional but kept for explicit/CI deploys.)
-- =============================================================================

USE las_lgu2_archives;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'users'
                      AND COLUMN_NAME  = 'google_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'users'
                      AND COLUMN_NAME  = 'google_picture');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN google_picture VARCHAR(500) NULL AFTER google_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill google_id for existing rows where email matches a previously
-- auto-registered Google account (email prefixed with the old mock pattern).
UPDATE users
SET google_id = CONCAT('legacy_', MD5(email))
WHERE google_id IS NULL
  AND username LIKE 'google_%';

-- Migration Script: Confirm-checklist storage columns (idempotent)
-- Date: 2026-03-19
-- Purpose:
--  - Ensure COA/checklist storage columns exist across voucher tables.
--  - Optionally backfill coa_category/coa_subsection to voucher_type when coa_options exists.
--
-- Notes:
--  - Uses INFORMATION_SCHEMA checks for compatibility (MySQL/MariaDB).
--  - Run on the target database (select the DB first: USE dvsdb;).

-- =========
-- Helpers
-- =========

SET @db := DATABASE();

-- =========
-- Ensure columns exist
-- =========

-- vouchers
SET @tbl := 'vouchers';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''vouchers.coa_options exists'';',
    'ALTER TABLE `vouchers` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''vouchers.coa_category exists'';',
    'ALTER TABLE `vouchers` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''vouchers.coa_subsection exists'';',
    'ALTER TABLE `vouchers` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_incoming
SET @tbl := 'voucher_incoming';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_incoming.coa_options exists'';',
    'ALTER TABLE `voucher_incoming` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_incoming.coa_category exists'';',
    'ALTER TABLE `voucher_incoming` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_incoming.coa_subsection exists'';',
    'ALTER TABLE `voucher_incoming` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_receiving
SET @tbl := 'voucher_receiving';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_receiving.coa_options exists'';',
    'ALTER TABLE `voucher_receiving` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_receiving.coa_category exists'';',
    'ALTER TABLE `voucher_receiving` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_receiving.coa_subsection exists'';',
    'ALTER TABLE `voucher_receiving` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_sent
SET @tbl := 'voucher_sent';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_sent.coa_options exists'';',
    'ALTER TABLE `voucher_sent` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_sent.coa_category exists'';',
    'ALTER TABLE `voucher_sent` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_sent.coa_subsection exists'';',
    'ALTER TABLE `voucher_sent` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_tracking
SET @tbl := 'voucher_tracking';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_tracking.coa_options exists'';',
    'ALTER TABLE `voucher_tracking` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_tracking.coa_category exists'';',
    'ALTER TABLE `voucher_tracking` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_tracking.coa_subsection exists'';',
    'ALTER TABLE `voucher_tracking` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_archives
SET @tbl := 'voucher_archives';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_archives.coa_options exists'';',
    'ALTER TABLE `voucher_archives` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_archives.coa_category exists'';',
    'ALTER TABLE `voucher_archives` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_archives.coa_subsection exists'';',
    'ALTER TABLE `voucher_archives` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- voucher_action_logs
SET @tbl := 'voucher_action_logs';
SET @col := 'coa_options';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_action_logs.coa_options exists'';',
    'ALTER TABLE `voucher_action_logs` ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT ''JSON string of confirmed checklist requirements'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_category';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_action_logs.coa_category exists'';',
    'ALTER TABLE `voucher_action_logs` ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / category label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'coa_subsection';
SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col),
    'SELECT ''voucher_action_logs.coa_subsection exists'';',
    'ALTER TABLE `voucher_action_logs` ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT ''Compatibility: voucher type / subsection label'';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========
-- Optional backfill (safe defaults)
-- =========
-- If older rows have coa_options but missing category/subsection, default them to voucher_type.

UPDATE `vouchers`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_incoming`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_receiving`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_sent`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_tracking`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_archives`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

UPDATE `voucher_action_logs`
SET `coa_category` = `voucher_type`, `coa_subsection` = `voucher_type`
WHERE (`coa_options` IS NOT NULL AND TRIM(`coa_options`) <> '')
  AND ((`coa_category` IS NULL OR TRIM(`coa_category`) = '') OR (`coa_subsection` IS NULL OR TRIM(`coa_subsection`) = ''));

-- End of migration


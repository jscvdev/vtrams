-- Migration: active_status on voucher_tracking (idempotent)
-- Date: 2026-06-02
--
-- Values:
--   no       = encoded (pending in vouchers table)
--   yes      = forwarded or received (included in dashboard / voucher status counts)
--   returned = returned to encoder (excluded from counts; re-forward sets yes again)
--
-- Run on target database: USE dvsdb; SOURCE migration_voucher_tracking_active_status.sql;

SET @db := DATABASE();
SET @tbl := 'voucher_tracking';
SET @col := 'active_status';

-- =========
-- Add column when missing (fresh install)
-- =========
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    'SELECT ''voucher_tracking.active_status already exists'' AS migration_note;',
    CONCAT(
      'ALTER TABLE `', @tbl, '` ADD COLUMN `', @col, '` ',
      'ENUM(''no'', ''yes'', ''returned'') NOT NULL DEFAULT ''no'' ',
      'COMMENT ''no=encoded; yes=forwarded/received; returned=returned to encoder'' ',
      'AFTER `voucher_status`;'
    )
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========
-- Upgrade legacy ENUM(''yes'', ''no'') to include ''returned''
-- =========
SET @enum_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
  LIMIT 1
);

SET @sql := (
  SELECT IF(
    @enum_type IS NULL,
    'SELECT ''voucher_tracking.active_status missing; add step failed?'' AS migration_note;',
    IF(
      LOCATE('returned', @enum_type) > 0,
      'SELECT ''voucher_tracking.active_status enum already includes returned'' AS migration_note;',
      CONCAT(
        'ALTER TABLE `', @tbl, '` MODIFY COLUMN `', @col, '` ',
        'ENUM(''no'', ''yes'', ''returned'') NOT NULL DEFAULT ''no'' ',
        'COMMENT ''no=encoded; yes=forwarded/received; returned=returned to encoder'';'
      )
    )
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========
-- Backfill from voucher_status and pending vouchers table
-- =========

-- Returned vouchers
UPDATE `voucher_tracking` vt
SET vt.`active_status` = 'returned'
WHERE vt.`voucher_status` LIKE 'Returned%'
  AND vt.`active_status` <> 'returned';

-- Encoded / edited still pending with encoder
UPDATE `voucher_tracking` vt
SET vt.`active_status` = 'no'
WHERE (
    vt.`voucher_status` LIKE 'Encoded%'
    OR vt.`voucher_status` LIKE 'Edited%'
    OR EXISTS (
      SELECT 1 FROM `vouchers` v WHERE v.`processing_no` = vt.`processing_no`
    )
  )
  AND vt.`active_status` <> 'no'
  AND vt.`voucher_status` NOT LIKE 'Returned%';

-- Forwarded / received pipeline (not pending, not returned)
UPDATE `voucher_tracking` vt
SET vt.`active_status` = 'yes'
WHERE vt.`active_status` = 'no'
  AND vt.`voucher_status` NOT LIKE 'Encoded%'
  AND vt.`voucher_status` NOT LIKE 'Edited%'
  AND vt.`voucher_status` NOT LIKE 'Returned%'
  AND NOT EXISTS (
    SELECT 1 FROM `vouchers` v WHERE v.`processing_no` = vt.`processing_no`
  );

SELECT 'migration_voucher_tracking_active_status complete' AS migration_note;

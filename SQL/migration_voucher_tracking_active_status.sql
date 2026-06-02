-- Migration: active_status on voucher_tracking
-- yes = forwarded and counted in dashboard / voucher status
-- no  = encoded, edited, returned, or otherwise inactive

ALTER TABLE `voucher_tracking`
ADD COLUMN `active_status` ENUM('yes', 'no') NOT NULL DEFAULT 'no'
COMMENT 'yes when forwarded after encoding; no when pending or returned'
AFTER `voucher_status`;

-- Backfill: mark existing forwarded pipeline rows as active
UPDATE `voucher_tracking` vt
SET vt.active_status = 'yes'
WHERE vt.voucher_status NOT LIKE 'Encoded%'
  AND vt.voucher_status NOT LIKE 'Edited%'
  AND vt.voucher_status NOT LIKE 'Returned%'
  AND NOT EXISTS (
      SELECT 1 FROM `vouchers` v WHERE v.processing_no = vt.processing_no
  );

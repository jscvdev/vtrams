-- Faster Status Report list/filter queries.
-- Run on the vtrams database. Skip any statement if the index already exists.

ALTER TABLE `voucher_tracking`
  ADD INDEX `idx_vsr_active_status_dt` (`active_status`, `datetime_status`);

ALTER TABLE `voucher_tracking`
  ADD INDEX `idx_vsr_office_from` (`office_from`);

ALTER TABLE `voucher_tracking`
  ADD INDEX `idx_vsr_voucher_type` (`voucher_type`);

ALTER TABLE `voucher_tracking`
  ADD INDEX `idx_vsr_processing_no` (`processing_no`);

ALTER TABLE `voucher_archives`
  ADD INDEX `idx_vsr_archives_processing_no` (`processing_no`);

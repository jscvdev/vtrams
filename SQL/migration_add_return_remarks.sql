-- Migration: Add return_remarks column to vouchers table
-- When a voucher is returned with remarks from Incoming, the remark is stored here and shown on the Vouchers list.

ALTER TABLE `vouchers`
ADD COLUMN `return_remarks` TEXT DEFAULT NULL COMMENT 'Remarks entered when voucher was returned from Incoming';

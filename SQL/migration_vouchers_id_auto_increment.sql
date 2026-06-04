-- Fix: SQLSTATE[HY000] 1364 Field 'id' doesn't have a default value
-- when returning a voucher to encoder (re-insert into vouchers).
-- Run once on production if vouchers.id is NOT NULL without AUTO_INCREMENT.

ALTER TABLE `vouchers`
  MODIFY `id` INT(10) NOT NULL AUTO_INCREMENT;

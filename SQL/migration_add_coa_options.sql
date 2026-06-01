-- Migration Script: Add COA Options, Category, and Subsection Columns to Voucher Tables
-- Date: 2026-02-19
-- Description: Adds coa_options, coa_category, and coa_subsection columns to store selected COA requirements

-- Add COA columns to vouchers table
ALTER TABLE `vouchers` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_incoming table
ALTER TABLE `voucher_incoming` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_receiving table
ALTER TABLE `voucher_receiving` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_sent table
ALTER TABLE `voucher_sent` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_tracking table
ALTER TABLE `voucher_tracking` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_archives table
ALTER TABLE `voucher_archives` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Add COA columns to voucher_action_logs table
ALTER TABLE `voucher_action_logs` 
ADD COLUMN `coa_options` TEXT DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
ADD COLUMN `coa_category` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA category',
ADD COLUMN `coa_subsection` VARCHAR(255) DEFAULT NULL COMMENT 'Selected COA subsection';

-- Migration completed successfully



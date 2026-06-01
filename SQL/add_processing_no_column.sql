-- Migration: Add processing_no column to existing audit_logs table
-- Run this if the audit_logs table exists but doesn't have the processing_no column yet.

-- Check if column exists first (run this query to check):
-- SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'processing_no';

-- If the result is 0, then run the ALTER TABLE below:

ALTER TABLE `audit_logs` 
ADD COLUMN `processing_no` varchar(255) DEFAULT NULL AFTER `description`;

ALTER TABLE `audit_logs` 
ADD INDEX `idx_processing_no` (`processing_no`);













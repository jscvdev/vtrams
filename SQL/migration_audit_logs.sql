-- Migration: Add audit_logs table for voucher system auditing (same pattern as PPMS)
-- Run this on the vtrams database if the table does not exist.
-- user_id stores user_group.id (no FK to avoid dependency on table name).

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `employee_name` varchar(255) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `processing_no` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `additional_data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_processing_no` (`processing_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Add processing_no column to existing audit_logs table (if table already exists)
-- Run this ONLY if the table exists but doesn't have the processing_no column yet.
-- Check if column exists first: SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'processing_no';
-- If result is 0, then run the ALTER TABLE below.

-- Uncomment the following lines if you need to add the column to an existing table:
-- ALTER TABLE `audit_logs` 
-- ADD COLUMN `processing_no` varchar(255) DEFAULT NULL AFTER `description`;
-- 
-- ALTER TABLE `audit_logs` 
-- ADD INDEX `idx_processing_no` (`processing_no`);

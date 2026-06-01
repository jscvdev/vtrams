-- =====================================================
-- Database Migration: system_settings Table
-- Created: 2026-01-27
-- Purpose: Create system_settings table for Settings page
-- =====================================================

-- Check if table exists, if not create it
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `system_name` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
  `page_title` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
  `company_name` varchar(255) NOT NULL DEFAULT 'Provincial Environment and Natural Resources Office',
  `browser_title` varchar(255) NOT NULL DEFAULT 'PENRO-DVS',
  `header_text` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System v1.0',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default settings if no record exists
INSERT INTO `system_settings` (`id`, `system_name`, `page_title`, `company_name`, `browser_title`, `header_text`)
SELECT 1, 
    'PENRO Disbursement Voucher System',
    'PENRO Disbursement Voucher System',
    'Provincial Environment and Natural Resources Office',
    'PENRO-DVS',
    'PENRO Disbursement Voucher System v1.0'
WHERE NOT EXISTS (
    SELECT 1 FROM `system_settings` WHERE `id` = 1
);

-- =====================================================
-- Migration Complete
-- =====================================================

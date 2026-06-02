-- User default COA / checklist selections for forward voucher (per emp_id + voucher_type)
-- Run: USE your_database; then source this file.

CREATE TABLE IF NOT EXISTS `user_coa_forward_prefs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `selected_options` text NOT NULL COMMENT 'JSON array of {id,value,label}',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_voucher_type` (`emp_id`, `voucher_type`),
  KEY `idx_emp_id` (`emp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

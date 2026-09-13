-- Pending retract requests that need System Admin approval after a processing-office unit has acted on the voucher.
CREATE TABLE IF NOT EXISTS `voucher_retract_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `processing_no` VARCHAR(255) NOT NULL,
  `retract_source` VARCHAR(50) NOT NULL DEFAULT 'incoming',
  `requested_by` VARCHAR(500) NOT NULL,
  `requested_from` VARCHAR(500) NOT NULL DEFAULT '',
  `office_from` VARCHAR(500) NOT NULL DEFAULT '',
  `remarks` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `datetime_requested` DATETIME NOT NULL,
  `reviewed_by` VARCHAR(500) NULL,
  `datetime_reviewed` DATETIME NULL,
  `review_remarks` TEXT NULL,
  KEY `idx_retract_status_requested` (`status`, `datetime_requested`),
  KEY `idx_retract_processing_no` (`processing_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

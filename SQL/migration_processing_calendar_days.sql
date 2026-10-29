-- Processing calendar: holidays, special orders, and custom work windows
-- used by voucher processing-time calculations (Mon–Thu office hours).

CREATE TABLE IF NOT EXISTS `processing_calendar_days` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_date` date NOT NULL,
  `event_type` varchar(32) NOT NULL DEFAULT 'holiday',
  `title` varchar(255) NOT NULL,
  `coverage` varchar(16) NOT NULL DEFAULT 'whole_day',
  `work_start` time DEFAULT NULL,
  `work_end` time DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'manual',
  `created_by` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_processing_calendar_date` (`event_date`),
  KEY `idx_processing_calendar_type` (`event_type`, `event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

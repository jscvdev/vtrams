<?php

declare(strict_types=1);

function voucher_retract_ensure_requests_schema(object $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if ($pdo->inTransaction()) {
        return;
    }

    $done = true;

    if (function_exists('schema_table_exists') && schema_table_exists($pdo, 'voucher_retract_requests')) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_retract_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            processing_no VARCHAR(255) NOT NULL,
            retract_source VARCHAR(50) NOT NULL DEFAULT 'incoming',
            requested_by VARCHAR(500) NOT NULL,
            requested_from VARCHAR(500) NOT NULL DEFAULT '',
            office_from VARCHAR(500) NOT NULL DEFAULT '',
            remarks TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            datetime_requested DATETIME NOT NULL,
            reviewed_by VARCHAR(500) NULL,
            datetime_reviewed DATETIME NULL,
            review_remarks TEXT NULL,
            KEY idx_retract_status_requested (status, datetime_requested),
            KEY idx_retract_processing_no (processing_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (defined('SCHEMA_CACHE_NS') && class_exists('RequestCache')) {
        RequestCache::forget(SCHEMA_CACHE_NS, 'exists:voucher_retract_requests');
        RequestCache::forget(SCHEMA_CACHE_NS, 'columns:voucher_retract_requests');
    }
}

<?php

declare(strict_types=1);

/**
 * Send standard no-cache headers for dynamic HTML/API responses.
 *
 * @param bool $expiresZero When true, also sends Expires: 0 (file download responses).
 */
function send_no_cache_headers(bool $expiresZero = false): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if ($expiresZero) {
        header('Expires: 0');
    }
}

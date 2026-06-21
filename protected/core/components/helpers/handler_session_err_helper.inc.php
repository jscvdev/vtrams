<?php

declare(strict_types=1);

require_once __DIR__ . '/handler_transaction_helper.inc.php';

/**
 * Emit session validation errors via top-right toast and clear the session key.
 */
function handler_emit_session_errors(string $sessionKey, string $notifyType = 'error', int $ms = 5000): bool
{
    if (!isset($_SESSION[$sessionKey])) {
        return false;
    }

    $errors = $_SESSION[$sessionKey];
    unset($_SESSION[$sessionKey]);

    if (is_array($errors)) {
        $message = trim(implode(' ', array_map('strval', $errors)));
    } else {
        $message = trim((string) $errors);
    }

    if ($message === '') {
        return false;
    }

    if (stripos($message, 'error:') !== 0) {
        $message = 'Error: ' . $message;
    }

    handler_set_flash_notify($message, $notifyType, $ms);

    return true;
}

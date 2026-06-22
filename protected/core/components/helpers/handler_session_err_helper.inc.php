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

/**
 * Redirect after a handler validation failure with a detailed top-right toast on the destination page.
 *
 * @param array<string, mixed> $errors
 * @return never
 */
function handler_redirect_with_errors(
    array $errors,
    string $redirectCode,
    string $prefix = 'Error: ',
    string $type = 'error',
    int $notifyMs = 6000
): void {
    $parts = [];
    foreach ($errors as $error) {
        $text = trim((string) $error);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    $detail = implode(' ', $parts);
    $message = trim($prefix . $detail);
    if ($message === '' || $message === trim($prefix)) {
        $message = 'Error: Request failed.';
    }

    handler_redirect_with_notify($message, $redirectCode, $type, $notifyMs);
}

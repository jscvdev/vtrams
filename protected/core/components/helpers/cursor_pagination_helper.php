<?php
declare(strict_types=1);

function clamp_int($value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }
    $v = (int) $value;
    if ($v < $min) {
        return $min;
    }
    if ($v > $max) {
        return $max;
    }
    return $v;
}

function base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function base64url_decode(string $b64url): string|false
{
    $b64 = strtr($b64url, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($b64, true);
}

/**
 * Cursor is an opaque token containing the last seen ordering tuple.
 * Keep it small and stable: JSON -> base64url.
 */
function encode_cursor(array $cursor): string
{
    return base64url_encode(json_encode($cursor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function decode_cursor(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    $raw = base64url_decode($token);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

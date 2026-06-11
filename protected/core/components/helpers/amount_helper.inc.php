<?php

declare(strict_types=1);

/**
 * Exact amount string helpers (no float rounding).
 */

/** Convert PDO amount values (int/float/string) without comma truncation. */
function amount_pdo_value_to_string(mixed $raw): string
{
    if ($raw === null) {
        return '';
    }
    if (is_string($raw)) {
        return trim($raw);
    }
    if (is_int($raw)) {
        return (string) $raw;
    }
    if (is_float($raw)) {
        if (floor($raw) === $raw && abs($raw) < 1e15) {
            return (string) (int) $raw;
        }

        return rtrim(rtrim(sprintf('%.10F', $raw), '0'), '.');
    }

    return trim((string) $raw);
}

function normalize_amount_string(mixed $raw): string
{
    $v = str_replace(',', '', amount_pdo_value_to_string($raw));
    if ($v === '') {
        return '';
    }

    $v = preg_replace('/[^\d.]/', '', $v) ?? '';
    $dot = strpos($v, '.');
    if ($dot !== false) {
        $v = substr($v, 0, $dot + 1) . str_replace('.', '', substr($v, $dot + 1));
    }

    return $v;
}

/** Append .00 when amount has no decimal part (e.g. 15000 → 15000.00). */
function ensure_amount_two_decimals(mixed $raw): string
{
    $normalized = normalize_amount_string($raw);
    if ($normalized === '') {
        return '';
    }

    return strpos($normalized, '.') === false ? $normalized . '.00' : $normalized;
}

/** True when a stored amount is a whole number without a decimal point. */
function amount_stored_value_needs_two_decimals(mixed $raw): bool
{
    if ($raw === null) {
        return false;
    }

    $trimmed = trim(amount_pdo_value_to_string($raw));
    if ($trimmed === '') {
        return false;
    }

    $normalized = normalize_amount_string($raw);

    return $normalized !== '' && strpos($normalized, '.') === false;
}

function amounts_equal_string(?string $a, ?string $b): bool
{
    return normalize_amount_string($a) === normalize_amount_string($b);
}

function format_amount_display(mixed $raw): string
{
    $normalized = normalize_amount_string($raw);
    if ($normalized === '') {
        return '';
    }

    $parts = explode('.', $normalized, 2);
    $intPart = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]) ?? $parts[0];

    return isset($parts[1]) ? $intPart . '.' . $parts[1] : $intPart;
}

/** POST/session text for DB storage — do not HTML-escape (escape on output only). */
function voucher_post_string(mixed $value): string
{
    return trim((string) $value);
}

/** Apply exact amount normalization to a handler-mapped POST variable. */
function voucher_apply_exact_amount(?string &$amount): void
{
    $amount = ensure_amount_two_decimals((string) ($amount ?? ''));
}

/** Normalize amount before DB write; runs column migration when voucher.model is loaded. */
function voucher_prepare_stored_amount(object $pdo, string $amount): string
{
    if (function_exists('vouchers_amount_ensure_string_column')) {
        vouchers_amount_ensure_string_column($pdo);
    }

    return ensure_amount_two_decimals($amount);
}

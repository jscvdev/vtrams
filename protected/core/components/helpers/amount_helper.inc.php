<?php

declare(strict_types=1);

/**
 * Exact amount string helpers (no float rounding).
 */

function normalize_amount_string(?string $raw): string
{
    $v = str_replace(',', '', trim((string) $raw));
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

function amounts_equal_string(?string $a, ?string $b): bool
{
    return normalize_amount_string($a) === normalize_amount_string($b);
}

function format_amount_display(?string $raw): string
{
    $normalized = normalize_amount_string($raw);
    if ($normalized === '') {
        return '';
    }

    $parts = explode('.', $normalized, 2);
    $intPart = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]) ?? $parts[0];

    return isset($parts[1]) ? $intPart . '.' . $parts[1] : $intPart;
}

/** Apply exact amount normalization to a handler-mapped POST variable. */
function voucher_apply_exact_amount(?string &$amount): void
{
    $amount = normalize_amount_string((string) ($amount ?? ''));
}

/** Normalize amount before DB write; runs column migration when voucher.model is loaded. */
function voucher_prepare_stored_amount(object $pdo, string $amount): string
{
    if (function_exists('vouchers_amount_ensure_string_column')) {
        vouchers_amount_ensure_string_column($pdo);
    }

    return normalize_amount_string($amount);
}

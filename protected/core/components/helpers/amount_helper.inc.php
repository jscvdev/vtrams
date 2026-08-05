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

/** True when a stored amount is present and not zero (matches amount_helper.js isNonZeroAmount). */
function amount_is_non_zero(mixed $raw): bool
{
    $normalized = normalize_amount_string($raw);
    if ($normalized === '') {
        return false;
    }

    return !preg_match('/^0+\.?0*$/', $normalized);
}

/** Prefer charged_amount when set, non-zero, and different from gross; otherwise use amount. */
function amount_resolve_charged_or_amount(mixed $charged, mixed $amount): string
{
    $gross = ensure_amount_two_decimals(amount_pdo_value_to_string($amount));
    if (amount_is_non_zero($charged) && !amounts_equal_string($charged, $amount)) {
        return ensure_amount_two_decimals(amount_pdo_value_to_string($charged));
    }

    return $gross;
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

/** True when charged_amount should display as a separate net line. */
function amount_show_charged_net(mixed $charged, mixed $gross = null): bool
{
    $chargedStr = trim(amount_pdo_value_to_string($charged));
    if ($chargedStr === '' || $chargedStr === '0' || $chargedStr === '0.00') {
        return false;
    }

    if ($gross !== null && amounts_equal_string($gross, $chargedStr)) {
        return false;
    }

    return true;
}

/**
 * Resolve gross/charged from a stored row; fallback gross is used only when row amount is empty.
 *
 * @return array{gross: string, charged: ?string}
 */
function voucher_resolve_stored_amounts(array $row, string $fallbackGross = ''): array
{
    $gross = trim($fallbackGross);
    $storedGross = trim(amount_pdo_value_to_string($row['amount'] ?? ''));
    if ($storedGross !== '') {
        $gross = $storedGross;
    }

    $grossNorm = ensure_amount_two_decimals($gross);
    $charged = null;
    if (amount_is_non_zero($row['charged_amount'] ?? null)) {
        $chargedRaw = amount_pdo_value_to_string($row['charged_amount']);
        if (amount_show_charged_net($chargedRaw, $grossNorm)) {
            $charged = ensure_amount_two_decimals($chargedRaw);
        }
    }

    return [
        'gross' => $grossNorm,
        'charged' => $charged,
    ];
}

/**
 * @return array{effective: string, gross: string, net: string, show_net: bool}
 */
function voucher_amount_stack_parts(mixed $grossAmount, mixed $chargedAmount = null): array
{
    $gross = amount_pdo_value_to_string($grossAmount);
    $net = amount_pdo_value_to_string($chargedAmount ?? '');
    $showNet = amount_show_charged_net($net, $gross);
    $effective = $showNet ? $net : $gross;

    return [
        'effective' => $effective,
        'gross' => $gross,
        'net' => $net,
        'show_net' => $showNet,
    ];
}

/** Inner HTML for a gross/net amount stack (label and value side by side). */
function voucher_amount_stack_inner_html(mixed $grossAmount, mixed $chargedAmount = null): string
{
    $parts = voucher_amount_stack_parts($grossAmount, $chargedAmount);
    $grossEsc = htmlspecialchars($parts['gross'], ENT_QUOTES, 'UTF-8');

    $html = '<div class="voucher-amount-stack">'
        . '<div class="voucher-amount-row voucher-amount-row--gross">'
        . '<span class="voucher-amount-row__label">Gross</span>'
        . '<span class="voucher-amount-row__value" data-amount-part="gross">' . $grossEsc . '</span>'
        . '</div>';

    if ($parts['show_net']) {
        $netEsc = htmlspecialchars($parts['net'], ENT_QUOTES, 'UTF-8');
        $html .= '<div class="voucher-amount-row voucher-amount-row--net">'
            . '<span class="voucher-amount-row__label">Net</span>'
            . '<span class="voucher-amount-row__value" data-amount-part="net">' . $netEsc . '</span>'
            . '</div>';
    }

    $html .= '</div>';

    return $html;
}

/** Full table cell markup for gross/net amount display. */
function voucher_amount_stack_cell_html(mixed $grossAmount, mixed $chargedAmount = null, string $extraClass = ''): string
{
    $parts = voucher_amount_stack_parts($grossAmount, $chargedAmount);
    $effectiveEsc = htmlspecialchars(normalize_amount_string($parts['effective']), ENT_QUOTES, 'UTF-8');
    $grossDataEsc = htmlspecialchars(normalize_amount_string($parts['gross']), ENT_QUOTES, 'UTF-8');
    $netDataEsc = htmlspecialchars($parts['show_net'] ? normalize_amount_string($parts['net']) : '', ENT_QUOTES, 'UTF-8');
    $class = trim('amount voucher-amount-stack-cell ' . $extraClass);

    return '<td data-label="amount" class="' . $class . '"'
        . ' data-amount="' . $effectiveEsc . '"'
        . ' data-amount-gross="' . $grossDataEsc . '"'
        . ' data-amount-net="' . $netDataEsc . '"'
        . ' data-amount-skip="1">'
        . voucher_amount_stack_inner_html($grossAmount, $chargedAmount)
        . '</td>';
}

/** Data attributes for a custom amount table cell wrapper. */
function voucher_amount_stack_cell_attrs(mixed $grossAmount, mixed $chargedAmount = null): string
{
    $parts = voucher_amount_stack_parts($grossAmount, $chargedAmount);
    $effectiveEsc = htmlspecialchars(normalize_amount_string($parts['effective']), ENT_QUOTES, 'UTF-8');
    $grossDataEsc = htmlspecialchars(normalize_amount_string($parts['gross']), ENT_QUOTES, 'UTF-8');
    $netDataEsc = htmlspecialchars($parts['show_net'] ? normalize_amount_string($parts['net']) : '', ENT_QUOTES, 'UTF-8');

    return 'class="voucher-amount-stack-cell" data-amount="' . $effectiveEsc . '"'
        . ' data-amount-gross="' . $grossDataEsc . '"'
        . ' data-amount-net="' . $netDataEsc . '"'
        . ' data-amount-skip="1"';
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

<?php

declare(strict_types=1);

/**
 * Cached SHOW COLUMNS for voucher portal tables (incoming / receiving / sent).
 *
 * @return array<string, bool>
 */
function voucher_portal_existing_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $out = [];
    try {
        $safe = str_replace('`', '', $table);
        $st = $pdo->query('SHOW COLUMNS FROM `' . $safe . '`');
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[(string) $r['Field']] = true;
            }
        }
    } catch (Throwable $e) {
        $out = [];
    }
    $cache[$table] = $out;

    return $out;
}

/**
 * Build AND (col LIKE ... OR ...) for server-side search; only binds columns that exist.
 * Clears and repopulates $bindOut with :sqN placeholders.
 *
 * @param array<string, array{0: string, 1: int}> $bindOut
 */
function voucher_portal_like_search_fragment(PDO $pdo, string $table, string $q, array $preferredTextColumns, array &$bindOut): string
{
    if ($q === '') {
        return '';
    }
    $bindOut = [];
    $pat = '%' . $q . '%';
    $existing = voucher_portal_existing_columns($pdo, $table);
    $parts = [];
    $i = 0;
    foreach ($preferredTextColumns as $col) {
        $safeCol = str_replace('`', '', $col);
        if (!isset($existing[$safeCol])) {
            continue;
        }
        $ph = ':sq' . $i;
        $parts[] = '`' . $safeCol . '` LIKE ' . $ph;
        $bindOut[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }
    if (isset($existing['amount'])) {
        $ph = ':sq' . $i;
        $parts[] = 'CAST(`amount` AS CHAR) LIKE ' . $ph;
        $bindOut[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }
    if (isset($existing['charged_amount'])) {
        $ph = ':sq' . $i;
        $parts[] = 'CAST(`charged_amount` AS CHAR) LIKE ' . $ph;
        $bindOut[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }
    if ($parts === []) {
        return ' AND 1=0';
    }

    return ' AND (' . implode(' OR ', $parts) . ')';
}

/**
 * GET URL for incoming / forwarding / sent list pages.
 */
function build_voucher_portal_page_url(int $page, int $rowsPerPage, string $voucherType, string $rawSearch = '', string $qParam = 'q'): string
{
    $params = ['page' => $page, 'rowsPerPage' => $rowsPerPage];
    if ($voucherType !== 'all') {
        $params['voucher_type'] = $voucherType;
    }
    if ($rawSearch !== '') {
        $params[$qParam] = $rawSearch;
    }

    return '?' . http_build_query($params);
}

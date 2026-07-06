<?php

declare(strict_types=1);

/**
 * Shared search / status filter for utilities CRUD list pages.
 *
 * @return array{q: string, status: string, is_filtered: bool}
 */
function utilities_list_filter_params(): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? 'all'));
    if (!in_array($status, ['all', 'active', 'inactive'], true)) {
        $status = 'all';
    }

    return [
        'q' => $q,
        'status' => $status,
        'is_filtered' => ($q !== '' || $status !== 'all'),
    ];
}

function utilities_list_status_matches(array $row, string $status): bool
{
    if ($status === 'all') {
        return true;
    }

    $active = (int) ($row['is_active'] ?? 1) === 1;

    return $status === 'active' ? $active : !$active;
}

/**
 * @param list<string> $fields
 */
function utilities_list_text_matches(array $row, string $q, array $fields): bool
{
    if ($q === '') {
        return true;
    }

    $needle = strtolower($q);
    $haystack = '';
    foreach ($fields as $field) {
        $haystack .= ' ' . strtolower((string) ($row[$field] ?? ''));
    }

    return str_contains($haystack, $needle);
}

/**
 * @param list<array<string, mixed>> $rows
 * @param list<string> $searchFields
 * @return list<array<string, mixed>>
 */
function utilities_list_filter_rows(array $rows, string $q, string $status, array $searchFields): array
{
    $filtered = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!utilities_list_status_matches($row, $status)) {
            continue;
        }
        if (!utilities_list_text_matches($row, $q, $searchFields)) {
            continue;
        }
        $filtered[] = $row;
    }

    return $filtered;
}

/**
 * When not searching/filtering, show only the first row for faster initial render.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function utilities_list_limit_initial(array $rows, bool $isFiltered, int $limit = 1): array
{
    if ($isFiltered || $rows === []) {
        return $rows;
    }

    return array_slice($rows, 0, max(1, $limit));
}

function utilities_list_filter_query_suffix(array $params): string
{
    $parts = [];
    if (($params['q'] ?? '') !== '') {
        $parts[] = 'q=' . rawurlencode((string) $params['q']);
    }
    if (($params['status'] ?? 'all') !== 'all') {
        $parts[] = 'status=' . rawurlencode((string) $params['status']);
    }
    if (($params['voucher_type'] ?? '') !== '') {
        $parts[] = 'voucher_type=' . rawurlencode((string) $params['voucher_type']);
    }

    return $parts ? ('?' . implode('&', $parts)) : '';
}

/**
 * Build type filter options from rows already stored for a utilities page.
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, string>
 */
function utilities_list_type_options_from_rows(array $rows, string $keyField, string $labelField = ''): array
{
    $options = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = trim((string) ($row[$keyField] ?? ''));
        if ($key === '') {
            continue;
        }
        $label = $labelField !== '' ? trim((string) ($row[$labelField] ?? '')) : '';
        $options[$key] = $label !== '' ? $label : $key;
    }

    return $options;
}

/**
 * @param array<string, string> $allowedTypes
 * @return array{q: string, voucher_type: string, is_filtered: bool}
 */
function utilities_list_filter_voucher_type_params(array $allowedTypes): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $voucherType = trim((string) ($_GET['voucher_type'] ?? ''));
    if ($voucherType !== '' && !isset($allowedTypes[$voucherType])) {
        $voucherType = '';
    }

    return [
        'q' => $q,
        'voucher_type' => $voucherType,
        'is_filtered' => ($q !== '' || $voucherType !== ''),
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function utilities_list_filter_by_field_value(array $rows, string $field, string $value): array
{
    if ($value === '') {
        return $rows;
    }

    return array_values(array_filter(
        $rows,
        static fn(array $row): bool => (string) ($row[$field] ?? '') === $value
    ));
}

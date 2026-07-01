<?php

declare(strict_types=1);

require_once __DIR__ . '/voucher_status_report_helper.inc.php';

/**
 * Liaison host office plus descendant sub-offices under it.
 *
 * @return list<string>
 */
function voucher_liaison_host_office_tree(PDO $pdo, string $hostOffice): array
{
    $hostOffice = utilities_office_normalize_name($hostOffice);
    if ($hostOffice === '') {
        return [];
    }

    $record = utilities_office_find_by_name($pdo, $hostOffice);
    $offices = [$hostOffice];
    if ($record !== null) {
        $officeId = (int) ($record['id'] ?? 0);
        if ($officeId > 0) {
            $offices = array_merge($offices, utilities_office_descendant_names($pdo, $officeId));
        }
    }

    return array_values(array_unique(array_filter(array_map('trim', $offices), static fn(string $o): bool => $o !== '')));
}

/**
 * Offices visible on the Returned Vouchers page for the current user.
 *
 * @return list<string>
 */
function voucher_liaison_returned_scope_offices(PDO $pdo, string $loggedOffice, bool $isSysAdmin): array
{
    utilities_office_ensure_schema($pdo);
    $loggedOffice = trim($loggedOffice);

    if ($isSysAdmin) {
        $offices = [];
        foreach (utilities_office_liaison_registered_offices($pdo) as $hostOffice) {
            $offices = array_merge($offices, voucher_liaison_host_office_tree($pdo, (string) $hostOffice));
        }

        return array_values(array_unique($offices));
    }

    if ($loggedOffice === '') {
        return [];
    }

    $liaisonHost = utilities_office_encoder_liaison_office($pdo, $loggedOffice);
    if ($liaisonHost === '') {
        $liaisonHost = $loggedOffice;
    }

    return voucher_liaison_host_office_tree($pdo, $liaisonHost);
}

/** True when a voucher is still at returned status (not paid, not re-received into processing). */
function voucher_liaison_is_currently_returned(PDO $pdo, array $row): bool
{
    if (voucher_status_report_is_paid($pdo, $row)) {
        return false;
    }

    // Uses active_status = 'returned' and/or voucher_status "Returned by: …"; excludes active_status = 'yes'.
    return voucher_status_report_is_returned($row);
}

/** SQL fragment: candidate rows that may still be returned (refined in PHP). */
function voucher_liaison_returned_include_sql(string $alias = 'vt'): string
{
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'vt';

    return " AND (
        {$alias}.active_status = 'returned'
        OR {$alias}.voucher_status LIKE 'Returned by:%'
    )";
}

/**
 * @return list<array<string, mixed>>
 */
function voucher_liaison_returned_fetch_entries(PDO $pdo, array $scopeOffices, ?string $officeFilter = null): array
{
    $scopeOffices = array_values(array_filter(array_map('trim', $scopeOffices), static fn(string $o): bool => $o !== ''));
    if ($scopeOffices === []) {
        return [];
    }

    $queryOffices = $scopeOffices;
    $officeFilter = trim((string) ($officeFilter ?? ''));
    if ($officeFilter !== '' && strcasecmp($officeFilter, 'all') !== 0) {
        $resolved = utilities_signatory_resolve_office($pdo, $officeFilter);
        if ($resolved !== '' && voucher_status_report_office_in_list($resolved, $scopeOffices)) {
            $queryOffices = [$resolved];
        }
    }

    $officeWhere = voucher_office_build_where_clause('vt.office_from', $queryOffices, 'lr_office');
    $sql = 'SELECT vt.* FROM voucher_tracking vt WHERE 1=1'
        . $officeWhere['sql']
        . voucher_liaison_returned_include_sql('vt')
        . ' ORDER BY vt.datetime_status DESC, vt.processing_no DESC';

    $stmt = $pdo->prepare($sql);
    foreach ($officeWhere['params'] as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    $entries = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!voucher_liaison_is_currently_returned($pdo, $row)) {
            continue;
        }

        $lines = voucher_tracking_parse_process_history_lines((string) ($row['process_history'] ?? ''));
        $originOffice = voucher_tracking_history_origin_office($lines);
        $officeFrom = trim((string) ($row['office_from'] ?? ''));
        if ($originOffice === '') {
            $originOffice = $officeFrom;
        }

        $returnedBy = voucher_tracking_resolve_returned_by(
            (string) ($row['voucher_status'] ?? ''),
            (string) ($row['process_history'] ?? '')
        );

        $entries[] = [
            'processing_no' => trim((string) ($row['processing_no'] ?? '')),
            'dv_no' => trim((string) ($row['dv_no'] ?? '')),
            'payee' => trim((string) ($row['payee'] ?? '')),
            'amount' => amount_resolve_charged_or_amount($row['charged_amount'] ?? '', $row['amount'] ?? ''),
            'voucher_type' => trim((string) ($row['voucher_type'] ?? '')),
            'office_from' => $officeFrom,
            'origin_office' => $originOffice,
            'returned_by' => $returnedBy,
            'voucher_status' => trim((string) ($row['voucher_status'] ?? '')),
            'datetime_status' => trim((string) ($row['datetime_status'] ?? '')),
            'process_history' => voucher_tracking_normalize_process_history((string) ($row['process_history'] ?? '')),
        ];
    }

    return $entries;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array{total: int, total_amount: string}
 */
function voucher_liaison_returned_summarize(array $entries): array
{
    $totalAmount = '0';
    foreach ($entries as $entry) {
        $amt = amount_resolve_charged_or_amount('', $entry['amount'] ?? '');
        if ($amt !== '') {
            $totalAmount = bcadd($totalAmount, $amt, 2);
        }
    }

    return [
        'total' => count($entries),
        'total_amount' => format_amount_display($totalAmount),
    ];
}

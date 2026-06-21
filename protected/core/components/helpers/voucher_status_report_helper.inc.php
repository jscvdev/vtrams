<?php

declare(strict_types=1);

require_once __DIR__ . '/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/utilities_office_helper.inc.php';
require_once __DIR__ . '/utilities_signatory_helper.inc.php';
require_once __DIR__ . '/amount_helper.inc.php';

/**
 * @return array{processing_office: string, sub_offices: list<string>, processing_office_id: int}
 */
function voucher_status_report_scope(PDO $pdo, string $loggedOffice): array
{
    utilities_office_ensure_schema($pdo);
    $processing = utilities_office_get_processing($pdo);
    $processingOffice = $loggedOffice;
    $processingOfficeId = 0;

    if (is_array($processing)) {
        $name = utilities_office_normalize_name((string) ($processing['office_name'] ?? ''));
        if ($name !== '') {
            $processingOffice = $name;
        }
        $processingOfficeId = (int) ($processing['id'] ?? 0);
    }

    $subOffices = [];
    foreach (utilities_office_fetch_all($pdo, true) as $row) {
        if ((int) ($row['is_processing_office'] ?? 0) === 1) {
            continue;
        }
        $parentId = (int) ($row['parent_office_id'] ?? 0);
        if ($processingOfficeId > 0 && $parentId === $processingOfficeId) {
            $name = utilities_office_normalize_name((string) ($row['office_name'] ?? ''));
            if ($name !== '') {
                $subOffices[] = $name;
            }
        }
    }

    return [
        'processing_office' => $processingOffice,
        'sub_offices' => array_values(array_unique($subOffices)),
        'processing_office_id' => $processingOfficeId,
    ];
}

function voucher_status_report_office_in_list(string $office, array $offices): bool
{
    $office = trim($office);
    if ($office === '') {
        return false;
    }

    foreach ($offices as $candidate) {
        if (voucher_tracking_offices_match($office, (string) $candidate)) {
            return true;
        }
    }

    return false;
}

function voucher_status_report_line_is_forward(array $line): bool
{
    return stripos((string) ($line['action'] ?? ''), 'Forwarded') !== false;
}

function voucher_status_report_line_is_liaison(array $line): bool
{
    return stripos((string) ($line['section'] ?? ''), 'Liaison') !== false;
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_status_report_has_liaison_to_main(array $lines, string $processingOffice): bool
{
    foreach ($lines as $line) {
        if (!voucher_status_report_line_is_forward($line)) {
            continue;
        }
        if (!voucher_status_report_line_is_liaison($line)) {
            continue;
        }
        if (
            voucher_tracking_offices_match((string) ($line['office'] ?? ''), $processingOffice)
            || stripos((string) ($line['section'] ?? ''), 'ICU') !== false
        ) {
            return true;
        }
    }

    foreach ($lines as $line) {
        if (!voucher_status_report_line_is_forward($line)) {
            continue;
        }
        if (stripos((string) ($line['section'] ?? ''), 'ICU') !== false) {
            return true;
        }
        if (
            voucher_tracking_offices_match((string) ($line['office'] ?? ''), $processingOffice)
            && stripos((string) ($line['action'] ?? ''), 'Forwarded') !== false
        ) {
            return true;
        }
    }

    return false;
}

function voucher_status_report_is_paid(PDO $pdo, array $row): bool
{
    if (strcasecmp(trim((string) ($row['status'] ?? '')), 'Paid') === 0) {
        return true;
    }

    static $archiveStmt = null;
    if ($archiveStmt === null) {
        $archiveStmt = $pdo->prepare('SELECT 1 FROM voucher_archives WHERE processing_no = :processing_no LIMIT 1');
    }
    $processingNo = trim((string) ($row['processing_no'] ?? ''));
    if ($processingNo === '') {
        return false;
    }
    $archiveStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
    $archiveStmt->execute();

    return (bool) $archiveStmt->fetchColumn();
}

function voucher_status_report_is_returned(array $row): bool
{
    return voucher_tracking_is_returned_active_status((string) ($row['active_status'] ?? ''));
}

/**
 * @param array<string, mixed> $row
 * @param array{processing_office: string, sub_offices: list<string>} $scope
 * @return array<string, mixed>|null
 */
function voucher_status_report_classify_row(PDO $pdo, array $row, array $scope): ?array
{
    if (voucher_tracking_is_excluded_from_counts((string) ($row['active_status'] ?? ''))) {
        return null;
    }

    $lines = voucher_tracking_parse_process_history_lines((string) ($row['process_history'] ?? ''));
    $officeFrom = trim((string) ($row['office_from'] ?? ''));
    $originOffice = voucher_tracking_history_origin_office($lines);
    if ($originOffice === '') {
        $originOffice = $officeFrom;
    }

    $processingOffice = (string) $scope['processing_office'];
    $subOffices = (array) ($scope['sub_offices'] ?? []);
    $categories = [];

    $fromSubOffice = voucher_status_report_office_in_list($officeFrom, $subOffices)
        || voucher_status_report_office_in_list($originOffice, $subOffices);

    if ($fromSubOffice && voucher_status_report_has_liaison_to_main($lines, $processingOffice)) {
        $categories[] = [
            'key' => 'sub_liaison',
            'label' => 'Sub-office liaison to main office',
        ];
    }

    $isPaid = voucher_status_report_is_paid($pdo, $row);
    $isReturned = voucher_status_report_is_returned($row) && !$isPaid;
    if ($isPaid) {
        $statusLabel = 'Paid';
    } elseif ($isReturned) {
        $statusLabel = 'Returned';
    } else {
        $statusLabel = 'Processing';
    }
    if ($categories === []) {
        $categories[] = [
            'key' => 'forwarded',
            'label' => 'Forwarded voucher',
        ];
    }

    return [
        'processing_no' => trim((string) ($row['processing_no'] ?? '')),
        'ors_no' => trim((string) ($row['ors_no'] ?? '')),
        'dv_no' => trim((string) ($row['dv_no'] ?? '')),
        'ada_check_no' => trim((string) ($row['ada_check_no'] ?? '')),
        'payee' => trim((string) ($row['payee'] ?? '')),
        'particulars' => trim((string) ($row['particulars'] ?? '')),
        'amount' => amount_resolve_charged_or_amount($row['charged_amount'] ?? '', $row['amount'] ?? ''),
        'voucher_type' => trim((string) ($row['voucher_type'] ?? '')),
        'office_from' => $officeFrom,
        'origin_office' => $originOffice,
        'voucher_status' => trim((string) ($row['voucher_status'] ?? '')),
        'status' => trim((string) ($row['status'] ?? '')),
        'datetime_status' => trim((string) ($row['datetime_status'] ?? '')),
        'datetime_encoded' => trim((string) ($row['datetime_encoded'] ?? '')),
        'process_history' => voucher_tracking_normalize_process_history((string) ($row['process_history'] ?? '')),
        'categories' => $categories,
        'category_label' => implode(' · ', array_column($categories, 'label')),
        'status_label' => $statusLabel,
        'is_paid' => $isPaid,
        'is_returned' => $isReturned,
        'active_status' => trim((string) ($row['active_status'] ?? '')),
    ];
}

/**
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function voucher_status_report_filter_by_status(array $entries, string $statusFilter): array
{
    $statusFilter = strtolower(trim($statusFilter));
    if ($statusFilter === '' || $statusFilter === 'all') {
        return $entries;
    }

    return array_values(array_filter($entries, static function (array $entry) use ($statusFilter): bool {
        if ($statusFilter === 'paid') {
            return !empty($entry['is_paid']);
        }
        if ($statusFilter === 'returned') {
            return !empty($entry['is_returned']);
        }
        if ($statusFilter === 'processing') {
            return empty($entry['is_paid']) && empty($entry['is_returned']);
        }

        return true;
    }));
}

/**
 * @return list<array<string, mixed>>
 */
function voucher_status_report_fetch_entries(PDO $pdo, array $scope, ?string $officeFilter = null, int $limit = 0): array
{
    $sql = 'SELECT vt.* FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');
    $params = [];

    $officeFilter = trim((string) ($officeFilter ?? ''));
    if ($officeFilter !== '' && strcasecmp($officeFilter, 'all') !== 0) {
        $sql .= ' AND LOWER(TRIM(vt.office_from)) = LOWER(TRIM(:office_filter))';
        $params[':office_filter'] = $officeFilter;
    }

    $sql .= ' ORDER BY vt.processing_no DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) max(1, min($limit, 5000));
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    $entries = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classified = voucher_status_report_classify_row($pdo, $row, $scope);
        if ($classified !== null) {
            $entries[] = $classified;
        }
    }

    return $entries;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array{total: int, sub_liaison: int, for_processing: int, paid: int, returned: int}
 */
function voucher_status_report_summarize(array $entries): array
{
    $summary = [
        'total' => count($entries),
        'sub_liaison' => 0,
        'for_processing' => 0,
        'paid' => 0,
        'returned' => 0,
    ];

    foreach ($entries as $entry) {
        foreach ((array) ($entry['categories'] ?? []) as $category) {
            $key = (string) ($category['key'] ?? '');
            if ($key === 'sub_liaison') {
                $summary['sub_liaison']++;
            }
        }
        if (!empty($entry['is_paid'])) {
            $summary['paid']++;
        } elseif (!empty($entry['is_returned'])) {
            $summary['returned']++;
        } else {
            $summary['for_processing']++;
        }
    }

    return $summary;
}

<?php

declare(strict_types=1);

/** @return 'no'|'yes'|'returned' */
function voucher_tracking_normalize_active_status(?string $status): string
{
    $s = strtolower(trim((string) $status));
    if ($s === 'yes' || $s === 'returned') {
        return $s;
    }

    return 'no';
}

/** Excluded from dashboard / voucher status counts only when encoded or pending at encoder. */
function voucher_tracking_is_excluded_from_counts(?string $status): bool
{
    return voucher_tracking_normalize_active_status($status) === 'no';
}

/** True when voucher_tracking.active_status is returned (matches voucher_status.php). */
function voucher_tracking_is_returned_active_status(?string $status): bool
{
    return voucher_tracking_normalize_active_status($status) === 'returned';
}

/**
 * SQL fragment for voucher_tracking listings that omit encoded/pending rows only.
 *
 * Includes active_status yes (forwarded/received) and returned (office return).
 */
function voucher_tracking_counts_include_sql(string $alias = 'vt'): string
{
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'vt';

    return " AND {$alias}.active_status <> 'no'";
}

/** True when a voucher identifier was never assigned (empty, TBD, etc.). */
function voucher_field_is_placeholder(string $value): bool
{
    $v = trim($value);
    if ($v === '') {
        return true;
    }
    $upper = strtoupper($v);

    return in_array($upper, ['TBD', 'N/A', 'NULL'], true);
}

/** Prefer the first non-placeholder candidate. */
function voucher_pick_field(string ...$candidates): string
{
    foreach ($candidates as $candidate) {
        $v = trim((string) $candidate);
        if (!voucher_field_is_placeholder($v)) {
            return $v;
        }
    }
    foreach ($candidates as $candidate) {
        $v = trim((string) $candidate);
        if ($v !== '') {
            return $v;
        }
    }

    return '';
}

/**
 * Read ors_no / dv_no / ada_check_no from a voucher table row, if present.
 *
 * @return array<string, string>
 */
function voucher_identifier_row(object $pdo, string $table, string $processing_no): array
{
    static $allowed = [
        'voucher_tracking' => ['ors_no', 'dv_no', 'ada_check_no'],
        'voucher_receiving' => ['ors_no', 'dv_no', 'ada_check_no'],
        'voucher_incoming' => ['ors_no', 'dv_no', 'ada_check_no'],
        'voucher_sent' => ['ors_no', 'dv_no', 'ada_check_no'],
        'dv_entries' => ['ors_no', 'dv_no', 'ada_check_no'],
        'vouchers' => ['dv_no', 'ada_check_no'],
    ];

    $processing_no = trim($processing_no);
    if ($processing_no === '' || !isset($allowed[$table])) {
        return [];
    }

    $cols = $allowed[$table];
    $select = implode(', ', $cols);

    try {
        $stmt = $pdo->prepare("SELECT {$select} FROM {$table} WHERE processing_no = :processing_no LIMIT 1");
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }

        $out = [];
        foreach ($cols as $col) {
            $out[$col] = trim((string) ($row[$col] ?? ''));
        }

        return $out;
    } catch (PDOException) {
        return [];
    }
}

/**
 * Best-known ORS/DV/ADA values across tracking, queue tables, dv_entries, and vouchers.
 *
 * @return array{ors_no: string, dv_no: string, ada_check_no: string}
 */
function voucher_fetch_identifiers(object $pdo, string $processing_no): array
{
    $processing_no = trim($processing_no);
    $result = ['ors_no' => '', 'dv_no' => '', 'ada_check_no' => ''];
    if ($processing_no === '') {
        return $result;
    }

    $tables = [
        'voucher_tracking',
        'voucher_receiving',
        'voucher_incoming',
        'voucher_sent',
        'dv_entries',
        'vouchers',
    ];

    $ors = [];
    $dv = [];
    $ada = [];
    foreach ($tables as $table) {
        $row = voucher_identifier_row($pdo, $table, $processing_no);
        if ($row !== []) {
            if (isset($row['ors_no'])) {
                $ors[] = $row['ors_no'];
            }
            if (isset($row['dv_no'])) {
                $dv[] = $row['dv_no'];
            }
            if (isset($row['ada_check_no'])) {
                $ada[] = $row['ada_check_no'];
            }
        }
    }

    $result['ors_no'] = voucher_pick_field(...$ors);
    $result['dv_no'] = voucher_pick_field(...$dv);
    $result['ada_check_no'] = voucher_pick_field(...$ada);

    return $result;
}

/** Persist known identifiers on voucher_tracking when returning or re-forwarding. */
function voucher_sync_tracking_identifiers(
    object $pdo,
    string $processing_no,
    string $ors_no,
    string $dv_no,
    string $ada_check_no
): void {
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return;
    }

    $current = voucher_identifier_row($pdo, 'voucher_tracking', $processing_no);
    if ($current === []) {
        return;
    }

    $finalOrs = voucher_pick_field($ors_no, $current['ors_no'] ?? '');
    $finalDv = voucher_pick_field($dv_no, $current['dv_no'] ?? '');
    $finalAda = voucher_pick_field($ada_check_no, $current['ada_check_no'] ?? '');

    if (voucher_field_is_placeholder($finalOrs)
        && voucher_field_is_placeholder($finalDv)
        && voucher_field_is_placeholder($finalAda)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE voucher_tracking
             SET ors_no = :ors_no, dv_no = :dv_no, ada_check_no = :ada_check_no
             WHERE processing_no = :processing_no'
        );
        $stmt->bindValue(':ors_no', $finalOrs, PDO::PARAM_STR);
        $stmt->bindValue(':dv_no', $finalDv, PDO::PARAM_STR);
        $stmt->bindValue(':ada_check_no', $finalAda, PDO::PARAM_STR);
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException) {
        // tracking row may be absent on edge paths
    }
}

/** @return array<string, mixed>|null */
function voucher_tracking_fetch_by_processing_no(object $pdo, string $processing_no): ?array
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT voucher_status, active_status, process_history, ors_no, dv_no, ada_check_no
         FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1'
    );
    $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function voucher_tracking_fetch_process_history(object $pdo, string $processing_no): ?string
{
    $row = voucher_tracking_fetch_by_processing_no($pdo, $processing_no);
    if ($row === null) {
        return null;
    }
    $hist = trim((string) ($row['process_history'] ?? ''));
    return $hist !== '' ? $hist : null;
}

function voucher_tracking_parse_returned_by(?string $voucher_status): string
{
    $status = trim((string) $voucher_status);
    if ($status === '') {
        return '';
    }
    if (preg_match('/Returned\s+by:\s*(.+)$/i', $status, $m)) {
        return trim($m[1]);
    }

    return '';
}

/** True when the logged-in user returned their own voucher (e.g. encoder recall from Sent). */
function voucher_tracking_is_self_return(?string $tracking_voucher_status, ?string $logged_user_name = null): bool
{
    $returnedBy = voucher_tracking_parse_returned_by($tracking_voucher_status);
    if ($returnedBy === '') {
        return false;
    }
    $logged = trim((string) ($logged_user_name ?? ($_SESSION['logged_user_emp_name'] ?? '')));
    if ($logged === '') {
        return false;
    }

    return strcasecmp($returnedBy, $logged) === 0;
}

/** e.g. "Forwarded by: Jane Doe" → "Forwarded" */
function voucher_tracking_action_label(?string $voucher_status): string
{
    $status = trim((string) $voucher_status);
    if ($status === '') {
        return '';
    }
    if (preg_match('/^(.+?)\s+by\s*:/i', $status, $m)) {
        return trim($m[1]);
    }

    return $status;
}

/**
 * @return array{udc: string, designation: string, section: string, office: string, display_name: string}|null
 */
function voucher_tracking_lookup_user_by_display_name(object $pdo, string $displayName): ?array
{
    $name = trim($displayName);
    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT udc, designation, section, office,
            TRIM(CONCAT(COALESCE(emp_fn, \'\'), \' \', COALESCE(emp_mi, \'\'), \' \', COALESCE(emp_ln, \'\'))) AS display_name
         FROM user_group
         WHERE TRIM(CONCAT(COALESCE(emp_fn, \'\'), \' \', COALESCE(emp_mi, \'\'), \' \', COALESCE(emp_ln, \'\'))) = :name
         LIMIT 1'
    );
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'udc' => trim((string) ($row['udc'] ?? '')),
        'designation' => trim((string) ($row['designation'] ?? '')),
        'section' => trim((string) ($row['section'] ?? '')),
        'office' => trim((string) ($row['office'] ?? '')),
        'display_name' => trim((string) ($row['display_name'] ?? $name)),
    ];
}

/** First designation token from comma-separated user_group.designation. */
function voucher_tracking_primary_designation(?string $designation): string
{
    $designation = trim((string) $designation);
    if ($designation === '') {
        return '';
    }
    $parts = array_map('trim', explode(',', $designation));

    return $parts[0] ?? '';
}

/**
 * Office from the most recent "Returned by" entry in process_history.
 */
function voucher_tracking_history_last_return_office(string $process_history): string
{
    $lines = voucher_tracking_parse_process_history_lines($process_history);
    $lastOffice = '';
    foreach ($lines as $line) {
        if (stripos($line['action'], 'Returned by') === false) {
            continue;
        }
        $office = trim($line['office']);
        if ($office !== '') {
            $lastOffice = $office;
        }
    }

    return $lastOffice;
}

/** Name from the most recent "Returned by" line in process_history. */
function voucher_tracking_parse_last_returned_by_from_history(string $process_history): string
{
    $lines = voucher_tracking_parse_process_history_lines($process_history);
    $lastReturnedBy = '';
    foreach ($lines as $line) {
        if (stripos((string) ($line['action'] ?? ''), 'Returned by') === false) {
            continue;
        }
        $name = trim((string) ($line['name'] ?? ''));
        if ($name !== '') {
            $lastReturnedBy = $name;
        }
    }

    return $lastReturnedBy;
}

/** Resolve who returned the voucher from voucher_status and/or process_history. */
function voucher_tracking_resolve_returned_by(?string $voucher_status, string $process_history = ''): string
{
    $returnedBy = voucher_tracking_parse_returned_by($voucher_status);
    if ($returnedBy !== '') {
        return $returnedBy;
    }

    return voucher_tracking_parse_last_returned_by_from_history($process_history);
}

/**
 * Resolve who returned the voucher for encoder re-forward routing.
 * Skips encoder self-recalls (e.g. Sent queue) and uses the latest processing-unit return.
 */
function voucher_tracking_resolve_returned_by_for_encoder_reforward(
    ?string $tracking_voucher_status,
    string $process_history = '',
    ?string $encoder_name = null
): string {
    $encoder = trim((string) ($encoder_name ?? ($_SESSION['logged_user_emp_name'] ?? '')));

    $fromStatus = voucher_tracking_parse_returned_by($tracking_voucher_status);
    if ($fromStatus !== '' && !voucher_tracking_is_self_return('Returned by: ' . $fromStatus, $encoder)) {
        return $fromStatus;
    }

    $lines = voucher_tracking_parse_process_history_lines($process_history);
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = $lines[$i];
        if (stripos((string) ($line['action'] ?? ''), 'Returned by') === false) {
            continue;
        }
        $name = trim((string) ($line['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        if ($encoder !== '' && strcasecmp($name, $encoder) === 0) {
            continue;
        }

        return $name;
    }

    return '';
}

/** Office recorded on the most recent return line for a specific returner. */
function voucher_tracking_history_return_office_for_returner(string $process_history, string $returned_by): string
{
    $returned_by = trim($returned_by);
    if ($returned_by === '') {
        return '';
    }

    $lines = voucher_tracking_parse_process_history_lines($process_history);
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = $lines[$i];
        if (stripos((string) ($line['action'] ?? ''), 'Returned by') === false) {
            continue;
        }
        $name = trim((string) ($line['name'] ?? ''));
        if ($name !== '' && strcasecmp($name, $returned_by) === 0) {
            return trim((string) ($line['office'] ?? ''));
        }
    }

    return '';
}

/**
 * Resolve forward target when re-forwarding a returned voucher.
 *
 * @return array{designation: string, label: string, returned_by: string, udc: string, office: string}
 */
function voucher_tracking_return_forward_target(
    object $pdo,
    ?string $tracking_voucher_status,
    string $process_history = '',
    ?string $encoder_name = null
): array {
    $empty = ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => '', 'office' => ''];
    $returnedBy = $encoder_name !== null
        ? voucher_tracking_resolve_returned_by_for_encoder_reforward($tracking_voucher_status, $process_history, $encoder_name)
        : voucher_tracking_resolve_returned_by($tracking_voucher_status, $process_history);
    if ($returnedBy === '') {
        return $empty;
    }

    $user = voucher_tracking_lookup_user_by_display_name($pdo, $returnedBy);
    $designation = '';
    $udc = '';
    $office = '';
    if ($user !== null) {
        $udc = trim((string) ($user['udc'] ?? ''));
        $office = trim((string) ($user['office'] ?? ''));
        $designation = voucher_tracking_primary_designation($user['designation']);
        if ($designation === '' && $user['section'] !== '') {
            $designation = $user['section'];
        }
    }

    if ($office === '') {
        $office = voucher_tracking_history_return_office_for_returner($process_history, $returnedBy);
    }
    if ($office === '' && trim($process_history) !== '') {
        $office = voucher_tracking_history_last_return_office($process_history);
    }
    if ($office === '' && $designation !== '') {
        $office = voucher_resolve_office_for_designation_route($pdo, $designation, '');
    }

    $label = $returnedBy;
    if ($designation !== '') {
        $label .= ' (' . $designation . ')';
    }

    return [
        'designation' => $designation,
        'label' => $label,
        'returned_by' => $returnedBy,
        'udc' => $udc,
        'office' => $office,
    ];
}

/**
 * Map a section/unit label (e.g. Planning Section) to a forward designation.
 *
 * @return array{designation: string, label: string, returned_by: string}
 */
function voucher_tracking_forward_target_from_section(object $pdo, string $sectionOrUnit): array
{
    $sectionOrUnit = trim($sectionOrUnit);
    if ($sectionOrUnit === '') {
        return ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => ''];
    }

    $designation = $sectionOrUnit;
    $stmt = $pdo->prepare(
        'SELECT designation, section FROM user_group
         WHERE section = :s_section OR FIND_IN_SET(:s_designation, designation) > 0
         LIMIT 1'
    );
    $stmt->bindValue(':s_section', $sectionOrUnit, PDO::PARAM_STR);
    $stmt->bindValue(':s_designation', $sectionOrUnit, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $primary = voucher_tracking_primary_designation($row['designation'] ?? '');
        if ($primary !== '') {
            $designation = $primary;
        } elseif (trim((string) ($row['section'] ?? '')) !== '') {
            $designation = trim((string) $row['section']);
        }
    }

    return [
        'designation' => $designation,
        'label' => $sectionOrUnit,
        'returned_by' => '',
        'udc' => '',
    ];
}

/**
 * Resolve Forward To when encoder re-forwards after a return (active_status may be "no").
 *
 * @return array{designation: string, label: string, returned_by: string, udc: string, office: string}
 */
function voucher_tracking_resolve_return_forward_target(
    object $pdo,
    ?string $tracking_voucher_status,
    ?string $encoded_from,
    ?string $encoder_section = null,
    ?string $logged_user_name = null,
    string $process_history = ''
): array {
    $empty = ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => '', 'office' => ''];
    $returnedBy = voucher_tracking_resolve_returned_by_for_encoder_reforward(
        $tracking_voucher_status,
        $process_history,
        $logged_user_name
    );
    if ($returnedBy !== '') {
        return voucher_tracking_return_forward_target(
            $pdo,
            'Returned by: ' . $returnedBy,
            $process_history,
            $logged_user_name
        );
    }

    $encodedFrom = trim((string) $encoded_from);
    $encoderSection = trim((string) $encoder_section);
    $loggedOffice = voucher_logged_user_office();
    if ($encodedFrom === '') {
        return $empty;
    }
    if ($encoderSection !== '' && strcasecmp($encodedFrom, $encoderSection) === 0) {
        return $empty;
    }
    if ($loggedOffice !== '' && voucher_tracking_offices_match($encodedFrom, $loggedOffice)) {
        return $empty;
    }

    $target = voucher_tracking_forward_target_from_section($pdo, $encodedFrom);
    $target['office'] = $target['designation'] !== ''
        ? voucher_resolve_office_for_designation_route($pdo, $target['designation'], '')
        : '';

    return $target;
}

/** Receiver for encoder re-forward after "Returned by:" (specific person, then designation). */
function voucher_forward_receiver_for_return_target(
    object $pdo,
    ?string $tracking_voucher_status,
    string $forward_designation,
    string $office_to,
    string $exclude_udc = '',
    string $process_history = '',
    ?string $encoder_name = null
): array {
    $returnTarget = voucher_tracking_return_forward_target(
        $pdo,
        $tracking_voucher_status,
        $process_history,
        $encoder_name
    );
    $returnOffice = trim((string) ($returnTarget['office'] ?? ''));
    if ($returnOffice !== '') {
        $office_to = $returnOffice;
    }
    $returnedByUdc = trim((string) ($returnTarget['udc'] ?? ''));
    if ($returnedByUdc !== '') {
        $validatedUdc = voucher_filter_udcs_by_user_group_office($pdo, $returnedByUdc, $office_to);
        if ($validatedUdc !== '') {
            return [
                'receiver_udc' => $validatedUdc,
                'forwarded_to' => $returnTarget['label'] !== '' ? $returnTarget['label'] : $returnTarget['returned_by'],
                'temp_errors' => [],
            ];
        }
    }
    $designation = trim($forward_designation);
    if ($designation === '') {
        $designation = trim((string) ($returnTarget['designation'] ?? ''));
    }
    if ($designation === '') {
        return [
            'receiver_udc' => '',
            'forwarded_to' => '',
            'temp_errors' => ['unassigned_udc' => 'No user is assigned to accept'],
        ];
    }
    return voucher_forward_receiver_udcs_for_designation($pdo, $designation, $office_to, $exclude_udc);
}

/**
 * Resolve office from the first UDC in a comma-separated receiver list.
 */
function voucher_tracking_lookup_office_by_udc(object $pdo, string $udc_list): string
{
    $udcs = array_values(array_filter(array_map('trim', explode(',', $udc_list)), static fn(string $v): bool => $v !== ''));
    if ($udcs === []) {
        return '';
    }

    $stmt = $pdo->prepare(
        "SELECT TRIM(office) AS office FROM user_group
         WHERE udc = :udc AND TRIM(COALESCE(office, '')) <> ''
         LIMIT 1"
    );
    foreach ($udcs as $udc) {
        $stmt->bindValue(':udc', $udc, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        $office = trim((string) ($row['office'] ?? ''));
        if ($office !== '') {
            return $office;
        }
    }

    return '';
}

/**
 * Remove one or more UDCs from a comma-separated receiver list.
 */
function voucher_udcs_excluding(string $udc_list, string $exclude_udc): string
{
    $exclude = array_values(array_filter(array_map('trim', explode(',', $exclude_udc)), static fn(string $v): bool => $v !== ''));
    if ($exclude === []) {
        return trim($udc_list);
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $udc_list)), static function (string $udc) use ($exclude): bool {
        if ($udc === '') {
            return false;
        }
        foreach ($exclude as $blocked) {
            if (strcasecmp($udc, $blocked) === 0) {
                return false;
            }
        }

        return true;
    }));

    return implode(',', array_unique($parts));
}

/**
 * Pick designated_udc entries that belong to the given PENRO office when designated_office is CSV-aligned.
 */
function voucher_pick_designated_udcs_for_office(string $designated_udc, string $designated_office, string $penro_office): string
{
    $udcs = array_values(array_filter(array_map('trim', explode(',', $designated_udc)), static fn(string $v): bool => $v !== ''));
    if ($udcs === []) {
        return '';
    }

    $offices = array_map('trim', explode(',', $designated_office));
    $penro_office = trim($penro_office);
    if ($penro_office === '' || count($offices) <= 1) {
        return implode(',', array_unique($udcs));
    }

    $picked = [];
    foreach ($offices as $i => $off) {
        if (strcasecmp($off, $penro_office) !== 0) {
            continue;
        }
        if (isset($udcs[$i]) && $udcs[$i] !== '') {
            $picked[] = $udcs[$i];
        } elseif (count($udcs) === 1) {
            // Single assignee shared across multiple listed offices (e.g. Liaison Officer per CENRO).
            $picked[] = $udcs[0];
        }
    }

    if ($picked !== []) {
        return implode(',', array_values(array_unique($picked)));
    }

    return implode(',', array_unique($udcs));
}

/**
 * Keep UDCs that exist in user_group for the given office (cross-check after designation_limit).
 */
function voucher_filter_udcs_by_user_group_office(object $pdo, string $udc_list, string $office): string
{
    $office = trim($office);
    $udcs = array_values(array_filter(array_map('trim', explode(',', $udc_list)), static fn(string $v): bool => $v !== ''));
    if ($udcs === []) {
        return '';
    }

    $sql = "SELECT udc FROM user_group WHERE udc = :udc AND TRIM(udc) <> ''";
    if ($office !== '') {
        $sql .= ' AND office = :office';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $validated = [];
    foreach ($udcs as $udc) {
        $stmt->bindValue(':udc', $udc, PDO::PARAM_STR);
        if ($office !== '') {
            $stmt->bindValue(':office', $office, PDO::PARAM_STR);
        }
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $validated[] = $udc;
        }
    }

    return implode(',', array_values(array_unique($validated)));
}

/**
 * Pick designated_udc for a designation/office pair, validated in user_group.
 */
function voucher_designation_limit_receiver_udcs_for_office(
    object $pdo,
    string $designation,
    string $office
): string {
    $designation = trim($designation);
    $office = trim($office);
    if ($designation === '') {
        return '';
    }

    $limitStmt = $pdo->prepare(
        'SELECT designated_udc, designated_office FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation)) LIMIT 1'
    );
    $limitStmt->bindValue(':designation', $designation, PDO::PARAM_STR);
    $limitStmt->execute();
    $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);
    if (!$limitRow) {
        return '';
    }

    $designated_udc = (string) ($limitRow['designated_udc'] ?? '');
    $designated_office = (string) ($limitRow['designated_office'] ?? '');

    $picked = voucher_pick_designated_udcs_for_office($designated_udc, $designated_office, $office);
    // Extra UDCs beyond the designated_office CSV (e.g. 5 UDCs, 3 offices) resolve via user_group.office.
    $officeMatched = voucher_filter_udcs_by_user_group_office($pdo, $designated_udc, $office);

    $merged = [];
    foreach (array_merge(
        array_filter(array_map('trim', explode(',', $picked))),
        array_filter(array_map('trim', explode(',', $officeMatched)))
    ) as $udc) {
        if ($udc !== '') {
            $merged[$udc] = $udc;
        }
    }

    return implode(',', array_values($merged));
}

/**
 * Resolve receiver_udc for a forward/return target (designation, section label, or UDC).
 * Uses designation_limit (same as voucher_receiving forward) then user_group fallbacks.
 */
function voucher_resolve_receiver_udc_for_destination(
    object $pdo,
    string $destination,
    string $penro_office,
    string $exclude_udc = ''
): string {
    $destination = trim($destination);
    $penro_office = trim($penro_office);
    $exclude_udc = trim($exclude_udc);
    if ($destination === '') {
        return '';
    }

    $finalize = static function (string $resolved) use ($exclude_udc): string {
        return voucher_udcs_excluding($resolved, $exclude_udc);
    };

    $candidates = [$destination];
    $mapped = voucher_tracking_forward_target_from_section($pdo, $destination);
    if (($mapped['designation'] ?? '') !== '' && !in_array($mapped['designation'], $candidates, true)) {
        $candidates[] = trim((string) $mapped['designation']);
    }

    $limitStmt = $pdo->prepare(
        'SELECT designated_udc, designated_office FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation)) LIMIT 1'
    );

    foreach ($candidates as $candidate) {
        if ($penro_office !== '') {
            $designated = voucher_designation_limit_receiver_udcs_for_office($pdo, $candidate, $penro_office);
            if ($designated !== '') {
                $resolved = $finalize($designated);
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        $limitStmt->bindValue(':designation', $candidate, PDO::PARAM_STR);
        $limitStmt->execute();
        $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);
        if (!$limitRow) {
            continue;
        }

        $listedOffices = array_values(array_filter(
            array_map('trim', explode(',', (string) ($limitRow['designated_office'] ?? ''))),
            static fn(string $office): bool => $office !== '' && strcasecmp($office, 'None') !== 0
        ));
        foreach ($listedOffices as $listedOffice) {
            if ($penro_office !== '' && strcasecmp($listedOffice, $penro_office) === 0) {
                continue;
            }
            $designated = voucher_designation_limit_receiver_udcs_for_office($pdo, $candidate, $listedOffice);
            if ($designated !== '') {
                $resolved = $finalize($designated);
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }
    }

    if ($penro_office !== '') {
        $udcStmt = $pdo->prepare(
            "SELECT udc FROM user_group
             WHERE udc = :dest AND office = :office AND TRIM(udc) <> ''
             LIMIT 1"
        );
        $udcStmt->bindValue(':dest', $destination, PDO::PARAM_STR);
        $udcStmt->bindValue(':office', $penro_office, PDO::PARAM_STR);
        $udcStmt->execute();
        $udcRow = $udcStmt->fetch(PDO::FETCH_ASSOC);
        if ($udcRow && trim((string) ($udcRow['udc'] ?? '')) !== '') {
            $resolved = $finalize(trim((string) $udcRow['udc']));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        $groupStmt = $pdo->prepare(
            "SELECT udc FROM user_group
             WHERE office = :office AND TRIM(udc) <> ''
               AND (
                    section = :dest_section
                    OR FIND_IN_SET(:dest_designation, designation) > 0
                    OR FIND_IN_SET(:dest_designation_spaced, REPLACE(designation, ', ', ',')) > 0
               )"
        );
        foreach ($candidates as $candidate) {
            $groupStmt->bindValue(':office', $penro_office, PDO::PARAM_STR);
            $groupStmt->bindValue(':dest_section', $candidate, PDO::PARAM_STR);
            $groupStmt->bindValue(':dest_designation', $candidate, PDO::PARAM_STR);
            $groupStmt->bindValue(':dest_designation_spaced', $candidate, PDO::PARAM_STR);
            $groupStmt->execute();
            $udcs = [];
            while ($row = $groupStmt->fetch(PDO::FETCH_ASSOC)) {
                $udc = trim((string) ($row['udc'] ?? ''));
                if ($udc !== '') {
                    $udcs[] = $udc;
                }
            }
            if ($udcs !== []) {
                $resolved = $finalize(implode(',', array_values(array_unique($udcs))));
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }
    }

    $fallbackStmt = $pdo->prepare(
        "SELECT udc FROM user_group
         WHERE TRIM(udc) <> ''
           AND (
                section = :dest_section
                OR FIND_IN_SET(:dest_designation, designation) > 0
                OR FIND_IN_SET(:dest_designation_spaced, REPLACE(designation, ', ', ',')) > 0
           )"
    );
    foreach ($candidates as $candidate) {
        $fallbackStmt->bindValue(':dest_section', $candidate, PDO::PARAM_STR);
        $fallbackStmt->bindValue(':dest_designation', $candidate, PDO::PARAM_STR);
        $fallbackStmt->bindValue(':dest_designation_spaced', $candidate, PDO::PARAM_STR);
        $fallbackStmt->execute();
        $udcs = [];
        while ($row = $fallbackStmt->fetch(PDO::FETCH_ASSOC)) {
            $udc = trim((string) ($row['udc'] ?? ''));
            if ($udc !== '') {
                $udcs[] = $udc;
            }
        }
        if ($udcs !== []) {
            $resolved = $finalize(implode(',', array_values(array_unique($udcs))));
            if ($resolved !== '') {
                return $resolved;
            }
        }
    }

    return '';
}

/**
 * Logged-in user's office from session (trimmed).
 */
function voucher_logged_user_office(): string
{
    return trim((string) ($_SESSION['logged_user_office'] ?? ''));
}

/**
 * Logged-in user's designations as a trimmed list.
 *
 * @return list<string>
 */
function voucher_logged_user_designations(): array
{
    return array_values(array_filter(array_map(
        'trim',
        explode(',', (string) ($_SESSION['logged_user_designation'] ?? ''))
    ), static fn(string $value): bool => $value !== ''));
}

/**
 * Whether the user has a specific designation (case-insensitive).
 *
 * @param list<string> $designations
 */
function voucher_user_has_designation(array $designations, string $needle): bool
{
    $needle = trim($needle);
    if ($needle === '') {
        return false;
    }

    foreach ($designations as $designation) {
        if (strcasecmp(trim($designation), $needle) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the logged-in user belongs to a downstream voucher processing unit
 * (Planning, ICU, Budget, Accounting, Cashiers, etc.).
 *
 * @param list<string> $designations
 */
function voucher_user_is_process_unit_member(array $designations): bool
{
    foreach (voucher_tracking_dashboard_sections() as $section) {
        if (voucher_user_has_designation($designations, $section)) {
            return true;
        }
    }

    foreach (['Processor', 'Accountant III', 'Budget Officer'] as $role) {
        if (voucher_user_has_designation($designations, $role)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the user may unlock payee on salary/locked voucher types.
 *
 * @param list<string> $designations
 */
function voucher_user_can_unlock_payee(array $designations): bool
{
    foreach ([
        'System Admin',
        'Accounting Unit',
        'Accountant III',
        'Cashiers Unit',
    ] as $role) {
        if (voucher_user_has_designation($designations, $role)) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve which office should be used when routing to a designation.
 * Prefers the logged user's office when registered, otherwise the first
 * designation_limit office that has an assigned UDC.
 */
function voucher_resolve_office_for_designation_route(object $pdo, string $designation, string $logged_user_office): string
{
    $logged_user_office = trim($logged_user_office);
    $designation = trim($designation);
    if ($designation === '') {
        return $logged_user_office;
    }

    if ($logged_user_office !== '' && voucher_designation_limit_office_registered($pdo, $logged_user_office, $designation)) {
        return $logged_user_office;
    }

    $stmt = $pdo->prepare(
        'SELECT designated_office, designated_udc FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation))
         LIMIT 1'
    );
    $stmt->bindValue(':designation', $designation, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $logged_user_office;
    }

    $offices = array_values(array_filter(
        array_map('trim', explode(',', (string) ($row['designated_office'] ?? ''))),
        static fn(string $office): bool => $office !== '' && strcasecmp($office, 'None') !== 0
    ));
    $udcs = array_map('trim', explode(',', (string) ($row['designated_udc'] ?? '')));

    foreach ($offices as $i => $office) {
        if (trim((string) ($udcs[$i] ?? '')) !== '') {
            return $office;
        }
    }

    if ($offices !== []) {
        return $offices[0];
    }

    return $logged_user_office;
}

/**
 * Resolve ICU receiver for Liaison Officer forwards.
 *
 * @return array{receiver_udc: string, forwarded_to: string, office_to: string, temp_errors: array<string, string>}
 */
function voucher_forward_liaison_icu_receiver(object $pdo, string $logged_user_office): array
{
    require_once __DIR__ . '/utilities_office_helper.inc.php';
    utilities_office_ensure_schema($pdo);

    $targetTo = 'ICU';
    $processingOffice = utilities_office_liaison_forward_processing_office($pdo, $logged_user_office);
    $officeTo = voucher_resolve_office_for_designation_route(
        $pdo,
        $targetTo,
        $processingOffice !== '' ? $processingOffice : $logged_user_office
    );
    $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $targetTo, $officeTo);

    return [
        'receiver_udc' => $resolved['receiver_udc'],
        'forwarded_to' => $resolved['forwarded_to'],
        'office_to' => $officeTo,
        'temp_errors' => $resolved['temp_errors'],
    ];
}

/**
 * Office where a Liaison Officer should receive encoder forwards.
 */
function voucher_encoder_liaison_route_office(object $pdo, string $encoder_office): string
{
    require_once __DIR__ . '/utilities_office_helper.inc.php';
    utilities_office_ensure_schema($pdo);

    $routeOffice = utilities_office_encoder_liaison_office($pdo, $encoder_office);
    if ($routeOffice !== '') {
        return $routeOffice;
    }

    return trim($encoder_office);
}

/**
 * Whether an office value appears in a designated_office CSV list.
 */
function voucher_designation_limit_office_matches(string $designated_office, string $office): bool
{
    $office = trim($office);
    if ($office === '') {
        return false;
    }

    foreach (array_map('trim', explode(',', $designated_office)) as $listedOffice) {
        if ($listedOffice !== '' && strcasecmp($listedOffice, $office) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a designation_limit row lists the given office in designated_office.
 */
function voucher_designation_limit_office_registered(object $pdo, string $office, string $designation): bool
{
    $office = trim($office);
    $designation = trim($designation);
    if ($office === '' || $designation === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT designated_office FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation))
         LIMIT 1'
    );
    $stmt->bindValue(':designation', $designation, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    return voucher_designation_limit_office_matches((string) ($row['designated_office'] ?? ''), $office);
}

/**
 * Resolve receiver_udc when the destination designation supports the logged user's office.
 *
 * @return array{receiver_udc: string, temp_errors: array<string, string>}
 */
function voucher_resolve_receiver_for_designation_at_office(object $pdo, string $designation, string $logged_user_office): array
{
    $designation = trim($designation);
    $logged_user_office = trim($logged_user_office);
    if ($designation === '' || $logged_user_office === '') {
        return ['receiver_udc' => '', 'temp_errors' => []];
    }

    if (!voucher_designation_limit_office_registered($pdo, $logged_user_office, $designation)) {
        return ['receiver_udc' => '', 'temp_errors' => []];
    }

    return voucher_forward_receiver_udcs_for_designation($pdo, $designation, $logged_user_office);
}

/**
 * Look up a payee within a specific office.
 *
 * @return array{found: bool, udc: string}
 */
function voucher_lookup_payee_at_office(object $pdo, string $payee, string $office): array
{
    $payee = trim($payee);
    $office = trim($office);
    if ($payee === '' || $office === '') {
        return ['found' => false, 'udc' => ''];
    }

    $stmt = $pdo->prepare(
        "SELECT udc FROM user_group
         WHERE office = :office
           AND TRIM(CONCAT(COALESCE(emp_fn,''), ' ', COALESCE(emp_mi,''), ' ', COALESCE(emp_ln,''))) = :payee
         LIMIT 1"
    );
    $stmt->bindValue(':office', $office, PDO::PARAM_STR);
    $stmt->bindValue(':payee', $payee, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['found' => false, 'udc' => ''];
    }

    return [
        'found' => true,
        'udc' => trim((string) ($row['udc'] ?? '')),
    ];
}

/**
 * Look up a payee's UDC within a specific office.
 */
function voucher_lookup_payee_udc_at_office(object $pdo, string $payee, string $office): string
{
    return voucher_lookup_payee_at_office($pdo, $payee, $office)['udc'];
}

function voucher_type_is_engp(string $voucher_type): bool
{
    $voucher_type = trim($voucher_type);
    if ($voucher_type === '') {
        return false;
    }

    // Matches eNGP, e-NGP, e-NGP Retention, e-NGP Seedling Production & MP, etc.
    if (preg_match('/e-?\s*ngp/i', $voucher_type)) {
        return true;
    }

    $collapsed = strtolower(preg_replace('/[^a-z0-9]+/i', '', $voucher_type) ?? '');

    return str_starts_with($collapsed, 'engp');
}

/** Voucher types configured for direct forward routing (Special Access utility). */
function voucher_type_has_special_access(object $pdo, string $voucher_type): bool
{
    return voucher_special_access_forward_target($pdo, $voucher_type) !== '';
}

/**
 * Direct forward designation from Special Access utility (empty when none).
 */
function voucher_special_access_forward_target(object $pdo, string $voucher_type): string
{
    require_once __DIR__ . '/utilities_special_access_helper.inc.php';
    utilities_special_access_ensure_schema($pdo);

    return utilities_special_access_resolve_target($pdo, $voucher_type);
}

/**
 * Encoders at CENRO offices or offices with a registered Liaison Officer
 * must forward all vouchers to Liaison Officer first.
 */
function voucher_encoder_forwards_to_liaison_first(object $pdo, string $encoder_office): bool
{
    $encoder_office = trim($encoder_office);
    if ($encoder_office === '') {
        return false;
    }

    require_once __DIR__ . '/utilities_office_helper.inc.php';
    utilities_office_ensure_schema($pdo);
    if (utilities_office_encoder_requires_liaison_first($pdo, $encoder_office)) {
        return true;
    }

    return voucher_designation_limit_office_registered($pdo, $encoder_office, 'Liaison Officer');
}

function voucher_tracking_normalize_process_history(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    $value = preg_replace('/\\\\n/', "\n", $value) ?? $value;

    return trim($value);
}

/**
 * @return list<array{name: string, action: string, section: string, office: string}>
 */
function voucher_tracking_parse_process_history_lines(string $value): array
{
    $value = voucher_tracking_normalize_process_history($value);
    if ($value === '') {
        return [];
    }

    $parsed = [];
    foreach (preg_split('/\n/', $value) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '|')) {
            continue;
        }

        $parts = preg_split('/\s*\|\s*/', $line, 4);
        if (!isset($parts[0], $parts[1], $parts[2], $parts[3])) {
            continue;
        }

        $parsed[] = [
            'name' => trim($parts[0]),
            'action' => trim($parts[1]),
            'section' => trim($parts[2]),
            'office' => trim($parts[3]),
        ];
    }

    return $parsed;
}

function voucher_tracking_offices_match(string $left, string $right): bool
{
    $left = trim($left);
    $right = trim($right);
    if ($left === '' || $right === '') {
        return false;
    }

    return strcasecmp($left, $right) === 0;
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_tracking_history_origin_office(array $lines): string
{
    foreach ($lines as $line) {
        if (stripos($line['action'], 'Encoded By') !== false) {
            return trim($line['office']);
        }
    }

    return trim((string) ($lines[0]['office'] ?? ''));
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_tracking_history_has_planning_receive(array $lines): bool
{
    foreach ($lines as $line) {
        if (stripos($line['action'], 'Received by') === false) {
            continue;
        }
        if (voucher_tracking_normalize_section_label($line['section']) === 'Planning Section') {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_tracking_history_has_budget_receive(array $lines): bool
{
    foreach ($lines as $line) {
        if (stripos($line['action'], 'Received by') === false) {
            continue;
        }
        if (voucher_tracking_normalize_section_label($line['section']) === 'Budget Unit') {
            return true;
        }
    }

    return false;
}

/** Whether special-access routing sends the voucher directly to accounting roles. */
function voucher_special_access_routes_to_accounting(object $pdo, string $voucher_type): bool
{
    $target = voucher_special_access_forward_target($pdo, $voucher_type);
    if ($target === '') {
        return false;
    }

    $normalized = voucher_tracking_normalize_section_label($target);

    return in_array($normalized, ['Accounting Unit', 'Processor', 'Accountant III'], true)
        || in_array($target, ['Accounting Unit', 'Processor', 'Accountant III'], true);
}

/**
 * Planning/Budget have received the voucher, or it has direct special-access routing to accounting.
 */
function voucher_forwarding_upstream_routing_complete(
    object $pdo,
    string $voucher_type,
    string $process_history
): bool {
    if (voucher_special_access_routes_to_accounting($pdo, $voucher_type)) {
        return true;
    }

    $lines = voucher_tracking_parse_process_history_lines($process_history);

    return voucher_tracking_history_has_planning_receive($lines)
        || voucher_tracking_history_has_budget_receive($lines);
}

function voucher_incoming_load_process_history(object $pdo, string $processing_no, string $process_history): string
{
    $process_history = voucher_tracking_normalize_process_history($process_history);
    if ($process_history !== '' || trim($processing_no) === '') {
        return $process_history;
    }

    $histStmt = $pdo->prepare(
        'SELECT process_history FROM voucher_incoming WHERE processing_no = :processing_no LIMIT 1'
    );
    $histStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $histStmt->execute();
    $histRow = $histStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($histRow)) {
        return '';
    }

    return voucher_tracking_normalize_process_history((string) ($histRow['process_history'] ?? ''));
}

/**
 * Status written to document tracking when a voucher is received on Incoming.
 */
function voucher_incoming_resolve_receive_status(
    array $target,
    string $voucher_type,
    string $process_history,
    object $pdo
): string {
    if (voucher_user_has_designation($target, 'Planning Section')) {
        return 'For Charging';
    }
    if (voucher_user_has_designation($target, 'Budget Unit')) {
        return 'Verifying Availability of Fund and Allotment';
    }
    if (voucher_user_has_designation($target, 'Office of the PENRO')) {
        return 'For Approval of the PENRO';
    }
    if (voucher_user_has_designation($target, 'Cashiers Unit')) {
        return 'For Preparation of Check, ACIC or LDDAP-ADA';
    }

    $isAccountingRole = voucher_user_has_designation($target, 'Accounting Unit')
        || voucher_user_has_designation($target, 'Processor')
        || voucher_user_has_designation($target, 'Accountant III');
    $isIcu = voucher_user_has_designation($target, 'ICU');

    if ($isIcu || $isAccountingRole) {
        if (voucher_forwarding_upstream_routing_complete($pdo, $voucher_type, $process_history)) {
            return 'Processing the Disbursement Voucher';
        }

        return 'Checking of Requirements';
    }

    return '';
}

function voucher_forwarding_process_action_html(string $processStatus): string
{
    $processEmpty = $processStatus === '' || $processStatus === 'N/A';
    $processProcessing = $processStatus === 'Processing';

    if ($processEmpty) {
        return '<button class="btn tertiary pPop" id="openPopup" name="btn_process" type="button">Process</button>';
    }
    if ($processProcessing) {
        return '<button class="btn success pPop" id="openPopup" name="btn_process_confirm" type="button">Confirm</button>';
    }

    return '';
}

/** e-NGP types that always require DV No. at accounting receive. */
function voucher_type_requires_dv_no_always(string $voucher_type): bool
{
    static $types = [
        'e-NGP Retention',
        'e-NGP Seedling Production & MP',
    ];

    $voucher_type = trim($voucher_type);
    foreach ($types as $type) {
        if (strcasecmp($voucher_type, $type) === 0) {
            return true;
        }
    }

    return false;
}

/** Whether a user_group UDC is registered for a designation in designation_limit. */
function voucher_tracking_designation_limit_includes_udc(object $pdo, string $udc, string $designation): bool
{
    $udc = trim($udc);
    $designation = trim($designation);
    if ($udc === '' || $designation === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT designated_udc FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation))
         LIMIT 1'
    );
    $stmt->bindValue(':designation', $designation, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return false;
    }

    foreach (array_map('trim', explode(',', (string) ($row['designated_udc'] ?? ''))) as $candidate) {
        if ($candidate !== '' && strcasecmp($candidate, $udc) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve whether a process_history actor holds a designation using user_group + designation_limit
 * (not the section/unit stored on the history line).
 */
function voucher_tracking_history_actor_has_designation(object $pdo, string $displayName, string $designation): bool
{
    $user = voucher_tracking_lookup_user_by_display_name($pdo, $displayName);
    if ($user === null) {
        return false;
    }

    $udc = trim((string) ($user['udc'] ?? ''));
    if ($udc === '') {
        return false;
    }

    return voucher_tracking_designation_limit_includes_udc($pdo, $udc, $designation);
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_tracking_history_has_designation_action(
    object $pdo,
    array $lines,
    string $actionNeedle,
    string $designation
): bool {
    foreach ($lines as $line) {
        if (stripos((string) ($line['action'] ?? ''), $actionNeedle) === false) {
            continue;
        }
        if (voucher_tracking_history_actor_has_designation($pdo, (string) ($line['name'] ?? ''), $designation)) {
            return true;
        }
    }

    return false;
}

/**
 * Cross-office vouchers forwarded by ICU then received by a downstream processing unit
 * (e.g. Budget Unit) require DV at accounting receive.
 *
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function voucher_tracking_cross_office_icu_routed_to_processing_unit(object $pdo, array $lines): bool
{
    $downstreamDesignations = [
        'Budget Unit',
        'Planning Section',
        'Conservation & Development Section',
    ];

    $seenIcuForward = false;
    foreach ($lines as $line) {
        $action = (string) ($line['action'] ?? '');
        if (stripos($action, 'Forwarded by') !== false) {
            if (voucher_tracking_history_actor_has_designation($pdo, (string) ($line['name'] ?? ''), 'ICU')) {
                $seenIcuForward = true;
            }
            continue;
        }

        if (!$seenIcuForward || stripos($action, 'Received by') === false) {
            continue;
        }

        foreach ($downstreamDesignations as $designation) {
            if (voucher_tracking_history_actor_has_designation($pdo, (string) ($line['name'] ?? ''), $designation)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Accounting receive requires DV No. based on process_history:
 * - Always for selected e-NGP types.
 * - Same processing office: after upstream routing is complete (Planning or Budget receive, or special-access routing to accounting).
 * - Other offices: after ICU forwards and a downstream processing unit receives.
 */
function voucher_incoming_requires_dv_no(
    object $pdo,
    string $voucher_type,
    string $process_history,
    string $logged_user_office = ''
): bool {
    if (voucher_type_requires_dv_no_always($voucher_type)) {
        return true;
    }

    $lines = voucher_tracking_parse_process_history_lines($process_history);
    if ($lines === []) {
        return false;
    }

    $sameOffice = $logged_user_office !== ''
        && voucher_history_origin_matches_logged_office($process_history, $logged_user_office);

    if ($sameOffice) {
        return voucher_forwarding_upstream_routing_complete($pdo, $voucher_type, $process_history);
    }

    return voucher_tracking_cross_office_icu_routed_to_processing_unit($pdo, $lines);
}

/** Whether process_history shows the voucher was encoded in the logged user's office. */
function voucher_history_origin_matches_logged_office(string $process_history, string $logged_user_office): bool
{
    $lines = voucher_tracking_parse_process_history_lines($process_history);
    if ($lines === []) {
        return false;
    }

    return voucher_tracking_offices_match(
        voucher_tracking_history_origin_office($lines),
        $logged_user_office
    );
}

/**
 * Forwarding workflow uses full in-office actions (Process, Transmit, all forward targets)
 * when encoded in the logged user's office or when the voucher type is e-NGP.
 */
function voucher_forwarding_treat_as_same_office_workflow(
    string $voucher_type,
    string $process_history,
    string $logged_user_office
): bool {
    if (voucher_type_is_engp($voucher_type)) {
        return true;
    }

    return voucher_history_origin_matches_logged_office($process_history, $logged_user_office);
}

/** Whether encoder forward should use the return/re-forward routing path. */
function voucher_tracking_needs_return_forward(
    ?array $tracking_row,
    ?string $tracking_voucher_status = null,
    ?string $logged_user_name = null
): bool {
    $status = $tracking_voucher_status;
    if ($status === null && $tracking_row !== null) {
        $status = (string) ($tracking_row['voucher_status'] ?? '');
    }

    $processHistory = trim((string) (($tracking_row ?? [])['process_history'] ?? ''));
    $returnedBy = voucher_tracking_resolve_returned_by_for_encoder_reforward($status, $processHistory, $logged_user_name);

    if ($returnedBy !== '') {
        return true;
    }

    $active = voucher_tracking_normalize_active_status((string) (($tracking_row ?? [])['active_status'] ?? ''));
    if ($active === 'returned') {
        return true;
    }

    return false;
}

/**
 * Stored voucher_type for a processing number (pending encode / tracking).
 */
function voucher_fetch_stored_voucher_type(object $pdo, string $processing_no): string
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return '';
    }

    foreach (['vouchers', 'voucher_tracking'] as $table) {
        $stmt = $pdo->prepare("SELECT voucher_type FROM {$table} WHERE processing_no = :processing_no LIMIT 1");
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stored = trim((string) ($row['voucher_type'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }
    }

    return '';
}

/**
 * Resolve voucher type for encoder forward (POST fields, then stored record).
 */
function voucher_resolve_forward_voucher_type(object $pdo, string $processing_no, string $postedType, array $post = []): string
{
    $postedType = trim($postedType);
    if ($postedType !== '') {
        return $postedType;
    }

    foreach (['voucher_type', 'encoded_type'] as $field) {
        if (!isset($post[$field])) {
            continue;
        }
        $value = $post[$field];
        if (is_array($value)) {
            $value = end($value);
        }
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return voucher_fetch_stored_voucher_type($pdo, $processing_no);
}

/**
 * Default forward target for encoders based on designation_limit office registration.
 * CENRO / liaison-assigned offices are routed via voucher_encoder_forwards_to_liaison_first().
 * eNGP vouchers → Conservation & Development Section when registered for the office.
 * Other PENRO vouchers at the processing office → ICU when Planning Section is registered.
 */
function voucher_forward_encoder_default_target(object $pdo, string $logged_user_office, string $voucher_type = ''): string
{
    $logged_user_office = trim($logged_user_office);
    if ($logged_user_office === '') {
        return '';
    }

    if (voucher_encoder_forwards_to_liaison_first($pdo, $logged_user_office)) {
        return '';
    }

    if (voucher_type_is_engp($voucher_type)) {
        if (voucher_designation_limit_office_registered($pdo, $logged_user_office, 'Conservation & Development Section')) {
            return 'Conservation & Development Section';
        }

        return '';
    }

    if (voucher_designation_limit_office_registered($pdo, $logged_user_office, 'Planning Section')) {
        return 'ICU';
    }

    return '';
}

/**
 * @return array{receiver_udc: string, forwarded_to: string, temp_errors: array<string, string>}
 */
function voucher_forward_receiver_udcs_for_designation(
    object $pdo,
    string $target_to,
    string $office_to,
    string $exclude_udc = ''
): array {
    $forwarded_to = trim($target_to);
    $receiver_udc = voucher_resolve_receiver_udc_for_destination($pdo, $target_to, $office_to, $exclude_udc);
    $temp_dump = [];
    if ($receiver_udc === '') {
        $temp_dump['unassigned_udc'] = 'No user is assigned to accept';
    }

    return [
        'receiver_udc' => $receiver_udc,
        'forwarded_to' => $forwarded_to,
        'temp_errors' => $temp_dump,
    ];
}

/** @return array<string, string> */
function voucher_tracking_section_label_map(): array
{
    return [
        'BUDGET' => 'Budget Unit',
        'BUDGET UNIT' => 'Budget Unit',
        'ACCOUNTING' => 'Accounting Unit',
        'ACCOUNTING UNIT' => 'Accounting Unit',
        'Accountant III' => 'Accounting Unit',
        'ACCOUNTANT III' => 'Accounting Unit',
        'PLANNING' => 'Planning Section',
        'PLANNING SECTION' => 'Planning Section',
        'CONSERVATION & DEVELOPMENT' => 'Conservation & Development Section',
        'CONSERVATION & DEVELOPMENT SECTION' => 'Conservation & Development Section',
        'CDS' => 'Conservation & Development Section',
        'CASHIERS' => 'Cashiers Unit',
        'CASHIERS UNIT' => 'Cashiers Unit',
        'CASHIER' => 'Cashiers Unit',
        'PENRO OFFICE' => 'Office of the PENRO',
        'PENRO' => 'Office of the PENRO',
        'OFFICE OF THE PENRO' => 'Office of the PENRO',
        'ICU' => 'ICU',
        'MSD' => 'MSD',
        'TSD' => 'TSD',
        'ICT' => 'ICT',
        'RECORDS' => 'Records',
        'ADMIN AND FINANCE' => 'Admin and Finance',
    ];
}

function voucher_tracking_normalize_section_label(?string $section): string
{
    $section = trim((string) $section);
    if ($section === '') {
        return '';
    }
    $map = voucher_tracking_section_label_map();
    $upper = strtoupper($section);

    return $map[$upper] ?? $section;
}

/** @return 'receive'|'forward'|'return'|'archive'|'process'|'encode'|'other' */
function voucher_tracking_action_kind(?string $action): string
{
    $action = strtolower(trim((string) $action));
    if ($action === '') {
        return 'other';
    }
    if (str_contains($action, 'received by')) {
        return 'receive';
    }
    if (str_contains($action, 'encoded by')) {
        return 'encode';
    }
    if (str_contains($action, 'forwarded by')) {
        return 'forward';
    }
    if (str_contains($action, 'returned by')) {
        return 'return';
    }
    if (str_contains($action, 'archived by')) {
        return 'archive';
    }
    if (str_contains($action, 'processed by')) {
        return 'process';
    }
    if (str_contains($action, 'paid')) {
        return 'process';
    }

    return 'other';
}

/**
 * Resolve a dashboard section from an action log row (action_from, optional action_by lookup).
 *
 * @param array{action_from?: string, action_by?: string} $row
 * @param array<string, array<string, mixed>|null> $userCache
 */
function voucher_tracking_dashboard_section_from_action_row(
    array $row,
    ?object $pdo = null,
    array &$userCache = []
): string {
    $actionFrom = trim((string) ($row['action_from'] ?? ''));
    $candidates = $actionFrom !== '' ? [$actionFrom] : [];
    foreach (array_map('trim', explode(',', $actionFrom)) as $token) {
        if ($token !== '' && !in_array($token, $candidates, true)) {
            $candidates[] = $token;
        }
    }

    foreach ($candidates as $candidate) {
        $section = voucher_tracking_normalize_section_label($candidate);
        if ($section !== '' && voucher_tracking_is_dashboard_section($section)) {
            return $section;
        }
    }

    if ($pdo === null) {
        return voucher_tracking_normalize_section_label($actionFrom);
    }

    $actionBy = trim((string) ($row['action_by'] ?? ''));
    if ($actionBy === '') {
        return voucher_tracking_normalize_section_label($actionFrom);
    }

    if (!array_key_exists($actionBy, $userCache)) {
        $userCache[$actionBy] = voucher_tracking_lookup_user_by_display_name($pdo, $actionBy);
    }
    $user = $userCache[$actionBy];
    if ($user === null) {
        return voucher_tracking_normalize_section_label($actionFrom);
    }

    $fromUser = array_filter([
        trim((string) ($user['section'] ?? '')),
        ...array_map('trim', explode(',', (string) ($user['designation'] ?? ''))),
    ]);
    foreach ($fromUser as $candidate) {
        $section = voucher_tracking_normalize_section_label($candidate);
        if ($section !== '' && voucher_tracking_is_dashboard_section($section)) {
            return $section;
        }
    }

    return voucher_tracking_normalize_section_label($actionFrom);
}

function voucher_tracking_format_duration_seconds(int $seconds): string
{
    if ($seconds <= 0) {
        return '—';
    }

    $days = intdiv($seconds, 86400);
    $remainder = $seconds % 86400;
    $hours = intdiv($remainder, 3600);
    $remainder %= 3600;
    $minutes = intdiv($remainder, 60);
    $secs = $remainder % 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' min';
    }
    if ($parts === [] && $secs > 0) {
        $parts[] = $secs . ' sec';
    }
    if ($parts === []) {
        return '< 1 min';
    }

    return implode(' ', $parts);
}

/**
 * Human-readable turnaround label for voucher_tracking.total_processing_time.
 */
function voucher_tracking_format_turnaround_seconds(int $seconds): string
{
    if ($seconds <= 0) {
        return '0 seconds';
    }

    $days = intdiv($seconds, 86400);
    $remainder = $seconds % 86400;
    $hours = intdiv($remainder, 3600);
    $remainder %= 3600;
    $minutes = intdiv($remainder, 60);
    $secs = $remainder % 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    if ($secs > 0) {
        $parts[] = $secs . ' second' . ($secs > 1 ? 's' : '');
    }

    return implode(' ', $parts);
}

/** First workflow section that starts total processing time for a voucher type. */
function voucher_tracking_total_processing_start_section(string $voucher_type): string
{
    return voucher_type_is_engp($voucher_type)
        ? 'Conservation & Development Section'
        : 'Planning Section';
}

/**
 * Earliest receive timestamp at the given dashboard section from action logs.
 *
 * @param list<array{action?: string, action_from?: string, action_by?: string, datetime_action?: string}> $actions
 */
function voucher_tracking_first_receive_at_section(
    array $actions,
    string $targetSection,
    ?object $pdo = null
): ?int {
    $targetSection = voucher_tracking_normalize_section_label($targetSection);
    if ($targetSection === '') {
        return null;
    }

    $userCache = [];
    $earliest = null;
    foreach ($actions as $row) {
        if (voucher_tracking_action_kind($row['action'] ?? '') !== 'receive') {
            continue;
        }

        $section = voucher_tracking_dashboard_section_from_action_row($row, $pdo, $userCache);
        if ($section !== $targetSection) {
            continue;
        }

        $ts = strtotime((string) ($row['datetime_action'] ?? ''));
        if ($ts === false) {
            continue;
        }

        if ($earliest === null || $ts < $earliest) {
            $earliest = $ts;
        }
    }

    return $earliest;
}

/**
 * Total processing time: first receive at the workflow start section (Planning or CDS)
 * through paid/processed by cashier.
 */
function voucher_tracking_calculate_total_processing_time(
    object $pdo,
    string $processing_no,
    string $endTimestamp,
    string $voucher_type = '',
    string $fallbackStartTimestamp = ''
): string {
    $endTs = strtotime(trim($endTimestamp));
    if ($endTs === false) {
        return 'TBD';
    }

    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return 'TBD';
    }

    if (trim($voucher_type) === '') {
        $voucher_type = voucher_fetch_stored_voucher_type($pdo, $processing_no);
    }

    $startSection = voucher_tracking_total_processing_start_section($voucher_type);
    $logs = voucher_tracking_fetch_action_logs_grouped($pdo, [$processing_no]);
    $actions = $logs[$processing_no] ?? [];
    $startTs = voucher_tracking_first_receive_at_section($actions, $startSection, $pdo);

    if ($startTs === null && trim($fallbackStartTimestamp) !== '') {
        $fallbackTs = strtotime(trim($fallbackStartTimestamp));
        if ($fallbackTs !== false) {
            $startTs = $fallbackTs;
        }
    }

    if ($startTs === null || $endTs < $startTs) {
        return 'TBD';
    }

    return voucher_tracking_format_turnaround_seconds($endTs - $startTs);
}

function voucher_tracking_dashboard_recent_voucher_limit(): int
{
    return 15;
}

/** Sections included in voucher dashboard processing-time analytics. */
function voucher_tracking_dashboard_sections(): array
{
    return [
        'Planning Section',
        'ICU',
        'Conservation & Development Section',
        'Budget Unit',
        'Accounting Unit',
        'Office of the PENRO',
        'Cashiers Unit',
    ];
}

function voucher_tracking_is_dashboard_section(string $section): bool
{
    return in_array($section, voucher_tracking_dashboard_sections(), true);
}

/**
 * Workflow order for dashboard section columns.
 *
 * @return array<string, int>
 */
function voucher_tracking_section_sort_ranks(): array
{
    $ranks = [];
    foreach (voucher_tracking_dashboard_sections() as $i => $label) {
        $ranks[$label] = $i;
    }

    return $ranks;
}

function voucher_tracking_section_sort_key(string $section): string
{
    $ranks = voucher_tracking_section_sort_ranks();
    $rank = $ranks[$section] ?? 999;

    return sprintf('%03d-%s', $rank, strtolower($section));
}

/** Whether a calendar day counts as work time for dashboard processing metrics (Mon–Thu only). */
function voucher_tracking_is_work_day(DateTimeInterface $date): bool
{
    $dayOfWeek = (int) $date->format('N');

    return $dayOfWeek >= 1 && $dayOfWeek <= 4;
}

/**
 * Elapsed seconds between two timestamps, counting only Monday through Thursday.
 * Used by voucher dashboard processing-time analytics.
 */
function voucher_tracking_elapsed_work_seconds(int $startTs, int $endTs): int
{
    if ($startTs <= 0 || $endTs <= 0 || $endTs <= $startTs) {
        return 0;
    }

    $total = 0;
    $cursor = (new DateTimeImmutable())->setTimestamp($startTs);

    while ($cursor->getTimestamp() < $endTs) {
        $dayStart = $cursor->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        if (voucher_tracking_is_work_day($dayStart)) {
            $segmentStart = max($startTs, $cursor->getTimestamp());
            $segmentEnd = min($endTs, $dayEnd->getTimestamp());
            if ($segmentEnd > $segmentStart) {
                $total += $segmentEnd - $segmentStart;
            }
        }

        $cursor = $dayEnd;
    }

    return $total;
}

/**
 * Dashboard total processing time (Mon–Thu): first receive at workflow start through last status time.
 *
 * @param list<array{action?: string, action_from?: string, action_by?: string, datetime_action?: string}> $actions
 */
function voucher_tracking_dashboard_total_processing_seconds(
    object $pdo,
    string $processing_no,
    int $endTs,
    string $voucher_type,
    string $fallbackStartTimestamp,
    array $actions
): ?int {
    $processing_no = trim($processing_no);
    if ($processing_no === '' || $endTs <= 0) {
        return null;
    }

    if (trim($voucher_type) === '') {
        $voucher_type = voucher_fetch_stored_voucher_type($pdo, $processing_no);
    }

    $startSection = voucher_tracking_total_processing_start_section($voucher_type);
    $startTs = voucher_tracking_first_receive_at_section($actions, $startSection, $pdo);

    if ($startTs === null && trim($fallbackStartTimestamp) !== '') {
        $fallbackTs = strtotime(trim($fallbackStartTimestamp));
        if ($fallbackTs !== false) {
            $startTs = $fallbackTs;
        }
    }

    if ($startTs === null || $endTs < $startTs) {
        return null;
    }

    $seconds = voucher_tracking_elapsed_work_seconds($startTs, $endTs);

    return $seconds > 0 ? $seconds : null;
}

/**
 * @param array<string, int> $totals
 */
function voucher_tracking_add_section_duration(array &$totals, string $section, int $startTs, int $endTs): void
{
    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return;
    }

    $delta = voucher_tracking_elapsed_work_seconds($startTs, $endTs);
    if ($delta > 0) {
        $totals[$section] = ($totals[$section] ?? 0) + $delta;
    }
}

/**
 * Close the most recent pending forward when the next office/section receives the voucher.
 *
 * @param array<string, int> $open
 * @param array<string, int> $pendingForward
 * @param array<string, int> $totals
 */
function voucher_tracking_complete_pending_forward_on_handoff(
    array &$open,
    array &$pendingForward,
    array &$totals,
    string $receivingSection,
    int $receiveTs
): void {
    $bestSection = null;
    $bestForwardTs = -1;

    foreach ($pendingForward as $fromSection => $forwardTs) {
        if ($receivingSection !== '' && $fromSection === $receivingSection) {
            continue;
        }
        if (!isset($open[$fromSection])) {
            continue;
        }
        if ($receiveTs < $open[$fromSection] || $forwardTs > $receiveTs) {
            continue;
        }
        if ($forwardTs > $bestForwardTs) {
            $bestForwardTs = $forwardTs;
            $bestSection = $fromSection;
        }
    }

    if ($bestSection === null) {
        return;
    }

    voucher_tracking_add_section_duration(
        $totals,
        $bestSection,
        $open[$bestSection],
        $receiveTs
    );
    unset($open[$bestSection], $pendingForward[$bestSection]);
}

/**
 * @param array{action_from?: string, action_by?: string} $row
 * @param array<string, array<string, mixed>|null> $userCache
 */
function voucher_tracking_resolve_cashiers_process_section(
    array $row,
    string $resolvedSection,
    ?object $pdo = null,
    array &$userCache = []
): string {
    if ($resolvedSection === 'Cashiers Unit') {
        return 'Cashiers Unit';
    }

    $fromAction = voucher_tracking_normalize_section_label(trim((string) ($row['action_from'] ?? '')));
    if ($fromAction === 'Cashiers Unit') {
        return 'Cashiers Unit';
    }

    if ($pdo === null) {
        return '';
    }

    $actionBy = trim((string) ($row['action_by'] ?? ''));
    if ($actionBy === '') {
        return '';
    }

    if (!array_key_exists($actionBy, $userCache)) {
        $userCache[$actionBy] = voucher_tracking_lookup_user_by_display_name($pdo, $actionBy);
    }
    $user = $userCache[$actionBy];
    if ($user === null) {
        return '';
    }

    foreach (array_filter([
        trim((string) ($user['section'] ?? '')),
        ...array_map('trim', explode(',', (string) ($user['designation'] ?? ''))),
    ]) as $candidate) {
        if (voucher_tracking_normalize_section_label($candidate) === 'Cashiers Unit') {
            return 'Cashiers Unit';
        }
    }

    return '';
}

/**
 * Section dwell time: received at section start → successful handoff end.
 * Non-cashier sections end when the next section/process receives, or at forward if still in transit.
 * Cashiers Unit ends when processed/paid (or latest activity while still at cashier).
 *
 * @param list<array{action?: string, action_from?: string, action_by?: string, datetime_action?: string}> $actions
 * @param array<string, array<string, mixed>|null> $userCache
 * @return array<string, int> normalized section => total seconds
 */
function voucher_tracking_section_durations_from_actions(
    array $actions,
    ?int $openEndTs = null,
    ?object $pdo = null,
    array &$userCache = [],
    ?string $trackingStatus = null,
    ?string $totalProcessingTime = null
): array {
    $cashiersSection = 'Cashiers Unit';
    $open = [];
    $pendingForward = [];
    $totals = [];
    /** @var int|null Timestamp of the most recent return (voucher redelivered for re-processing). */
    $lastReturnTs = null;

    foreach ($actions as $row) {
        $section = voucher_tracking_dashboard_section_from_action_row($row, $pdo, $userCache);
        $ts = strtotime((string) ($row['datetime_action'] ?? ''));
        if ($ts === false) {
            continue;
        }

        $kind = voucher_tracking_action_kind($row['action'] ?? '');

        if ($kind === 'receive') {
            if ($section !== '' && voucher_tracking_is_dashboard_section($section)) {
                if (isset($pendingForward[$section], $open[$section])) {
                    voucher_tracking_add_section_duration(
                        $totals,
                        $section,
                        $open[$section],
                        $pendingForward[$section]
                    );
                    unset($pendingForward[$section]);
                }
            }

            voucher_tracking_complete_pending_forward_on_handoff(
                $open,
                $pendingForward,
                $totals,
                $section,
                $ts
            );

            if ($section !== '' && voucher_tracking_is_dashboard_section($section)) {
                $open[$section] = $ts;
                unset($pendingForward[$section]);
            }
            $lastReturnTs = null;
            continue;
        }

        if ($kind === 'process' && isset($open[$cashiersSection])) {
            $processSection = voucher_tracking_resolve_cashiers_process_section(
                $row,
                $section,
                $pdo,
                $userCache
            );
            if ($processSection === $cashiersSection) {
                voucher_tracking_add_section_duration($totals, $cashiersSection, $open[$cashiersSection], $ts);
                unset($open[$cashiersSection], $pendingForward[$cashiersSection]);
                continue;
            }
        }

        if ($kind === 'return') {
            if ($section !== '' && voucher_tracking_is_dashboard_section($section) && isset($open[$section])) {
                voucher_tracking_add_section_duration($totals, $section, $open[$section], $ts);
            }
            unset($open[$section], $pendingForward[$section]);
            $lastReturnTs = $ts;
            continue;
        }

        if ($section === '' || !voucher_tracking_is_dashboard_section($section)) {
            continue;
        }

        if ($kind === 'forward') {
            if (!isset($open[$section])) {
                // Return-to-receiving paths may skip a "Received by" log; start dwell at return time.
                $open[$section] = ($lastReturnTs !== null && $lastReturnTs > 0 && $lastReturnTs <= $ts)
                    ? $lastReturnTs
                    : $ts;
            }
            if ($section !== $cashiersSection) {
                $pendingForward[$section] = $ts;
            }
            $lastReturnTs = null;
            continue;
        }

        if ($kind === 'archive' && $section === $cashiersSection && isset($open[$section])) {
            voucher_tracking_add_section_duration($totals, $section, $open[$section], $ts);
            unset($open[$section], $pendingForward[$section]);
            continue;
        }

        if ($kind === 'archive') {
            unset($open[$section], $pendingForward[$section]);
        }
    }

    foreach ($pendingForward as $section => $forwardTs) {
        if ($section === $cashiersSection || !isset($open[$section])) {
            continue;
        }
        voucher_tracking_add_section_duration($totals, $section, $open[$section], $forwardTs);
        unset($open[$section], $pendingForward[$section]);
    }

    if ($open !== []) {
        $fallbackEnd = ($openEndTs !== null && $openEndTs > 0) ? $openEndTs : time();
        foreach ($open as $section => $startTs) {
            $endTs = $fallbackEnd <= $startTs ? time() : $fallbackEnd;
            voucher_tracking_add_section_duration($totals, $section, $startTs, $endTs);
        }
    }

    return $totals;
}

/**
 * @param list<string> $processingNos
 * @return array<string, list<array{action: string, action_by: string, action_from: string, datetime_action: string}>>
 */
function voucher_tracking_fetch_action_logs_grouped(object $pdo, array $processingNos): array
{
    $processingNos = array_values(array_unique(array_filter(array_map(
        static fn($pn): string => trim((string) $pn),
        $processingNos
    ))));
    if ($processingNos === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($processingNos), '?'));
    $sql = "SELECT processing_no, action, action_by, action_from, datetime_action
            FROM voucher_action_logs
            WHERE processing_no IN ({$placeholders})
            ORDER BY processing_no ASC, datetime_action ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($processingNos as $i => $pn) {
        $stmt->bindValue($i + 1, $pn, PDO::PARAM_STR);
    }
    $stmt->execute();

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pn = trim((string) ($row['processing_no'] ?? ''));
        if ($pn === '') {
            continue;
        }
        if (!isset($grouped[$pn])) {
            $grouped[$pn] = [];
        }
        $grouped[$pn][] = [
            'action' => (string) ($row['action'] ?? ''),
            'action_by' => (string) ($row['action_by'] ?? ''),
            'action_from' => (string) ($row['action_from'] ?? ''),
            'datetime_action' => (string) ($row['datetime_action'] ?? ''),
        ];
    }

    return $grouped;
}

/**
 * Most recent processing activity for dashboard-unit actions on a voucher.
 *
 * @param list<array{action?: string, action_from?: string, action_by?: string, datetime_action?: string}> $actions
 */
function voucher_tracking_voucher_last_processed_ts(array $trackingRow, array $actions, ?object $pdo = null): int
{
    $statusTs = strtotime((string) ($trackingRow['datetime_status'] ?? ''));
    if ($statusTs !== false) {
        return $statusTs;
    }

    $max = 0;
    $userCache = [];
    foreach ($actions as $row) {
        $section = voucher_tracking_dashboard_section_from_action_row($row, $pdo, $userCache);
        if (!voucher_tracking_is_dashboard_section($section)) {
            continue;
        }

        $kind = voucher_tracking_action_kind($row['action'] ?? '');
        if (!in_array($kind, ['receive', 'forward', 'return', 'archive', 'process'], true)) {
            continue;
        }

        $ts = strtotime((string) ($row['datetime_action'] ?? ''));
        if ($ts !== false && $ts > $max) {
            $max = $ts;
        }
    }

    $encodedTs = strtotime((string) ($trackingRow['datetime_encoded'] ?? ''));
    if ($encodedTs !== false && $encodedTs > $max) {
        return $encodedTs;
    }

    return $max;
}

/**
 * Build section timing summary and per-voucher breakdown for dashboard analytics.
 *
 * @param list<array<string, mixed>> $voucherRows voucher_tracking rows
 * @return array{
 *   sections: list<string>,
 *   summary: list<array{section: string, count: int, avg_seconds: int, min_seconds: int, max_seconds: int, avg_label: string, min_label: string, max_label: string}>,
 *   by_voucher: list<array{processing_no: string, payee: string, dv_no: string, total_processing_time: string, sections: array<string, int>, sections_label: array<string, string>}>
 * }
 */
function voucher_tracking_build_section_timing_report(object $pdo, array $voucherRows): array
{
    $processingNos = [];
    $rowByPn = [];
    foreach ($voucherRows as $row) {
        $pn = trim((string) ($row['processing_no'] ?? ''));
        if ($pn === '') {
            continue;
        }
        $processingNos[] = $pn;
        $rowByPn[$pn] = $row;
    }

    $logsByPn = voucher_tracking_fetch_action_logs_grouped($pdo, $processingNos);
    $sectionBuckets = [];
    $allSections = [];
    $byVoucher = [];
    $userCache = [];

    foreach ($processingNos as $pn) {
        $trackingRow = $rowByPn[$pn] ?? [];
        $openEndTs = null;
        $statusTs = strtotime((string) ($trackingRow['datetime_status'] ?? ''));
        if ($statusTs !== false) {
            $openEndTs = $statusTs;
        }

        $durations = voucher_tracking_section_durations_from_actions(
            $logsByPn[$pn] ?? [],
            $openEndTs,
            $pdo,
            $userCache,
            trim((string) ($trackingRow['status'] ?? '')),
            trim((string) ($trackingRow['total_processing_time'] ?? ''))
        );
        $labels = [];
        foreach ($durations as $section => $seconds) {
            if ($seconds <= 0) {
                continue;
            }
            $allSections[$section] = true;
            $sectionBuckets[$section] = $sectionBuckets[$section] ?? [];
            $sectionBuckets[$section][] = $seconds;
            $labels[$section] = voucher_tracking_format_duration_seconds($seconds);
        }

        $totalProcessingTime = '';
        if ($openEndTs !== null && $openEndTs > 0) {
            $totalSeconds = voucher_tracking_dashboard_total_processing_seconds(
                $pdo,
                $pn,
                $openEndTs,
                trim((string) ($trackingRow['voucher_type'] ?? '')),
                trim((string) ($trackingRow['datetime_encoded'] ?? '')),
                $logsByPn[$pn] ?? []
            );
            if ($totalSeconds !== null) {
                $totalProcessingTime = voucher_tracking_format_turnaround_seconds($totalSeconds);
            }
        }
        if ($totalProcessingTime === '') {
            $totalProcessingTime = trim((string) ($trackingRow['total_processing_time'] ?? ''));
        }

        $byVoucher[] = [
            'processing_no' => $pn,
            'payee' => trim((string) ($trackingRow['payee'] ?? '')),
            'dv_no' => trim((string) ($trackingRow['dv_no'] ?? '')),
            'total_processing_time' => $totalProcessingTime,
            'sections' => $durations,
            'sections_label' => $labels,
            'last_processed_ts' => voucher_tracking_voucher_last_processed_ts(
                $trackingRow,
                $logsByPn[$pn] ?? [],
                $pdo
            ),
        ];
    }

    $sections = voucher_tracking_dashboard_sections();
    foreach ($sections as $section) {
        if (!isset($allSections[$section])) {
            $allSections[$section] = false;
        }
    }

    $summary = [];
    foreach ($sections as $section) {
        $values = $sectionBuckets[$section] ?? [];
        if ($values === []) {
            continue;
        }
        $avg = (int) round(array_sum($values) / count($values));
        $min = min($values);
        $max = max($values);
        $summary[] = [
            'section' => $section,
            'count' => count($values),
            'avg_seconds' => $avg,
            'min_seconds' => $min,
            'max_seconds' => $max,
            'avg_label' => voucher_tracking_format_duration_seconds($avg),
            'min_label' => voucher_tracking_format_duration_seconds($min),
            'max_label' => voucher_tracking_format_duration_seconds($max),
        ];
    }

    usort($byVoucher, static function (array $a, array $b): int {
        $cmp = ($b['last_processed_ts'] ?? 0) <=> ($a['last_processed_ts'] ?? 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp((string) ($b['processing_no'] ?? ''), (string) ($a['processing_no'] ?? ''));
    });

    $recentLimit = voucher_tracking_dashboard_recent_voucher_limit();
    $byVoucherRecent = array_slice($byVoucher, 0, $recentLimit);

    return [
        'sections' => $sections,
        'summary' => $summary,
        'by_voucher' => $byVoucherRecent,
        'by_voucher_limit' => $recentLimit,
    ];
}

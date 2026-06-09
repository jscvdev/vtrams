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
 * Resolve forward target when re-forwarding a returned voucher.
 *
 * @return array{designation: string, label: string, returned_by: string}
 */
function voucher_tracking_return_forward_target(object $pdo, ?string $tracking_voucher_status): array
{
    $returnedBy = voucher_tracking_parse_returned_by($tracking_voucher_status);
    if ($returnedBy === '') {
        return ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => ''];
    }

    $user = voucher_tracking_lookup_user_by_display_name($pdo, $returnedBy);
    $designation = '';
    $udc = '';
    if ($user !== null) {
        $udc = trim((string) ($user['udc'] ?? ''));
        $designation = voucher_tracking_primary_designation($user['designation']);
        if ($designation === '' && $user['section'] !== '') {
            $designation = $user['section'];
        }
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
 * @return array{designation: string, label: string, returned_by: string}
 */
function voucher_tracking_resolve_return_forward_target(
    object $pdo,
    ?string $tracking_voucher_status,
    ?string $encoded_from,
    ?string $encoder_section = null
): array {
    if (voucher_tracking_parse_returned_by($tracking_voucher_status) !== '') {
        return voucher_tracking_return_forward_target($pdo, $tracking_voucher_status);
    }

    $encodedFrom = trim((string) $encoded_from);
    $encoderSection = trim((string) $encoder_section);
    if ($encodedFrom === '') {
        return ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => ''];
    }
    if ($encoderSection !== '' && strcasecmp($encodedFrom, $encoderSection) === 0) {
        return ['designation' => '', 'label' => '', 'returned_by' => '', 'udc' => ''];
    }

    return voucher_tracking_forward_target_from_section($pdo, $encodedFrom);
}

/** Receiver for encoder re-forward after "Returned by:" (specific person, then designation). */
function voucher_forward_receiver_for_return_target(
    object $pdo,
    ?string $tracking_voucher_status,
    string $forward_designation,
    string $office_to
): array {
    $returnTarget = voucher_tracking_return_forward_target($pdo, $tracking_voucher_status);
    $returnedByUdc = trim((string) ($returnTarget['udc'] ?? ''));
    if ($returnedByUdc !== '') {
        return [
            'receiver_udc' => $returnedByUdc,
            'forwarded_to' => $returnTarget['label'] !== '' ? $returnTarget['label'] : $returnTarget['returned_by'],
            'temp_errors' => [],
        ];
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
    return voucher_forward_receiver_udcs_for_designation($pdo, $designation, $office_to);
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
 * Resolve receiver_udc for a forward/return target (designation, section label, or UDC).
 * Uses designation_limit (same as voucher_receiving forward) then user_group fallbacks.
 */
function voucher_resolve_receiver_udc_for_destination(object $pdo, string $destination, string $penro_office): string
{
    $destination = trim($destination);
    $penro_office = trim($penro_office);
    if ($destination === '') {
        return '';
    }

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
        $limitStmt->bindValue(':designation', $candidate, PDO::PARAM_STR);
        $limitStmt->execute();
        $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);
        if (!$limitRow) {
            continue;
        }
        $designated = voucher_pick_designated_udcs_for_office(
            (string) ($limitRow['designated_udc'] ?? ''),
            (string) ($limitRow['designated_office'] ?? ''),
            $penro_office
        );
        if ($designated !== '') {
            return $designated;
        }

        $listedOffices = array_values(array_filter(
            array_map('trim', explode(',', (string) ($limitRow['designated_office'] ?? ''))),
            static fn(string $office): bool => $office !== '' && strcasecmp($office, 'None') !== 0
        ));
        foreach ($listedOffices as $listedOffice) {
            if ($penro_office !== '' && strcasecmp($listedOffice, $penro_office) === 0) {
                continue;
            }
            $designated = voucher_pick_designated_udcs_for_office(
                (string) ($limitRow['designated_udc'] ?? ''),
                (string) ($limitRow['designated_office'] ?? ''),
                $listedOffice
            );
            if ($designated !== '') {
                return $designated;
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
            return trim((string) $udcRow['udc']);
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
                return implode(',', array_values(array_unique($udcs)));
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
            return implode(',', array_values(array_unique($udcs)));
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
    $targetTo = 'ICU';
    $officeTo = voucher_resolve_office_for_designation_route($pdo, $targetTo, $logged_user_office);
    $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $targetTo, $officeTo);

    return [
        'receiver_udc' => $resolved['receiver_udc'],
        'forwarded_to' => $resolved['forwarded_to'],
        'office_to' => $officeTo,
        'temp_errors' => $resolved['temp_errors'],
    ];
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

/**
 * Default forward target for encoders based on designation_limit office registration.
 * PENRO encoders → Planning Section; CENRO/other offices → Liaison Officer when registered.
 */
function voucher_forward_encoder_default_target(object $pdo, string $logged_user_office): string
{
    $logged_user_office = trim($logged_user_office);
    if ($logged_user_office === '') {
        return '';
    }

    $targets = ['Planning Section', 'Liaison Officer'];
    foreach ($targets as $designation) {
        if (voucher_designation_limit_office_registered($pdo, $logged_user_office, $designation)) {
            return $designation;
        }
    }

    return '';
}

/**
 * @return array{receiver_udc: string, forwarded_to: string, temp_errors: array<string, string>}
 */
function voucher_forward_receiver_udcs_for_designation(object $pdo, string $target_to, string $office_to): array
{
    $forwarded_to = trim($target_to);
    $receiver_udc = voucher_resolve_receiver_udc_for_destination($pdo, $target_to, $office_to);
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

function voucher_tracking_dashboard_recent_voucher_limit(): int
{
    return 15;
}

/** Sections included in voucher dashboard processing-time analytics. */
function voucher_tracking_dashboard_sections(): array
{
    return [
        'Planning Section',
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

/**
 * @param array<string, int> $totals
 */
function voucher_tracking_add_section_duration(array &$totals, string $section, int $startTs, int $endTs): void
{
    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return;
    }

    $delta = $endTs - $startTs;
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
 * Section dwell time: received at section start → successful handoff end.
 * Non-cashier sections end when the next section/process receives, or at forward if still in transit.
 * Cashiers Unit ends when processed/paid.
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
    ?string $trackingStatus = null
): array {
    $cashiersSection = 'Cashiers Unit';
    $isPaid = strcasecmp(trim((string) $trackingStatus), 'Paid') === 0;
    $open = [];
    $pendingForward = [];
    $totals = [];

    foreach ($actions as $row) {
        $section = voucher_tracking_dashboard_section_from_action_row($row, $pdo, $userCache);
        $ts = strtotime((string) ($row['datetime_action'] ?? ''));
        if ($ts === false) {
            continue;
        }

        $kind = voucher_tracking_action_kind($row['action'] ?? '');

        if ($kind === 'receive') {
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
            continue;
        }

        if ($section === '' || !voucher_tracking_is_dashboard_section($section)) {
            continue;
        }

        if ($kind === 'forward') {
            if (!isset($open[$section])) {
                continue;
            }
            if ($section !== $cashiersSection) {
                $pendingForward[$section] = $ts;
            }
            continue;
        }

        if ($kind === 'process' && $section === $cashiersSection && isset($open[$section])) {
            voucher_tracking_add_section_duration($totals, $section, $open[$section], $ts);
            unset($open[$section], $pendingForward[$section]);
            continue;
        }

        if (in_array($kind, ['return', 'archive'], true)) {
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

    if (isset($open[$cashiersSection])) {
        $startTs = $open[$cashiersSection];
        $endTs = null;
        if ($isPaid && $openEndTs !== null && $openEndTs > $startTs) {
            $endTs = $openEndTs;
        }
        if ($endTs !== null) {
            voucher_tracking_add_section_duration($totals, $cashiersSection, $startTs, $endTs);
            unset($open[$cashiersSection]);
        }
    }

    if ($open !== []) {
        $fallbackEnd = ($openEndTs !== null && $openEndTs > 0) ? $openEndTs : time();
        foreach ($open as $section => $startTs) {
            if ($section === $cashiersSection) {
                continue;
            }
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
 *   by_voucher: list<array{processing_no: string, payee: string, dv_no: string, sections: array<string, int>, sections_label: array<string, string>}>
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
            trim((string) ($trackingRow['status'] ?? ''))
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

        $byVoucher[] = [
            'processing_no' => $pn,
            'payee' => trim((string) ($trackingRow['payee'] ?? '')),
            'dv_no' => trim((string) ($trackingRow['dv_no'] ?? '')),
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

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
        return ['designation' => '', 'label' => '', 'returned_by' => ''];
    }

    $user = voucher_tracking_lookup_user_by_display_name($pdo, $returnedBy);
    $designation = '';
    if ($user !== null) {
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
        return ['designation' => '', 'label' => '', 'returned_by' => ''];
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
        return ['designation' => '', 'label' => '', 'returned_by' => ''];
    }
    if ($encoderSection !== '' && strcasecmp($encodedFrom, $encoderSection) === 0) {
        return ['designation' => '', 'label' => '', 'returned_by' => ''];
    }

    return voucher_tracking_forward_target_from_section($pdo, $encodedFrom);
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

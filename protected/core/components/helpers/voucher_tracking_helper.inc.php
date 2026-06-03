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
 * @return array{receiver_udc: string, forwarded_to: string, temp_errors: array<string, string>}
 */
function voucher_forward_receiver_udcs_for_designation(object $pdo, string $target_to, string $office_to): array
{
    $temp_dump = [];
    $receiver_udc_array = [];
    $forwarded_to = $target_to;

    $check_exists_query = 'SELECT * FROM user_group WHERE FIND_IN_SET(:designation, designation)';
    $check_exists_query_statement = $pdo->prepare($check_exists_query);
    $check_exists_query_statement->bindParam(':designation', $target_to);
    $check_exists_query_statement->execute();

    while ($row3 = $check_exists_query_statement->fetch(PDO::FETCH_ASSOC)) {
        if ($row3['office'] === $office_to) {
            if (!empty($row3['udc'])) {
                $receiver_udc_array[] = $row3['udc'];
                $forwarded_to = $target_to;
            } else {
                $temp_dump['unassigned_udc'] = 'No user is assigned to accept';
            }
        }
    }

    return [
        'receiver_udc' => implode(',', array_unique($receiver_udc_array)),
        'forwarded_to' => $forwarded_to,
        'temp_errors' => $temp_dump,
    ];
}

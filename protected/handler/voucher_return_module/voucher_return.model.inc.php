<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../voucher_module/voucher.model.inc.php';

function incoming_voucher_sent_delete_from_incoming(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_incoming WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function incoming_voucher_delete_from_sent(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_sent WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_incoming_sent_to_receiving(object $pdo, string $ors_no, string $ada_check_no, string $processing_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $office_from, string $office_to, string $sender_udc, string $receiver_udc, 
string $encoded_by, string $encoded_from, string $datetime_encoded, string $process_status, string $combined_remarks) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $transmit = 'No';

    // Carry supporting_documents, charged_amount, and process_history forward from voucher_incoming
    $charged_amount = null;
    $supporting_documents = null;
    $process_history = null;
    $selectQuery = "SELECT charged_amount, supporting_documents, process_history FROM voucher_incoming WHERE processing_no = :processing_no";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->bindParam(":processing_no", $processing_no);
    $selectStmt->execute();
    if ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $charged_amount = $row['charged_amount'] ?? null;
        $supporting_documents = $row['supporting_documents'] ?? null;
        $histValue = trim((string) ($row['process_history'] ?? ''));
        if ($histValue !== '') {
            $process_history = $histValue;
        }
    }
    if ($process_history === null) {
        try {
            $histStmt = $pdo->prepare('SELECT process_history FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1');
            $histStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
            $histStmt->execute();
            $histRow = $histStmt->fetch(PDO::FETCH_ASSOC);
            $histValue = trim((string) ($histRow['process_history'] ?? ''));
            if ($histValue !== '') {
                $process_history = $histValue;
            }
        } catch (PDOException $e) {
            // Best-effort: proceed without process_history if unavailable.
        }
    }

    $query = "INSERT INTO voucher_receiving (processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, charged_amount, voucher_type, voucher_date, datetime_forwarded, office_from, office_to, sender_udc, receiver_udc, encoded_by, encoded_from, datetime_encoded, forwarded_by, transmit, process_status, remarks, sender_remarks, supporting_documents, process_history) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :charged_amount, :voucher_type, :voucher_date, :datetime_forwarded, :office_from, :office_to, :sender_udc, :receiver_udc, :encoded_by, :encoded_from, :datetime_encoded, :forwarded_by, :transmit, :process_status, :remarks, :sender_remarks, :supporting_documents, :process_history)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
    $statement->bindParam(":charged_amount",$charged_amount);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":sender_udc",$sender_udc);
    $statement->bindParam(":receiver_udc",$receiver_udc);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$encoded_by);
    $statement->bindParam(":transmit",$transmit);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":sender_remarks",$combined_remarks);
    $statement->bindParam(":supporting_documents",$supporting_documents);
    $statement->bindParam(":process_history", $process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

/** Section/unit of the user returning a voucher (same source as encode "encoded_from"). */
function voucher_return_returner_encoded_from(): string
{
    $section = trim((string) ($_SESSION['logged_user_section'] ?? ''));
    if ($section !== '') {
        return $section;
    }
    $designation = trim((string) ($_SESSION['logged_user_designation'] ?? ''));
    if ($designation === '') {
        return '';
    }
    $parts = array_map('trim', explode(',', $designation));

    return $parts[0] ?? '';
}

/**
 * Keep voucher origin offices from the row; never substitute the returner's office.
 *
 * @return array{office_from: string, encoded_from: string}
 */
function voucher_return_resolve_preserved_offices(string $posted_office_from, string $posted_encoded_from): array
{
    $encoded_from = trim($posted_encoded_from);
    $office_from = voucher_pick_field(trim($posted_office_from), $encoded_from);

    if ($encoded_from === '' && $office_from !== '') {
        $encoded_from = $office_from;
    }

    return [
        'office_from' => $office_from,
        'encoded_from' => $encoded_from,
    ];
}

/**
 * Preserve origin offices from POST, falling back to queue/tracking snapshot (not returner session).
 *
 * @return array{office_from: string, encoded_from: string}
 */
function voucher_return_load_origin_offices(
    object $pdo,
    string $processing_no,
    string $return_source,
    string $posted_office_from,
    string $posted_encoded_from
): array {
    $preserved = voucher_return_resolve_preserved_offices($posted_office_from, $posted_encoded_from);
    if ($preserved['office_from'] !== '' && $preserved['encoded_from'] !== '') {
        return $preserved;
    }

    $snapshot = voucher_return_fetch_encoder_return_snapshot($pdo, $processing_no, $return_source);
    $office_from = voucher_pick_field(
        $preserved['office_from'],
        (string) ($snapshot['office_from'] ?? ''),
        (string) ($snapshot['encoded_from'] ?? '')
    );
    $encoded_from = voucher_pick_field(
        $preserved['encoded_from'],
        (string) ($snapshot['encoded_from'] ?? ''),
        $office_from
    );

    return [
        'office_from' => $office_from,
        'encoded_from' => $encoded_from,
    ];
}

/** @deprecated Use voucher_field_is_placeholder() */
function voucher_return_field_is_placeholder(string $value): bool
{
    return voucher_field_is_placeholder($value);
}

/** @deprecated Use voucher_pick_field() */
function voucher_return_pick_field(string ...$candidates): string
{
    return voucher_pick_field(...$candidates);
}

/**
 * Load current voucher values from the queue, tracking, and dv_entries before return deletes rows.
 *
 * @return array<string, string>
 */
function voucher_return_fetch_encoder_return_snapshot(object $pdo, string $processing_no, string $return_source): array
{
    $snapshot = [];
    $sourceTable = match ($return_source) {
        'forwarding' => 'voucher_receiving',
        'sent' => 'voucher_sent',
        default => 'voucher_incoming',
    };

    try {
        $stmt = $pdo->prepare("SELECT * FROM {$sourceTable} WHERE processing_no = :processing_no LIMIT 1");
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach ($row as $key => $value) {
                if (is_string($key) && $value !== null && $value !== '') {
                    $snapshot[$key] = trim((string) $value);
                }
            }
        }
    } catch (PDOException $e) {
        // Source row may already be gone on retry paths.
    }

    if (!function_exists('voucher_tracking_fetch_by_processing_no')) {
        require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    }
    $tracking = voucher_tracking_fetch_by_processing_no($pdo, $processing_no);
    if (is_array($tracking)) {
        foreach ($tracking as $key => $value) {
            if (is_string($key) && $value !== null && $value !== '') {
                $snapshot[$key] = trim((string) $value);
            }
        }
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM dv_entries WHERE processing_no = :processing_no LIMIT 1');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach ($row as $key => $value) {
                if (is_string($key) && $value !== null && $value !== '') {
                    $snapshot[$key] = trim((string) $value);
                }
            }
        }
    } catch (PDOException $e) {
        // dv_entries may be absent on older installs.
    }

    return $snapshot;
}

/**
 * When returning to encoder, keep identifiers and other fields already set downstream.
 *
 * @param array<string, string> $posted
 * @return array<string, string>
 */
function voucher_return_encoder_retention_values(object $pdo, string $processing_no, string $return_source, array $posted): array
{
    if (!function_exists('voucher_fetch_identifiers')) {
        require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    }

    $snapshot = voucher_return_fetch_encoder_return_snapshot($pdo, $processing_no, $return_source);
    $stored = voucher_fetch_identifiers($pdo, $processing_no);
    $fields = [
        'ors_no',
        'dv_no',
        'ada_check_no',
        'payee',
        'address',
        'particulars',
        'tin_employee_no',
        'amount',
        'voucher_type',
        'voucher_date',
        'coa_options',
        'coa_category',
        'coa_subsection',
    ];

    $retained = [];
    foreach ($fields as $field) {
        if (in_array($field, ['ors_no', 'dv_no', 'ada_check_no'], true)) {
            $retained[$field] = voucher_pick_field(
                $posted[$field] ?? '',
                $snapshot[$field] ?? '',
                $stored[$field] ?? ''
            );
            continue;
        }
        $retained[$field] = voucher_pick_field(
            $posted[$field] ?? '',
            $snapshot[$field] ?? ''
        );
    }

    return $retained;
}

/** Keep dv_entries aligned when a returned voucher is back at the encoder. */
function voucher_return_sync_dv_entry_for_encoder_return(
    object $pdo,
    string $processing_no,
    string $encoded_from,
    string $ors_no,
    string $dv_no,
    string $ada_check_no
): void {
    $encoded_from = trim($encoded_from);
    if ($encoded_from === '' && voucher_field_is_placeholder($ors_no)
        && voucher_field_is_placeholder($dv_no) && voucher_field_is_placeholder($ada_check_no)) {
        return;
    }

    try {
        $existing = [];
        $stmt = $pdo->prepare('SELECT ors_no, dv_no, ada_check_no, encoded_from FROM dv_entries WHERE processing_no = :processing_no LIMIT 1');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $existing = $row;
        }

        if ($existing === []) {
            return;
        }

        $stored = voucher_fetch_identifiers($pdo, $processing_no);
        $finalOrs = voucher_pick_field($ors_no, (string) ($existing['ors_no'] ?? ''), $stored['ors_no'] ?? '');
        $finalDv = voucher_pick_field($dv_no, (string) ($existing['dv_no'] ?? ''), $stored['dv_no'] ?? '');
        $finalAda = voucher_pick_field($ada_check_no, (string) ($existing['ada_check_no'] ?? ''), $stored['ada_check_no'] ?? '');
        $finalEncodedFrom = voucher_pick_field($encoded_from, (string) ($existing['encoded_from'] ?? ''));

        $stmt = $pdo->prepare(
            'UPDATE dv_entries
             SET encoded_from = :encoded_from,
                 ors_no = :ors_no,
                 dv_no = :dv_no,
                 ada_check_no = :ada_check_no
             WHERE processing_no = :processing_no'
        );
        $stmt->bindValue(':encoded_from', $finalEncodedFrom, PDO::PARAM_STR);
        $stmt->bindValue(':ors_no', $finalOrs, PDO::PARAM_STR);
        $stmt->bindValue(':dv_no', $finalDv, PDO::PARAM_STR);
        $stmt->bindValue(':ada_check_no', $finalAda, PDO::PARAM_STR);
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // dv_entries may be absent on older installs.
    }
}

/** @deprecated Use voucher_return_sync_dv_entry_for_encoder_return() */
function voucher_return_sync_dv_encoded_from(object $pdo, string $processing_no, string $encoded_from): void
{
    voucher_return_sync_dv_entry_for_encoder_return($pdo, $processing_no, $encoded_from, '', '', '');
}

function voucher_incoming_sent_to_pending(
    object $pdo,
    string $processing_no,
    string $ors_no,
    string $dv_no,
    string $ada_check_no,
    string $payee,
    string $address,
    string $particulars,
    string $tin_employee_no,
    string $amount,
    string $voucher_type,
    string $voucher_date,
    string $encoded_by,
    string $encoded_from,
    string $datetime_encoded,
    ?string $coa_options = null,
    ?string $coa_category = null,
    ?string $coa_subsection = null
) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    vouchers_ensure_ors_no_column($pdo);
    vouchers_ensure_id_auto_increment($pdo);

    $deleteStmt = $pdo->prepare('DELETE FROM vouchers WHERE processing_no = :processing_no');
    $deleteStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $deleteStmt->execute();

    $query = "INSERT INTO vouchers (processing_no, ors_no, dv_no, ada_check_no, payee, address, particulars, tin_employee_no, amount, voucher_type, voucher_date, encoded_by, encoded_from, datetime_encoded, coa_options, coa_category, coa_subsection)
                        VALUES (:processing_no, :ors_no, :dv_no, :ada_check_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :voucher_date, :encoded_by, :encoded_from, :datetime_encoded, :coa_options, :coa_category, :coa_subsection)";

    $statement = $pdo->prepare($query);

    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->bindValue(':ors_no', $ors_no, PDO::PARAM_STR);
    $statement->bindValue(':dv_no', $dv_no, PDO::PARAM_STR);
    $statement->bindValue(':ada_check_no', $ada_check_no, PDO::PARAM_STR);
    $statement->bindValue(':payee', $payee, PDO::PARAM_STR);
    $statement->bindValue(':address', $address, PDO::PARAM_STR);
    $statement->bindValue(':particulars', $particulars, PDO::PARAM_STR);
    $statement->bindValue(':tin_employee_no', $tin_employee_no, PDO::PARAM_STR);
    $statement->bindValue(':amount', $amount, PDO::PARAM_STR);
    $statement->bindValue(':voucher_type', $voucher_type, PDO::PARAM_STR);
    $statement->bindValue(':voucher_date', $voucher_date, PDO::PARAM_STR);
    $statement->bindValue(':encoded_by', $encoded_by, PDO::PARAM_STR);
    $statement->bindValue(':encoded_from', $encoded_from, PDO::PARAM_STR);
    $statement->bindValue(':datetime_encoded', $datetime_encoded, PDO::PARAM_STR);
    $statement->bindValue(':coa_options', $coa_options, $coa_options === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':coa_category', $coa_category, $coa_category === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':coa_subsection', $coa_subsection, $coa_subsection === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $statement->execute();

    return $statement->rowCount() > 0;
}


function voucher_incoming_return_log_to_document_tracking(object $pdo, string $processing_no, string $action, string $datetime_action, string $combined_remarks, string $active_status = 'returned') {
    if (!in_array($active_status, ['no', 'yes', 'returned'], true)) {
        $active_status = 'returned';
    }
    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status, remarks = :remarks, active_status = :active_status WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":active_status",$active_status);

    $statement->execute();

    return $statement->rowCount() > 0;
}


function voucher_incoming_return_get_document_id (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_receiving WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_receiving_return_exists(object $pdo, string $processing_no): bool
{
    $query = 'SELECT 1 FROM voucher_receiving WHERE processing_no = :processing_no LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':processing_no', $processing_no);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

function voucher_incoming_return_exists(object $pdo, string $processing_no): bool
{
    $query = 'SELECT 1 FROM voucher_incoming WHERE processing_no = :processing_no LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':processing_no', $processing_no);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

function voucher_pending_return_exists(object $pdo, string $processing_no): bool
{
    $query = 'SELECT 1 FROM vouchers WHERE processing_no = :processing_no LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':processing_no', $processing_no);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

function voucher_return_fetch_process_history(object $pdo, string $processing_no, string $return_source): string
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return '';
    }

    $tables = $return_source === 'forwarding'
        ? ['voucher_receiving', 'voucher_tracking']
        : ['voucher_incoming', 'voucher_tracking'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT process_history FROM ' . $table . ' WHERE processing_no = :processing_no LIMIT 1');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $value = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function voucher_return_destination_matches_office(PDO $pdo, string $destination): string
{
    $destination = trim($destination);
    if ($destination === '') {
        return '';
    }

    require_once __DIR__ . '/../../core/components/helpers/utilities_office_helper.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/utilities_signatory_helper.inc.php';

    utilities_office_ensure_schema($pdo);
    foreach (utilities_office_registered_names($pdo, true) as $officeName) {
        if (utilities_signatory_offices_match($destination, $officeName)) {
            return utilities_signatory_resolve_office($pdo, $officeName);
        }
    }

    foreach (utilities_signatory_fetch_offices($pdo) as $officeName) {
        if (utilities_signatory_offices_match($destination, $officeName)) {
            return utilities_signatory_resolve_office($pdo, $officeName);
        }
    }

    return '';
}

/**
 * Resolve receiver_udc and destination office when returning to a previous process.
 *
 * @return array{receiver_udc: string, office_from: string, office_to: string}
 */
function voucher_return_resolve_previous_sender_target(
    object $pdo,
    string $destination,
    string $process_history,
    string $previous_sender_udc,
    string $returner_udc,
    string $logged_user_office
): array {
    $destination = trim($destination);
    $result = [
        'receiver_udc' => '',
        'office_from' => '',
        'office_to' => '',
    ];

    $targetOffice = voucher_return_destination_matches_office($pdo, $destination);
    if ($targetOffice !== '') {
        $result['office_from'] = $targetOffice;
        $result['office_to'] = $targetOffice;

        $lines = voucher_tracking_parse_process_history_lines($process_history);
        foreach (array_reverse($lines) as $line) {
            if (stripos((string) ($line['action'] ?? ''), 'Forwarded') === false) {
                continue;
            }

            $lineOffice = trim((string) ($line['office'] ?? ''));
            $lineSection = trim((string) ($line['section'] ?? ''));
            $officeMatches = voucher_tracking_offices_match($lineOffice, $targetOffice)
                || voucher_tracking_offices_match($lineSection, $targetOffice)
                || strcasecmp($lineSection, $destination) === 0;
            if (!$officeMatches) {
                continue;
            }

            $user = voucher_tracking_lookup_user_by_display_name($pdo, (string) ($line['name'] ?? ''));
            if ($user === null) {
                continue;
            }

            $udc = trim((string) ($user['udc'] ?? ''));
            if ($udc === '') {
                continue;
            }

            $validated = voucher_filter_udcs_by_user_group_office($pdo, $udc, $targetOffice);
            $validated = voucher_udcs_excluding($validated !== '' ? $validated : $udc, $returner_udc);
            if ($validated !== '') {
                $result['receiver_udc'] = $validated;

                return $result;
            }
        }

        foreach (['Liaison Officer', 'Liaison'] as $liaisonDesignation) {
            $resolved = voucher_return_resolve_receiver_udc($pdo, $liaisonDesignation, $targetOffice);
            $resolved = voucher_udcs_excluding($resolved, $returner_udc);
            if ($resolved !== '') {
                $result['receiver_udc'] = $resolved;

                return $result;
            }
        }

        if ($previous_sender_udc !== '') {
            $filtered = voucher_filter_udcs_by_user_group_office($pdo, $previous_sender_udc, $targetOffice);
            $filtered = voucher_udcs_excluding($filtered, $returner_udc);
            if ($filtered !== '') {
                $result['receiver_udc'] = $filtered;
            }
        }

        return $result;
    }

    $resolveOffice = trim($logged_user_office);
    $receiver_udc = voucher_return_resolve_receiver_udc($pdo, $destination, $resolveOffice);
    if ($receiver_udc === '') {
        $receiver_udc = voucher_return_resolve_receiver_udc($pdo, $destination, '');
    }
    if ($receiver_udc === '' && $previous_sender_udc !== '') {
        $receiver_udc = voucher_udcs_excluding($previous_sender_udc, $returner_udc);
    }

    $receiverOffice = voucher_tracking_lookup_office_by_udc($pdo, $receiver_udc);
    if ($receiverOffice === '') {
        $receiverOffice = voucher_resolve_office_for_designation_route($pdo, $destination, $logged_user_office);
    }
    if ($receiverOffice !== '') {
        $result['office_from'] = $receiverOffice;
        $result['office_to'] = $receiverOffice;
    }
    $result['receiver_udc'] = $receiver_udc;

    return $result;
}

/**
 * Resolve receiver_udc for a return target (section/designation or UDC).
 */
function voucher_return_resolve_receiver_udc(object $pdo, string $destination, string $penro_office): string
{
    if (!function_exists('voucher_resolve_receiver_udc_for_destination')) {
        require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    }

    return voucher_resolve_receiver_udc_for_destination($pdo, $destination, $penro_office);
}

function voucher_update_return_remarks(object $pdo, string $processing_no, string $return_remarks) {
    // Append new return remarks to existing (retain history).
    // If existing is empty/NULL, store just the new remarks.
    $query = "UPDATE vouchers
              SET return_remarks = TRIM(BOTH '\n' FROM CONCAT(
                  COALESCE(NULLIF(return_remarks, ''), ''),
                  CASE
                      WHEN return_remarks IS NULL OR return_remarks = '' THEN ''
                      ELSE '\n'
                  END,
                  :return_remarks
              ))
              WHERE processing_no = :processing_no";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":return_remarks", $return_remarks);
    $statement->bindParam(":processing_no", $processing_no);
    $statement->execute();
    return $statement->rowCount() > 0;
}

/**
 * Load encoded-field snapshot for retract (prefer tracking, fallback to queue row).
 *
 * @return array<string, string>|null
 */
function voucher_retract_fetch_encode_snapshot(object $pdo, string $processing_no, string $retract_source): ?array
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return null;
    }

    $snapshot = [];
    if ($retract_source === 'forwarding') {
        $sourceTable = 'voucher_receiving';
    } elseif ($retract_source === 'pending') {
        $sourceTable = 'vouchers';
    } else {
        $sourceTable = 'voucher_incoming';
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM {$sourceTable} WHERE processing_no = :processing_no LIMIT 1");
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach ($row as $key => $value) {
                if (is_string($key) && $value !== null && $value !== '') {
                    $snapshot[$key] = trim((string) $value);
                }
            }
        }
    } catch (PDOException $e) {
        // Source row may be missing on retry paths.
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $sourceAmount = (string) ($snapshot['amount'] ?? '');
            foreach ($row as $key => $value) {
                if (!is_string($key) || $value === null || $value === '') {
                    continue;
                }
                if ($key === 'amount' && $sourceAmount !== '') {
                    continue;
                }
                $snapshot[$key] = trim((string) $value);
            }
            if ($sourceAmount !== '') {
                $snapshot['amount'] = $sourceAmount;
            }
        }
    } catch (PDOException $e) {
        return $snapshot !== [] ? $snapshot : null;
    }

    return $snapshot !== [] ? $snapshot : null;
}

function voucher_retract_source_exists(object $pdo, string $processing_no, string $retract_source): bool
{
    if ($retract_source === 'forwarding') {
        return voucher_receiving_return_exists($pdo, $processing_no);
    }

    if ($retract_source === 'pending') {
        return voucher_pending_return_exists($pdo, $processing_no);
    }

    return voucher_incoming_return_exists($pdo, $processing_no);
}

function voucher_retract_clear_all_queues(object $pdo, string $processing_no): void
{
    incoming_voucher_sent_delete_from_incoming($pdo, $processing_no);
    incoming_voucher_delete_from_sent($pdo, $processing_no);

    if (!function_exists('voucher_delete_from_receiving')) {
        require_once __DIR__ . '/../voucher_receiving_module/voucher_receiving.model.inc.php';
    }
    voucher_delete_from_receiving($pdo, $processing_no);

    try {
        $stmt = $pdo->prepare('DELETE FROM voucher_temp WHERE processing_no = :processing_no');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // voucher_temp may be absent on older installs.
    }
}

function voucher_retract_insert_pending(object $pdo, array $fields): bool
{
    return voucher_incoming_sent_to_pending(
        $pdo,
        (string) ($fields['processing_no'] ?? ''),
        'TBD',
        'TBD',
        'TBD',
        (string) ($fields['payee'] ?? ''),
        (string) ($fields['address'] ?? ''),
        (string) ($fields['particulars'] ?? ''),
        (string) ($fields['tin_employee_no'] ?? ''),
        (string) ($fields['amount'] ?? ''),
        (string) ($fields['voucher_type'] ?? ''),
        (string) ($fields['voucher_date'] ?? ''),
        (string) ($fields['encoded_by'] ?? ''),
        (string) ($fields['encoded_from'] ?? ''),
        (string) ($fields['datetime_encoded'] ?? ''),
        null,
        null,
        null
    );
}

function voucher_retract_reset_tracking(
    object $pdo,
    string $processing_no,
    array $fields,
    string $encoded_action,
    string $datetime_status
): bool {
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return false;
    }

    $office_from = voucher_pick_field(
        (string) ($fields['encoded_from'] ?? ''),
        (string) ($fields['office_from'] ?? '')
    );
    $amount = voucher_prepare_stored_amount($pdo, (string) ($fields['amount'] ?? ''));

    $query = 'UPDATE voucher_tracking SET
        ors_no = :ors_no,
        ada_check_no = :ada_check_no,
        ada_check_date = :ada_check_date,
        dv_no = :dv_no,
        payee = :payee,
        address = :address,
        particulars = :particulars,
        amount = :amount,
        voucher_type = :voucher_type,
        voucher_date = :voucher_date,
        voucher_status = :voucher_status,
        status = :status,
        datetime_status = :datetime_status,
        encoded_by = :encoded_by,
        office_to = :office_to,
        office_from = :office_from,
        remarks = :remarks,
        coa_options = NULL,
        coa_category = NULL,
        coa_subsection = NULL,
        active_status = :active_status,
        process_history = NULL,
        charged_amount = NULL
        WHERE processing_no = :processing_no';

    $statement = $pdo->prepare($query);
    $statement->bindValue(':ors_no', 'TBD', PDO::PARAM_STR);
    $statement->bindValue(':ada_check_no', 'TBD', PDO::PARAM_STR);
    $statement->bindValue(':ada_check_date', 'TBD', PDO::PARAM_STR);
    $statement->bindValue(':dv_no', 'TBD', PDO::PARAM_STR);
    $statement->bindValue(':payee', (string) ($fields['payee'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':address', (string) ($fields['address'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':particulars', (string) ($fields['particulars'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':amount', $amount, PDO::PARAM_STR);
    $statement->bindValue(':voucher_type', (string) ($fields['voucher_type'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':voucher_date', (string) ($fields['voucher_date'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':voucher_status', $encoded_action, PDO::PARAM_STR);
    $statement->bindValue(':status', 'TBD', PDO::PARAM_STR);
    $statement->bindValue(':datetime_status', $datetime_status, PDO::PARAM_STR);
    $statement->bindValue(':encoded_by', (string) ($fields['encoded_by'] ?? ''), PDO::PARAM_STR);
    $statement->bindValue(':office_to', '', PDO::PARAM_STR);
    $statement->bindValue(':office_from', $office_from, PDO::PARAM_STR);
    $statement->bindValue(':remarks', '', PDO::PARAM_STR);
    $statement->bindValue(':active_status', 'no', PDO::PARAM_STR);
    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_retract_reset_dv_entry(object $pdo, string $processing_no, array $fields): void
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return;
    }

    if (!function_exists('ensure_dv_entries_table')) {
        require_once __DIR__ . '/../voucher_module/voucher.model.inc.php';
    }

    try {
        ensure_dv_entries_table($pdo);
        $existsStmt = $pdo->prepare('SELECT 1 FROM dv_entries WHERE processing_no = :processing_no LIMIT 1');
        $existsStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $existsStmt->execute();
        if (!(bool) $existsStmt->fetchColumn()) {
            return;
        }

        $amount = voucher_prepare_stored_amount($pdo, (string) ($fields['amount'] ?? ''));
        $office_from = voucher_pick_field(
            (string) ($fields['encoded_from'] ?? ''),
            (string) ($fields['office_from'] ?? '')
        );

        $stmt = $pdo->prepare(
            'UPDATE dv_entries SET
                ors_no = :ors_no,
                dv_no = :dv_no,
                ada_check_no = :ada_check_no,
                payee = :payee,
                address = :address,
                tin_employee_no = :tin_employee_no,
                amount = :amount,
                voucher_type = :voucher_type,
                voucher_date = :voucher_date,
                particulars = :particulars,
                encoded_by = :encoded_by,
                office_from = :office_from,
                encoded_from = :encoded_from,
                return_remarks = NULL,
                process_history = NULL
             WHERE processing_no = :processing_no'
        );
        $stmt->bindValue(':ors_no', 'TBD', PDO::PARAM_STR);
        $stmt->bindValue(':dv_no', 'TBD', PDO::PARAM_STR);
        $stmt->bindValue(':ada_check_no', 'TBD', PDO::PARAM_STR);
        $stmt->bindValue(':payee', (string) ($fields['payee'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':address', (string) ($fields['address'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':tin_employee_no', (string) ($fields['tin_employee_no'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindValue(':voucher_type', (string) ($fields['voucher_type'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':voucher_date', (string) ($fields['voucher_date'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':particulars', (string) ($fields['particulars'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':encoded_by', (string) ($fields['encoded_by'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':office_from', $office_from, PDO::PARAM_STR);
        $stmt->bindValue(':encoded_from', (string) ($fields['encoded_from'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // dv_entries may be absent on older installs.
    }
}

function voucher_retract_clear_voucher_return_remarks(object $pdo, string $processing_no): void
{
    try {
        $stmt = $pdo->prepare('UPDATE vouchers SET return_remarks = NULL WHERE processing_no = :processing_no');
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // return_remarks column may be absent on older installs.
    }
}

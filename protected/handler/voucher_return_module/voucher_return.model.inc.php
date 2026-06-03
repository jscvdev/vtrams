<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';

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

    // Carry supporting_documents (and charged_amount) forward from voucher_incoming
    $charged_amount = null;
    $supporting_documents = null;
    $selectQuery = "SELECT charged_amount, supporting_documents FROM voucher_incoming WHERE processing_no = :processing_no";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->bindParam(":processing_no", $processing_no);
    $selectStmt->execute();
    if ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $charged_amount = $row['charged_amount'] ?? null;
        $supporting_documents = $row['supporting_documents'] ?? null;
    }

    $query = "INSERT INTO voucher_receiving (processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, charged_amount, voucher_type, voucher_date, datetime_forwarded, office_from, office_to, sender_udc, receiver_udc, encoded_by, encoded_from, datetime_encoded, forwarded_by, transmit, process_status, remarks, sender_remarks, supporting_documents) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :charged_amount, :voucher_type, :voucher_date, :datetime_forwarded, :office_from, :office_to, :sender_udc, :receiver_udc, :encoded_by, :encoded_from, :datetime_encoded, :forwarded_by, :transmit, :process_status, :remarks, :sender_remarks, :supporting_documents)";

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

/** Keep dv_entries.encoded_from aligned when a returned voucher is back at the encoder. */
function voucher_return_sync_dv_encoded_from(object $pdo, string $processing_no, string $encoded_from): void
{
    $encoded_from = trim($encoded_from);
    if ($encoded_from === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE dv_entries SET encoded_from = :encoded_from WHERE processing_no = :processing_no'
        );
        $stmt->bindValue(':encoded_from', $encoded_from, PDO::PARAM_STR);
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // dv_entries may be absent on older installs
    }
}

function voucher_incoming_sent_to_pending(object $pdo, string $processing_no, string $dv_no, string $ada_check_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $encoded_by, string $encoded_from, string $datetime_encoded) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $query = "INSERT INTO vouchers (processing_no, dv_no, ada_check_no, payee, address, particulars, tin_employee_no,  amount, voucher_type, voucher_date, encoded_by, encoded_from, datetime_encoded) 
                        VALUES (:processing_no, :dv_no, :ada_check_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :voucher_date, :encoded_by, :encoded_from, :datetime_encoded)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);

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
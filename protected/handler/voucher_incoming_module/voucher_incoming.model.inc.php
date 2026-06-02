<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';

function delete_from_voucher_incoming(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_incoming WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function delete_from_voucher_sent(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_sent WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_insert_into_receving(
    object $pdo,
    string $processing_no,
    string $dv_no,
    string $ors_no,
    string $ada_check_no,
    string $payee,
    string $address,
    string $particulars,
    string $tin_employee_no,
    string $amount,
    string $voucher_type,
    string $voucher_date,
    string $datetime_action,
    string $office_from,
    string $office_to,
    string $sender_udc,
    string $receiver_udc,
    string $encoded_by,
    string $encoded_from,
    string $datetime_encoded,
    string $forwarded_by,
    string $process_status,
    string $combined_remarks,
    string $sender_remarks,
    string $supporting_documents,
    string $coa_options = null,
    string $coa_category = null,
    string $coa_subsection = null
) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $transmit = 'No';

    // Preserve existing process_history so receiving doesn't "reset" history to the latest action only.
    // Prefer voucher_incoming (same stage), fallback to voucher_tracking (global).
    $process_history = null;
    try {
        $histStmt = $pdo->prepare("SELECT process_history FROM voucher_incoming WHERE processing_no = :processing_no LIMIT 1");
        $histStmt->bindParam(":processing_no", $processing_no);
        $histStmt->execute();
        $row = $histStmt->fetch(PDO::FETCH_ASSOC);
        $histValue = trim((string)($row['process_history'] ?? ''));
        if ($histValue !== '') {
            $process_history = $histValue;
        } else {
            $histStmt2 = $pdo->prepare("SELECT process_history FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1");
            $histStmt2->bindParam(":processing_no", $processing_no);
            $histStmt2->execute();
            $row2 = $histStmt2->fetch(PDO::FETCH_ASSOC);
            $histValue2 = trim((string)($row2['process_history'] ?? ''));
            if ($histValue2 !== '') {
                $process_history = $histValue2;
            }
        }
    } catch (PDOException $e) {
        // Best-effort: if process_history cannot be read, proceed without it.
        $process_history = null;
    }

    $query = "INSERT INTO voucher_receiving (
                    processing_no,
                    dv_no,
                    ors_no,
                    ada_check_no,
                    payee,
                    address,
                    particulars,
                    tin_employee_no,
                    amount,
                    voucher_type,
                    voucher_date,
                    datetime_forwarded,
                    office_from,
                    office_to,
                    sender_udc,
                    receiver_udc,
                    encoded_by,
                    encoded_from,
                    datetime_encoded,
                    forwarded_by,
                    transmit,
                    process_status,
                    remarks,
                    sender_remarks,
                    supporting_documents,
                    coa_options,
                    coa_category,
                    coa_subsection,
                    process_history
                ) 
                VALUES (
                    :processing_no,
                    :dv_no,
                    :ors_no,
                    :ada_check_no,
                    :payee,
                    :address,
                    :particulars,
                    :tin_employee_no,
                    :amount,
                    :voucher_type,
                    :voucher_date,
                    :datetime_forwarded,
                    :office_from,
                    :office_to,
                    :sender_udc,
                    :receiver_udc,
                    :encoded_by,
                    :encoded_from,
                    :datetime_encoded,
                    :forwarded_by,
                    :transmit,
                    :process_status,
                    :remarks,
                    :sender_remarks,
                    :supporting_documents,
                    :coa_options,
                    :coa_category,
                    :coa_subsection,
                    :process_history
                )";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
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
    $statement->bindParam(":forwarded_by",$forwarded_by);
    $statement->bindParam(":transmit",$transmit);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":sender_remarks",$sender_remarks);
    $statement->bindParam(":supporting_documents",$supporting_documents);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);
    $statement->bindParam(":process_history", $process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_forwarded_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $status)
{
    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status, status = :status WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":status",$status);
    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_proccesing_no_incoming (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_receiving WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
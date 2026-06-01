<?php

declare(strict_types=1);

function voucher_delete_from_receiving(object $pdo, string $processing_no)
{
    $query = "DELETE FROM voucher_receiving WHERE processing_no = :processing_no";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no", $processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
function voucher_pending_to_incoming(
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
    string $remarks,
    ?string $coa_options_override = null,
    ?string $coa_category_override = null,
    ?string $coa_subsection_override = null
) {
    // Fetch original amount and COA data (if any) from voucher_receiving
    $charged_amount = null;
    $supporting_documents = null;
    $coa_options = null;
    $coa_category = null;
    $coa_subsection = null;
    $selectQuery = "SELECT charged_amount, supporting_documents, coa_options, coa_category, coa_subsection FROM voucher_receiving WHERE processing_no = :processing_no";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->bindParam(":processing_no", $processing_no);
    $selectStmt->execute();
    if ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $charged_amount = $row['charged_amount'] ?? null;
        $supporting_documents = $row['supporting_documents'] ?? null;
        $coa_options = $row['coa_options'] ?? null;
        $coa_category = $row['coa_category'] ?? null;
        $coa_subsection = $row['coa_subsection'] ?? null;
    }

    if ($coa_options_override !== null && trim($coa_options_override) !== '') {
        $coa_options = $coa_options_override;
    }
    if ($coa_category_override !== null && trim($coa_category_override) !== '') {
        $coa_category = $coa_category_override;
    }
    if ($coa_subsection_override !== null && trim($coa_subsection_override) !== '') {
        $coa_subsection = $coa_subsection_override;
    }

    // Carry over existing process_history (if any) so new actions are concatenated,
    // not started from scratch when the voucher enters Incoming.
    $process_history = null;
    try {
        $histStmt = $pdo->prepare("SELECT process_history FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1");
        $histStmt->bindParam(":processing_no", $processing_no);
        $histStmt->execute();
        if ($histRow = $histStmt->fetch(PDO::FETCH_ASSOC)) {
            $histValue = trim((string)($histRow['process_history'] ?? ''));
            if ($histValue !== '') {
                $process_history = $histValue;
            }
        }
    } catch (PDOException $e) {
        // If the column/table is missing or any error occurs, just leave process_history as null.
    }

    $query = "INSERT INTO voucher_incoming (
                    processing_no,
                    dv_no,
                    ors_no,
                    ada_check_no,
                    payee,
                    address,
                    particulars,
                    amount,
                    charged_amount,
                    voucher_type,
                    voucher_date,
                    tin_employee_no,
                    datetime_forwarded,
                    sender_udc,
                    receiver_udc,
                    office_from,
                    office_to,
                    encoded_by,
                    encoded_from,
                    datetime_encoded,
                    forwarded_by,
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
                    :amount,
                    :charged_amount,
                    :voucher_type,
                    :voucher_date,
                    :tin_employee_no,
                    :datetime_forwarded,
                    :sender_udc,
                    :receiver_udc,
                    :office_from,
                    :office_to,
                    :encoded_by,
                    :encoded_from,
                    :datetime_encoded,
                    :forwarded_by,
                    :process_status,
                    :combined_remarks,
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
    $statement->bindParam(":amount",$amount);
    $statement->bindParam(":charged_amount",$charged_amount);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindParam(":sender_udc",$sender_udc);
    $statement->bindParam(":receiver_udc",$receiver_udc);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$forwarded_by);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":sender_remarks",$remarks);
    $statement->bindParam(":combined_remarks",$combined_remarks);
    $statement->bindParam(":supporting_documents",$supporting_documents);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);
    $statement->bindParam(":process_history",$process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_pending_to_sent(
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
    string $remarks,
    ?string $coa_options_override = null,
    ?string $coa_category_override = null,
    ?string $coa_subsection_override = null
) {
    // Fetch original amount and COA data (if any) from voucher_receiving
    $charged_amount = null;
    $supporting_documents = null;
    $coa_options = null;
    $coa_category = null;
    $coa_subsection = null;
    $selectQuery = "SELECT charged_amount, supporting_documents, coa_options, coa_category, coa_subsection FROM voucher_receiving WHERE processing_no = :processing_no";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->bindParam(":processing_no", $processing_no);
    $selectStmt->execute();
    if ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $charged_amount = $row['charged_amount'] ?? null;
        $supporting_documents = $row['supporting_documents'] ?? null;
        $coa_options = $row['coa_options'] ?? null;
        $coa_category = $row['coa_category'] ?? null;
        $coa_subsection = $row['coa_subsection'] ?? null;
    }

    if ($coa_options_override !== null && trim($coa_options_override) !== '') {
        $coa_options = $coa_options_override;
    }
    if ($coa_category_override !== null && trim($coa_category_override) !== '') {
        $coa_category = $coa_category_override;
    }
    if ($coa_subsection_override !== null && trim($coa_subsection_override) !== '') {
        $coa_subsection = $coa_subsection_override;
    }

    $query = "INSERT INTO voucher_sent (
                    processing_no,
                    dv_no,
                    ors_no,
                    ada_check_no,
                    payee,
                    address,
                    particulars,
                    amount,
                    charged_amount,
                    voucher_type,
                    voucher_date,
                    tin_employee_no,
                    datetime_forwarded,
                    sender_udc,
                    receiver_udc,
                    office_from,
                    office_to,
                    encoded_by,
                    encoded_from,
                    datetime_encoded,
                    forwarded_by,
                    process_status,
                    remarks,
                    sender_remarks,
                    supporting_documents,
                    coa_options,
                    coa_category,
                    coa_subsection
                ) 
                VALUES (
                    :processing_no,
                    :dv_no,
                    :ors_no,
                    :ada_check_no,
                    :payee,
                    :address,
                    :particulars,
                    :amount,
                    :charged_amount,
                    :voucher_type,
                    :voucher_date,
                    :tin_employee_no,
                    :datetime_forwarded,
                    :sender_udc,
                    :receiver_udc,
                    :office_from,
                    :office_to,
                    :encoded_by,
                    :encoded_from,
                    :datetime_encoded,
                    :forwarded_by,
                    :process_status,
                    :combined_remarks,
                    :sender_remarks,
                    :supporting_documents,
                    :coa_options,
                    :coa_category,
                    :coa_subsection
                )";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":amount",$amount);
    $statement->bindParam(":charged_amount",$charged_amount);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindParam(":sender_udc",$sender_udc);
    $statement->bindParam(":receiver_udc",$receiver_udc);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$forwarded_by);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":sender_remarks",$remarks);
    $statement->bindParam(":combined_remarks",$combined_remarks);
    $statement->bindParam(":supporting_documents",$supporting_documents);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_received_voucher(object $pdo, string $processing_no, string $dv_no, string $ors_no, string $ada_check_no, string $action, string $datetime_action, string $combined_remarks) {

    $query = "UPDATE voucher_tracking SET dv_no = :dv_no, ors_no = :ors_no, ada_check_no = :ada_check_no, voucher_status = :voucher_status, datetime_status = :datetime_status, remarks = :remarks WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":remarks",$combined_remarks);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_receiving_get_processing_no (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_incoming INNER JOIN voucher_sent ON voucher_incoming.processing_no = voucher_sent.processing_no WHERE voucher_incoming.processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function set_voucher_transmit_status(object $pdo, string $processing_no, string $transmit_status)
{
    $query = "UPDATE voucher_receiving SET transmit = :transmit_status WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":transmit_status", $transmit_status);
    $statement->bindParam(":processing_no", $processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function set_voucher_status(object $pdo, string $processing_no, string $process_status)
{
    $query = "UPDATE voucher_receiving SET process_status = :process_status WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":process_status", $process_status);
    $statement->bindParam(":processing_no", $processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_document(object $pdo, string $ors_no, string $processing_no)
{
    $query = "UPDATE voucher_receiving SET ors_no = :ors_no WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":ors_no", $ors_no);
    $statement->bindParam(":processing_no", $processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_dv(object $pdo, string $dv_no, string $processing_no)
{
    $query = "UPDATE voucher_receiving SET dv_no = :dv_no WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":dv_no", $dv_no);
    $statement->bindParam(":processing_no", $processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_amount(object $pdo, string $processing_no, string $amount)
{
    // Keep original amount in `amount`.
    // Store into `charged_amount` only when the edited value truly differs.
    // If unchanged (or empty), clear charged_amount so downstream inserts stay clean.
    $query = "UPDATE voucher_receiving 
              SET charged_amount = CASE
                    WHEN TRIM(:new_amount_trim) = '' THEN NULL
                    WHEN CAST(REPLACE(:new_amount_num, ',', '') AS DECIMAL(18,2)) = CAST(REPLACE(amount, ',', '') AS DECIMAL(18,2)) THEN NULL
                    ELSE :new_amount_out
                  END
              WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);
    // Named placeholders must be unique (or MySQL PDO can throw HY093).
    $statement->bindParam(':new_amount_trim', $amount);
    $statement->bindParam(':new_amount_num', $amount);
    $statement->bindParam(':new_amount_out', $amount);
    $statement->bindParam(':processing_no', $processing_no);
    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_employee_name_by_designation(object $pdo, string $designation, string $office = ''): ?string
{
    $query = "SELECT TRIM(CONCAT_WS(' ',
                NULLIF(TRIM(ug.emp_fn), ''),
                NULLIF(TRIM(ug.emp_mi), ''),
                NULLIF(TRIM(ug.emp_ln), '')
            )) AS full_name
            FROM designation_limit dl
            INNER JOIN user_group ug ON FIND_IN_SET(ug.udc, dl.designated_udc)
            WHERE dl.designation = :designation";

    if ($office !== '') {
        $query .= " AND ug.office = :office";
    }

    $query .= " ORDER BY ug.emp_ln ASC, ug.emp_fn ASC, ug.emp_mi ASC LIMIT 1";

    $statement = $pdo->prepare($query);
    $statement->bindParam(":designation", $designation);

    if ($office !== '') {
        $statement->bindParam(":office", $office);
    }

    $statement->execute();
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    $fullName = trim((string)($result['full_name'] ?? ''));

    return $fullName !== '' ? $fullName : null;
}
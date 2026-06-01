<?php

declare(strict_types=1);

function remove_from_temp_vouchers(object $pdo, string $processing_no, string $dv_no) {
    $query = "DELETE FROM voucher_temp WHERE processing_no = :processing_no AND dv_no = :dv_no";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_temp_to_receiving(
    object $pdo,
    string $processing_no,
    string $ors_no,
    string $ada_check_no,
    string $dv_no,
    string $payee,
    string $address,
    string $tin_employee_no,
    string $particulars,
    string $amount,
    string $voucher_type,
    string $voucher_date,
    string $encoded_by,
    string $encoded_from,
    string $datetime_encoded,
    string $action,
    string $action_by,
    string $datetime_action,
    string $office_from,
    string $office_to,
    string $sender_udc,
    string $receiver_udc,
    string $remarks
) {
    $transmit = 'No';
    $process_status = 'N/A';

    // Carry supporting_documents (and charged_amount) forward from voucher_temp
    $charged_amount = null;
    $supporting_documents = null;
    $selectQuery = "SELECT charged_amount, supporting_documents FROM voucher_temp WHERE processing_no = :processing_no AND dv_no = :dv_no";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->bindParam(":processing_no", $processing_no);
    $selectStmt->bindParam(":dv_no", $dv_no);
    $selectStmt->execute();
    if ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $charged_amount = $row['charged_amount'] ?? null;
        $supporting_documents = $row['supporting_documents'] ?? null;
    }

    $query = "INSERT INTO voucher_receiving (processing_no, ors_no, ada_check_no, dv_no, payee, address, tin_employee_no, particulars, amount, charged_amount, voucher_type, voucher_date, datetime_forwarded, office_from, office_to, sender_udc, encoded_by, encoded_from, datetime_encoded, forwarded_by, transmit, process_status, receiver_udc, remarks, sender_remarks, supporting_documents) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :tin_employee_no, :particulars, :amount, :charged_amount, :voucher_type, :voucher_date, :datetime_forwarded, :office_from, :office_to, :sender_udc, :encoded_by, :encoded_from, :datetime_encoded, :forwarded_by, :transmit, :process_status, :receiver_udc, :remarks, :sender_remarks, :supporting_documents)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":amount",$amount);
    $statement->bindParam(":charged_amount",$charged_amount);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":sender_udc",$sender_udc);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$encoded_by);
    $statement->bindParam(":transmit",$transmit);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":receiver_udc",$receiver_udc);
    $statement->bindParam(":remarks",$remarks);
    $statement->bindParam(":sender_remarks",$remarks);
    $statement->bindParam(":supporting_documents",$supporting_documents);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_log_to_document_tracking(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $datetime_encoded, string $action, string $datetime_action, string $encoded_by, string $office_to, string $office_from, string $remarks) {
    $ada_check_date = 'TBD';
    $status = 'TBD';
    $query = "INSERT INTO voucher_tracking (processing_no, ors_no, ada_check_no, ada_check_date, dv_no, payee, address, particulars, amount, voucher_type, voucher_date, datetime_encoded, voucher_status, status, datetime_status, encoded_by, office_to, office_from, remarks) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :ada_check_date, :dv_no, :payee, :address, :particulars, :amount, :voucher_type, :voucher_date, :datetime_encoded, :voucher_status, :status, :datetime_status, :encoded_by, :office_to, :office_from, :remarks)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":ada_check_date",$ada_check_date);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":amount",$amount);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":status",$status);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":remarks",$remarks);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_returned_voucher(object $pdo, string $processing_no, string $action, string $datetime_action)
{
    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_processing_no (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_temp WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
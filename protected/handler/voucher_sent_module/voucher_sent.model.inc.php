<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';

function voucher_sent_delete_from_incoming(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_incoming WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_delete_from_sent(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_sent WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_sent_to_receiving(object $pdo, string $ors_no, string $ada_check_no, string $processing_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $office_from, string $office_to, string $sender_udc, 
string $receiver_udc, string $encoded_by, string $encoded_from, string $datetime_encoded, string $process_status, string $combined_remarks, string $file_path = '') {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $transmit = 'No';

    // Carry supporting_documents (and charged_amount) forward from voucher_sent
    $charged_amount = null;
    $supporting_documents = null;
    $selectQuery = "SELECT charged_amount, supporting_documents FROM voucher_sent WHERE processing_no = :processing_no";
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
    $statement->bindParam(":receiver_udc",$sender_udc);
    $statement->bindParam(":sender_udc",$receiver_udc);
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

function voucher_sent_to_pending(object $pdo, string $processing_no, string $dv_no, string $ada_check_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $encoded_by, string $encoded_from, string $datetime_encoded, string $file_path = '') {
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

function voucher_sent_log_to_document_tracking(object $pdo, string $processing_no, string $action, string $datetime_action, string $remarks) {
    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status, remarks = :remarks WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":remarks",$remarks);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function sent_get_processing_no (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_receiving WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
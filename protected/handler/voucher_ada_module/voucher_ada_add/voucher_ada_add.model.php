<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../core/components/helpers/amount_helper.inc.php';

function remove_from_received_vouchers(object $pdo, string $processing_no, string $dv_no) {
    $query = "DELETE FROM voucher_receiving WHERE processing_no = :processing_no AND dv_no = :dv_no";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_receiving_to_temp(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, 
string $tin_employee_no, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $encoded_by, string $encoded_from, string $datetime_encoded, string $action, string $action_by, 
string $datetime_action, string $office_from, string $office_to, string $receiver_udc, string $remarks, string $process_history) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $query = "INSERT INTO voucher_temp (processing_no, ors_no, ada_check_no, dv_no, payee, address, tin_employee_no, particulars, amount, voucher_type, voucher_date, datetime_action, office_from, office_to, encoded_by, encoded_from, datetime_encoded, receiver_udc, action, action_by, remarks, process_history) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :tin_employee_no, :particulars, :amount, :voucher_type, :voucher_date, :datetime_action, :office_from, :office_to, :encoded_by, :encoded_from, :datetime_encoded, :receiver_udc, :action, :action_by, :remarks, :process_history)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_action",$datetime_action);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":receiver_udc",$receiver_udc);
    $statement->bindParam(":action",$action);
    $statement->bindParam(":action_by",$action_by);
    $statement->bindParam(":remarks",$remarks);
    $statement->bindParam(":process_history",$process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_log_to_document_tracking(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $datetime_encoded, string $action, string $datetime_action, string $encoded_by, string $office_to, string $office_from, string $remarks) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
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
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
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
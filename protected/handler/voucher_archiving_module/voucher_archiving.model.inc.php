<?php

declare(strict_types=1);

function archiving_delete_from_voucher_receiving(object $pdo, string $processing_no) {
    $query = "DELETE FROM voucher_receiving WHERE processing_no = :processing_no";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
function insert_to_voucher_archive(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_date,
                                      string $priority, string $action, string $action_by, string $datetime_action, string $office_from, string $office_to, string $encoded_by, string $receiver_udc) {
    $query = "INSERT INTO voucher_archives (processing_no, ors_no, ada_check_no, dv_no, payee, address, tin_employee_no, particulars, amount, voucher_date,
        priority, action, action_by, datetime_action, office_from, office_to, encoded_by, receiver_udc) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :tin_employee_no, :particulars, :amount, :voucher_date,
        :priority, :action, :action_by, :datetime_action, :office_from, :office_to, :encoded_by, :receiver_udc)";

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
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":priority",$priority);
    $statement->bindParam(":action",$action);
    $statement->bindParam(":action_by",$action_by);
    $statement->bindParam(":datetime_action",$datetime_action);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":receiver_udc",$receiver_udc);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_archived_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $totalProcessingTime) {

    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status, total_processing_time = :total_processing_time WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":total_processing_time",$totalProcessingTime);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_archiving_get_document_id (object $pdo, $processing_no){
    $query = "SELECT * FROM voucher_archives WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}
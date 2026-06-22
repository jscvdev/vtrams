<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';

function delete_from_vouchers(object $pdo, string $processing_no) {
    $query = "DELETE FROM vouchers WHERE processing_no = :processing_no";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_pending_to_incoming(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $sender_udc,
                                     string $receiver_udc, string $office_from, string $office_to, string $encoded_by, string $encoded_from, string $datetime_encoded, string $forwarded_by, string $process_status, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $process_history = voucher_tracking_fetch_process_history($pdo, $processing_no);
    $query = "INSERT INTO voucher_incoming (processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, voucher_type, voucher_date, datetime_forwarded, sender_udc, receiver_udc, office_from, office_to, encoded_by, encoded_from, datetime_encoded, forwarded_by, process_status, remarks, sender_remarks, coa_options, coa_category, coa_subsection, process_history) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :voucher_date, :datetime_forwarded, :sender_udc, :receiver_udc, :office_from, :office_to, :encoded_by, :encoded_from, :datetime_encoded, :forwarded_by, :process_status, :remarks, :sender_remarks, :coa_options, :coa_category, :coa_subsection, :process_history)";

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
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindValue(":sender_udc", $sender_udc, PDO::PARAM_STR);
    $statement->bindValue(":receiver_udc", $receiver_udc, PDO::PARAM_STR);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$forwarded_by);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":sender_remarks",$combined_remarks);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);
    $statement->bindParam(":process_history", $process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_pending_to_sent(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $sender_udc,
                                     string $receiver_udc, string $office_from, string $office_to, string $encoded_by, string $encoded_from, string $datetime_encoded, string $forwarded_by, string $process_status, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null) {
    $amount = voucher_prepare_stored_amount($pdo, $amount);
    $process_history = voucher_tracking_fetch_process_history($pdo, $processing_no);
    $query = "INSERT INTO voucher_sent (processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, voucher_type, voucher_date, datetime_forwarded, sender_udc, receiver_udc, office_from, office_to, encoded_by, encoded_from, datetime_encoded, forwarded_by, process_status, remarks, sender_remarks, coa_options, coa_category, coa_subsection, process_history) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :voucher_date, :datetime_forwarded, :sender_udc, :receiver_udc, :office_from, :office_to, :encoded_by, :encoded_from, :datetime_encoded, :forwarded_by, :process_status, :remarks, :sender_remarks, :coa_options, :coa_category, :coa_subsection, :process_history)";

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
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_forwarded",$datetime_action);
    $statement->bindValue(":sender_udc", $sender_udc, PDO::PARAM_STR);
    $statement->bindValue(":receiver_udc", $receiver_udc, PDO::PARAM_STR);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":forwarded_by",$forwarded_by);
    $statement->bindParam(":process_status",$process_status);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":sender_remarks",$combined_remarks);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);
    $statement->bindParam(":process_history", $process_history);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_returned_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $combined_remarks)
{
    $query = "UPDATE voucher_tracking SET voucher_status = :voucher_status, datetime_status = :datetime_status, remarks = :combined_remarks, active_status = 'yes' WHERE processing_no = :processing_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":combined_remarks",$combined_remarks);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_processing_no(object $pdo, $processing_no): bool
{
    $query = 'SELECT 1 FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':processing_no', $processing_no);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}
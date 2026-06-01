<?php

declare(strict_types=1);

//CHECKS IF REQUIRED DATA IS COMPLETE
function is_voucher_incoming_required_data_empty(array $variables_to_check)
{
    $empty_variables = [];

    foreach ($variables_to_check as $var_name => $var_value) {
        if (empty($var_value)) {
            $empty_variables[$var_name] = $var_value;
        }
    }

    return [
        'is_empty' => !empty($empty_variables),
        'empty_variables' => $empty_variables
    ];
}

//CHECKS IF THE ENCODED DOCUMENT IS ALREADY RECEIVED
function check_if_voucher_received(object $pdo, $processing_no)
{
    if (get_proccesing_no_incoming($pdo, $processing_no)){
        return true;
    }
    else
    {
        return false;
    }
}

//MAKE SURE THAT NO DUPLICATE IN INCOMING
function remove_from_voucher_incoming(object $pdo, string $processing_no)
{
    delete_from_voucher_incoming($pdo, $processing_no);
}

//MAKE SURE THAT NO DUPLICATE IN SENT
function remove_from_voucher_sent(object $pdo, string $processing_no)
{
    delete_from_voucher_sent($pdo, $processing_no);
}

//MOVES THE ENCODED DOCUMENT TO RECEIVING
function voucher_move_to_receiving(
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
    voucher_insert_into_receving(
        $pdo,
        $processing_no,
        $dv_no,
        $ors_no,
        $ada_check_no,
        $payee,
        $address,
        $particulars,
        $tin_employee_no,
        $amount,
        $voucher_type,
        $voucher_date,
        $datetime_action,
        $sender_udc,
        $receiver_udc,
        $office_from,
        $office_to,
        $encoded_by,
        $encoded_from,
        $datetime_encoded,
        $forwarded_by,
        $process_status,
        $combined_remarks,
        $sender_remarks,
        $supporting_documents,
        $coa_options,
        $coa_category,
        $coa_subsection
    );
}

//UPDATE THE DOCUMENT FORWARDED LOG IN DOCUMENT TRACKING
function update_incoming_forwarded_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $status)
{
    update_forwarded_voucher($pdo, $processing_no, $action, $datetime_action, $status);
}


<?php

declare(strict_types=1);

//CHECKS IF REQUIRED DATA IS COMPLETE
function is_voucher_forward_required_data_empty(array $variables_to_check)
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

function check_if_voucher_added_exists(object $pdo, $processing_no)
{
    if (get_processing_no($pdo, $processing_no)){
        return true;
    }
    else
    {
        return false;
    }
}
function voucher_add_voucher(object $pdo, string $processing_no, string $dv_no)
{
    remove_from_received_vouchers($pdo, $processing_no, $dv_no);
}

function voucher_move_to_temp(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, 
string $tin_employee_no, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $encoded_by, string $encoded_from, string $datetime_encoded, string $action, string $action_by, 
string $datetime_action, string $office_from, string $office_to, string $receiver_udc, string $remarks, string $process_history)
{
    voucher_receiving_to_temp($pdo, $processing_no,$ors_no, $ada_check_no, $dv_no, $payee, $address, $tin_employee_no, $particulars, $amount, $voucher_type,
    $voucher_date, $encoded_by, $encoded_from, $datetime_encoded, $action, $action_by, $datetime_action, $office_from, $office_to, $receiver_udc, $remarks, $process_history);
}


function voucher_document_tracking_logging(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $amount, 
string $voucher_type, string $voucher_date, string $datetime_encoded, string $action, string $datetime_action, string $encoded_by, string $office_to, string $office_from, string $remarks)
{
    voucher_log_to_document_tracking($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $amount, $voucher_type, $voucher_date, $datetime_encoded, $action, $datetime_action, $encoded_by, $office_to, $office_from, $remarks);
}

function update_returned_forwarded_voucher(object $pdo, string $processing_no, string $action, string $datetime_action)
{
    update_returned_voucher($pdo, $processing_no, $action, $datetime_action);
}


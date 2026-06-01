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

function check_if_voucher_forwarded_exists(object $pdo, $processing_no)
{
    if (get_processing_no($pdo, $processing_no)){
        return true;
    }
    else
    {
        return false;
    }
}
function voucher_forward_pending(object $pdo, string $processing_no)
{
    delete_from_vouchers($pdo, $processing_no);
}

function voucher_move_to_incoming(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $sender_udc,
                                  string $receiver_udc, string $office_from, string $office_to, string $encoded_by, string $encoded_from, string $datetime_encoded, string $forwarded_by, string $process_status, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null)
{
    voucher_pending_to_incoming($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $tin_employee_no, $amount, $voucher_type, $voucher_date, $datetime_action, $sender_udc,
        $receiver_udc, $office_from, $office_to, $encoded_by, $encoded_from, $datetime_encoded, $forwarded_by, $process_status, $combined_remarks, $coa_options, $coa_category, $coa_subsection);
}

function voucher_move_to_sent(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date, string $datetime_action, string $sender_udc,
                                  string $receiver_udc, string $office_from, string $office_to, string $encoded_by, string $encoded_from, string $datetime_encoded, string $forwarded_by, string $process_status, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null)
{
    voucher_pending_to_sent($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $tin_employee_no, $amount, $voucher_type, $voucher_date, $datetime_action, $sender_udc,
        $receiver_udc, $office_from, $office_to, $encoded_by, $encoded_from, $datetime_encoded, $forwarded_by, $process_status, $combined_remarks, $coa_options, $coa_category, $coa_subsection);
}

function update_returned_forwarded_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $combined_remarks)
{
    update_returned_voucher($pdo, $processing_no, $action, $datetime_action, $combined_remarks);
}


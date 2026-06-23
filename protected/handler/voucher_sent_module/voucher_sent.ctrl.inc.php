<?php

declare(strict_types=1);

//CHECKS IF REQUIRED DATA IS COMPLETE

function is_voucher_sent_required_data_empty(array $variables_to_check)
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

//MAKE SURE THAT NO DUPLICATE IN RECEIVING
function check_if_voucher_returned_exists(object $pdo, $processing_no)
{
    if (sent_get_processing_no($pdo, $processing_no)){
        return true;
    }
    else
    {
        return false;
    }
}

//MAKE SURE THAT NO DUPLICATE IN INCOMING
function voucher_remove_incoming_from_sent(object $pdo, string $processing_no)
{
    voucher_sent_delete_from_incoming($pdo, $processing_no);
}

//MAKE SURE THAT NO DUPLICATE IN SENT
function voucher_remove_from_sent(object $pdo, string $processing_no)
{
   voucher_delete_from_sent($pdo, $processing_no);
}

function voucher_sent_move_to_receiving(object $pdo, string $ors_no, string $ada_check_no, string $processing_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type,
                                string $voucher_date, string $datetime_action, string $sender_udc, string $receiver_udc, string $office_from, string $office_to, 
                                $encoded_by, string $encoded_from, string $datetime_encoded, string $process_status, string $combined_remarks, string $file_path)
{
    voucher_sent_to_receiving($pdo, $ors_no, $ada_check_no, $processing_no, $dv_no, $payee, $address, $particulars, $tin_employee_no, $amount, $voucher_type,
        $voucher_date, $datetime_action, $office_from, $office_to, $sender_udc, $receiver_udc, $encoded_by, $encoded_from, $datetime_encoded, $process_status, $combined_remarks, $file_path);
}

function voucher_sent_return_document (object $pdo, string $processing_no, string $dv_no, string $ada_check_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type,
                                       string $voucher_date, string $encoded_by, string $encoded_from, string $datetime_encoded, string $file_path)
{
    voucher_sent_to_pending ($pdo, $processing_no, $dv_no, $ada_check_no, $payee, $address, $particulars, $tin_employee_no, $amount, $voucher_type,
        $voucher_date, $encoded_by, $encoded_from, $datetime_encoded, $file_path);
}

//UPDATE THE DOCUMENT FORWARDED LOG IN DOCUMENT TRACKING
function voucher_sent_update_document_tracking(object $pdo, string $processing_no, string $action, string $datetime_action, string $remarks, string $active_status = 'returned', bool $update_remarks = true)
{
    voucher_sent_log_to_document_tracking($pdo, $processing_no, $action, $datetime_action, $remarks, $active_status, $update_remarks);
}



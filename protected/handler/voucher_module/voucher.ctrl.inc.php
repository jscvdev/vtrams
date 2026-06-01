<?php

declare(strict_types=1);

//CHECKS IF REQUIRED DATA IS COMPLETE
function is_voucher_encoding_required_data_empty(array $variables_to_check)
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

function insert_voucher_no (object $pdo, string $dv_no) {
    insert_dv_no($pdo, $dv_no);
}

function move_to_pending_voucher_no(
    object $pdo,
    string $processing_no,
    string $dv_no,
    string $ada_check_no,
    string $payee,
    string $address,
    string $tin_employee_no,
    string $voucher_date,
    string $amount,
    string $voucher_type,
    string $particulars,
    string $datetime_action,
    string $action_from,
    string $encoded_by,
    string $ors_no = 'TBD',
    string $office_from = ''
): void {
    insert_to_vouchers($pdo, $processing_no, $dv_no, $ada_check_no, $payee, $address, $tin_employee_no, $voucher_date, $amount, $voucher_type, $particulars, $datetime_action, $action_from, $encoded_by);
    try {
        insert_dv_entry(
            $pdo,
            $processing_no,
            $dv_no,
            $ada_check_no,
            $ors_no,
            $payee,
            $address,
            $tin_employee_no,
            $voucher_date,
            $amount,
            $voucher_type,
            $particulars,
            $datetime_action,
            $action_from,
            $encoded_by,
            $office_from
        );
    } catch (Throwable $e) {
        error_log('dv_entries insert failed: ' . $e->getMessage());
    }
}

function voucher_document_tracking_logging(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $datetime_encoded, string $action, string $datetime_action, string $encoded_by, string $office_to, string $office_from, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null)
{
    voucher_log_to_document_tracking($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $amount, $voucher_type, $voucher_date, $datetime_encoded, $action, $datetime_action, $encoded_by, $office_to, $office_from, $combined_remarks, $coa_options, $coa_category, $coa_subsection);
}

function check_if_voucher_exists(object $pdo, string $dv_no)
{
    if (get_dv_no($pdo, $dv_no)){
        return true;
    }
    else {
        return false;
    }
}
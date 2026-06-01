<?php

declare(strict_types=1);

function is_voucher_archiving_required_data_empty(array $variables_to_check)
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

function check_if_voucher_archived_exists(object $pdo, $processing_no)
{
    if (voucher_archiving_get_document_id($pdo, $processing_no)){
        return true;
    }
    else
    {
        return false;
    }
}
function remove_from_voucher_receiving(object $pdo, string $processing_no)
{
    return archiving_delete_from_voucher_receiving($pdo, $processing_no);
}

function voucher_archive_data(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_date,
                              string $priority, string $action, string $action_by, string $datetime_action, string $office_from, string $office_to, string $encoded_by, string $receiver_udc)
{
    return insert_to_voucher_archive($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $tin_employee_no, $particulars, $amount, $voucher_date,
        $priority, $action, $action_by, $datetime_action, $office_from, $office_to, $encoded_by, $receiver_udc);
}

function update_forwarded_archived_voucher(object $pdo, string $processing_no, string $action, string $datetime_action, string $totalProcessingTime)
{
    return update_archived_voucher($pdo, $processing_no, $action, $datetime_action, $totalProcessingTime);
}

<?php

declare(strict_types=1);

function voucher_log_user_action(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $tin_employee_no, string $amount, string $voucher_type, string $voucher_date,
                                 string $action, string $action_by, string $action_from, string $datetime_action, string $office_from, string $office_to, string $encoded_by, string $remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null)
{
    return voucher_document_user_action($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $tin_employee_no, $amount, $voucher_type, $voucher_date,
        $action, $action_by, $action_from, $datetime_action, $office_from, $office_to, $encoded_by, $remarks, $coa_options, $coa_category, $coa_subsection);
}

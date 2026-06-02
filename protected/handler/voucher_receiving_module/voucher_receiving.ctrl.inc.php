<?php

declare(strict_types=1);

function is_voucher_receiving_required_data_empty(array $variables_to_check)
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

function receiving_check_if_forwarded_voucher_exists(object $pdo, $processing_no)
{
    if (voucher_receiving_get_processing_no($pdo, $processing_no)) {
        return true;
    } else {
        return false;
    }
}
function voucher_forward_receiving(object $pdo, string $processing_no)
{
    voucher_delete_from_receiving($pdo, $processing_no);
}

function voucher_receiving_move_to_incoming(
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
    string $remarks,
    ?string $coa_options = null,
    ?string $coa_category = null,
    ?string $coa_subsection = null
) {
    voucher_pending_to_incoming(
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
        $remarks,
        $coa_options,
        $coa_category,
        $coa_subsection
    );
}

function voucher_receiving_move_to_sent(
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
    string $remarks,
    ?string $coa_options = null,
    ?string $coa_category = null,
    ?string $coa_subsection = null
) {
    voucher_pending_to_sent(
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
        $remarks,
        $coa_options,
        $coa_category,
        $coa_subsection
    );
}

function update_forwarded_received_voucher(object $pdo, string $processing_no, string $dv_no, string $ors_no, string $ada_check_no, string $action, string $datetime_action, string $combined_remarks)
{
    update_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);
}

function set_voucher_transmit(object $pdo, string $processing_no, string $transmit_status)
{
    set_voucher_transmit_status($pdo, $processing_no, $transmit_status);
}

function set_voucher_process_status(object $pdo, string $processing_no, string $process_status)
{
    set_voucher_status($pdo, $processing_no, $process_status);
}

function update_voucher_document(object $pdo, string $ors_no, string $processing_no)
{
    update_document($pdo, $ors_no, $processing_no);
}
function update_voucher_dv_no(object $pdo, string $dv_no, string $processing_no)
{
    update_dv($pdo, $dv_no, $processing_no);
}

function update_voucher_amount(object $pdo, string $processing_no, string $amount)
{
    voucher_apply_exact_amount($amount);
    update_amount($pdo, $processing_no, $amount);
}

function get_voucher_receiver_name_by_role(object $pdo, string $role, string $office = ''): string
{
    $receiverName = get_employee_name_by_designation($pdo, $role, $office);
    return !empty($receiverName) ? $receiverName : '';
}

function get_voucher_transmit_configurations(): array
{
    return [
        [
            'matches' => ['Budget Unit'],
            'receiver_role' => 'Budget Officer',
            'update_document' => true,
            'update_amount' => true
        ],
        [
            'matches' => ['Planning Section'],
            'receiver_role' => 'Planning Section Chief',
            'update_document' => false,
            'update_amount' => false
        ],
        [
            'matches' => ['Cashiers Unit'],
            'receiver_role' => 'Cashier',
            'update_document' => false,
            'update_amount' => false
        ],
        [
            'matches' => ['Accounting Unit', 'Processor'],
            'receiver_role' => 'Accountant III',
            'update_document' => false,
            'update_amount' => true
        ],
        [
            'matches' => ['Office of the PENRO'],
            'receiver_role' => 'PENR Officer',
            'update_document' => false,
            'update_amount' => false
        ],
    ];
}

function get_voucher_retransmit_configurations(): array
{
    return [
        [
            'matches' => ['Budget Unit'],
            'forwarder_role' => 'Budget Officer',
            'update_amount' => true
        ],
        [
            'matches' => ['Planning Section'],
            'forwarder_role' => 'Planning Section Chief',
            'update_amount' => false
        ],
        [
            'matches' => ['Cashiers Unit'],
            'forwarder_role' => 'Cashier',
            'update_amount' => false
        ],
        [
            'matches' => ['Accounting Unit', 'Processor'],
            'forwarder_role' => 'Accountant III',
            'update_amount' => true
        ],
        [
            'matches' => ['Office of the PENRO'],
            'forwarder_role' => 'PENR Officer',
            'update_amount' => false
        ],
    ];
}

function find_voucher_action_config(array $target, array $configurations): ?array
{
    foreach ($configurations as $config) {
        foreach (($config['matches'] ?? []) as $matchDesignation) {
            if (in_array($matchDesignation, $target, true)) {
                return $config;
            }
        }
    }

    return null;
}

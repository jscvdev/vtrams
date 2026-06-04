<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once __DIR__ . '/../voucher_module/voucher.model.inc.php';

    $keyList = array(
        "processing_no",
        "encoded_dv_no",
        "encoded_payee",
        "encoded_address",
        "encoded_particulars",
        "encoded_tin_employee_no",
        "encoded_amount",
        "encoded_voucher_date",
        "encoded_by",
        "encoded_type",
        "remarks"
    );

    $variable_map = array(
        'processing_no' => 'processing_no',
        'encoded_dv_no' => 'dv_no',
        'encoded_payee' => 'payee',
        'encoded_address' => 'address',
        'encoded_particulars' => 'particulars',
        'encoded_tin_employee_no' => 'tin_employee_no',
        'encoded_amount' => 'amount',
        'encoded_voucher_date' => 'voucher_date',
        'encoded_by' => 'encoded_by',
        'encoded_type' => 'voucher_type',
        'remarks' => 'remarks'

        // Add more mappings as needed
    );

    //LOOP METHOD
    foreach ($keyList as $key) {
        $variable_name = $variable_map[$key];
        if (isset($_POST[$key])) {
            $$variable_name = voucher_post_string($_POST[$key]);
        } else {
            $$variable_name = "";
        }
    }

    voucher_apply_exact_amount($amount);

    if (isset($_SESSION['logged_user_office'])) {
        $office_from = $_SESSION['logged_user_office'];
    } else {
        $office_from = '';
    }

    $ors_no = "TBD";
    $ada_check_no = "TBD";
    $office_to = "";

    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (isset($_REQUEST['edit_voucher'])) {

                $action = "Edited By: " . $_SESSION['logged_user_emp_name'];

                date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                $datetime_action = $currTime;
                $action_from = $_SESSION['logged_user_section'];
                $action_by = $_SESSION['logged_user_emp_name'];

                $variables_to_check = [
                    'processing_no' => $processing_no,
                    'payee' => $payee,
                    'particulars' => $particulars,
                    'amount' => $amount,
                    'voucher_date' => $voucher_date,
                    'encoded_by' => $encoded_by,
                    'voucher_type' => $voucher_type
                ];

                function is_voucher_editing_required_data_empty(array $variables_to_check)
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

                //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                $result = is_voucher_editing_required_data_empty($variables_to_check);

                //CHECK IF REQUIRED DATA EMPTY
                if ($result['is_empty']) {
                    $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                    $empty_value_strings = [];

                    foreach ($result['empty_variables'] as $var_name => $var_value) {
                        $empty_value_strings[] = "$var_name: $var_value";
                    }

                    $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                }

                if ($temp_dump) {
                    $_SESSION['error_dv_encode'] = $temp_dump;
                    echo "<script>process_functionAlert('Edit failed!', 'voucher_edit_err')</script>";
                    die();
                } else {
                    vouchers_amount_ensure_string_column($pdo);
                    $dv_no = voucher_resolve_existing_dv_no($pdo, $processing_no, $dv_no);
                    //QUERY — dv_no is not editable from the pending-voucher form; preserve existing value.
                    $query = "UPDATE vouchers SET payee = :payee, address = :address, particulars = :particulars, tin_employee_no = :tin_employee_no, amount = :amount, voucher_type = :voucher_type, voucher_date = :voucher_date WHERE processing_no=:processing_no";

                    $statement = $pdo->prepare($query);

                    $statement->bindParam(":payee", $payee);
                    $statement->bindParam(":address", $address);
                    $statement->bindParam(":particulars", $particulars);
                    $statement->bindParam(":tin_employee_no", $tin_employee_no);
                    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
                    $statement->bindParam(":voucher_type", $voucher_type);
                    $statement->bindParam(":voucher_date", $voucher_date);
                    $statement->bindParam(":processing_no", $processing_no);

                    if ($statement->execute()) {
                        if (!sync_voucher_tracking_after_edit(
                            $pdo,
                            $processing_no,
                            $payee,
                            $address,
                            $particulars,
                            $amount,
                            $voucher_type,
                            $voucher_date,
                            $action,
                            $datetime_action
                        )) {
                            voucher_log_to_document_tracking(
                                $pdo,
                                $processing_no,
                                $ors_no,
                                $ada_check_no,
                                $dv_no,
                                $payee,
                                $address,
                                $particulars,
                                $amount,
                                $voucher_type,
                                $voucher_date,
                                $datetime_action,
                                $action,
                                $datetime_action,
                                $encoded_by,
                                $office_to,
                                $office_from,
                                (string) ($remarks ?? '')
                            );
                        }
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
                            error_log('dv_entries upsert after edit failed: ' . $e->getMessage());
                        }
                        voucher_log_user_action(
                            $pdo,
                            $processing_no,
                            $ors_no,
                            $ada_check_no,
                            $dv_no,
                            $payee,
                            $address,
                            $particulars,
                            $tin_employee_no,
                            $amount,
                            $voucher_type,
                            $voucher_date,
                            $action,
                            $action_by,
                            $action_from,
                            $datetime_action,
                            $office_from,
                            $office_to,
                            $encoded_by,
                            $remarks
                        );
                        // Log to audit_logs
                        AuditHelper::logActivity('editing', "Edited voucher: {$processing_no}", [
                            'processing_no' => $processing_no,
                            'dv_no' => $dv_no,
                            'payee' => $payee,
                            'amount' => $amount,
                            'voucher_type' => $voucher_type
                        ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                        echo "<script>process_functionAlert('Edit success!', 'edit_pending_voucher_redirect')</script>";
                        die();
                    }
                }
            } else {
                echo "<script>process_functionAlert('Edit error: Wrong Module Used!', 'edit_pending_voucher_redirect')</script>";
                die();
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        $pdo = null;
        $statement = null;

        die();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('voucher');
    die();
}

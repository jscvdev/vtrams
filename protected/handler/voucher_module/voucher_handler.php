<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher.model.inc.php';
    require_once 'voucher.ctrl.inc.php';

    // Check if token is valid
    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        // Valid token, process the data

        $keyList = array(
            "payee",
            "address",
            "particulars",
            "tin_employee_no",
            "amount",
            "voucher_type",
            "voucher_date",
        );

        $variable_map = array(
            'payee' => 'payee',
            'address' => 'address',
            'particulars' => 'particulars',
            'tin_employee_no' => 'tin_employee_no',
            'amount' => 'amount',
            'voucher_type' => 'voucher_type',
            'voucher_date' => 'voucher_date'

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
        $sent_to = "";
        $dv_no = "TBD";
        $combined_remarks = "";
        $remarks = "";


        try {
            $temp_dump = [];

            try {
                if (isset($_REQUEST['save_document'])) {
                    // TRACKING NO GENERATOR
                    $query = "SELECT count(*) FROM encoded_voucher_no";
                    $statement = $pdo->prepare($query);
                    $statement->execute();
                    $row = $statement->fetchColumn();
                    $total = $row + 1;

                    $totalFormatted = str_pad($total, 4, '0', STR_PAD_LEFT);

                    $processing_no = "PN" . "-" . date("y-m") . "-" . "{$totalFormatted}";

                    // PASSED HIDDEN INPUT
                    $action_from = voucher_post_string($_SESSION['logged_user_section'] ?? '');
                    $encoded_by = voucher_post_string($_SESSION['logged_user_emp_name'] ?? '');

                    date_default_timezone_set('Asia/Singapore'); // SET TIMEZONE TO GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // FORMAT THE CURRENT TIME

                    $datetime_action = $currTime;
                    $action = "Encoded By: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'voucher_type' => $voucher_type,
                        'action' => $action,
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_encoding_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    //CHECK IF DOCUMENT IS ALREADY ENCODED
                    if (check_if_voucher_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher already exists!";
                    }

                    // Determine redirect code
                    $redirect_code = 'voucher_redirect';

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_dv_encode'] = $temp_dump;
                        echo "<script>process_functionAlert('Encode failed!', '$redirect_code')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        insert_voucher_no($pdo, $processing_no);
                        move_to_pending_voucher_no($pdo, $processing_no, $dv_no, $ada_check_no, $payee, $address, $tin_employee_no, $voucher_date, $amount, $voucher_type, $particulars, $datetime_action, $action_from, $encoded_by, $ors_no, $office_from);
                        // COA options are NULL when creating voucher (only added when forwarding)
                        voucher_document_tracking_logging($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $particulars, $amount, $voucher_type, $voucher_date, $datetime_action, $action, $datetime_action, $encoded_by, $office_to, $office_from, $combined_remarks, null, null, null);
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
                        try {
                            $logResult = AuditHelper::logActivity('encoding', "Encoded voucher: {$processing_no}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'amount' => $amount,
                                'voucher_type' => $voucher_type
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            if (!$logResult) {
                                error_log("Failed to log encoding activity for processing_no: {$processing_no}");
                            }
                        } catch (Exception $e) {
                            error_log("Audit logging exception for encoding: " . $e->getMessage());
                        }
                        echo "<script>process_functionAlert('Encode success!', '$redirect_code')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }
                } else {
                    // Determine redirect code
                    $redirect_code = 'voucher_redirect';
                    echo "<script>process_functionAlert('Encode Error: Wrong module used!', '$redirect_code')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
                die();
            }

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            echo $e->getMessage();
            die();
        }
    } else {
        // Invalid token
        $redirect_code = 'voucher_redirect';
        echo "<script>process_functionAlert('Invalid token!', '$redirect_code')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

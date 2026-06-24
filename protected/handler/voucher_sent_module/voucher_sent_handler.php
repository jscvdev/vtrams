<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_sent.model.inc.php';
    require_once 'voucher_sent.ctrl.inc.php';
    require_once __DIR__ . '/../voucher_return_module/voucher_return.model.inc.php';

    // Check if token is valid
    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        // Valid token, process the data

        $keyList = array(
            "processing_no",
            "ors_no",
            "ada_check_no",
            "dv_no",
            "payee",
            "address",
            "particulars",
            "tin_employee_no",
            "amount",
            "voucher_type",
            "voucher_date",
            "office_from",
            "office_to",
            "document_to",
            "sender_udc",
            "receiver_udc",
            "ors_no",
            "ada_check_no",
            "encoded_by",
            "encoded_from",
            "datetime_encoded",
            "process_status",
            "sender_remarks",
            "combined_remarks",
            "remarks",
            "file_path",
        );

        $variable_map = array(
            'processing_no' => 'processing_no',
            'dv_no' => 'dv_no',
            'ors_no' => 'ors_no',
            'ada_check_no' => 'ada_check_no',
            'payee' => 'payee',
            'address' => 'address',
            'particulars' => 'particulars',
            'tin_employee_no' => 'tin_employee_no',
            'amount' => 'amount',
            'voucher_type' => 'voucher_type',
            'voucher_date' => 'voucher_date',
            'office_from' => 'office_from',
            'office_to' => 'office_to',
            'document_to' => 'document_to',
            'sender_udc' => 'sender_udc',
            'receiver_udc' => 'receiver_udc',
            'encoded_by' => 'encoded_by',
            'encoded_from' => 'encoded_from',
            'datetime_encoded' => 'datetime_encoded',
            'process_status' => 'process_status',
            'sender_remarks' => 'sender_remarks',
            'combined_remarks' => 'combined_remarks',
            'remarks' => 'remarks',
            'file_path' => 'file_path',

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

        $logged_user_office = voucher_logged_user_office();
        $action_log_office = $logged_user_office;
        $originOffices = voucher_return_load_origin_offices(
            $pdo,
            $processing_no,
            'sent',
            $office_from,
            $encoded_from
        );
        $office_from = $originOffices['office_from'];
        $encoded_from = $originOffices['encoded_from'];

        $remarks = "";

        try {
            $temp_dump = [];

            // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
            try {
                if (isset($_REQUEST['return_voucher'])) {

                    $action = "Returned by: " . $_SESSION['logged_user_emp_name'];

                    $received_from = $_SESSION['logged_user_section'];
                    $document_received_by =  $_SESSION['logged_user_emp_name'];

                    date_default_timezone_set('Asia/Singapore'); // SET TIMEZONE TO GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // FORMAT THE CURRENT TIME

                    $datetime_action = $currTime;
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];

                    // Self-recall from Sent: no return remarks on the voucher row or tracking.
                    $combined_remarks = '';
                    $sender_remarks = '';
                    $remarks = '';

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_type' => $voucher_type,
                        'voucher_date' => $voucher_date,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'sender_udc' => $sender_udc,
                        'receiver_udc' => $receiver_udc,
                        'encoded_by' => $encoded_by,
                        'encoded_from' => $encoded_from,
                        'datetime_encoded' => $datetime_encoded,
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_sent_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    //CHECK IF DOCUMENT IS ALREADY RECEIVED
                    if (check_if_voucher_returned_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher already returned!";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_sent'] = $temp_dump;
                        echo "<script>process_functionAlert('Return failed!', 'voucher_sent_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        if ($encoded_by == $_SESSION['logged_user_emp_name']) {
                            voucher_sent_return_document(
                                $pdo,
                                $processing_no,
                                $dv_no,
                                $ada_check_no,
                                $payee,
                                $address,
                                $particulars,
                                $tin_employee_no,
                                $amount,
                                $voucher_type,
                                $voucher_date,
                                $encoded_by,
                                $encoded_from,
                                $datetime_encoded,
                                $file_path
                            );
                            voucher_remove_incoming_from_sent($pdo, $processing_no);
                            voucher_remove_from_sent($pdo, $processing_no);
                            voucher_sent_update_document_tracking($pdo, $processing_no, $action, $datetime_action, '', 'no', false);
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
                                $action_log_office,
                                $office_to,
                                $encoded_by,
                                $remarks
                            );
                            // Log to audit_logs
                            $returnedBy = !empty($action_by) ? $action_by : $_SESSION['logged_user_emp_name'] ?? 'Unknown';
                            AuditHelper::logActivity('returning', "Returned voucher: {$processing_no} returned by {$returnedBy}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from,
                                'encoded_from' => $encoded_from,
                                'returned_from_office' => $action_log_office,
                                'office_to' => $office_to,
                                'document_to' => $document_to ?? null,
                                'action_by' => $action_by
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            echo "<script>process_functionAlert('Return success!', 'voucher_sent_redirect')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        } else {
                            // Recall from Sent: route back to the user who forwarded it, not the original recipient.
                            $returner_udc = trim((string) ($_SESSION['logged_user_udc'] ?? ''));
                            $returner_office = trim((string) ($_SESSION['logged_user_office'] ?? ''));
                            if ($returner_udc !== '') {
                                $previous_receiver_udc = trim((string) $receiver_udc);
                                $receiver_udc = $returner_udc;
                                if (
                                    $previous_receiver_udc !== ''
                                    && strcasecmp($previous_receiver_udc, $returner_udc) !== 0
                                ) {
                                    $sender_udc = $previous_receiver_udc;
                                }
                            }
                            if ($returner_office !== '') {
                                $office_to = $returner_office;
                            }

                            voucher_sent_move_to_receiving(
                                $pdo,
                                $ors_no,
                                $ada_check_no,
                                $processing_no,
                                $dv_no,
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
                                $process_status,
                                $combined_remarks,
                                $file_path
                            );
                            voucher_remove_incoming_from_sent($pdo, $processing_no);
                            voucher_remove_from_sent($pdo, $processing_no);
                            voucher_sent_update_document_tracking($pdo, $processing_no, $action, $datetime_action, '', 'returned', false);
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
                                $action_log_office,
                                $office_to,
                                $encoded_by,
                                $remarks
                            );
                            // Log to audit_logs
                            $returnedBy = !empty($action_by) ? $action_by : $_SESSION['logged_user_emp_name'] ?? 'Unknown';
                            AuditHelper::logActivity('returning', "Returned voucher: {$processing_no} returned by {$returnedBy}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from,
                                'encoded_from' => $encoded_from,
                                'returned_from_office' => $action_log_office,
                                'office_to' => $office_to,
                                'document_to' => $document_to ?? null,
                                'action_by' => $action_by
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            echo "<script>process_functionAlert('Return success!', 'voucher_sent_redirect')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        }
                    }
                } else {
                    echo "<script>process_functionAlert('Return: Wrong module used!', 'voucher_sent_redirect')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
            }

            $pdo = null;
            $statement = null;

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    } else {
        // Invalid token
        echo "<script>process_functionAlert('Invalid token!', 'voucher_sent_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_incoming.model.inc.php';
    require_once 'voucher_incoming.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    /** @var PDO $pdo */

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
            "sender_udc",
            "receiver_udc",
            "encoded_by",
            "encoded_from",
            "datetime_encoded",
            "forwarded_by",
            "process_status",
            "combined_remarks",
            "sender_remarks",
            "process_history",
            "selected_coa_options",
            "coa_category",
            "coa_subsection"
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
            'sender_udc' => 'sender_udc',
            'receiver_udc' => 'receiver_udc',
            'encoded_by' => 'encoded_by',
            'encoded_from' => 'encoded_from',
            'datetime_encoded' => 'datetime_encoded',
            'forwarded_by' => 'forwarded_by',
            'process_status' => 'process_status',
            'combined_remarks' => 'combined_remarks',
            'sender_remarks' => 'sender_remarks',
            'process_history' => 'process_history',
            'selected_coa_options' => 'coa_options',
            'coa_category' => 'coa_category',
            'coa_subsection' => 'coa_subsection'
            // Add more mappings as needed
        );

        //LOOP METHOD
        foreach ($keyList as $key) {
            $variable_name = $variable_map[$key];
            if (isset($_POST[$key])) {
                // IMPORTANT: do NOT HTML-escape JSON payloads (it corrupts stored JSON)
                if ($key === 'selected_coa_options') {
                    $$variable_name = trim((string)$_POST[$key]);
                } else {
                    $$variable_name = voucher_post_string($_POST[$key]);
                }
            } else {
                $$variable_name = "";
            }
        }

        voucher_apply_exact_amount($amount);

        $target = array_map('trim', explode(',', (string) ($_SESSION['logged_user_designation'] ?? '')));

        if (isset($_SESSION["logged_user_udc"])) {
            $receiver_udc = $_SESSION["logged_user_udc"];
        }

        $remarks = "";

        try {
            $temp_dump = [];

            // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
            try {
                if (isset($_REQUEST['receive_voucher'])) {

                    date_default_timezone_set('Asia/Singapore'); // SET TIMEZONE TO GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // FORMAT THE CURRENT TIME

                    $datetime_action = $currTime;
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action = "Received by: " . $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];
                    $received_from = $_SESSION['logged_user_section'];
                    $status = "";

                    if (!empty($remarks)) {
                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    }

                    if (in_array("Planning Section", $target)) {
                        $status = "For Charging";
                    } elseif (in_array("Budget Unit", $target)) {
                        $status = "Verifying Availability of Fund and Allotment";
                    } elseif (
                        in_array('Accounting Unit', $target, true)
                        || in_array('Processor', $target, true)
                        || in_array('Accountant III', $target, true)
                    ) {
                        $status = 'Processing the Disbursement Voucher';
                    } elseif (in_array("Office of the PENRO", $target)) {
                        $status = "For Approval of the PENRO";
                    } elseif (in_array("Cashiers Unit", $target)) {
                        $status = "For Preparation of Check, ACIC or LDDAP-ADA";
                    }

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
                        'datetime_encoded' => $datetime_encoded
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_incoming_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $isAccountingReceive = in_array('Accounting Unit', $target, true)
                        || in_array('Processor', $target, true)
                        || in_array('Accountant III', $target, true);
                    if ($isAccountingReceive) {
                        $processHistoryForDv = voucher_tracking_normalize_process_history($process_history);
                        if ($processHistoryForDv === '' && trim($processing_no) !== '') {
                            $histStmt = $pdo->prepare(
                                'SELECT process_history FROM voucher_incoming WHERE processing_no = :processing_no LIMIT 1'
                            );
                            $histStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
                            $histStmt->execute();
                            $histRow = $histStmt->fetch(PDO::FETCH_ASSOC);
                            if (is_array($histRow)) {
                                $processHistoryForDv = voucher_tracking_normalize_process_history(
                                    (string) ($histRow['process_history'] ?? '')
                                );
                            }
                        }

                        if (
                            voucher_incoming_requires_dv_no(
                                $voucher_type,
                                $processHistoryForDv,
                                voucher_logged_user_office()
                            )
                        ) {
                            $dvTrim = trim((string) $dv_no);
                            if ($dvTrim === '' || strcasecmp($dvTrim, 'TBD') === 0) {
                                $temp_dump['invalid_dv'] = "Please enter a valid DV No. before receiving. Empty or 'TBD' is not allowed.";
                            }
                        }
                    }

                    //CHECK IF DOCUMENT IS ALREADY RECEIVED
                    if (check_if_voucher_received($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher is already received!";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_incoming'] = $temp_dump;
                        echo "<script>process_functionAlert('Receive failed!', 'voucher_incoming_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        $coa_options = null;
                        $coa_category = null;
                        $coa_subsection = null;

                        if (isset($_POST['selected_coa_options']) && !empty($_POST['selected_coa_options'])) {
                            $coa_options = $_POST['selected_coa_options'];
                            $coa_category = isset($_POST['coa_category']) ? $_POST['coa_category'] : null;
                            $coa_subsection = isset($_POST['coa_subsection']) ? $_POST['coa_subsection'] : null;
                        } else {
                            $fetch_coa_query = 'SELECT coa_options, coa_category, coa_subsection FROM voucher_incoming WHERE processing_no = :processing_no';
                            $fetch_coa_stmt = $pdo->prepare($fetch_coa_query);
                            $fetch_coa_stmt->bindParam(':processing_no', $processing_no);
                            $fetch_coa_stmt->execute();
                            $coa_row = $fetch_coa_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($coa_row) {
                                if (!empty($coa_row['coa_options'])) {
                                    $coa_options = $coa_row['coa_options'];
                                }
                                if (!empty($coa_row['coa_category'])) {
                                    $coa_category = $coa_row['coa_category'];
                                }
                                if (!empty($coa_row['coa_subsection'])) {
                                    $coa_subsection = $coa_row['coa_subsection'];
                                }
                            }
                        }

                        handler_execute_writes(
                            $pdo,
                            function (PDO $pdo) use (
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
                                $sender_remarks,
                                $action,
                                $status,
                                $action_by,
                                $action_from,
                                $remarks,
                                $coa_options,
                                $coa_category,
                                $coa_subsection
                            ) {
                                voucher_move_to_receiving(
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
                            $sender_remarks,
                            "",
                            $coa_options,
                            $coa_category,
                            $coa_subsection
                        );
                        update_incoming_forwarded_voucher($pdo, $processing_no, $action, $datetime_action, $status);
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
                                remove_from_voucher_incoming($pdo, $processing_no);
                                remove_from_voucher_sent($pdo, $processing_no);

                                return true;
                            },
                            'Receive success!',
                            'Receive failed!',
                            'voucher_incoming_redirect',
                            function () use ($processing_no, $dv_no, $payee, $office_from, $office_to, $forwarded_by) {
                                $forwardedBy = !empty($forwarded_by) ? $forwarded_by : 'Unknown';
                                AuditHelper::logActivity('receiving', "Received voucher: {$processing_no} forwarded by {$forwardedBy}", [
                                    'processing_no' => $processing_no,
                                    'dv_no' => $dv_no,
                                    'payee' => $payee,
                                    'office_from' => $office_from,
                                    'office_to' => $office_to,
                                    'forwarded_by' => $forwarded_by
                                ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            }
                        );
                    }
                } else {
                    echo "<script>process_functionAlert('Forward Error: Wrong module used!', 'voucher_incoming_redirect')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
                // die();
            }

            $pdo = null;
            $statement = null;

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            echo $e->getMessage();
            // die();
        }
    } else {
        // Invalid token
        echo "<script>process_functionAlert('Invalid token!', 'voucher_incoming_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_forward.model.inc.php';
    require_once 'voucher_forward.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    /** @var PDO $pdo */

    // Check if token is valid
    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        // Valid token, process the data

        $keyList = array(
            "processing_no",
            "encoded_payee",
            "encoded_address",
            "encoded_particulars",
            "encoded_tin_employee_no",
            "encoded_amount",
            "encoded_type",
            "encoded_voucher_date",
            "encoded_by",
            "datetime_encoded",
            "encoded_from",
            "combined_remarks",
            "remarks",
            "selected_coa_options_forward",
            "coa_category_forward",
            "coa_subsection_forward",
            "forward_return_designation",
        );

        $variable_map = array(
            'processing_no' => 'processing_no',
            'encoded_payee' => 'payee',
            'encoded_address' => 'address',
            'encoded_particulars' => 'particulars',
            'encoded_tin_employee_no' => 'tin_employee_no',
            'encoded_amount' => 'amount',
            'encoded_type' => 'voucher_type',
            'encoded_voucher_date' => 'voucher_date',
            'encoded_by' => 'encoded_by',
            'datetime_encoded' => 'datetime_encoded',
            'encoded_from' => 'encoded_from',
            'combined_remarks' => 'combined_remarks',
            'remarks' => 'remarks',
            'selected_coa_options_forward' => 'coa_options',
            'coa_category_forward' => 'coa_category',
            'coa_subsection_forward' => 'coa_subsection',
            'forward_return_designation' => 'forward_return_designation',

            // Add more mappings as needed
        );

        //LOOP METHOD
        foreach ($keyList as $key) {
            $variable_name = $variable_map[$key];
            if (isset($_POST[$key])) {
                // IMPORTANT: do NOT HTML-escape JSON payloads (it corrupts stored JSON)
                if ($key === 'selected_coa_options_forward') {
                    $$variable_name = trim((string)$_POST[$key]);
                } else {
                    $$variable_name = htmlspecialchars($_POST[$key]);
                }
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

        $dv_no = "";
        $ors_no = "TBD";
        $ada_check_no = "TBD";
        $process_status = "N/A";
        $receiver_udc = "";
        $forward_return_designation = isset($forward_return_designation) ? trim((string) $forward_return_designation) : '';
        if (empty($combined_remarks)) {
            $combined_remarks = "";
        }
        $target = explode(",", $_SESSION['logged_user_designation']);

        try {
            $temp_dump = [];
            try {
                if (isset($_REQUEST['forward_voucher'])) {
                    $query2 = "SELECT * FROM designation_limit";
                    $statement2 = $pdo->prepare($query2);
                    $statement2->execute();

                    $office_from = $_SESSION['logged_user_office'];
                    $office_to = $_SESSION['logged_user_office'];
                    $forwarded_by = $_SESSION['logged_user_emp_name'];
                    $action_from = htmlspecialchars($_SESSION['logged_user_section']);

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    } else {
                        $combined_remarks = "N/A";
                    }

                    $forwarded_to = '';
                    if ($forward_return_designation !== '') {
                        // Re-forward returned voucher to the office/designation of whoever returned it.
                        $resolved = voucher_forward_receiver_udcs_for_designation(
                            $pdo,
                            $forward_return_designation,
                            $office_to
                        );
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    } elseif ($_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                        if ($office_from === "DENR-PENRO EASTERN SAMAR") {
                            $target_to = "Planning Section";
                        } else {
                            $target_to = "ICU";
                        }
                        $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $target_to, $office_to);
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    } elseif (in_array("Liaison Officer", $target)) {
                        $target_to = "ICU";
                        $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $target_to, $office_to);
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    }

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;

                    $action = "Forwarded by: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $sender_udc = $_SESSION['logged_user_udc'];

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_type' => $voucher_type,
                        'voucher_date' => $voucher_date,
                        'datetime_encoded' => $datetime_encoded,
                    ];

                    // Require slip_printed_flag to be set (defense-in-depth against bypassing the JS check)
                    $slip_printed_flag = isset($_POST['slip_printed_flag']) ? $_POST['slip_printed_flag'] : '0';
                    if ($slip_printed_flag !== '1') {
                        $temp_dump['slip_not_printed'] = 'Please print the slip before forwarding.';
                    }

                    // COA selections UI was removed; only the confirmed checklist (JSON) is required.
                    // Validate that user clicked CONFIRM and selected ALL requirements.
                    $coa_options_raw = isset($_POST['selected_coa_options_forward']) ? trim((string)$_POST['selected_coa_options_forward']) : '';
                    if ($coa_options_raw === '') {
                        $temp_dump['coa_not_confirmed'] = 'Please confirm the checklist requirements before forwarding.';
                    } else {
                        $decoded = json_decode($coa_options_raw, true);
                        if (!is_array($decoded) || count($decoded) < 1) {
                            $temp_dump['coa_invalid'] = 'Invalid checklist confirmation data. Please confirm again.';
                        }
                    }

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_forward_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    // Determine redirect code
                    $redirect_code = 'voucher_pending_forward_redirect';

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_forward'] = $temp_dump;
                        echo "<script>process_functionAlert('Forward failed!', '$redirect_code')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        if (check_if_voucher_forwarded_exists($pdo, $processing_no)) {
                            voucher_forward_pending($pdo, $processing_no);
                            
                            // Get confirmed checklist JSON from POST (store raw JSON)
                            $coa_options = isset($_POST['selected_coa_options_forward']) ? trim((string)$_POST['selected_coa_options_forward']) : null;
                            // COA picker was removed; keep DB columns populated for compatibility
                            $coa_category = isset($_POST['coa_category_forward']) && trim((string)$_POST['coa_category_forward']) !== ''
                                ? trim((string)$_POST['coa_category_forward'])
                                : $voucher_type;
                            $coa_subsection = isset($_POST['coa_subsection_forward']) && trim((string)$_POST['coa_subsection_forward']) !== ''
                                ? trim((string)$_POST['coa_subsection_forward'])
                                : $voucher_type;
                            
                            voucher_move_to_incoming(
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
                                $coa_options,
                                $coa_category,
                                $coa_subsection
                            );
                            voucher_move_to_sent(
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
                                $coa_options,
                                $coa_category,
                                $coa_subsection
                            );
                            // active_status=yes: include in dashboard and voucher status counts
                            update_returned_forwarded_voucher($pdo, $processing_no, $action, $datetime_action, $combined_remarks);
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
                            $destination = !empty($forwarded_to) ? $forwarded_to : (!empty($target_to) ? $target_to : $office_to);
                            AuditHelper::logActivity('forwarding', "Forwarded voucher: {$processing_no} to {$destination}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from,
                                'office_to' => $office_to,
                                'document_to' => $destination
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            echo "<script>process_functionAlert('Forward success!', '$redirect_code')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        }
                    }
                } else {
                    // Determine redirect code
                    $redirect_code = 'voucher_pending_forward_redirect';
                    echo "<script>process_functionAlert('Forward Error: Wrong module used!', '$redirect_code')</script>";
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
        $redirect_code = 'voucher_pending_forward_redirect';
        echo "<script>process_functionAlert('Invalid token!', '$redirect_code')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

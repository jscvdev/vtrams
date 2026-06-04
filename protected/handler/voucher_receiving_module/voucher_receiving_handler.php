<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_receiving.model.inc.php';
    require_once 'voucher_receiving.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';

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
            "document_to",
            "encoded_by",
            "encoded_from",
            "datetime_encoded",
            "process_status",
            "remarks",
            "combined_remarks"
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
            'document_to' => 'document_to',
            'encoded_by' => 'encoded_by',
            'encoded_from' => 'encoded_from',
            'datetime_encoded' => 'datetime_encoded',
            'process_status' => 'process_status',
            'remarks' => 'remarks',
            'combined_remarks' => 'combined_remarks'
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

        $forwarded_by  = $_SESSION['logged_user_emp_name']; // FORWARDED BY -> CURRENT LOGGED USER

        if (isset($_SESSION['logged_user_office'])) {
            $office_to = $_SESSION['logged_user_office'];
        } else {
            $office_to = '';
        }

        $udc = "";
        $receiver_udc = "";

        try {
            $temp_dump = [];

            // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
            try {
                if (isset($_REQUEST['edit_voucher_amount'])) {
                    // Simple edit of amount only, no forwarding/transmitting logic
                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'amount' => $amount,
                    ];

                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Edit amount failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // Preserve original amount; store edit in charged_amount and sync voucher_tracking.
                        $amountUpdate = update_voucher_amount($pdo, $processing_no, $amount);
                        $logAmount = $amountUpdate['effective_amount'] ?? $amount;

                        date_default_timezone_set('Asia/Singapore');
                        $datetime_action = date('Y-m-d H:i:s');
                        $action = 'Amount edited by: ' . ($_SESSION['logged_user_emp_name'] ?? '');
                        $action_by = $_SESSION['logged_user_emp_name'] ?? '';
                        $action_from = $_SESSION['logged_user_section'] ?? '';
                        $log_office_to = $office_to !== '' ? $office_to : ($_SESSION['logged_user_office'] ?? '');

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
                            $logAmount,
                            $voucher_type,
                            $voucher_date,
                            $action,
                            $action_by,
                            $action_from,
                            $datetime_action,
                            $office_from,
                            $log_office_to,
                            $encoded_by,
                            $remarks
                        );

                        AuditHelper::logActivity('editing', "Edited voucher amount: {$processing_no}", [
                            'processing_no' => $processing_no,
                            'dv_no' => $dv_no,
                            'payee' => $payee,
                            'amount' => $logAmount,
                        ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);

                        echo "<script>process_functionAlert('Amount updated successfully!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }
                } elseif (isset($_REQUEST['forward_voucher'])) {
                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;

                    $action = "Forwarded by: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];
                    $sender_udc = $_SESSION['logged_user_udc'];

                    //REMARKS
                    if (!empty($remarks)) {
                        if ($combined_remarks != "N/A") {
                            $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                            $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        } else {
                            $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                            $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        }
                    }

                    $receiver_udc = voucher_resolve_receiver_udc_for_destination($pdo, $document_to, $office_to);
                    if ($receiver_udc === '') {
                        $temp_dump['unassigned_udc'] = 'No user is assigned to accept';
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
                        'document_to' => $document_to,
                        'encoded_by' => $encoded_by,
                        'forwarded_by' => $forwarded_by,
                        'datetime_encoded' => $datetime_encoded
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

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
                    if (receiving_check_if_forwarded_voucher_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher is already forwarded!";
                    }

                    $sender_udc = $_SESSION['logged_user_udc'];

                    $x = explode(',', $_SESSION['logged_user_designation']);

                    if (in_array($document_to, $x)) {
                        $temp_dump["self_send"] = "Cannot forward this document to yourself";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Forward failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();;
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        // 1) Load COA data from voucher_receiving (if any), then move to incoming and sent
                        $coa_options = null;
                        $coa_category = null;
                        $coa_subsection = null;

                        $fetch_coa_query = "SELECT coa_options, coa_category, coa_subsection FROM voucher_receiving WHERE processing_no = :processing_no";
                        $fetch_coa_stmt = $pdo->prepare($fetch_coa_query);
                        $fetch_coa_stmt->bindParam(":processing_no", $processing_no);
                        $fetch_coa_stmt->execute();
                        $coa_row = $fetch_coa_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($coa_row) {
                            $coa_options = $coa_row['coa_options'] ?? null;
                            $coa_category = $coa_row['coa_category'] ?? null;
                            $coa_subsection = $coa_row['coa_subsection'] ?? null;
                        }

                        // Forward form carries confirmed checklist JSON in hidden fields (same as first forward from voucher.php).
                        // Prefer POST when set so multi-hop forwards do not lose COA when receiving row columns are empty.
                        $post_coa_forward = isset($_POST['selected_coa_options_forward']) ? trim((string)$_POST['selected_coa_options_forward']) : '';
                        if ($post_coa_forward !== '') {
                            $coa_options = $post_coa_forward;
                        }
                        $post_cat_forward = isset($_POST['coa_category_forward']) ? trim((string)$_POST['coa_category_forward']) : '';
                        if ($post_cat_forward !== '') {
                            $coa_category = $post_cat_forward;
                        }
                        $post_sub_forward = isset($_POST['coa_subsection_forward']) ? trim((string)$_POST['coa_subsection_forward']) : '';
                        if ($post_sub_forward !== '') {
                            $coa_subsection = $post_sub_forward;
                        }

                        // 2) Move to incoming and sent (so we can still read any original/charged amounts)
                        voucher_receiving_move_to_incoming(
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
                        voucher_receiving_move_to_sent(
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
                        // 2) Then delete from receiving
                        voucher_forward_receiving($pdo, $processing_no);
                        update_forwarded_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);
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
                        $destination = !empty($document_to) ? $document_to : $office_to;
                        AuditHelper::logActivity('forwarding', "Forwarded voucher: {$processing_no} to {$destination}", [
                            'processing_no' => $processing_no,
                            'dv_no' => $dv_no,
                            'payee' => $payee,
                            'office_from' => $office_from,
                            'office_to' => $office_to,
                            'document_to' => $document_to
                        ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                        echo "<script>process_functionAlert('Forward success!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();;
                    }
                } elseif (isset($_REQUEST['transmit_voucher'])) {
                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;

                    $target = array_map('trim', explode(",", $_SESSION['logged_user_designation']));
                    if (in_array("Cashiers Unit", $target, true) || in_array("Cashier", $target, true)) {
                        echo "<script>process_functionAlert('Transmit is not available for Cashiers. Use Archive instead.', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }

                    $action_from = $_SESSION['logged_user_section'];

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    }

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_date' => $voucher_date,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'encoded_by' => $encoded_by,
                        'encoded_from' => $encoded_from,
                        'datetime_encoded' => $datetime_encoded,
                        'forwarded_by' => $forwarded_by
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $x = explode(',', $_SESSION['logged_user_designation']);

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Transmit failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();;
                    } else {
                        try {

                            $selectedTransmitConfig = find_voucher_action_config($target, get_voucher_transmit_configurations());

                            if ($selectedTransmitConfig !== null) {
                                $receiverName = get_voucher_receiver_name_by_role(
                                    $pdo,
                                    $selectedTransmitConfig['receiver_role'],
                                    $office_to
                                );
                                if ($receiverName === '') {
                                    $message = "Transmit failed: no employee is assigned to " . $selectedTransmitConfig['receiver_role'] . ".";
                                    echo "<script>
                                        if (typeof showNotify === 'function') {
                                            showNotify(" . json_encode($message) . ", 'error', 3200);
                                            setTimeout(function () {
                                                if (window.redirectMap && window.redirectMap['voucher_receiving_redirect']) {
                                                    window.location.href = window.redirectMap['voucher_receiving_redirect'];
                                                } else {
                                                    window.location.reload();
                                                }
                                            }, 900);
                                        } else {
                                            process_functionAlert(" . json_encode($message) . ", 'voucher_receiving_redirect');
                                        }
                                    </script>";
                                    $_SESSION['token'] = generateToken();
                                    die();
                                }

                                $action = "Received By: " . $receiverName;

                                if ($selectedTransmitConfig['update_document']) {
                                    update_voucher_document($pdo, $ors_no, $processing_no);
                                }

                                // charged_amount should only be written via explicit Edit Amount flow.

                                update_forwarded_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);

                                $action = "Forwarded By: " . $_SESSION['logged_user_emp_name'];
                                $action_by  = $_SESSION['logged_user_emp_name'];
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

                                $action = "Received By: " . $receiverName;
                                $action_by = $receiverName;
                                $remarks = "";
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
                                AuditHelper::logActivity('transmitting', "Transmitted voucher: {$processing_no}", [
                                    'processing_no' => $processing_no,
                                    'dv_no' => $dv_no,
                                    'payee' => $payee,
                                    'office_from' => $office_from
                                ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                                $transmit_status = "Yes";
                                set_voucher_transmit($pdo, $processing_no, $transmit_status);
                                echo "<script>process_functionAlert('Transmit success!', 'voucher_receiving_redirect')</script>";
                                $_SESSION['token'] = generateToken();
                                die();;
                            }
                        } catch (PDOException $e) {
                            echo $e;
                        }
                    }
                } elseif (isset($_REQUEST['re_transmit_voucher'])) {

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;
                    $action = "Received By: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];

                    $target = array_map('trim', explode(",", $_SESSION['logged_user_designation']));
                    if (in_array("Cashiers Unit", $target, true) || in_array("Cashier", $target, true)) {
                        echo "<script>process_functionAlert('Transmit is not available for Cashiers. Use Archive instead.', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }

                    // Require valid ORS for budget roles when retransmitting.
                    if (in_array("Budget Unit", $target, true) || in_array("Budget Officer", $target, true)) {
                        $orsValue = trim((string)$ors_no);
                        if ($orsValue === "" || strtoupper($orsValue) === "TBD") {
                            $temp_dump['invalid_ors_no'] = "Please enter a valid ORS No. before retransmitting. Empty or 'TBD' is not allowed.";
                        }
                    }

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    }

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_date' => $voucher_date,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'encoded_by' => $encoded_by,
                        'forwarded_by' => $forwarded_by
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

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
                    if (receiving_check_if_forwarded_voucher_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher is already forwarded!";
                    }

                    $x = explode(',', $_SESSION['logged_user_designation']);

                    if (in_array($document_to, $x)) {
                        $temp_dump["self_send"] = "Cannot forward this document to yourself";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Transmit failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();;
                    } else {
                        $selectedReTransmitConfig = find_voucher_action_config($target, get_voucher_retransmit_configurations());

                        if ($selectedReTransmitConfig !== null) {
                            $forwarderName = get_voucher_receiver_name_by_role(
                                $pdo,
                                $selectedReTransmitConfig['forwarder_role'],
                                $office_to
                            );
                            if ($forwarderName === '') {
                                $message = "Transmit failed: no employee is assigned to " . $selectedReTransmitConfig['forwarder_role'] . ".";
                                echo "<script>
                                    if (typeof showNotify === 'function') {
                                        showNotify(" . json_encode($message) . ", 'error', 3200);
                                        setTimeout(function () {
                                            if (window.redirectMap && window.redirectMap['voucher_receiving_redirect']) {
                                                window.location.href = window.redirectMap['voucher_receiving_redirect'];
                                            } else {
                                                window.location.reload();
                                            }
                                        }, 900);
                                    } else {
                                        process_functionAlert(" . json_encode($message) . ", 'voucher_receiving_redirect');
                                    }
                                </script>";
                                $_SESSION['token'] = generateToken();
                                die();
                            }

                            // charged_amount should only be written via explicit Edit Amount flow.

                            // Persist editable ORS input for budget roles during retransmit.
                            if (in_array("Budget Unit", $target, true) || in_array("Budget Officer", $target, true)) {
                                $orsValueToSave = trim((string)$ors_no);
                                if ($orsValueToSave !== "" && strtoupper($orsValueToSave) !== "TBD") {
                                    update_voucher_document($pdo, $orsValueToSave, $processing_no);
                                }
                            }

                            update_forwarded_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);

                            $action = "Forwarded By: " . $forwarderName;
                            $action_by  = $forwarderName;
                            $remarks = "";
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

                            $action = "Received By: " . $_SESSION['logged_user_emp_name'];
                            $action_by  = $_SESSION['logged_user_emp_name'];
                            $remarks = "";
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
                            AuditHelper::logActivity('transmitting', "Transmitted voucher: {$processing_no}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            $transmit_status = "Done";
                            set_voucher_transmit($pdo, $processing_no, $transmit_status);
                            echo "<script>process_functionAlert('Transmit success!', 'voucher_receiving_redirect')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        }
                    }
                } elseif (isset($_REQUEST['process_voucher'])) {

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;
                    $action = "Prepared By: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];

                    $target = explode(",", $_SESSION['logged_user_designation']);

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    }

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_date' => $voucher_date,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'encoded_by' => $encoded_by,
                        'forwarded_by' => $forwarded_by
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $x = explode(',', $_SESSION['logged_user_designation']);

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Process failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        if (in_array("Accounting Unit", $target) or in_array("Processor", $target)) {
                            update_voucher_dv_no($pdo, $dv_no, $processing_no);
                            update_forwarded_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);
                            $action = "Prepared By: " . $_SESSION['logged_user_emp_name'];
                            $action_by  = $_SESSION['logged_user_emp_name'];
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
                            AuditHelper::logActivity('processing', "Processed voucher: {$processing_no}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            $process_status = "Processing";
                            set_voucher_process_status($pdo, $processing_no, $process_status);
                            $_SESSION['token'] = generateToken();
                            echo "<script>process_functionAlert('Process success!', 'voucher_receiving_redirect')</script>";
                            die();
                        }
                    }
                } elseif (isset($_REQUEST['confirm_process_voucher'])) {

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;
                    $action = "Processed By: " . $_SESSION['logged_user_emp_name'];
                    $action_by  = $_SESSION['logged_user_emp_name'];
                    $action_from = $_SESSION['logged_user_section'];

                    $target = explode(",", $_SESSION['logged_user_designation']);

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    }

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'payee' => $payee,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_date' => $voucher_date,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'encoded_by' => $encoded_by,
                        'forwarded_by' => $forwarded_by
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_receiving_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $x = explode(',', $_SESSION['logged_user_designation']);

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_receiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Process failed!', 'voucher_receiving_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        if (in_array("Accounting Unit", $target) or in_array("Processor", $target)) {
                            update_voucher_dv_no($pdo, $dv_no, $processing_no);
                            update_forwarded_received_voucher($pdo, $processing_no, $dv_no, $ors_no, $ada_check_no, $action, $datetime_action, $combined_remarks);
                            $action = "Processed By: " . $_SESSION['logged_user_emp_name'];
                            $action_by  = $_SESSION['logged_user_emp_name'];
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
                            AuditHelper::logActivity('processing', "Processed voucher: {$processing_no}", [
                                'processing_no' => $processing_no,
                                'dv_no' => $dv_no,
                                'payee' => $payee,
                                'office_from' => $office_from
                            ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            $process_status = "Processed";
                            set_voucher_process_status($pdo, $processing_no, $process_status);
                            echo "<script>process_functionAlert('Process success!', 'voucher_receiving_redirect')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        }
                    }
                } else {
                    echo "<script>process_functionAlert('Process Error: Wrong module used!', 'voucher_receiving_redirect')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo "
                    console.error('Database Error: {$e->getMessage()}');
                    <script>process_functionAlert('Process Error: {$e->getMessage()}', 'voucher_receiving_redirect')</script>";
            }

            $pdo = null;
            $statement = null;

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            echo "<script>process_functionAlert('Transmit Error: ..!', 'voucher_receiving_redirect')</script>";
        }
    } else {
        // Invalid token
        echo "<script>process_functionAlert('Invalid token!', 'voucher_receiving_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

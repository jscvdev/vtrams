<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_return.model.inc.php';
    require_once 'voucher_return.ctrl.inc.php';

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
            "encoded_by",
            "encoded_from",
            "datetime_encoded",
            "forwarded_by",
            "process_status",
            "sender_remarks",
            "combined_remarks",
            "remarks",
            "return_destination"
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
            'forwarded_by' => 'forwarded_by',
            'process_status' => 'process_status',
            'sender_remarks' => 'sender_remarks',
            'combined_remarks' => 'combined_remarks',
            'remarks' => 'remarks',
            'return_destination' => 'return_destination',
            // Add more mappings as needed
        );

        //LOOP METHOD
        foreach ($keyList as $key) {
            $variable_name = $variable_map[$key];
            if (isset($_POST[$key])) {
                $$variable_name = htmlspecialchars($_POST[$key]);
            } else {
                $$variable_name = "";
            }
        }

        // Normalize remarks: treat explicit "NULL" or empty string as true NULL
        if (isset($remarks)) {
            $trimmedRemarks = trim($remarks);
            if ($trimmedRemarks === '' || strcasecmp($trimmedRemarks, 'NULL') === 0) {
                $remarks = null;
            }
        }

        voucher_apply_exact_amount($amount);

        if (isset($_SESSION['logged_user_office'])) {
            $office_from = $_SESSION['logged_user_office'];
        } else {
            $office_from = '';
        }

        if (empty($remarks)) {
            $remarks = "";
        }

        if (!empty($return_destination) && $return_destination === 'encoder') {
            $office_to = isset($encoded_from) ? $encoded_from : '';
            $receiver_udc = '';
            if (!empty($encoded_by)) {
                $enc_stmt = $pdo->prepare("SELECT udc FROM user_group WHERE TRIM(CONCAT(COALESCE(emp_fn,''), ' ', COALESCE(emp_mi,''), ' ', COALESCE(emp_ln,''))) = :encoded_by LIMIT 1");
                $enc_stmt->bindParam(":encoded_by", $encoded_by);
                $enc_stmt->execute();
                $enc_row = $enc_stmt->fetch(PDO::FETCH_ASSOC);
                if ($enc_row && !empty($enc_row['udc'])) {
                    $receiver_udc = $enc_row['udc'];
                }
            }
        }

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

                    if (!empty($combined_remarks) && $combined_remarks != "N/A") {
                        // Prepare the text to remove, handling the optional comma
                        $remove_text = trim($sender_remarks);
                        if (!empty($remove_text)) {
                            // Create a pattern to match with or without a preceding comma
                            $pattern = '/(?:, )?' . preg_quote($remove_text, '/') . '/';

                            // Remove the text from $combined_remarks
                            $combined_remarks = preg_replace($pattern, '', $combined_remarks);

                            // Add the new remark only if the $combined_remarks is not empty
                            if (!empty($combined_remarks)) {
                                $combined_remarks .= ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                            } else {
                                // If $combined_remarks is empty after removal, just set it
                                $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                            }
                        }
                    } else {
                        $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
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
                        'datetime_encoded' => $datetime_encoded,
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = voucher_incoming_return_required_data_empty($variables_to_check);

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
                    if (voucher_incoming_check_if_returned_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher is already returned!";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_return'] = $temp_dump;
                        echo "<script>process_functionAlert('Return failed!', 'voucher_incoming_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        $target = explode(",", $_SESSION['logged_user_designation']);
                        if (in_array("Accounting Unit", $target)) {
                            $query2 = "SELECT * FROM user_group";
                            $statement2 = $pdo->prepare($query2);
                            $statement2->execute();


                            if ($_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                while ($row2 = $statement2->fetch(PDO::FETCH_ASSOC)) {
                                    $formattedResultName = explode(" ", $row2['emp_fn'] . " " . $row2['emp_mi'] . " " . $row2['emp_ln']);
                                    $formattedResultName2  = implode(" ", $formattedResultName);
                                    $full_name = $formattedResultName2;

                                    if ($full_name === $payee) {
                                        if (!empty($row2['udc'])) {
                                            $sender_udc = $row2['udc'];
                                        } else {
                                            $temp_dump['unassigned_udc'] = "No user is assigned to accept";
                                            $sender_udc = "failed";
                                        }
                                    }
                                }
                            }
                        }

                        // If returning to encoder, move back to vouchers table (pending);
                        // otherwise, move to voucher_receiving as before.
                        if (!empty($return_destination) && $return_destination === 'encoder') {
                            voucher_incoming_sent_return_document(
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
                                $datetime_encoded
                            );
                        } else {
                            voucher_incoming_sent_move_to_receiving(
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
                                $receiver_udc,
                                $sender_udc,
                                $office_from,
                                $office_to,
                                $encoded_by,
                                $encoded_from,
                                $datetime_encoded,
                                $process_status,
                                $combined_remarks
                            );
                        }
                        incoming_voucher_remove_incoming_from_sent($pdo, $processing_no);
                        incoming_voucher_remove_from_sent($pdo, $processing_no);
                        voucher_incoming_return_update_document_tracking($pdo, $processing_no, $action, $datetime_action, $combined_remarks);
                        if (!empty($remarks)) {
                            // Persist the accumulated remarks history so it remains visible
                            // when the voucher is forwarded again.
                            voucher_update_return_remarks($pdo, $processing_no, $combined_remarks);
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
                            $combined_remarks
                        );
                        // Log to audit_logs
                        $returnedBy = !empty($action_by) ? $action_by : $_SESSION['logged_user_emp_name'] ?? 'Unknown';
                        AuditHelper::logActivity('returning', "Returned voucher: {$processing_no} returned by {$returnedBy}", [
                            'processing_no' => $processing_no,
                            'dv_no' => $dv_no,
                            'payee' => $payee,
                            'office_from' => $office_from,
                            'office_to' => $office_to,
                            'document_to' => $document_to ?? null,
                            'action_by' => $action_by
                        ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                        echo "<script>process_functionAlert('Return success!', 'voucher_incoming_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }
                } else {
                    echo "<script>process_functionAlert('Forward Error: Wrong module used!', 'voucher_incoming_redirect')</script>";
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
        echo "<script>process_functionAlert('Invalid token!', 'voucher_incoming_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

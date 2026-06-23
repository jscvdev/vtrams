<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_return.model.inc.php';
    require_once 'voucher_return.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/utilities_return_previous_helper.inc.php';

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
            "return_destination",
            "return_target_section",
            "return_source"
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
            'return_target_section' => 'return_target_section',
            'return_source' => 'return_source',
        );

        $return_source = isset($_POST['return_source']) ? voucher_post_string($_POST['return_source']) : 'incoming';
        $success_redirect = $return_source === 'forwarding' ? 'voucher_forwarding_return_redirect' : 'voucher_incoming_redirect';
        $_SESSION['voucher_return_redirect_key'] = $return_source === 'forwarding'
            ? 'voucher_forwarding_return_err_redirect'
            : 'voucher_incoming_return_err_redirect';

        //LOOP METHOD
        foreach ($keyList as $key) {
            $variable_name = $variable_map[$key];
            if (isset($_POST[$key])) {
                $$variable_name = voucher_post_string($_POST[$key]);
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

        $logged_user_office = voucher_logged_user_office();
        $office_from = $logged_user_office;

        if (empty($remarks)) {
            $remarks = "";
        }

        $return_previous_validation_error = '';

        if (!empty($return_destination) && $return_destination === 'encoder') {
            // Re-insert into vouchers on return; production DBs may lack AUTO_INCREMENT on id.
            vouchers_ensure_ors_no_column($pdo);
            vouchers_ensure_id_auto_increment($pdo);

            // Route back to the encoder's unit; keep the original encoded_from.
            $encoder_destination = trim((string) $encoded_from);
            $office_to = $encoder_destination;
            $penro_office = $logged_user_office;
            $receiver_udc = '';
            if (!empty($encoded_by)) {
                $enc_stmt = $pdo->prepare(
                    "SELECT udc FROM user_group
                     WHERE TRIM(CONCAT(COALESCE(emp_fn,''), ' ', COALESCE(emp_mi,''), ' ', COALESCE(emp_ln,''))) = :encoded_by
                     LIMIT 1"
                );
                $enc_stmt->bindParam(':encoded_by', $encoded_by);
                $enc_stmt->execute();
                $enc_row = $enc_stmt->fetch(PDO::FETCH_ASSOC);
                if ($enc_row && !empty($enc_row['udc'])) {
                    $receiver_udc = trim((string) $enc_row['udc']);
                }
            }
            if ($receiver_udc === '' && $encoder_destination !== '') {
                $receiver_udc = voucher_return_resolve_receiver_udc($pdo, $encoder_destination, $penro_office);
            }
        } elseif (!empty($return_destination) && $return_destination === 'previous_sender') {
            $destination_designation = trim((string) (
                ($return_target_section ?? '') !== ''
                    ? $return_target_section
                    : (($document_to ?? '') !== '' ? $document_to : '')
            ));
            if ($destination_designation === '' || !utilities_return_previous_destination_is_allowed($pdo, $destination_designation)) {
                $return_previous_validation_error = 'The selected previous process is not allowed for return.';
            } else {
                $previous_sender_udc = trim((string) ($sender_udc ?? ''));
                $returner_udc = trim((string) ($_SESSION['logged_user_udc'] ?? ''));
                $sender_udc = $returner_udc !== '' ? $returner_udc : $sender_udc;
                $process_history = voucher_return_fetch_process_history($pdo, $processing_no, $return_source);
                $previousTarget = voucher_return_resolve_previous_sender_target(
                    $pdo,
                    $destination_designation,
                    $process_history,
                    $previous_sender_udc,
                    $returner_udc,
                    $logged_user_office
                );
                if (($previousTarget['receiver_udc'] ?? '') !== '') {
                    $receiver_udc = (string) $previousTarget['receiver_udc'];
                }
                if (($previousTarget['office_from'] ?? '') !== '') {
                    $office_from = (string) $previousTarget['office_from'];
                }
                if (($previousTarget['office_to'] ?? '') !== '') {
                    $office_to = (string) $previousTarget['office_to'];
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

                    if ($return_source === 'forwarding') {
                        if (!voucher_receiving_return_exists($pdo, $processing_no)) {
                            $temp_dump['voucher_missing'] = 'Voucher not found in forwarding queue.';
                        }
                    } elseif (voucher_incoming_check_if_returned_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = 'Voucher is already returned!';
                    }

                    if ($return_previous_validation_error !== '') {
                        $temp_dump['return_destination'] = $return_previous_validation_error;
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_return'] = $temp_dump;
                        echo "<script>process_functionAlert('Return failed!', '" . $success_redirect . "')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        // DATABASE STATEMENTS VIA MODE/CTRL
                        $target = array_map('trim', explode(',', (string) ($_SESSION['logged_user_designation'] ?? '')));
                        if (
                            in_array('Accounting Unit', $target, true)
                            && $logged_user_office !== ''
                            && trim((string) $payee) !== ''
                            && (empty($return_destination) || $return_destination !== 'previous_sender')
                        ) {
                            $payeeLookup = voucher_lookup_payee_at_office($pdo, (string) $payee, $logged_user_office);
                            if ($payeeLookup['udc'] !== '') {
                                $sender_udc = $payeeLookup['udc'];
                            } elseif ($payeeLookup['found']) {
                                $temp_dump['unassigned_udc'] = 'No user is assigned to accept';
                                $sender_udc = 'failed';
                            }
                        }

                        $return_remarks_for_incoming = $sender_remarks !== '' ? $sender_remarks : (string) $remarks;
                        $return_forwarded_by = $forwarded_by !== ''
                            ? $forwarded_by
                            : ($_SESSION['logged_user_emp_name'] ?? '');

                        if (!empty($return_destination) && $return_destination === 'encoder') {
                            $retained = voucher_return_encoder_retention_values($pdo, $processing_no, $return_source, [
                                'ors_no' => $ors_no,
                                'dv_no' => $dv_no,
                                'ada_check_no' => $ada_check_no,
                                'payee' => $payee,
                                'address' => $address,
                                'particulars' => $particulars,
                                'tin_employee_no' => $tin_employee_no,
                                'amount' => $amount,
                                'voucher_type' => $voucher_type,
                                'voucher_date' => $voucher_date,
                                'coa_options' => '',
                                'coa_category' => '',
                                'coa_subsection' => '',
                            ]);
                            $ors_no = $retained['ors_no'];
                            $dv_no = $retained['dv_no'];
                            $ada_check_no = $retained['ada_check_no'];
                            $payee = $retained['payee'];
                            $address = $retained['address'];
                            $particulars = $retained['particulars'];
                            $tin_employee_no = $retained['tin_employee_no'];
                            $amount = $retained['amount'];
                            $voucher_type = $retained['voucher_type'];
                            $voucher_date = $retained['voucher_date'];
                            $retained_coa_options = voucher_field_is_placeholder($retained['coa_options']) ? null : $retained['coa_options'];
                            $retained_coa_category = voucher_field_is_placeholder($retained['coa_category']) ? null : $retained['coa_category'];
                            $retained_coa_subsection = voucher_field_is_placeholder($retained['coa_subsection']) ? null : $retained['coa_subsection'];
                            voucher_sync_tracking_identifiers($pdo, $processing_no, $ors_no, $dv_no, $ada_check_no);
                        }

                        if ($return_source === 'forwarding') {
                            if (!empty($return_destination) && $return_destination === 'encoder') {
                                voucher_incoming_sent_return_document(
                                    $pdo,
                                    $processing_no,
                                    $ors_no,
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
                                    $retained_coa_options ?? null,
                                    $retained_coa_category ?? null,
                                    $retained_coa_subsection ?? null
                                );
                                voucher_return_sync_dv_entry_for_encoder_return(
                                    $pdo,
                                    $processing_no,
                                    $encoded_from,
                                    $ors_no,
                                    $dv_no,
                                    $ada_check_no
                                );
                            } else {
                                voucher_forwarding_return_move_to_incoming(
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
                                    $office_from,
                                    $office_to,
                                    $sender_udc,
                                    $receiver_udc,
                                    $encoded_by,
                                    $encoded_from,
                                    $datetime_encoded,
                                    $return_forwarded_by,
                                    $process_status,
                                    $combined_remarks,
                                    $return_remarks_for_incoming
                                );
                            }
                            voucher_forwarding_return_remove_from_receiving($pdo, $processing_no);
                        } else {
                            // Incoming: return to encoder (pending) or previous unit (receiving).
                            if (!empty($return_destination) && $return_destination === 'encoder') {
                                voucher_incoming_sent_return_document(
                                    $pdo,
                                    $processing_no,
                                    $ors_no,
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
                                    $retained_coa_options ?? null,
                                    $retained_coa_category ?? null,
                                    $retained_coa_subsection ?? null
                                );
                                voucher_return_sync_dv_entry_for_encoder_return(
                                    $pdo,
                                    $processing_no,
                                    $encoded_from,
                                    $ors_no,
                                    $dv_no,
                                    $ada_check_no
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
                                    $sender_udc,
                                    $receiver_udc,
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
                        }
                        $return_active_status = (!empty($return_destination) && $return_destination === 'encoder') ? 'no' : 'returned';
                        voucher_incoming_return_update_document_tracking($pdo, $processing_no, $action, $datetime_action, $combined_remarks, $return_active_status);
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
                        echo "<script>process_functionAlert('Return success!', '" . $success_redirect . "')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }
                } else {
                    echo "<script>process_functionAlert('Forward Error: Wrong module used!', '" . $success_redirect . "')</script>";
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
        $invalid_redirect = isset($_POST['return_source']) && $_POST['return_source'] === 'forwarding'
            ? 'voucher_forwarding_return_redirect'
            : 'voucher_incoming_redirect';
        echo "<script>process_functionAlert('Invalid token!', '" . $invalid_redirect . "')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

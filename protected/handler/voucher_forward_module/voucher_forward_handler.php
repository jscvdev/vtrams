<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once '../../core/components/helpers/handler_session_err_helper.inc.php';
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
                    $$variable_name = voucher_post_string($_POST[$key]);
                }
            } else {
                $$variable_name = "";
            }
        }

        voucher_apply_exact_amount($amount);

        $voucher_type = voucher_resolve_forward_voucher_type(
            $pdo,
            trim((string) ($processing_no ?? '')),
            (string) ($voucher_type ?? ''),
            $_POST
        );

        $office_from = voucher_logged_user_office();

        $dv_no = "";
        $ors_no = "TBD";
        $ada_check_no = "TBD";
        $stored_identifiers = voucher_fetch_identifiers($pdo, trim((string) ($processing_no ?? '')));
        $ors_no = voucher_pick_field($stored_identifiers['ors_no'], $ors_no);
        $dv_no = voucher_pick_field($stored_identifiers['dv_no'], $dv_no);
        $ada_check_no = voucher_pick_field($stored_identifiers['ada_check_no'], $ada_check_no);
        $process_status = "N/A";
        $receiver_udc = "";
        $tracking_row = voucher_tracking_fetch_by_processing_no($pdo, $processing_no ?? '');
        $tracking_voucher_status = $tracking_row['voucher_status'] ?? null;
        $process_history = (string) ($tracking_row['process_history'] ?? '');
        $loggedEncoderName = (string) ($_SESSION['logged_user_emp_name'] ?? '');
        $needsReturnForward = voucher_tracking_needs_return_forward($tracking_row, $tracking_voucher_status, $loggedEncoderName);
        $forward_return_designation = isset($forward_return_designation) ? trim((string) $forward_return_designation) : '';
        $returnForwardOffice = '';
        if ($needsReturnForward) {
            $returnForward = voucher_tracking_resolve_return_forward_target(
                $pdo,
                $tracking_voucher_status,
                $encoded_from ?? '',
                (string) ($_SESSION['logged_user_section'] ?? ''),
                $loggedEncoderName,
                $process_history
            );
            if ($forward_return_designation === '' && $returnForward['designation'] !== '') {
                $forward_return_designation = $returnForward['designation'];
            }
            $returnForwardOffice = trim((string) ($returnForward['office'] ?? ''));
        }
        if (empty($combined_remarks)) {
            $combined_remarks = "";
        }
        $target = voucher_logged_user_designations();

        try {
            $temp_dump = [];
            try {
                if (isset($_REQUEST['forward_voucher'])) {
                    $logged_user_office = voucher_logged_user_office();
                    $voucher_office_from = trim(voucher_post_string($_POST['office_from'] ?? ''));
                    $office_from = $logged_user_office;
                    $office_to = $logged_user_office;
                    $forwarded_by = $_SESSION['logged_user_emp_name'];
                    $action_from = voucher_post_string($_SESSION['logged_user_section'] ?? '');
                    $sender_udc = trim((string) ($_SESSION['logged_user_udc'] ?? ''));

                    //REMARKS
                    if (!empty($remarks)) {
                        $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    } else {
                        $combined_remarks = "N/A";
                    }

                    // Resolve encoder office for routing (designation_limit uses office names, not sections).
                    $encoder_office = trim($voucher_office_from);
                    $loggedEncoderName = trim((string) ($_SESSION['logged_user_emp_name'] ?? ''));
                    $postedEncoderName = trim((string) ($encoded_by ?? ''));
                    if ($postedEncoderName !== '' && $loggedEncoderName !== '' && strcasecmp($postedEncoderName, $loggedEncoderName) === 0) {
                        $encoder_office = $logged_user_office;
                    } elseif ($encoder_office === '') {
                        $encoder_office = trim((string) ($tracking_row['office_from'] ?? ''));
                    }
                    if ($encoder_office === '') {
                        $encoder_office = $logged_user_office;
                    }

                    // encoded_from stores the encoder's office; correct legacy section labels on encoder forward.
                    if (
                        $encoder_office !== ''
                        && trim((string) ($encoded_by ?? '')) === trim((string) ($_SESSION['logged_user_emp_name'] ?? ''))
                    ) {
                        $encoded_from = $encoder_office;
                    }

                    $forwarded_to = '';
                    $target_to = '';
                    if ($needsReturnForward) {
                        // Re-forward returned voucher to whoever returned it (from voucher_tracking).
                        if ($returnForwardOffice !== '') {
                            $office_to = $returnForwardOffice;
                        }
                        $resolved = voucher_forward_receiver_for_return_target(
                            $pdo,
                            $tracking_voucher_status,
                            $forward_return_designation,
                            $office_to,
                            $sender_udc,
                            $process_history,
                            $loggedEncoderName
                        );
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    } elseif (!$needsReturnForward) {
                        $specialAccessTargets = voucher_special_access_forward_targets(
                            $pdo,
                            (string) ($voucher_type ?? '')
                        );
                        $specialAccessTarget = '';
                        if ($specialAccessTargets !== []) {
                            $pickedTarget = utilities_special_access_normalize_value($forward_return_designation);
                            if (count($specialAccessTargets) > 1) {
                                if ($pickedTarget !== '' && in_array($pickedTarget, $specialAccessTargets, true)) {
                                    $specialAccessTarget = $pickedTarget;
                                } else {
                                    $temp_dump['special_access_target'] = 'Please select a forward destination.';
                                }
                            } else {
                                $specialAccessTarget = $specialAccessTargets[0];
                            }
                        }
                        if ($specialAccessTarget !== '') {
                            $target_to = $specialAccessTarget;
                            $office_to = voucher_resolve_office_for_designation_route($pdo, $target_to, $encoder_office);
                            $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $target_to, $office_to, $sender_udc);
                            $receiver_udc = $resolved['receiver_udc'];
                            $forwarded_to = $resolved['forwarded_to'];
                            if ($resolved['temp_errors']) {
                                $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                            }
                        }
                    } elseif (voucher_user_has_designation($target, 'Liaison Officer')) {
                        // Liaison officers forward upstream to ICU at the processing office,
                        // even when their office normally routes encoders to another liaison first.
                        $resolved = voucher_forward_liaison_icu_receiver($pdo, $logged_user_office);
                        $target_to = 'ICU';
                        $office_to = $resolved['office_to'];
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    } elseif (voucher_encoder_forwards_to_liaison_first($pdo, $encoder_office)) {
                        $target_to = 'Liaison Officer';
                        $liaisonOffice = voucher_encoder_liaison_route_office($pdo, $encoder_office);
                        $office_to = voucher_resolve_office_for_designation_route($pdo, $target_to, $liaisonOffice);
                        $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $target_to, $office_to, $sender_udc);
                        $receiver_udc = $resolved['receiver_udc'];
                        $forwarded_to = $resolved['forwarded_to'];
                        if ($resolved['temp_errors']) {
                            $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                        }
                    } elseif (
                        ($encoderForwardTarget = voucher_forward_encoder_default_target($pdo, $encoder_office, $voucher_type ?? '')) !== ''
                    ) {
                        $target_to = $encoderForwardTarget;
                        $office_to = voucher_resolve_office_for_designation_route($pdo, $target_to, $encoder_office);
                        $resolved = voucher_forward_receiver_udcs_for_designation($pdo, $target_to, $office_to, $sender_udc);
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

                    if (
                        $sender_udc !== ''
                        && $receiver_udc !== ''
                        && voucher_udcs_excluding($receiver_udc, $sender_udc) === ''
                    ) {
                        $temp_dump['self_send'] = 'Cannot forward this document to yourself';
                    }

                    if (!$needsReturnForward && trim($receiver_udc) === '') {
                        $temp_dump['unassigned_udc'] = $temp_dump['unassigned_udc']
                            ?? 'No user is assigned to accept this voucher. Forwarding was blocked.';
                    }

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
                        handler_redirect_with_errors($temp_dump, $redirect_code, 'Forward failed: ');
                    } else {
                        if (!check_if_voucher_forwarded_exists($pdo, $processing_no)) {
                            handler_redirect_with_notify(
                                'Forward failed: Voucher not found for forwarding.',
                                $redirect_code,
                                'error',
                                6000
                            );
                        }

                        // Get confirmed checklist JSON from POST (store raw JSON)
                        $coa_options = isset($_POST['selected_coa_options_forward']) ? trim((string)$_POST['selected_coa_options_forward']) : null;
                        $coa_category = isset($_POST['coa_category_forward']) && trim((string)$_POST['coa_category_forward']) !== ''
                            ? trim((string)$_POST['coa_category_forward'])
                            : $voucher_type;
                        $coa_subsection = isset($_POST['coa_subsection_forward']) && trim((string)$_POST['coa_subsection_forward']) !== ''
                            ? trim((string)$_POST['coa_subsection_forward'])
                            : $voucher_type;

                        handler_execute_writes(
                            $pdo,
                            function (PDO $pdo) use (
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
                                $coa_subsection,
                                $action,
                                $action_by,
                                $action_from,
                                $remarks
                            ) {
                                voucher_forward_pending($pdo, $processing_no);

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
                            // active_status=yes: included in dashboard and voucher status (returned also included via <> no)
                            update_returned_forwarded_voucher($pdo, $processing_no, $action, $datetime_action, $combined_remarks);
                            voucher_sync_tracking_identifiers($pdo, $processing_no, $ors_no, $dv_no, $ada_check_no);
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

                                return true;
                            },
                            'Forward success!',
                            'Forward failed!',
                            $redirect_code,
                            function () use (
                                $processing_no,
                                $dv_no,
                                $payee,
                                $office_from,
                                $office_to,
                                $forwarded_to,
                                $target_to
                            ) {
                                $destination = !empty($forwarded_to) ? $forwarded_to : (!empty($target_to) ? $target_to : $office_to);
                                AuditHelper::logActivity('forwarding', "Forwarded voucher: {$processing_no} to {$destination}", [
                                    'processing_no' => $processing_no,
                                    'dv_no' => $dv_no,
                                    'payee' => $payee,
                                    'office_from' => $office_from,
                                    'office_to' => $office_to,
                                    'document_to' => $destination
                                ], $_SESSION['logged_user_emp_name'] ?? null, $processing_no);
                            }
                        );
                    }
                } else {
                    $redirect_code = 'voucher_pending_forward_redirect';
                    handler_redirect_with_notify(
                        'Forward failed: Wrong module used.',
                        $redirect_code,
                        'error',
                        5000
                    );
                }
            } catch (PDOException $e) {
                $redirect_code = 'voucher_pending_forward_redirect';
                handler_redirect_with_notify(
                    handler_format_transaction_error('Forward failed.', $e),
                    $redirect_code,
                    'error',
                    8000
                );
            }

            $pdo = null;
            $statement = null;

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            handler_redirect_with_notify(
                handler_format_transaction_error('Forward failed.', $e),
                'voucher_pending_forward_redirect',
                'error',
                8000
            );
        }
    } else {
        handler_redirect_with_notify(
            'Forward failed: Invalid or expired form token. Refresh the page and try again.',
            'voucher_pending_forward_redirect',
            'error',
            6000
        );
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

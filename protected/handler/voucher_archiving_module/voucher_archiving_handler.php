<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'voucher_archiving.model.inc.php';
    require_once 'voucher_archiving.ctrl.inc.php';
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
            "voucher_date",
            "priority",
            "office_from",
            "document_to",
            "encoded_by",
            "datetime_encoded",
            "remarks",
            "combined_remarks"
        );

        $variable_map = array(
            'processing_no' => 'processing_no',
            'ors_no' => 'ors_no',
            'ada_check_no' => 'ada_check_no',
            'dv_no' => 'dv_no',
            'payee' => 'payee',
            'address' => 'address',
            'particulars' => 'particulars',
            'tin_employee_no' => 'tin_employee_no',
            'amount' => 'amount',
            'voucher_date' => 'voucher_date',
            'priority' => 'priority',
            'office_from' => 'office_from',
            'document_to' => 'document_to',
            'encoded_by' => 'encoded_by',
            'datetime_encoded' => 'datetime_encoded',
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

        $office_to = voucher_logged_user_office();

        $receiver_udc = "";

        try {
            $temp_dump = [];

            // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
            try {
                if (isset($_REQUEST['archive_voucher'])) {
                    $logged_user_office = voucher_logged_user_office();
                    $forwarded_to =  "Budget Unit";
                    $action = "Archived by: " . $_SESSION['logged_user_emp_name'];


                    // If combined_remarks is not empty and not "N/A"
                    if (!empty($combined_remarks) && $combined_remarks != "N/A") {

                        $combined_remarks = $combined_remarks . ", " . $_SESSION['logged_user_emp_name'] . ": " . $remarks;

                        // Update the remarks
                        $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                    } else {
                        // Directly append without a comma for the first remark
                        if (!empty($remarks)) {
                            $combined_remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                            $remarks = $_SESSION['logged_user_emp_name'] . ": " . $remarks;
                        }
                    }

                    $resolved = voucher_resolve_receiver_for_designation_at_office(
                        $pdo,
                        $forwarded_to,
                        $logged_user_office
                    );
                    $receiver_udc = $resolved['receiver_udc'];
                    if ($resolved['temp_errors']) {
                        $temp_dump = array_merge($temp_dump, $resolved['temp_errors']);
                    }

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                    $datetime_action = $currTime;
                    $archived_by_from = $_SESSION['logged_user_section'];
                    $action_by  = $_SESSION['logged_user_emp_name'];

                    $variables_to_check = [
                        'processing_no' => $processing_no,
                        'dv_no' => $dv_no,
                        'payee' => $payee,
                        'address' => $address,
                        'particulars' => $particulars,
                        'amount' => $amount,
                        'voucher_date' => $voucher_date,
                        'priority' => $priority,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'receiver_udc' => $receiver_udc,
                        'document_to' => $document_to,
                        'encoded_by' => $encoded_by,
                        'forwarded_by' => $forwarded_by
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_voucher_archiving_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $totalProcessingTime = voucher_tracking_calculate_total_processing_time(
                        $pdo,
                        $processing_no,
                        $currTime,
                        '',
                        $datetime_encoded
                    );

                    //CHECK IF DOCUMENT IS ALREADY RECEIVED
                    if (check_if_voucher_archived_exists($pdo, $processing_no)) {
                        $temp_dump['voucher_exists'] = "Voucher is already archived!";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_voucher_archiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Archive Error!', 'voucher_archive_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        handler_execute_writes(
                            $pdo,
                            function (PDO $pdo) use (
                                $processing_no,
                                $ors_no,
                                $ada_check_no,
                                $dv_no,
                                $payee,
                                $address,
                                $tin_employee_no,
                                $particulars,
                                $amount,
                                $voucher_date,
                                $priority,
                                $action,
                                $action_by,
                                $datetime_action,
                                $office_from,
                                $office_to,
                                $encoded_by,
                                $receiver_udc,
                                $totalProcessingTime,
                                $archived_by_from,
                                $remarks
                            ) {
                                voucher_archive_data(
                                    $pdo,
                                    $processing_no,
                                    $ors_no,
                                    $ada_check_no,
                                    $dv_no,
                                    $payee,
                                    $address,
                                    $tin_employee_no,
                                    $particulars,
                                    $amount,
                                    $voucher_date,
                                    $priority,
                                    $action,
                                    $action_by,
                                    $datetime_action,
                                    $office_from,
                                    $office_to,
                                    $encoded_by,
                                    $receiver_udc
                                );

                                update_forwarded_archived_voucher(
                                    $pdo,
                                    $processing_no,
                                    $action,
                                    $datetime_action,
                                    $totalProcessingTime,
                                    $ada_check_no,
                                    date('Y-m-d')
                                );

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
                                    $priority,
                                    $voucher_date,
                                    $action,
                                    $action_by,
                                    $archived_by_from,
                                    $datetime_action,
                                    $office_from,
                                    $office_to,
                                    $encoded_by,
                                    $remarks
                                );

                                if (!check_if_voucher_archived_exists($pdo, $processing_no)) {
                                    throw new RuntimeException("Archive save failed for processing_no={$processing_no}");
                                }

                                remove_from_voucher_receiving($pdo, $processing_no);

                                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM voucher_receiving WHERE processing_no = :processing_no');
                                $checkStmt->bindParam(':processing_no', $processing_no);
                                $checkStmt->execute();
                                if ((int) $checkStmt->fetchColumn() > 0) {
                                    throw new RuntimeException("voucher_receiving still exists for processing_no={$processing_no}");
                                }

                                return true;
                            },
                            'Archive success!',
                            'Archive Error!',
                            'voucher_archive_redirect'
                        );
                    }
                } else {
                    echo "<script>process_functionAlert('Archive Error: Wrong Module Used!', 'voucher_archive_redirect')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
            }

            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    } else {
        // Invalid token
        echo "<script>process_functionAlert('Invalid token!', 'voucher_archive_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

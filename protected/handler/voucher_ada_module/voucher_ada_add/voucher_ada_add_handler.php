<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../../dbconnection.inc.php';
    /** @var PDO $pdo */
    require_once '../../../core/components/security/config_session.inc.php';
    require_once '../../../core/components/security/router.inc.php';
    require_once '../../action_module/voucher_action.model.inc.php';
    require_once '../../action_module/voucher_action.ctrl.inc.php';
    require '../../../core/components/notifications/custom_process_alert.php';
    require_once 'voucher_ada_add.ctrl.php';
    require_once 'voucher_ada_add.model.php';

    $keyList = array(
        "processing_no",
        "dv_no",
        "ors_no",
        "payee",
        "address",
        "particulars",
        "tin_employee_no",
        "amount",
        "voucher_type",
        "voucher_date",
        "encoded_by",
        "encoded_from",
        "datetime_encoded",
        "receiver_udc",
        "combined_remarks",
        "process_history"
    );

    $variable_map = array(
        'processing_no' => 'processing_no',
        'ors_no' => 'ors_no',
        'dv_no' => 'dv_no',
        'payee' => 'payee',
        'address' => 'address',
        'particulars' => 'particulars',
        'tin_employee_no' => 'tin_employee_no',
        'amount' => 'amount',
        'voucher_type' => 'voucher_type',
        'voucher_date' => 'voucher_date',
        'encoded_by' => 'encoded_by',
        'encoded_from' => 'encoded_from',
        'datetime_encoded' => 'datetime_encoded',
        'receiver_udc' => 'receiver_udc',
        'combined_remarks' => 'combined_remarks',
        'process_history' => 'process_history',

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

    if (isset($_SESSION['logged_user_office'])) {
        $office_from = $_SESSION['logged_user_office'];
    } else {
        $office_from = '';
    }

    $ada_check_no = "TBD";

    // Normalize process_history formatting so it stays line-delimited.
    // Some clients may send literal "\\n" sequences instead of real newlines.
    function normalize_process_history($value)
    {
        if ($value === null) return '';
        $value = (string)$value;

        // Windows newlines -> unix newlines
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);

        // Convert literal backslash+n into real newlines
        $value = preg_replace('/\\\\n/', "\n", $value);

        return trim($value);
    }

    try {
        $temp_dump = [];
        try {
            if (isset($_REQUEST['add_voucher'])) {
                $query2 = "SELECT * FROM designation_limit";
                $statement2 = $pdo->prepare($query2);
                $statement2->execute();

                $office_from = $_SESSION['logged_user_office'];
                $office_to = $_SESSION['logged_user_office'];
                $forwarded_by = $_SESSION['logged_user_emp_name'];

                date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                $datetime_action = $currTime;

                $action = "Forwarded by: " . $_SESSION['logged_user_emp_name'];
                $action_by  = $_SESSION['logged_user_emp_name'];
                $sender_udc = $_SESSION['logged_user_udc'];

                $variables_to_check = [
                    'processing_no' => $processing_no,
                    'ors_no' => $ors_no,
                    'dv_no' => $dv_no,
                    'payee' => $payee,
                    'particulars' => $particulars,
                    'amount' => $amount,
                    'voucher_type' => $voucher_type,
                    'voucher_date' => $voucher_date,
                    'encoded_by' => $encoded_by,
                    'encoded_from' => $encoded_from,
                    'datetime_encoded' => $datetime_encoded,
                    'receiver_udc' => $receiver_udc,
                ];

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

                //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                if ($temp_dump) {
                    $_SESSION['error_voucher_add'] = $temp_dump;
                    echo "<script>process_functionAlert('Add failed!', 'voucher_ada_redirect')</script>";
                    die();
                } else {
                    // DATABASE STATEMENTS VIA MODE/CTRL
                    if (check_if_voucher_added_exists($pdo, $processing_no)) {
                        $process_history = normalize_process_history($process_history);
                        voucher_add_voucher($pdo, $processing_no, $dv_no);
                        voucher_move_to_temp(
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
                            $voucher_type,
                            $voucher_date,
                            $encoded_by,
                            $encoded_from,
                            $datetime_encoded,
                            $action,
                            $action_by,
                            $datetime_action,
                            $office_from,
                            $office_to,
                            $receiver_udc,
                            $combined_remarks,
                            $process_history,
                        );
                        // update_returned_forwarded_voucher($pdo, $processing_no, $action, $datetime_action);
                        // voucher_log_user_action($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $tin_employee_no, $particulars, $amount, $voucher_date,
                        //     $priority, $action, $action_by, $datetime_action, $office_from, $office_to, $encoded_by);
                        echo "<script>process_functionAlert('Add success!', 'voucher_ada_redirect')</script>";
                        die();
                    } else {
                        $process_history = normalize_process_history($process_history);
                        voucher_add_voucher($pdo, $processing_no, $dv_no);
                        voucher_move_to_temp(
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
                            $voucher_type,
                            $voucher_date,
                            $encoded_by,
                            $encoded_from,
                            $datetime_encoded,
                            $action,
                            $action_by,
                            $datetime_action,
                            $office_from,
                            $office_to,
                            $receiver_udc,
                            $combined_remarks,
                            $process_history,
                        );
                        // voucher_document_tracking_logging($pdo, $processing_no, $dv_no, $payee, $address, $particulars, $amount, $voucher_date, $datetime_encoded, $action, $datetime_action, $encoded_by, $office_to, $office_from);
                        // voucher_log_user_action($pdo, $processing_no, $ors_no, $ada_check_no, $dv_no, $payee, $address, $tin_employee_no, $particulars, $amount, $voucher_date,
                        //     $priority, $action, $action_by, $datetime_action, $office_from, $office_to, $encoded_by);
                        echo "<script>process_functionAlert('Add success!', 'voucher_ada_redirect')</script>";
                        die();
                    }
                }
            } else {
                echo "<script>process_functionAlert('Add Error: Wrong module used!', 'voucher_ada_redirect')</script>";
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
    require_once __DIR__ . '/../../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

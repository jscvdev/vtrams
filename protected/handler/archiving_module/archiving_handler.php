<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'archiving.model.inc.php';
    require_once 'archiving.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';

    // Check if token is valid
    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        // Valid token, process the data

        $keyList = array(
            "document_id",
            "document_title",
            "document_type",
            "document_receiver",
            "document_receive_type",
            "document_date",
            "document_description",
            "document_sender",
            "document_no_pages",
            "encoded_from",
            "datetime_encoded",
            "datetime_forwarded",
            "encoded_by",
            "combined_remarks",
            "remarks",
            "justification",
            "purpose",
            "office_from",
            "office_to",
            "for_action",
            "complexity",
            "file_path",
            "file_name",
            "file_type",
            "reply_id",
        );

        $variable_map = array(
            'document_id' => 'document_id',
            'document_title' => 'document_title',
            'document_type' => 'document_type',
            'document_receiver' => 'document_receiver',
            'document_receive_type' => 'document_receive_type',
            'document_date' => 'document_date',
            'document_description' => 'document_description',
            'document_sender' => 'document_sender',
            'document_no_pages' => 'document_no_pages',
            'justification' => 'justification',

            //PASSED HIDDEN INPUT
            'encoded_from' => 'encoded_from',
            'datetime_encoded' => 'datetime_encoded',
            'datetime_forwarded' => 'datetime_forwarded',
            'encoded_by' => 'encoded_by',
            'combined_remarks' => 'combined_remarks',
            'remarks' => 'remarks',
            'purpose' => 'purpose',
            'office_from' => 'office_from',
            'office_to' => 'office_to',
            'for_action' => 'for_action',
            'complexity' => 'complexity',
            'file_path' => 'file_path',
            'file_name' => 'file_name',
            'file_type' => 'file_type',
            'reply_id' => 'reply_id',
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

        $forwarded_by = htmlspecialchars($_SESSION['logged_user_emp_name']); // FORWARDED BY -> CURRENT LOGGED USER
        $archived_from = htmlspecialchars($_SESSION['logged_user_section']); // ARCHIVED BY -> CURRENT LOGGED USER
        $forwarded_from = $_SESSION['logged_user_designation'];
        $logged_user_office = voucher_logged_user_office();
        $office_from = $logged_user_office;

        $action_by_from = $logged_user_office;

        try {
            $temp_dump = [];

            // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
            try {
                if (isset($_REQUEST['archive_document'])) {
                    $receiver_udc = '';
                    $forwarded_to =  "Records Unit";
                    $action = "Archived by: " . $_SESSION['logged_user_emp_name'];

                    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

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

                    function calculateTurnaroundTime_Archiving($startTimestamp, $endTimestamp)
                    {
                        // Convert datetime strings to Unix timestamps
                        $startTime = strtotime($startTimestamp);
                        $endTime = strtotime($endTimestamp);

                        // Calculate the difference in seconds
                        $durationSeconds = $endTime - $startTime;

                        // Calculate days, hours, minutes, and seconds
                        $days = floor($durationSeconds / (24 * 3600));
                        $remainder = $durationSeconds % (24 * 3600);
                        $hours = floor($remainder / 3600);
                        $remainder = $remainder % 3600;
                        $minutes = floor($remainder / 60);
                        $seconds = $remainder % 60;

                        // Prepare the output string
                        $output = '';
                        if ($days > 0) {
                            $output .= "$days day" . ($days > 1 ? 's ' : ' ');
                        }
                        if ($hours > 0) {
                            $output .= "$hours hour" . ($hours > 1 ? 's ' : ' ');
                        }
                        if ($minutes > 0) {
                            $output .= "$minutes minute" . ($minutes > 1 ? 's ' : ' ');
                        }
                        if ($seconds > 0) {
                            $output .= "$seconds second" . ($seconds > 1 ? 's ' : ' ');
                        }

                        return trim($output);
                    }

                    $datetime_action = $currTime;
                    $archived_by_from = $_SESSION['logged_user_section'];
                    $action_by  = $_SESSION['logged_user_emp_name'];

                    $variables_to_check = [
                        'document_id' => $document_id,
                        'document_title' => $document_title,
                        'document_description' => $document_description,
                        'document_receive_type' => $document_receive_type,
                        'encoded_from' => $encoded_from,
                        'encoded_by' => $encoded_by,
                        'document_type' => $document_type,
                        'document_no_pages' => $document_no_pages,
                        'document_receiver' => $document_receiver,
                        'document_sender' => $document_sender,
                        'document_date' => $document_date,

                        'forwarded_to' => $forwarded_to,
                        'archived_from' => $archived_from,
                        'forwarded_by' => $forwarded_by,
                        'currTime' => $currTime,
                        'action' => $action,
                        'justification' => $justification,
                        'purpose' => $purpose,
                        'office_from' => $office_from,
                        'office_to' => $office_to,
                        'for_action' => $for_action,
                        'complexity' => $complexity,
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_archiving_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    $turnaround_time = calculateTurnaroundTime_Archiving($datetime_encoded, $currTime);

                    //CHECK IF DOCUMENT IS ALREADY RECEIVED
                    if (check_if_archived_exists($pdo, $document_id)) {
                        $temp_dump['document_exists'] = "Document is already archived!";
                    }

                    //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                    if ($temp_dump) {
                        $_SESSION['error_archiving'] = $temp_dump;
                        echo "<script>process_functionAlert('Archive Error!', 'redirect_archiving')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    } else {
                        if (isset($for_action) && $for_action === "true") {
                            if (!check_reply_exists($pdo, $reply_id, $forwarded_by)) {
                                $temp_dump['document_exists'] = "Document does not exist!";
                            }
                            if ($temp_dump) {
                                $_SESSION['error_archiving'] = $temp_dump;
                                echo "<script>process_functionAlert('Archive Error!', 'redirect_archiving')</script>";
                                $_SESSION['token'] = generateToken();
                                die();
                            } else {
                                // DATABASE STATEMENTS VIA MODE/CTRL
                                update_tracking($pdo, $reply_id, $document_id);
                                archive_data(
                                    $pdo,
                                    $document_id,
                                    $document_title,
                                    $document_description,
                                    $document_receive_type,
                                    $encoded_from,
                                    $encoded_by,
                                    $document_type,
                                    $document_no_pages,
                                    $document_receiver,
                                    $document_sender,
                                    $document_date,
                                    $forwarded_to,
                                    $archived_from,
                                    $forwarded_by,
                                    $action_by_from,
                                    $combined_remarks,
                                    $datetime_encoded,
                                    $currTime,
                                    $action,
                                    $justification,
                                    $purpose,
                                    $office_from,
                                    $office_to,
                                    $for_action,
                                    $complexity,
                                    $file_path,
                                    $file_name,
                                    $file_type,
                                    $reply_id,
                                );
                                archiving_document_tracking_logging($pdo, $document_id, $forwarded_to, $forwarded_from, $forwarded_by, $combined_remarks, $currTime, $action, $turnaround_time);
                                log_user_action(
                                    $pdo,
                                    $document_id,
                                    $document_title,
                                    $document_description,
                                    $document_receive_type,
                                    $document_type,
                                    $document_no_pages,
                                    $document_receiver,
                                    $document_sender,
                                    $document_date,
                                    $action,
                                    $datetime_action,
                                    $archived_by_from,
                                    $action_by,
                                    $action_by_from,
                                    $encoded_by,
                                    $office_from,
                                    $remarks
                                );
                                remove_from_receiving($pdo, $document_id);
                                echo "<script>process_functionAlert('Archive success!', 'redirect_archiving')</script>";
                                $_SESSION['token'] = generateToken();
                                die();
                            }
                        } else {
                            archive_data(
                                $pdo,
                                $document_id,
                                $document_title,
                                $document_description,
                                $document_receive_type,
                                $encoded_from,
                                $encoded_by,
                                $document_type,
                                $document_no_pages,
                                $document_receiver,
                                $document_sender,
                                $document_date,
                                $forwarded_to,
                                $archived_from,
                                $forwarded_by,
                                $action_by_from,
                                $combined_remarks,
                                $datetime_encoded,
                                $currTime,
                                $action,
                                $justification,
                                $purpose,
                                $office_from,
                                $office_to,
                                $for_action,
                                $complexity,
                                $file_path,
                                $file_name,
                                $file_type,
                                $reply_id,
                            );
                            archiving_document_tracking_logging($pdo, $document_id, $forwarded_to, $forwarded_from, $forwarded_by, $combined_remarks, $currTime, $action, $turnaround_time);
                            log_user_action(
                                $pdo,
                                $document_id,
                                $document_title,
                                $document_description,
                                $document_receive_type,
                                $document_type,
                                $document_no_pages,
                                $document_receiver,
                                $document_sender,
                                $document_date,
                                $action,
                                $datetime_action,
                                $archived_by_from,
                                $action_by,
                                $action_by_from,
                                $encoded_by,
                                $office_from,
                                $remarks
                            );
                            remove_from_receiving($pdo, $document_id);
                            echo "<script>process_functionAlert('Archive success!', 'redirect_archiving')</script>";
                            $_SESSION['token'] = generateToken();
                            die();
                        }
                    }
                } else {
                    echo "<script>process_functionAlert('Archive Error: Wrong Module Used!', 'redirect_archiving')</script>";
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
        echo "<script>process_functionAlert('Invalid token!', 'redirect_archiving')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

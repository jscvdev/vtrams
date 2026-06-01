<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../../dbconnection.inc.php';
    require_once '../../../core/components/security/config_session.inc.php';
    require_once '../../../core/components/security/router.inc.php';
    require_once '../../action_module/action.model.inc.php';
    require_once '../../action_module/action.ctrl.inc.php';
    require '../../../core/components/notifications/custom_process_alert.php';
    require_once 'routing_delete.ctrl.inc.php';
    require_once 'routing_delete.model.inc.php';

    $keyList = array(
        "encoded_document_id",
        "encoded_document_title",
        "encoded_document_type",
        "encoded_document_receiver",
        "encoded_document_receive_type",
        "encoded_document_description",
        "encoded_document_sender",
        "encoded_document_no_pages",
        "encoded_document_date",
        "document_encoded_from",
        "document_encoded_by"
    );

    $variable_map = array(
        'encoded_document_id' => 'encoded_document_id',
        'encoded_document_title' => 'encoded_document_title',
        'encoded_document_type' => 'encoded_document_type',
        'encoded_document_receiver' => 'encoded_document_receiver',
        'encoded_document_receive_type' => 'encoded_document_receive_type',
        'encoded_document_description' => 'encoded_document_description',
        'encoded_document_sender' => 'encoded_document_sender',
        'encoded_document_no_pages' => 'encoded_document_no_pages',
        'encoded_document_date' => 'encoded_document_date',
        'document_encoded_from' => 'document_encoded_from',
        'document_encoded_by' => 'document_encoded_by'
        // Add more mappings as needed
    );

    //LOOP METHOD
    foreach ($keyList as $key) {
        $variable_name = $variable_map[$key];
        if (isset($_POST[$key])) {
            $$variable_name = $_POST[$key];
        } else {
            $$variable_name = "";
        }
    }

    try {
        $temp_dump = [];

        try {
            if (isset($_REQUEST['delete_document'])) {

                date_default_timezone_set('Asia/Singapore'); // SET TIMEZONE TO GMT+8
                $currTime = $date = date('Y-m-d H:i:s', time()); // FORMAT THE CURRENT TIME
                $datetime_action = $currTime;
                $action_by  = $_SESSION['logged_user_emp_name'];
                $action = "Deleted";

                $variables_to_check = [
                    'encoded_document_id' => $encoded_document_id,
                    'encoded_document_title' => $encoded_document_title,
                    'encoded_document_type' => $encoded_document_type,
                    'encoded_document_receiver' => $encoded_document_receiver,
                    'encoded_document_receive_type' => $encoded_document_receive_type,
                    'encoded_document_description' => $encoded_document_description,
                    'encoded_document_sender' => $encoded_document_sender,
                    'encoded_document_no_pages' => $encoded_document_no_pages,
                    'encoded_document_date' => $encoded_document_date,
                    'document_encoded_from' => $document_encoded_from,
                    'document_encoded_by' => $document_encoded_by,
                ];

                //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                $result = is_routing_delete_required_data_empty($variables_to_check);

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
                if (check_exists_in_routing($pdo, $encoded_document_id)) {
                    $temp_dump['document_exists'] = "Document does not exist!";
                }

                //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                if ($temp_dump) {
                    $_SESSION['error_routing_delete'] = $temp_dump;
                    echo "<script>process_functionAlert('Delete Failed!', 'delete_routing_process_err')</script>";
                    die();
                } else {
                    // DATABASE STATEMENTS VIA MODE/CTRL
                    routing_delete_target_logs($pdo, $encoded_document_id);
                    log_user_action(
                        $pdo,
                        $encoded_document_id,
                        $encoded_document_title,
                        $encoded_document_description,
                        $encoded_document_receive_type,
                        $encoded_document_type,
                        $encoded_document_no_pages,
                        $encoded_document_receiver,
                        $encoded_document_sender,
                        $encoded_document_date,
                        $action,
                        $datetime_action,
                        $document_encoded_from,
                        $action_by,
                        $document_encoded_by
                    );
                    echo "<script>process_functionAlert('Delete success!', 'delete_routing_success')</script>";
                }
            } else {
                echo "<script>process_functionAlert('Delete Error: Wrong module used!', 'delete_routing_document_err')</script>";
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        die();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
} else {
    require_once __DIR__ . '/../../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

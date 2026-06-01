<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../dbconnection.inc.php';
    require_once '../../core/components/security/config_session.inc.php';
    require_once '../../core/components/security/router.inc.php';
    require 'change_password.model.inc.php';
    require 'change_password.ctrl.inc.php';
    require '../../core/components/notifications/custom_process_alert.php';

    $emp_id  = $_SESSION['logged_user_emp_id'];
    $new_password = htmlspecialchars($_POST['new_password']);
    $confirm_password = htmlspecialchars($_POST['confirm_password']);

    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (isset($_REQUEST['change_password'])) {

                $action = "edited";


                //CHECK IF REQUIRED DATA EMPTY
                if (is_change_password_required_data_empty($emp_id, $confirm_password, $new_password)) {
                    $temp_dump['empty_data'] = "Some data required is missing!";
                }

                //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                if ($temp_dump) {
                    $_SESSION['error_change_password'] = $temp_dump;
                    echo "<script>process_functionAlert('Change Password Error!', 'change_pw_redirect')</script>";
                    die();
                } else {
                    change_password($pdo, $confirm_password, $emp_id);
                    die();
                }
            } else {
                echo "<script>process_functionAlert('Change Password Error: Wrong Module Used!', 'change_pw_redirect')</script>";
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
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

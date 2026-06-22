<?php
session_start();
$username = $password = "";

require_once '../core/components/notifications/custom_process_alert.php';
require_once '../core/components/security/err_blocker.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_once '../dbconnection.inc.php';
    require_once '../core/components/security/config_session.inc.php';
    require_once '../core/components/helpers/user_login_security_helper.inc.php';
    require_once 'login.model.inc.php';
    require_once 'login_errhandler.inc.php';
    require_once 'login.ctrl.inc.php';

    user_login_ensure_schema($pdo);

    $emp_id = htmlspecialchars($_POST['emp_id']);
    $password = htmlspecialchars($_POST['password']);

    try {
        $temp_dump = [];

        //ERR HANDLERS
        if (is_input_empty($emp_id, $password)) {
            $temp_dump["empty_input"] = "Fill in all required fields!";
        }

        //GETS USER
        $result = get_user($pdo, $emp_id);

        if (is_user_incorrect($result)) {
            $temp_dump["login_incorrect"] = "Incorrect login User";
        } elseif (user_login_is_blocked($result)) {
            $temp_dump["login_incorrect"] = user_login_blocked_message();
        } elseif (is_password_incorrect($password, $result["password"])) {
            $attempts = user_login_record_failed_attempt($pdo, $emp_id);
            $temp_dump["login_incorrect"] = user_login_failed_password_message($attempts);
        }

        if ($temp_dump) {
            $_SESSION["error_login"] = $temp_dump;
            echo "<script>process_functionAlert('Login Error!', 'login_err')</script>";
            die();
        } else {
            //IF TEMP DUMP IS EMPTY PROCEED TO LOGIN
            user_login_reset_attempts($pdo, $emp_id);

            $newSessionId = session_create_id();
            $sessionId = htmlspecialchars($newSessionId . "_" . $result['id']);
            session_id($sessionId);

            $formattedResultName = explode(" ", $result['emp_fn'] . " " . $result['emp_mi'] . " " . $result['emp_ln']);
            $formattedResultName2  = implode(" ", $formattedResultName);
            $full_name = $formattedResultName2;


            $_SESSION["logged_user_emp_name"] = $full_name;
            $_SESSION["logged_user_emp_id"] = $result['emp_id'];
            $_SESSION["logged_user_office"] = $result['office'];
            $_SESSION["logged_user_section"] = $result['section'];
            $_SESSION["logged_user_division"] = $result['division'];
            $_SESSION["logged_user_password"] = $result['password'];
            $_SESSION["logged_user_udc"] = $result['udc'];
            $_SESSION["change_type"] = "vouchers";

            $_SESSION["last_regeneration"] = time();

            $_SESSION['logged_in'] = "true"; //LOGIN FLAG

            $_SESSION['acl'] = $result['access_level'];
            $_SESSION['logged_user_designation'] = $result['designation'];


            if ($_SESSION['logged_user_section'] != "RECORDS") {
                $_SESSION["routing"] = "new route";
            }

            if ($_SESSION['logged_user_section'] == 'RECORDS') {
                echo "<script>process_functionAlert('Login Success!', 'login_records')</script>";
            } elseif ($_SESSION['acl'] >= 3 and $_SESSION['acl'] <= 7) {
                echo "<script>process_functionAlert('Login Success!', 'login_default_normal')</script>";
            } elseif ($_SESSION['acl'] == 8) {
                echo "<script>process_functionAlert('Login Success!', 'login_default_authorized')</script>";
            } elseif ($_SESSION['acl'] >= 999) {
                echo "<script>process_functionAlert('Login Success!', 'login_developer')</script>";
            } elseif ($_SESSION['acl'] == 888) {
                echo "<script>process_functionAlert('Login Success!', 'login_default_normal')</script>";
            }

            $pdo = null;
            $statement = null;

            die();
        }
    } catch (PDOException $e) {
        die("Query failed " . $e->getMessage());
    }
} else {
    require_once __DIR__ . '/../core/components/redirects/redirect_config.inc.php';
    redirect_to('login_index');
}

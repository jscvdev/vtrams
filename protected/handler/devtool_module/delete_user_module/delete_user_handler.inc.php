<?php

if (!empty($_GET['deleteid'])) {
    require_once '../../../dbconnection.inc.php';
    require_once '../../../core/components/security/config_session.inc.php';
    require_once '../../../core/components/security/router.inc.php';
    require '../../../core/components/notifications/custom_process_alert.php';
    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (!empty($_GET['deleteid'])) {
                $emp_id = $_GET['deleteid'];
                //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                $query = "DELETE FROM user_group WHERE emp_id=:emp_id";
                $statement = $pdo->prepare($query);
                $statement->bindParam(":emp_id", $emp_id);
                $statement->execute();
                echo "<script>process_functionAlert('Delete Success!', 'developer_edit_success')</script>";
                die();
            } else {
                echo "<script>process_functionAlert('Delete Account: Wrong Module Used!', 'developer_edit_err')</script>";
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
    redirect_to('devtool');
}

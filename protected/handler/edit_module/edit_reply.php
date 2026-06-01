<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../dbconnection.inc.php';
    require_once '../../core/components/security/config_session.inc.php';
    require_once '../../core/components/security/router.inc.php';
    require_once '../action_module/action.model.inc.php';
    require_once '../action_module/action.ctrl.inc.php';
    require '../../core/components/notifications/custom_process_alert.php';

    $document_id = $_POST['encoded_document_id'];
    $document_title = $_POST['encoded_document_title'];
    $document_description = $_POST['encoded_document_description'];
    $document_receive_type = $_POST['encoded_document_receive_type'];
    $document_type = $_POST['encoded_document_type'];
    $document_no_pages = $_POST['encoded_document_no_pages'];
    $document_receiver = $_POST['encoded_document_receiver'];
    $document_sender = $_POST['encoded_document_sender'];
    $document_date = $_POST['encoded_document_date'];
    $remarks = $_POST['encoded_remarks'];
    $encoded_by = $_POST['document_encoded_by'];

    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (isset($_REQUEST['edit_document'])) {

                $action = "Edited";

                date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
                $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

                $datetime_action = $currTime;
                $action_from = $_SESSION['logged_user_section'];
                $action_by = $_SESSION['logged_user_emp_name'];

                //QUERY
                $query = "UPDATE pending SET document_title = :document_title, document_desc = :document_desc, document_receive_type = :document_receive_type, document_type = :document_type, 
                   no_pages = :document_no_pages, document_receiver = :document_receiver, document_sender = :document_sender, document_date = :document_date, 
                   document_status = :document_status, remarks = :remarks WHERE document_id=:document_id";

                $statement = $pdo->prepare($query);

                $statement->bindParam(":document_id", $document_id);
                $statement->bindParam(":document_title", $document_title);
                $statement->bindParam(":document_desc", $document_description);
                $statement->bindParam(":document_receive_type", $document_receive_type);
                $statement->bindParam(":document_type", $document_type);
                $statement->bindParam(":document_no_pages", $document_no_pages);
                $statement->bindParam(":document_receiver", $document_receiver);
                $statement->bindParam(":document_sender", $document_sender);
                $statement->bindParam(":document_date", $document_date);
                $statement->bindParam(":document_status", $action);
                $statement->bindParam(":remarks", $remarks);

                if ($statement->execute()) {
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
                        $action_from,
                        $action_by,
                        $encoded_by
                    );
                    echo "<script>process_functionAlert('Edit success!', 'edit_reply_document')</script>";
                }
            } else {
                echo "<script>process_functionAlert('Edit error: Wrong Module Used!', 'edit_reply_document_err')</script>";
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
    redirect_to('documents_pending');
    die();
}

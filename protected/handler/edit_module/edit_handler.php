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
    $remarks = $_POST['remarks'];
    $encoded_by = $_POST['document_encoded_by'];
    $encoded_file_name = $_POST['file_name'];
    $encoded_file_path = $_POST['file_path'];
    $encoded_file_type = $_POST['file_type'];

    if (isset($_SESSION['logged_user_office'])) {
        $office_from = $_SESSION['logged_user_office'];
    } else {
        $office_from = '';
    }

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

                if (isset($_FILES['files2']) && !empty($_FILES['files2']['name'][0])) {
                    $files = $_FILES['files2'];

                    $allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
                    $maxFileSize = 25 * 1024 * 1024; // 25MB
                    $targetDir = '\\\\192.168.254.122\\drive 1\\uploads\\'; // Ensure this directory exists and is writable

                    // Create the upload directory if it doesn't exist
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    // Loop through each uploaded file
                    foreach ($files['tmp_name'] as $key => $tmpName) {
                        if ($files['error'][$key] === UPLOAD_ERR_OK) {
                            $fileType = $files['type'][$key];
                            $fileSize = $files['size'][$key];
                            $fileName = basename($files['name'][$key]);
                            $filePath = $targetDir . $fileName;
                            $filePath2 = $targetDir . $fileName;

                            // Validate file type and size
                            if (!in_array($fileType, $allowedTypes)) {
                                $temp_dump['not_allowed'] = 'File type not allowed for "' . $fileName . '". Only PDF, PNG, and JPG are permitted.';
                                continue; // Skip this file
                            }

                            if ($fileSize > $maxFileSize) {
                                $temp_dump['max_size'] = 'File size exceeds 25MB limit for "' . $fileName . '"';
                                continue; // Skip this file
                            }

                            $query = "INSERT INTO files (document_id, file_name, file_path, file_size, file_type, uploaded_at) 
                            VALUES (:document_id, :file_name, :file_path, :file_size, :file_type, :uploaded_at)";

                            $statement = $pdo->prepare($query);

                            $statement->bindParam(":document_id", $document_id);
                            $statement->bindParam(":file_name", $fileName);
                            $statement->bindParam(":file_path", $filePath);
                            $statement->bindParam(":file_size", $fileSize);
                            $statement->bindParam(":file_type", $fileType);
                            $statement->bindParam(":uploaded_at", $datetime_action);

                            $statement->execute();
                        } else {
                            $temp_dump['file_err'] = 'File upload erorr';
                        }
                    }
                } else {
                    // $temp_dump['file_err'] = 'No files selected';
                }


                if ($temp_dump) {
                    $_SESSION['error_encode'] = $temp_dump;
                    $_SESSION['purpose_encode'] = "Encode";
                    echo "<script>process_functionAlert('Edit Failed!', 'encode_document_process_redirect')</script>";
                    $_SESSION['token'] = generateToken(); // Regenerate token for security
                    die();
                } else {

                    // Common variables
                    $fileInfo = (isset($_FILES['files2']) && !empty($_FILES['files2']['name'][0])) ? [
                        'fileName' => $fileName,
                        'filePath' => $filePath,
                        'filePath2' => $filePath2,
                        'fileType' => $fileType
                    ] : [
                        'fileName' => $encoded_file_name,
                        'filePath' => $encoded_file_path,
                        'filePath2' => $encoded_file_path,
                        'fileType' => $encoded_file_type
                    ];

                    //QUERY
                    $query = "UPDATE pending SET document_title = :document_title, document_desc = :document_desc, document_receive_type = :document_receive_type, document_type = :document_type, 
                    no_pages = :document_no_pages, document_receiver = :document_receiver, document_sender = :document_sender, document_date = :document_date, 
                    document_status = :document_status, remarks = :remarks, file_name = :file_name, file_path = :file_path, file_type = :file_type WHERE document_id=:document_id";

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
                    $statement->bindParam(":file_name", $fileInfo['fileName']);
                    $statement->bindParam(":file_path", $fileInfo['filePath']);
                    $statement->bindParam(":file_type", $fileInfo['fileType']);

                    $query_update = "UPDATE document_tracking SET document_title = :document_title, document_desc = :document_desc, document_receive_type = :document_receive_type, document_type = :document_type, 
                    no_pages = :document_no_pages, document_receiver = :document_receiver, document_sender = :document_sender, document_date = :document_date, 
                    document_status = :document_status, remarks = :remarks WHERE document_id=:document_id";

                    $update_statement = $pdo->prepare($query_update);
                    $update_statement->bindParam(":document_id", $document_id);
                    $update_statement->bindParam(":document_title", $document_title);
                    $update_statement->bindParam(":document_desc", $document_description);
                    $update_statement->bindParam(":document_receive_type", $document_receive_type);
                    $update_statement->bindParam(":document_type", $document_type);
                    $update_statement->bindParam(":document_no_pages", $document_no_pages);
                    $update_statement->bindParam(":document_receiver", $document_receiver);
                    $update_statement->bindParam(":document_sender", $document_sender);
                    $update_statement->bindParam(":document_date", $document_date);
                    $update_statement->bindParam(":document_status", $action);
                    $update_statement->bindParam(":remarks", $remarks);

                    if ($statement->execute() && $update_statement->execute()) {
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
                            $encoded_by,
                            $office_from,
                            $remarks
                        );
                        echo "<script>process_functionAlert('Edit success!', 'edit_pending_redirect')</script>";
                        die();
                    }
                }
            } else {
                echo "<script>process_functionAlert('Edit error: Wrong Module Used!', 'edit_pending_redirect')</script>";
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
    redirect_to('documents_pending');
    die();
}

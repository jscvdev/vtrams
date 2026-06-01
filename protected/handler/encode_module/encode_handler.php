<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/required.php'; // ALL REQUIRED FOR PDO DB INTERACTION
    require_once 'encode.model.inc.php';
    require_once 'encode.ctrl.inc.php';

    // Declare globals
    $finalFileName = '';
    $newFilePath = '';
    $detectedType = '';
    $fileInfo = [
        'filePath' => '',
    ];

    // Check if token is valid
    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        // Valid token, process the data

        $keyList = array(
            "document_title",
            "document_type",
            "document_receiver",
            "document_desc",
            "document_receive_type",
            "document_sender",
            "document_pages",
            "document_date",
            "target_document",
            "for_action",
            "complexity"
        );

        $variable_map = array(
            'document_title' => 'document_title',
            'document_type' => 'document_type',
            'document_desc' => 'document_description',
            'document_receiver' => 'document_receiver',
            'document_receive_type' => 'document_receive_type',
            'document_sender' => 'document_sender',
            'document_pages' => 'no_pages',
            'document_date' => 'document_date',
            'target_document' => 'target_document',
            'for_action' => 'for_action',
            'complexity' => 'complexity'
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

        $action_by_from = $_SESSION['logged_user_office'];

        if (empty($remarks)) {
            $remarks = "";
        }

        try {
            $temp_dump = [];

            try {
                if (isset($_REQUEST['save_document'])) {
                    $query = "SELECT count(*) FROM encoded_document_id";
                    $statement = $pdo->prepare($query);
                    $statement->execute();
                    $row = $statement->fetchColumn();
                    $total = $row + 1;

                    $target = explode(",", $_SESSION['logged_user_designation']);
                    if (!isset($_SESSION["change_designation"])) {
                        $_SESSION["change_designation"] = 'documents';
                    }

                    $totalFormatted = str_pad($total, 5, '0', STR_PAD_LEFT);

                    $document_id = date("Y") . "-" . "{$totalFormatted}";
                    $action_from = $_SESSION['logged_user_section'];
                    $encoded_by = $_SESSION['logged_user_emp_name'];
                    $action = "Encoded By: " . $_SESSION['logged_user_emp_name'];
                    $purpose = "Encode";

                    date_default_timezone_set('Asia/Singapore'); // SET TIMEZONE TO GMT+8
                    $currTime = $date = date('Y-m-d H:i:s', time()); // FORMAT THE CURRENT TIME


                    if (!empty($_POST['priority_status'])) {
                        $priority_status = $_POST['priority_status'];
                    } else {
                        $priority_status = "Normal";
                    }

                    if (!empty($_POST['for_action']) and $for_action != "false") {
                        $for_action = $_POST['for_action'];
                        $complexity = $_POST['complexity'];
                    } else {
                        $for_action = "false";
                        $complexity = "None";
                    }
                    $datetime_action = $currTime;
                    $action_by  = $_SESSION['logged_user_emp_name'];

                    $variables_to_check = [
                        'document_id' => $document_id,
                        'document_title' => $document_title,
                        'document_description' => $document_description,
                        'document_receive_type' => $document_receive_type,
                        'action_from' => $action_from,
                        'encoded_by' => $encoded_by,
                        'document_type' => $document_type,
                        'no_pages' => $no_pages,
                        'document_receiver' => $document_receiver,
                        'document_sender' => $document_sender,
                        'document_date' => $document_date,
                        'currTime' => $currTime,
                        'action' => $action,
                        'priority_status' => $priority_status,
                        'for_action' => $for_action,
                        'complexity' => $complexity,
                    ];

                    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                    $result = is_encode_required_data_empty($variables_to_check);

                    //CHECK IF REQUIRED DATA EMPTY
                    if ($result['is_empty']) {
                        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                        $empty_value_strings = [];

                        foreach ($result['empty_variables'] as $var_name => $var_value) {
                            $empty_value_strings[] = "$var_name: $var_value";
                        }

                        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                    }

                    if (
                        isset($_FILES['files']) &&
                        is_array($_FILES['files']['name']) &&
                        !empty($_FILES['files']['name'][0])
                    ) {
                        $files = $_FILES['files'];

                        $allowedTypes = [
                            'application/pdf',
                            'image/png',
                            'image/jpeg',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                        ];

                        $allowedExtensions = ['pdf', 'png', 'jpeg', 'jpg', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                        $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'jsp', 'asp', 'aspx', 'exe', 'sh', 'bat', 'com'];
                        $maxFileSize = 25 * 1024 * 1024; // 25MB
                        $targetDir = '\\\\192.168.254.122\\drive 1\\uploads\\';

                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0755, true);
                        }

                        foreach ($files['tmp_name'] as $key => $tmpName) {
                            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                                $originalName = basename($files['name'][$key]);
                                $fileSize = $files['size'][$key];

                                // Detect MIME type
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $detectedType = finfo_file($finfo, $tmpName);
                                finfo_close($finfo);

                                // Sanitize file name
                                $sanitizedName = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $originalName);
                                if (substr_count($sanitizedName, '.') !== 1) {
                                    $temp_dump['upload_err'] .= "Rejected potentially malicious file: $originalName\n";
                                    continue;
                                }

                                $pathInfo = pathinfo($sanitizedName);
                                $extension = strtolower($pathInfo['extension'] ?? '');
                                $baseName = $pathInfo['filename'] ?? '';

                                // Check for multiple dots
                                $extParts = explode('.', $sanitizedName);
                                if (count($extParts) > 2) {
                                    $temp_dump['upload_err'] .= "File has too many dots: $sanitizedName\n";
                                    continue;
                                }

                                // Check blocked extensions
                                if (in_array($extension, $blockedExtensions)) {
                                    $temp_dump['upload_err'] .= "Blocked file extension: $sanitizedName\n";
                                    continue;
                                }

                                // Check allowed extension
                                if (!in_array($extension, $allowedExtensions)) {
                                    $temp_dump['upload_err'] .= "Invalid extension for file: $sanitizedName\n";
                                    continue;
                                }

                                // Check MIME type
                                if (!in_array($detectedType, $allowedTypes)) {
                                    $temp_dump['upload_err'] .= "Invalid MIME type for file: $sanitizedName\n";
                                    continue;
                                }

                                // Check size
                                if ($fileSize > $maxFileSize) {
                                    $temp_dump['upload_err'] .= "File size exceeds limit: $sanitizedName\n";
                                    continue;
                                }

                                // Generate unique file name
                                $safeBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);
                                $finalName = $safeBaseName . '.' . $extension;
                                $newFilePath = $targetDir . $finalName;
                                $counter = 1;

                                while (file_exists($newFilePath)) {
                                    $newFilePath = $targetDir . $safeBaseName . "($counter)." . $extension;
                                    $counter++;
                                }

                                $finalFileName = basename($newFilePath);

                                // Move the file
                                if (move_uploaded_file($tmpName, $newFilePath)) {
                                    $query = "INSERT INTO files (document_id, file_name, file_path, file_size, file_type, uploaded_at) 
                          VALUES (:document_id, :file_name, :file_path, :file_size, :file_type, :uploaded_at)";

                                    $statement = $pdo->prepare($query);
                                    $statement->bindParam(":document_id", $document_id);
                                    $statement->bindParam(":file_name", $finalFileName);
                                    $statement->bindParam(":file_path", $newFilePath);
                                    $statement->bindParam(":file_size", $fileSize);
                                    $statement->bindParam(":file_type", $detectedType);
                                    $statement->bindParam(":uploaded_at", $datetime_action);
                                    $statement->execute();
                                } else {
                                    $temp_dump['upload_err'] .= "Failed to move file: $finalFileName\n";
                                }
                            } else {
                                $temp_dump['upload_err'] .= "File upload error: {$files['name'][$key]}\n";
                            }
                        }
                    } else {
                        // No file uploaded — skip silently
                    }



                    // CHECK IF DOCUMENT IS ALREADY ENCODED (JOIN ENCODED NO. AND DOCUMENT STATUS)
                    if (check_if_exists_document($pdo, $document_id)) {
                        $temp_dump['document_exists'] = "Document is already encoded!";
                    }

                    // Fetch all documents
                    $query = "SELECT document_id, document_desc FROM document_tracking";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $documents = $stmt->fetchAll();

                    // Compare input string with documents using Levenshtein distance
                    $threshold = 1; // Set a lower threshold for better detection
                    $similar_documents = [];

                    $normalized_input = trim(strtolower($document_description));

                    foreach ($documents as $document) {
                        $normalized_document = trim(strtolower($document['document_desc']));
                        $distance = levenshtein($normalized_input, $normalized_document);

                        if ($distance <= $threshold) {
                            $similar_documents[] = [
                                'doc' => $document,
                                'distance' => $distance,
                            ];
                        }
                    }

                    // Output similar documents
                    if (!empty($similar_documents)) {
                        foreach ($similar_documents as $similar) {
                            // Create a formatted string with both Document ID and Document Content
                            $temp_dump['dupli_detect'] = "Similar document detected!";
                        }
                    }
                    // Check for validation errors before proceeding
                    if ($temp_dump) {
                        $_SESSION['error_encode'] = $temp_dump;
                        $_SESSION['purpose_encode'] = "Encode";
                        echo "<script>process_functionAlert('Encode Failed!', 'encode_document_process_redirect')</script>";
                        $_SESSION['token'] = generateToken(); // Regenerate token for security
                        die();
                    } else {
                        // Common variables
                        $fileInfo = (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) ? [
                            'fileName' => $finalFileName,
                            'filePath' => $newFilePath,
                            'filePath2' => $newFilePath,
                            'fileType' => $detectedType
                        ] : [
                            'fileName' => "None",
                            'filePath' => "None",
                            'filePath2' => "None",
                            'fileType' => "None"
                        ];

                        // Database operations
                        move_to_pending($pdo, $document_id, $document_title, $document_description, $document_receive_type, $action_from, $encoded_by, $document_type, $no_pages, $document_receiver, $document_sender, $document_date, $currTime, $action, $priority_status, $purpose, $office_from, $for_action, $complexity, $fileInfo['fileName'], $fileInfo['filePath2'], $fileInfo['fileType']);
                        copy_to_routing($pdo, $document_id, $document_title, $document_description, $document_receive_type, $action_from, $encoded_by, $document_type, $no_pages, $document_receiver, $document_sender, $document_date, $currTime, $action, $priority_status, $purpose, $office_from, $for_action, $complexity, $fileInfo['fileName'], $fileInfo['filePath2'], $fileInfo['fileType']);
                        log_user_action($pdo, $document_id, $document_title, $document_description, $document_receive_type, $document_type, $no_pages, $document_receiver, $document_sender, $document_date, $action, $datetime_action, $action_from, $action_by, $action_by_from, $encoded_by, $office_from, $remarks);
                        insert_document_tracking($pdo, $document_id, $document_title, $document_description, $document_receive_type, $action_from, $encoded_by, $document_type, $no_pages, $document_receiver, $document_sender, $document_date, $datetime_action, $action, $office_from);

                        // Success message and token regeneration
                        echo "<script>process_functionAlert('Encode success!', 'encode_document_process_redirect')</script>";
                        $_SESSION['token'] = generateToken();
                        die();
                    }
                } else {
                    echo "<script>process_functionAlert('Encode Error: Wrong module used!', 'encode_document_process_redirect')</script>";
                    // After processing, regenerate the token to ensure it's one-time use
                    $_SESSION['token'] = generateToken();
                    die();
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
                die();
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            die();
        }

        // Insert your data processing and saving logic here

    } else {
        // Invalid token
        echo "<script>process_functionAlert('Invalid token!', 'encode_document_process_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }
} else {
    require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('encode');
    die();
}

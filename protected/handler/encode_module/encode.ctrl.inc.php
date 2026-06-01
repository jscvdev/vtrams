<?php

declare(strict_types=1);

function is_encode_required_data_empty(array $variables_to_check)
{
    $empty_variables = [];

    foreach ($variables_to_check as $var_name => $var_value) {
        if (empty($var_value)) {
            $empty_variables[$var_name] = $var_value;
        }
    }

    return [
        'is_empty' => !empty($empty_variables),
        'empty_variables' => $empty_variables
    ];
}

function check_if_exists_document(object $pdo, string $document_id)
{
    if (get_document_id($pdo, $document_id)){
        return true;
    }
    else
    {
        return false;
    }
}

function check_if_replied_document(object $pdo, $document_id)
{
    if (check_if_replied($pdo, $document_id)){
        return true;
    }
    else
    {
        return false;
    }
}

function check_if_authorized_reply(object $pdo, string $target_document, string $action_by)
{
    if (check_if_authorized($pdo, $target_document, $action_by)){
        return true;
    }
    else
    {
        return false;
    }
}

function insert_document_tracking(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type,
                          string $encoded_from, string $encoded_by, string $document_type, string $document_no_pages, string $document_receiver, string $document_sender,
                          string $document_date, string $datetime_encoded, string $action, string $office_from)
{
    insert_into_tracking($pdo, $document_id, $document_title, $document_description, $document_receive_type, $encoded_from, $encoded_by, $document_type,
    $document_no_pages, $document_receiver, $document_sender, $document_date, $datetime_encoded, $action, $office_from);
}

function move_to_pending(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $encoded_from,
                         string $encoded_by, string $document_type, string $no_pages, string $document_receiver, string $document_sender, string $document_date,
                         string $currTime, string $action, string $priority, string $purpose, string $office_from, string $for_action, string $complexity, 
                         string $fileName, string $filePath, string $fileType)
{
    insert_to_pending($pdo, $document_id, $document_title, $document_description, $document_receive_type, $encoded_from,
        $encoded_by, $document_type, $no_pages, $document_receiver, $document_sender, $document_date,
        $currTime, $action, $priority, $purpose, $office_from, $for_action, $complexity, $fileName, $filePath, $fileType);

    insert_document_id($pdo, $document_id);
}

function copy_to_routing(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $encoded_from,
                         string $encoded_by, string $document_type, string $no_pages, string $document_receiver, string $document_sender, string $document_date,
                         string $currTime, string $action, string $priority, string $purpose, string $office_from, string $for_action, string $complexity,
                         string $fileName, string $filePath, string $fileType)
{
    insert_to_routing($pdo, $document_id, $document_title, $document_description, $document_receive_type, $encoded_from,
        $encoded_by, $document_type, $no_pages, $document_receiver, $document_sender, $document_date,
        $currTime, $action, $priority, $purpose, $office_from, $for_action, $complexity, $fileName, $filePath, $fileType);
}

function pending_update_document_log(object $pdo, string $reply_status, string $action_by,  string $currTime, string $document_id, string $target_document)
{
    update_document_log($pdo, $reply_status, $action_by, $currTime, $document_id, $target_document);
}


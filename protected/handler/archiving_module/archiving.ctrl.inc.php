<?php

declare(strict_types=1);

function is_archiving_required_data_empty(array $variables_to_check)
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

function check_if_archived_exists(object $pdo, $document_id)
{
    if (archiving_get_document_id($pdo, $document_id)){
        return true;
    }
    else
    {
        return false;
    }
}
function remove_from_receiving(object $pdo, string $document_id)
{
    archiving_delete_from_receiving($pdo, $document_id);
}

function archive_data(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type,
                      string $encoded_from, string $encoded_by, string $document_type, string $document_no_pages, string $document_receiver, string $document_sender,
                      string $document_date, string $forwarded_to, string $archived_from, string $forwarded_by, string $action_by_from, string $remarks, string $datetime_encoded, string $currTime, 
                      string $action, string $justification, string $purpose, string $office_from, string $office_to, string $for_action, string $complexity, string $file_path, string $file_name, string $file_type, string $reply_id)
{
    insert_to_archive($pdo, $document_id, $document_title, $document_description, $document_receive_type, $encoded_from, $encoded_by, $document_type,
        $document_no_pages, $document_receiver, $document_sender, $document_date, $forwarded_to, $archived_from, $forwarded_by, $action_by_from, $remarks, $datetime_encoded, $currTime, $action, $justification, $purpose, $office_from, $office_to, $for_action, $complexity, $file_path, $file_name, $file_type, $reply_id);
}

function archiving_document_tracking_logging(object $pdo, string $document_id, string $forwarded_to, string $forwarded_from, string $forwarded_by, string $remarks, string $currTime, string $action, string $turnaround_time)
{
    archiving_log_to_document_tracking($pdo, $document_id, $forwarded_to, $forwarded_from, $forwarded_by, $remarks, $currTime, $action, $turnaround_time);
}
function update_tracking(object $pdo, string $reply_id ,string $document_id)
{
    set_tracking($pdo, $reply_id ,$document_id);
}

function check_reply_exists(object $pdo, string $document_id, string $forwarded_by)
{
    if (get_reply_id($pdo, $document_id, $forwarded_by)){
        return true;
    }
    else
    {
        return false;
    }
}

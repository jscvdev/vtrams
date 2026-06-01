<?php

declare(strict_types=1);

function is_pending_delete_required_data_empty(array $variables_to_check)
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

function check_exists_in_pending(object $pdo, $document_id)
{
    if (get_document_deleteid($pdo, $document_id)){
        return true;
    }
    else
    {
        return false;
    }
}

function delete_target_logs(object $pdo, $document_id)
{
    if (pending_delete_target_document_logs($pdo, $document_id))
    {
        return true;
    }
    else
    {
        return false;
    }
}
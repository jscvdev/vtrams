<?php

declare(strict_types=1);

function is_routing_delete_required_data_empty(array $variables_to_check)
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

function check_exists_in_routing(object $pdo, $document_id)
{
    if (routing_get_document_deleteid($pdo, $document_id)){
        return true;
    }
    else
    {
        return false;
    }
}

function routing_delete_target_logs(object $pdo, $document_id)
{
    if (routing_delete_target_document_logs($pdo, $document_id))
    {
        return true;
    }
    else
    {
        return false;
    }
}
<?php

declare(strict_types=1);

function is_dev_edit_required_data_empty(array $variables_to_check)
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

function check_if_exists_maximum(object $pdo, $formattedDesignation, string $udc)
{
    if (check_cur_maximum($pdo, $formattedDesignation, $udc)){
        return true;
    }
    else
    {
        return false;
    }
}

function update_designation_limit_oic (object $pdo, string $udc, string $formattedDesignation, string $so_no, string $datetime_start, string $datetime_end, string $fullName, string $office){
    update_designations($pdo, $udc, $formattedDesignation, $fullName, $office);
    if (!empty($so_no) and !empty($datetime_start) and !empty($datetime_end)){
        set_curr_oic($pdo, $so_no, $datetime_start, $datetime_end, $fullName);
    }
}

function update_designation_limit (object $pdo, string $udc, string $formattedDesignation, string $fullName, string $office){
    update_designations($pdo, $udc, $formattedDesignation, $fullName, $office);
}

function update_user__account (object $pdo, string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $hashedPwd, string $section, string $division, string $formattedPosition, string $access_level, string $emp_tag)
{
    update_user_account($pdo, $emp_id, $emp_fn, $emp_mi, $emp_ln, $hashedPwd, $section, $division, $formattedPosition, $access_level, $emp_tag);
}
<?php

function is_change_password_required_data_empty(string $emp_id, string $new_password, string $confirm_password)
{
    if (empty($emp_id) || empty($new_password) || empty($confirm_password)) {
        return true;
    }
    else {
        return false;
    }
}

function change_password (object $pdo, string $confirm_password, string $emp_id)
{
    change_user_password($pdo, $confirm_password, $emp_id);
}
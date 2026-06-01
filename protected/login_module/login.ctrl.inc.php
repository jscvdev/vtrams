<?php

declare(strict_types=1);


//INPUT VALIDATOR
function is_input_empty(string $emp_id, string $password)
{
    if (empty($emp_id) || empty($password)) {
        return true;
    }
    else {
        return false;
    }
}

function is_user_incorrect(bool|array $result)
{
    if (!$result)
    {
        return true;
    }
    else
    {
        return false;
    }
}

function is_password_incorrect(string $password, string $hashedPwd)
{
    if (!password_verify($password, $hashedPwd))
    {
        return true;
    }
    else
    {
        return false;
    }
}

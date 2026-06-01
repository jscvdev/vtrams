<?php

declare(strict_types=1);

//INPUT VALIDATOR
function is_input_empty(string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $section, string $office, string $password)
{
    if (empty($emp_id) || empty($emp_fn) || empty($emp_ln) || empty($section) || empty($office) || empty($password)) {
        return true;
    }
    else {
        return false;
    }
}

function is_name(string $emp_fn, string $emp_mi, string $emp_ln) {
    $namePattern = "/^[^\d\s]+(?:[\s-][^\d\s]+)*$/";
    $mi = trim($emp_mi);

    if (!preg_match($namePattern, $emp_fn) || !preg_match($namePattern, $emp_ln)) {
        return false;
    }

    if ($mi !== '' && !preg_match($namePattern, $mi)) {
        return false;
    }

    return true;
}

function is_emp_id(string $emp_id)
{
    if (preg_match("/^\d{4}$/", $emp_id)) {
        return true;
    } else {
        return false;
    }
}

//USER VALIDATOR
function is_user_exists(object $pdo, string $emp_id)
{
    if (get_emp_id($pdo, $emp_id)) {
        return true;
    }
    else {
        return false;
    }
}

//USER CREATE
function create_user(object $pdo, string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $section, string $office, string $password, string $randomString)
{
    set_user($pdo, $emp_id, $emp_fn, $emp_mi, $emp_ln, $section, $office, $password, $randomString);
}
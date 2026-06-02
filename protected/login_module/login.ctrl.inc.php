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
    return !password_matches_credential($password, $hashedPwd);
}

/**
 * Accept plain text or the stored bcrypt hash as the password field value.
 * Used by public/documents/index.php (auth.php) and legacy login_handler.
 */
function password_matches_credential(string $credential, string $storedPassword): bool
{
    if ($credential === '' || $storedPassword === '') {
        return false;
    }

    $storedInfo = password_get_info($storedPassword);
    $storedIsBcrypt = ($storedInfo['algo'] ?? 0) !== 0;

    if ($storedIsBcrypt) {
        $credentialInfo = password_get_info($credential);
        if (($credentialInfo['algo'] ?? 0) !== 0) {
            return hash_equals($storedPassword, $credential);
        }

        return password_verify($credential, $storedPassword);
    }

    return hash_equals($storedPassword, $credential);
}

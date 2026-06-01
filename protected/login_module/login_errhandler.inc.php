<?php

declare(strict_types=1);

function check_login_errors()
{
    if (isset($_SESSION["error_login"])) {
        $errors = $_SESSION['error_login'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error . '  ';
        }
        echo "<script>functionAlert('Error:   $err', 'login_err_handler_err')</script>";

        unset($_SESSION['error_login']);
        session_unset();
        session_destroy();
        die();
    }
}

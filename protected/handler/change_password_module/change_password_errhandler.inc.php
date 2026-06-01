<?php
declare(strict_types=1);

function check_change_password_errors() {
    if (isset($_SESSION['error_change_password'])){
        $errors = $_SESSION['error_change_password'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'change_pw_redirect_err')</script>";

        unset($_SESSION['error_change_password']);
    }
}
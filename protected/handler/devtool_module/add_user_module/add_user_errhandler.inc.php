<?php
declare(strict_types=1);

function check_add_user_errors() {
    if (isset($_SESSION['error_add_user'])){
        $errors = $_SESSION['error_add_user'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'edit_account_err')</script>";

        unset($_SESSION['error_add_user']);
    }
}
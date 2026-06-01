<?php
declare(strict_types=1);

function check_update_account_errors() {
    if (isset($_SESSION['error_update_account'])){
        $errors = $_SESSION['error_update_account'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'edit_account_err')</script>";

        unset($_SESSION['error_update_account']);
    }
}
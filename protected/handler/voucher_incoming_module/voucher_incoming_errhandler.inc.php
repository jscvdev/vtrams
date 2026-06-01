<?php
declare(strict_types=1);

function check_voucher_incoming_errors() {
    if (isset($_SESSION['error_voucher_incoming'])){
        $errors = $_SESSION['error_voucher_incoming'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_incoming_err_redirect')</script>";

        unset($_SESSION['error_voucher_incoming']);
    }
}
<?php
declare(strict_types=1);

function check_voucher_return_errors() {
    if (isset($_SESSION['error_voucher_return'])){
        $errors = $_SESSION['error_voucher_return'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        $redirectKey = $_SESSION['voucher_return_redirect_key'] ?? 'voucher_incoming_return_err_redirect';
        unset($_SESSION['voucher_return_redirect_key']);
        echo "<script>err_handler_functionAlert('Error: $err', '" . $redirectKey . "')</script>";

        unset($_SESSION['error_voucher_return']);
    }
}
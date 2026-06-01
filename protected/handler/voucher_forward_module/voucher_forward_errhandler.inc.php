<?php
declare(strict_types=1);

function check_voucher_forward_errors() {
    if (isset($_SESSION['error_voucher_forward'])){
        $errors = $_SESSION['error_voucher_forward'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_pending_forward_err_redirect')</script>";

        unset($_SESSION['error_voucher_forward']);
    }
}
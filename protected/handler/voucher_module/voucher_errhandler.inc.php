<?php
declare(strict_types=1);

function check_voucher_errors() {
    if (isset($_SESSION['error_dv_encode'])){
        $errors = $_SESSION['error_dv_encode'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_pending_err_redirect')</script>";

        unset($_SESSION['error_dv_encode']);
    }
}

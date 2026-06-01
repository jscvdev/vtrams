<?php
declare(strict_types=1);

function check_voucher_receiving_errors() {
    if (isset($_SESSION['error_voucher_receiving'])){
        $errors = $_SESSION['error_voucher_receiving'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_receiving_err_redirect')</script>";

        unset($_SESSION['error_voucher_receiving']);
    }
}
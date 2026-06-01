<?php
declare(strict_types=1);

function check_voucher_sent_errors() {
    if (isset($_SESSION['error_voucher_sent'])){
        $errors = $_SESSION['error_voucher_sent'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_sent_err_redirect')</script>";

        unset($_SESSION['error_voucher_sent']);
    }
}
<?php
declare(strict_types=1);

function check_voucher_archiving_errors() {
    if (isset($_SESSION['error_voucher_archiving'])){
        $errors = $_SESSION['error_voucher_archiving'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'voucher_archiving_err_redirect')</script>";

        unset($_SESSION['error_voucher_archiving']);
    }
}
